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
        8 => function (PDO $pdo): void {
            // Which shop files show on which pages (picked in the file editor or the page editor).
            $pdo->exec('CREATE TABLE page_files (
                page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
                file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
                sort_order INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (page_id, file_id))');
        },
        9 => function (PDO $pdo): void {
            // drmadeit.com email is on Google Workspace: point the untouched default at Gmail's server.
            $pdo->exec("UPDATE settings SET value = 'smtp.gmail.com' WHERE key = 'smtp_host' AND value = 'smtp.hostinger.com'
                AND COALESCE((SELECT value FROM settings WHERE key = 'smtp_user'), '') = ''");
        },
        10 => function (PDO $pdo): void {
            // Short links: drmadeit.com/<code> sends people to any long link, with a click count.
            $pdo->exec('CREATE TABLE short_links (
                id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, url TEXT NOT NULL, note TEXT NOT NULL DEFAULT \'\',
                clicks INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        },
        11 => function (PDO $pdo): void {
            // Private stats: one counter per day per thing (see app/stats.php), plus today's "already counted" hashes.
            $pdo->exec('CREATE TABLE stats (day TEXT NOT NULL, kind TEXT NOT NULL, ref TEXT NOT NULL DEFAULT \'\', n INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (day, kind, ref)) WITHOUT ROWID');
            $pdo->exec('CREATE TABLE stat_seen (h TEXT PRIMARY KEY, day TEXT NOT NULL) WITHOUT ROWID');
        },
        12 => function (PDO $pdo): void {
            // Home page sections under the buttons: latest videos, files, shop tools (all on; switch off in Profile & header)
            foreach (['videos_on_home', 'files_on_home', 'tools_on_home'] as $k) {
                $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, '1') ON CONFLICT(key) DO UPDATE SET value = '1'")->execute([$k]);
            }
        },
        13 => function (PDO $pdo): void {
            $pdo->exec("UPDATE settings SET value = '0' WHERE key = 'tools_on_home'"); // only videos + files were wanted
        },
        14 => function (PDO $pdo): void {
            // Links, pages, categories, tool lists and short links all become "buttons" (rows in pages):
            //   kind  page | link | folder      place  home | folder | none (address only)
            // Every old address keeps working through the redirects table.
            foreach ([
                "kind TEXT NOT NULL DEFAULT 'page'", "url TEXT NOT NULL DEFAULT ''", 'parent_id INTEGER',
                "place TEXT NOT NULL DEFAULT 'none'", "thumb TEXT NOT NULL DEFAULT ''", "icon TEXT NOT NULL DEFAULT ''",
                'featured INTEGER NOT NULL DEFAULT 0', 'clicks INTEGER NOT NULL DEFAULT 0',
            ] as $col) {
                $pdo->exec("ALTER TABLE pages ADD COLUMN $col");
            }
            $pdo->exec("CREATE TABLE redirects (path TEXT PRIMARY KEY, target TEXT NOT NULL, target_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP) WITHOUT ROWID"); // target: button | file
            $redirect = $pdo->prepare('INSERT OR IGNORE INTO redirects (path, target, target_id) VALUES (?, ?, ?)');
            $one = function (string $sql, array $args = []) use ($pdo) {
                $st = $pdo->prepare($sql);
                $st->execute($args);
                return $st;
            };
            $slugTaken = fn (string $slug) => (bool) $one('SELECT 1 FROM pages WHERE slug = ?', [$slug])->fetchColumn();
            $insert = function (array $row) use ($pdo): int {
                $cols = implode(', ', array_keys($row));
                $pdo->prepare("INSERT INTO pages ($cols) VALUES (:" . implode(', :', array_keys($row)) . ')')->execute($row);
                return (int) $pdo->lastInsertId();
            };

            // 1. Categories become folders; their pages move inside.
            $page = 0;
            foreach ($pdo->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll() as $c) {
                $slug = $c['slug'];
                if ($slugTaken($slug)) {
                    $slug = new_code($slugTaken);
                }
                $fid = $insert(['kind' => 'folder', 'title' => $c['name'], 'slug' => $slug, 'body' => $c['intro'], 'cover' => $c['cover'],
                    'visible' => $c['visible'], 'sort_order' => $c['sort_order'], 'deleted_at' => $c['deleted_at'], 'created_at' => $c['created_at']]);
                foreach ($one('SELECT id, slug FROM pages WHERE category_id = ?', [$c['id']])->fetchAll() as $p) {
                    $pdo->prepare("UPDATE pages SET parent_id = ?, place = 'folder' WHERE id = ?")->execute([$fid, $p['id']]);
                    $redirect->execute(['/' . $c['slug'] . '/' . $p['slug'], 'button', $p['id']]);
                }
            }

            // 2. Tool lists people can reach (they have tools, or a home button points at them) become pages.
            $toolPage = [];
            $dropped = [];
            foreach ($pdo->query('SELECT * FROM tool_lists ORDER BY sort_order, id')->fetchAll() as $l) {
                $items = $one('SELECT * FROM tool_items WHERE list_id = ? AND visible = 1 ORDER BY sort_order, id', [$l['id']])->fetchAll();
                $linked = (bool) $one('SELECT 1 FROM links WHERE url = ? AND visible = 1', ['/tools/' . $l['slug']])->fetchColumn();
                if (!$items && !$linked) {
                    $dropped['/tools/' . $l['slug']] = true; // empty and nobody's sent there: just gone
                    continue;
                }
                $body = trim($l['description']);
                foreach ($items as $it) {
                    $line = $it['name'] . ($it['note'] !== '' ? ' — ' . $it['note'] : '');
                    $body .= "\n\n" . ($it['amazon_url'] !== '' ? '[' . $it['name'] . '](' . $it['amazon_url'] . ')' . ($it['note'] !== '' ? ' — ' . $it['note'] : '') : $line);
                }
                $slug = $slugTaken($l['slug']) ? new_code($slugTaken) : $l['slug'];
                $pid = $insert(['kind' => 'page', 'title' => $l['title'], 'slug' => $slug, 'body' => trim($body), 'cover' => $l['cover_image'],
                    'visible' => $l['visible'], 'sort_order' => 0]);
                $redirect->execute(['/tools/' . $l['slug'], 'button', $pid]);
                $toolPage['/tools/' . $l['slug']] = $pid;
            }

            // 3. Home links: a link to one of your own top-level pages/folders turns that page into the home button;
            //    anything else becomes a link button. Order, photos and "featured" carry over.
            $map = [];
            foreach ($pdo->query('SELECT * FROM links ORDER BY sort_order, id')->fetchAll() as $i => $l) {
                $target = null;
                $path = '/' . trim((string) parse_url($l['url'], PHP_URL_PATH), '/');
                if (!$l['visible'] && isset($dropped[$path])) {
                    continue; // a hidden button to an empty tool list
                }
                if ($l['visible'] && !preg_match('#^https?://#i', $l['url'])) {
                    $target = $toolPage[$path] ?? null;
                    if (!$target && preg_match('#^/([a-z0-9-]+)$#', $path, $m)) {
                        $target = $one("SELECT id FROM pages WHERE slug = ? AND place = 'none' AND deleted_at IS NULL", [$m[1]])->fetchColumn() ?: null;
                    }
                    if ($target && $one("SELECT place FROM pages WHERE id = ?", [$target])->fetchColumn() !== 'none') {
                        $target = null; // already used by an earlier button
                    }
                }
                $moved = $one('SELECT p.slug FROM redirects r JOIN pages p ON p.id = r.target_id WHERE r.path = ?', [$path])->fetchColumn();
                if (!$target && $moved !== false && !preg_match('#^https?://#i', $l['url'])) {
                    $l['url'] = '/' . $moved; // e.g. /builds/reaper-trailer is now just /reaper-trailer
                }
                if ($target) {
                    $pdo->prepare("UPDATE pages SET place = 'home', sort_order = ?, thumb = ?, icon = ?, featured = ? WHERE id = ?")
                        ->execute([$i, $l['image'], $l['icon'], $l['featured'], $target]);
                    $map[$l['id']] = (int) $target;
                } else {
                    $map[$l['id']] = $insert(['kind' => 'link', 'title' => $l['title'], 'slug' => new_code($slugTaken), 'url' => $l['url'],
                        'place' => 'home', 'thumb' => $l['image'], 'icon' => $l['icon'], 'featured' => $l['featured'],
                        'visible' => $l['visible'], 'sort_order' => $i]);
                }
            }

            // 4. Short links become address-only link buttons (same address, same click count).
            foreach ($pdo->query('SELECT * FROM short_links ORDER BY id')->fetchAll() as $s) {
                $slug = $slugTaken($s['code']) ? new_code($slugTaken) : $s['code'];
                $id = $insert(['kind' => 'link', 'title' => $s['note'] !== '' ? $s['note'] : $s['code'], 'slug' => $slug, 'url' => $s['url'],
                    'place' => 'none', 'clicks' => $s['clicks'], 'visible' => 1, 'sort_order' => 0, 'created_at' => $s['created_at']]);
                $pdo->prepare("UPDATE stats SET kind = 'go', ref = ? WHERE kind = 'short' AND ref = ?")->execute([(string) $id, $s['code']]);
            }

            // 5. Stats: home taps now point at buttons.
            foreach ($map as $old => $new) {
                $pdo->prepare("UPDATE stats SET kind = 'btn', ref = ? WHERE kind = 'link' AND ref = ?")->execute([(string) $new, (string) $old]);
            }
            $pdo->exec("UPDATE settings SET value = '0' WHERE key = 'tools_on_home'");
        },
        15 => function (PDO $pdo): void {
            // Optional MakerWorld page for a file: shown first on the file page (downloads there earn rewards)
            $pdo->exec("ALTER TABLE files ADD COLUMN makerworld_url TEXT NOT NULL DEFAULT ''");
        },
        16 => function (PDO $pdo): void {
            // A long write-up typed into "Material & machine notes" belongs in the description
            $pdo->exec("UPDATE files SET description = notes, notes = '' WHERE description = '' AND length(notes) > 300");
            // The home "files" button repeats the Files section right under it: hide it (it can be shown again in Buttons)
            $pdo->exec("UPDATE pages SET visible = 0 WHERE kind = 'link' AND place = 'home' AND url = '/files' AND deleted_at IS NULL");
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
