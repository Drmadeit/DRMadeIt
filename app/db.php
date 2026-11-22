<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DATA_DIR . '/site.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
        migrate($pdo);
    }
    return $pdo;
}

/** Schema versions run once each, tracked by PRAGMA user_version. Only ever append. */
function migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $migrations = [
        1 => function (PDO $pdo): void {
            $pdo->exec(<<<SQL
            CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
            CREATE TABLE links (
                id INTEGER PRIMARY KEY, title TEXT NOT NULL, url TEXT NOT NULL, image TEXT NOT NULL DEFAULT '',
                icon TEXT NOT NULL DEFAULT '', featured INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0, visible INTEGER NOT NULL DEFAULT 1);
            CREATE TABLE tool_lists (
                id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, description TEXT NOT NULL DEFAULT '',
                cover_image TEXT NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0, visible INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE tool_items (
                id INTEGER PRIMARY KEY, list_id INTEGER NOT NULL REFERENCES tool_lists(id) ON DELETE CASCADE,
                name TEXT NOT NULL, image TEXT NOT NULL DEFAULT '', amazon_url TEXT NOT NULL DEFAULT '', note TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0, visible INTEGER NOT NULL DEFAULT 1);
            CREATE TABLE files (
                id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, type TEXT NOT NULL DEFAULT 'cnc',
                price_cents INTEGER NOT NULL DEFAULT 0, buy_url TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '',
                formats TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', license TEXT NOT NULL DEFAULT '',
                video_url TEXT NOT NULL DEFAULT '', file_path TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft',
                featured INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE file_images (
                id INTEGER PRIMARY KEY, file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
                image TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE subscribers (
                id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, opted_in INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE downloads (
                id INTEGER PRIMARY KEY, file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
                subscriber_id INTEGER NOT NULL REFERENCES subscribers(id) ON DELETE CASCADE,
                token TEXT NOT NULL UNIQUE, expires_at TEXT NOT NULL, uses INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE login_attempts (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, at INTEGER NOT NULL);
            SQL);
            seed($pdo);
        },
        2 => function (PDO $pdo): void {
            $st = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
            foreach ([
                'amazon_tag' => '',
                'mail_from' => '',
                'mail_from_name' => 'D.R. Made It',
                'smtp_host' => 'smtp.hostinger.com',
                'smtp_port' => '465',
                'license_personal' => "Personal use: make it for yourself or as a gift. Please don't sell items made from it or share the file.",
                'license_commercial' => "Commercial OK: you can sell what you make with this file. Please don't share or resell the file itself.",
                'videos_on_home' => '0',
            ] as $k => $v) {
                $st->execute([$k, $v]);
            }
            // Empty lists matching the old bio.site buttons. Fill them in Admin → Tools.
            $st = $pdo->prepare('INSERT OR IGNORE INTO tool_lists (title, slug, description, sort_order) VALUES (?, ?, ?, ?)');
            $st->execute(['Tools in My Videos', 'shop-tools', 'The tools I use in my shop and videos.', 0]);
            $st->execute(['Reaper Build', 'reaper-build', 'Everything I used to build out the Reaper trailer.', 1]);
        },
        3 => function (PDO $pdo): void {
            // Pages you create in admin, optionally grouped into categories (like a portfolio).
            // "Delete" only sets deleted_at, so everything can be undone and photo files are never erased.
            $pdo->exec(<<<SQL
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, intro TEXT NOT NULL DEFAULT '',
                cover TEXT NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0, visible INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY, category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
                title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, body TEXT NOT NULL DEFAULT '', cover TEXT NOT NULL DEFAULT '',
                buttons TEXT NOT NULL DEFAULT '[]', sort_order INTEGER NOT NULL DEFAULT 0, visible INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE page_photos (
                id INTEGER PRIMARY KEY, page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
                image TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, deleted_at TEXT);
            SQL);
        },
        4 => function (PDO $pdo): void {
            // YouTube links that play on the page (one per line)
            $pdo->exec("ALTER TABLE pages ADD COLUMN videos TEXT NOT NULL DEFAULT ''");
        },
        5 => function (PDO $pdo): void {
            // Title + saved thumbnail for TikTok links, looked up once when the page is saved
            $pdo->exec("ALTER TABLE pages ADD COLUMN video_meta TEXT NOT NULL DEFAULT '{}'");
        },
        6 => function (PDO $pdo): void {
            // /calc is now the Fractions app; the calculator list moved to /toolbox
            $pdo->exec("UPDATE links SET url = '/toolbox', title = CASE WHEN title = 'Shop Calculators' THEN 'Toolbox' ELSE title END WHERE url = '/calc'");
        },
        7 => function (PDO $pdo): void {
            // About moves from Settings to a regular page at /about, keeping its text, photo, business link and email.
            if ($pdo->query("SELECT 1 FROM pages WHERE slug = 'about'")->fetchColumn()) {
                return;
            }
            $s = [];
            foreach ($pdo->query('SELECT key, value FROM settings') as $r) {
                $s[$r['key']] = (string) $r['value'];
            }
            $buttons = [];
            if (($s['business_url'] ?? '') !== '') {
                $buttons[] = ['label' => ($s['business_name'] ?? '') ?: 'My business', 'url' => $s['business_url']];
            }
            if (($s['contact_email'] ?? '') !== '') {
                $buttons[] = ['label' => 'Email me', 'url' => 'mailto:' . $s['contact_email']];
            }
            $img = $s['about_image'] ?? '';
            $pdo->prepare('INSERT INTO pages (title, slug, body, cover, buttons, visible, sort_order) VALUES (?, ?, ?, ?, ?, 1, -1)')
                ->execute([($s['about_title'] ?? '') ?: 'About Me', 'about', $s['about_text'] ?? '', $img, json_encode($buttons)]);
            if ($img !== '') {
                $pdo->prepare('INSERT INTO page_photos (page_id, image, sort_order) VALUES (?, ?, 0)')->execute([(int) $pdo->lastInsertId(), $img]);
            }
        },
    ];
    foreach ($migrations as $v => $fn) {
        if ($v > $version) {
            $pdo->beginTransaction();
            $fn($pdo);
            $pdo->exec('PRAGMA user_version = ' . $v);
            $pdo->commit();
        }
    }
}

/** First-run content, matching the old bio.site. Everything is editable in admin. */
function seed(PDO $pdo): void
{
    $settings = [
        'site_name' => 'D.R. Made It',
        'bio' => 'Cabinets, CNC and shop builds.',
        'social_tiktok' => 'https://www.tiktok.com/@drmadeit',
        'social_instagram' => 'https://www.instagram.com/drmadeit',
        'social_youtube' => 'https://www.youtube.com/@drmadeit',
        'social_facebook' => '',
        'social_x' => '',
        'social_music' => '',
        'social_music_label' => 'Music',
        'footer_text' => 'As an Amazon Associate I earn from qualifying purchases.',
        'about_title' => 'About Me',
        'about_text' => "Hi, I'm David. I build cabinets, CNC projects and whatever the shop needs next.\n\nEdit this text in Admin → Settings.",
        'about_image' => '',
        'contact_email' => '',
        'business_name' => 'Raleigh Cabinets',
        'business_url' => '',
    ];
    $st = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
    foreach ($settings as $k => $v) {
        $st->execute([$k, $v]);
    }

    // [title, url, seed image, icon, visible]. Pages that start empty stay hidden until filled in.
    $links = [
        ['About Me', '/about', 'about.jpg', '', 1],
        ['Stuff I Design and Sell', '/files', 'design-sell.jpg', '', 0],
        ['Tools in My Videos', '/tools/shop-tools', 'shop-tools.jpg', '', 0],
        ['Reaper Build Items', '/tools/reaper-build', 'reaper-build.jpg', '', 0],
        ['Toolbox', '/toolbox', '', 'ruler', 1],
        ['My Amazon Storefront', 'https://www.amazon.com/shop/raleighcabinets', '', 'link', 1],
    ];
    $st = $pdo->prepare('INSERT INTO links (title, url, image, icon, sort_order, visible) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($links as $i => [$title, $url, $img, $icon, $visible]) {
        $name = '';
        if ($img !== '' && is_file(ROOT . '/assets/img/seed/' . $img)) {
            $name = 'seed-' . $img;
            copy(ROOT . '/assets/img/seed/' . $img, UPLOAD_DIR . '/' . $name);
        }
        $st->execute([$title, $url, $name, $icon, $i, $visible]);
    }
}
