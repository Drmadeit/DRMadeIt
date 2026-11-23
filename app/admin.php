<?php
declare(strict_types=1);

require ROOT . '/app/admin_content.php';
require ROOT . '/app/admin_pages.php';

function admin_render(string $view, array $vars = []): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    render('admin/' . $view, $vars, 'admin/layout');
}

/** Tables the generic reorder / show-hide endpoints may touch. */
const SORTABLE = ['links', 'tool_lists', 'tool_items', 'files', 'file_images', 'categories', 'pages', 'page_photos'];
const HIDEABLE = ['links', 'tool_lists', 'tool_items', 'categories', 'pages'];

function admin_route(string $path, string $method): void
{
    $post = $method === 'POST';
    if ($post) {
        check_csrf();
    }

    // ---- First run + login (no session needed) ----
    if (!admin_exists()) {
        admin_setup($post);
        return;
    }
    if ($path === '/admin/login') {
        admin_login($post);
        return;
    }
    if ($path === '/admin/logout' && $post) {
        $_SESSION = [];
        session_destroy();
        redirect('/admin/login');
    }

    require_admin();
    if (!isset($_COOKIE[STAT_OWNER_COOKIE])) { // keep this phone out of the stats, even after logging out
        setcookie(STAT_OWNER_COOKIE, '1', ['expires' => time() + 5 * 365 * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }

    // ---- Generic: reorder + show/hide ----
    if ($post && preg_match('#^/admin/reorder/(\w+)$#', $path, $m) && in_array($m[1], SORTABLE, true)) {
        $st = db()->prepare("UPDATE {$m[1]} SET sort_order = ? WHERE id = ?");
        foreach (array_map('intval', (array) ($_POST['ids'] ?? [])) as $i => $id) {
            $st->execute([$i, $id]);
        }
        header('Content-Type: application/json');
        echo '{"ok":true}';
        return;
    }
    if ($post && preg_match('#^/admin/toggle/(\w+)/(\d+)$#', $path, $m) && in_array($m[1], HIDEABLE, true)) {
        $row = find_row($m[1], (int) $m[2]);
        db()->prepare("UPDATE {$m[1]} SET visible = 1 - visible WHERE id = ?")->execute([$row['id']]);
        flash($row['visible'] ? 'Hidden.' : 'Showing.');
        redirect(back_to('/admin'));
    }

    $routes = [
        // dashboard
        'GET /admin' => fn () => admin_dashboard(),

        // links
        'GET /admin/links' => fn () => admin_render('links', [
            'links' => db()->query('SELECT * FROM links ORDER BY sort_order, id')->fetchAll(), 'tab' => 'links', 'title' => 'Links']),
        'GET /admin/links/new' => fn () => admin_render('link_edit', [
            'link' => ['id' => 0, 'title' => '', 'url' => '', 'image' => '', 'icon' => '', 'featured' => 0, 'visible' => 1], 'tab' => 'links', 'title' => 'Add link']),
        'GET /admin/links/(\d+)' => fn ($id) => admin_render('link_edit', ['link' => find_row('links', (int) $id), 'tab' => 'links', 'title' => 'Edit link']),
        'POST /admin/links/save' => fn () => save_link(),
        'POST /admin/links/(\d+)/feature' => function ($id) {
            $l = find_row('links', (int) $id);
            db()->prepare('UPDATE links SET featured = CASE WHEN id = ? THEN 1 - featured ELSE 0 END')->execute([$l['id']]);
            flash($l['featured'] ? 'No featured link now.' : '"' . $l['title'] . '" is featured.');
            redirect('/admin/links');
        },
        'POST /admin/links/(\d+)/delete' => function ($id) {
            $l = find_row('links', (int) $id);
            db()->prepare('DELETE FROM links WHERE id = ?')->execute([$l['id']]);
            delete_image($l['image']);
            flash('Link deleted.');
            redirect('/admin/links');
        },

        // tool lists
        'GET /admin/tools' => fn () => admin_render('tools', [
            'lists' => db()->query('SELECT l.*, (SELECT COUNT(*) FROM tool_items i WHERE i.list_id = l.id) n FROM tool_lists l ORDER BY sort_order, id')->fetchAll(),
            'tab' => 'tools', 'title' => 'Tool lists']),
        'GET /admin/tools/new' => fn () => admin_render('tool_list_edit', [
            'list' => ['id' => 0, 'title' => '', 'slug' => '', 'description' => '', 'cover_image' => '', 'visible' => 1], 'items' => [], 'tab' => 'tools', 'title' => 'New list']),
        'GET /admin/tools/(\d+)' => function ($id) {
            $list = find_row('tool_lists', (int) $id);
            $items = db()->prepare('SELECT * FROM tool_items WHERE list_id = ? ORDER BY sort_order, id');
            $items->execute([$list['id']]);
            admin_render('tool_list_edit', ['list' => $list, 'items' => $items->fetchAll(), 'tab' => 'tools', 'title' => $list['title']]);
        },
        'POST /admin/tools/save' => fn () => save_tool_list(),
        'POST /admin/tools/(\d+)/delete' => function ($id) {
            $list = find_row('tool_lists', (int) $id);
            $imgs = db()->prepare('SELECT image FROM tool_items WHERE list_id = ?');
            $imgs->execute([$list['id']]);
            foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $img) {
                delete_image($img);
            }
            delete_image($list['cover_image']);
            db()->prepare('DELETE FROM tool_lists WHERE id = ?')->execute([$list['id']]);
            flash('List deleted.');
            redirect('/admin/tools');
        },
        'GET /admin/tools/(\d+)/items/new' => fn ($lid) => admin_render('tool_item_edit', [
            'list' => find_row('tool_lists', (int) $lid),
            'item' => ['id' => 0, 'name' => '', 'amazon_url' => '', 'note' => '', 'image' => '', 'visible' => 1], 'tab' => 'tools', 'title' => 'Add tool']),
        'GET /admin/items/(\d+)' => function ($id) {
            $item = find_row('tool_items', (int) $id);
            admin_render('tool_item_edit', ['list' => find_row('tool_lists', (int) $item['list_id']), 'item' => $item, 'tab' => 'tools', 'title' => 'Edit tool']);
        },
        'POST /admin/items/save' => fn () => save_tool_item(),
        'POST /admin/items/(\d+)/delete' => function ($id) {
            $item = find_row('tool_items', (int) $id);
            delete_image($item['image']);
            db()->prepare('DELETE FROM tool_items WHERE id = ?')->execute([$item['id']]);
            flash('Tool deleted.');
            redirect('/admin/tools/' . $item['list_id']);
        },

        // files
        'GET /admin/files' => fn () => admin_render('files', [
            'files' => db()->query('SELECT f.*, (SELECT image FROM file_images i WHERE i.file_id = f.id ORDER BY sort_order, id LIMIT 1) thumb FROM files f ORDER BY sort_order, id')->fetchAll(),
            'tab' => 'files', 'title' => 'Files']),
        'GET /admin/files/new' => fn () => admin_render('file_edit', [
            'file' => ['id' => 0, 'title' => '', 'slug' => '', 'type' => 'cnc', 'price_cents' => 0, 'buy_url' => '', 'description' => '',
                'formats' => '', 'notes' => '', 'license' => 'personal', 'video_url' => '', 'file_path' => '', 'status' => 'draft', 'featured' => 0],
            'images' => [], 'tab' => 'files', 'title' => 'New file']),
        'GET /admin/files/(\d+)' => function ($id) {
            $file = find_row('files', (int) $id);
            $imgs = db()->prepare('SELECT * FROM file_images WHERE file_id = ? ORDER BY sort_order, id');
            $imgs->execute([$file['id']]);
            admin_render('file_edit', ['file' => $file, 'images' => $imgs->fetchAll(), 'tab' => 'files', 'title' => $file['title']]);
        },
        'POST /admin/files/save' => fn () => save_file_item(),
        'POST /admin/files/(\d+)/delete' => function ($id) {
            $file = find_row('files', (int) $id);
            $imgs = db()->prepare('SELECT image FROM file_images WHERE file_id = ?');
            $imgs->execute([$file['id']]);
            foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $img) {
                delete_image($img);
            }
            db()->prepare('DELETE FROM files WHERE id = ?')->execute([$file['id']]);
            // Its home page button would point nowhere now
            $links = db()->prepare('SELECT id, image FROM links WHERE url = ?');
            $links->execute(['/files/' . $file['slug']]);
            foreach ($links->fetchAll() as $l) {
                db()->prepare('DELETE FROM links WHERE id = ?')->execute([$l['id']]);
                delete_image($l['image']);
            }
            flash('File item deleted. (The download file itself stays in drmadeit-data/files.)');
            redirect('/admin/files');
        },
        'POST /admin/file-images/(\d+)/crop' => function ($id) {
            $img = find_row('file_images', (int) $id);
            $new = crop_upload();
            db()->prepare('UPDATE file_images SET image = ? WHERE id = ?')->execute([$new, $img['id']]);
            delete_image($img['image']);
            flash('Photo cropped.');
            echo '{"ok":true}';
        },
        'POST /admin/file-images/(\d+)/delete' => function ($id) {
            $img = find_row('file_images', (int) $id);
            delete_image($img['image']);
            db()->prepare('DELETE FROM file_images WHERE id = ?')->execute([$img['id']]);
            flash('Photo removed.');
            redirect('/admin/files/' . $img['file_id'] . '#photos');
        },

        // emails
        'GET /admin/emails' => fn () => admin_render('emails', [
            'subs' => db()->query('SELECT s.*, (SELECT COUNT(*) FROM downloads d WHERE d.subscriber_id = s.id) n FROM subscribers s ORDER BY s.id DESC')->fetchAll(),
            'tab' => 'more', 'title' => 'Emails']),
        'GET /admin/emails.csv' => fn () => export_emails(),
        'POST /admin/emails/(\d+)/delete' => function ($id) {
            $s = find_row('subscribers', (int) $id);
            db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$s['id']]);
            flash($s['email'] . ' deleted.');
            redirect('/admin/emails');
        },

        // videos
        'GET /admin/videos' => fn () => admin_videos(),
        'POST /admin/videos' => fn () => save_videos(),

        // settings
        'GET /admin/stats' => function () {
            $days = in_array((int) ($_GET['days'] ?? 30), [7, 30, 0], true) ? (int) ($_GET['days'] ?? 30) : 30;
            admin_render('stats', stats_report($days) + ['days' => $days, 'tab' => 'more', 'title' => 'Stats']);
        },
        'GET /admin/more' => fn () => admin_render('more', ['tab' => 'more', 'title' => 'More']),
        'GET /admin/settings' => fn () => redirect('/admin/more'),
        'GET /admin/settings/(\w+)' => function ($section) {
            if (!isset(SETTINGS_SECTIONS[$section])) {
                not_found();
            }
            admin_render('settings', ['section' => $section, 'tab' => 'more', 'title' => SETTINGS_SECTIONS[$section][0]]);
        },
        'POST /admin/settings/(\w+)' => fn ($section) => save_settings(isset(SETTINGS_SECTIONS[$section]) ? $section : 'profile'),
        'POST /admin/password' => fn () => change_password(),
        'POST /admin/test-email' => function () {
            $to = setting('contact_email') ?: setting('mail_from');
            $err = $to === '' ? 'Add a contact email first.' : send_mail($to, 'Test email from your site', "It works! Download emails will look like they come from this address.\n\n" . base_url());
            flash($err === null ? "Test email sent to $to. Check your inbox (and spam)." : "Email failed: $err");
            redirect('/admin/settings/email');
        },
        'GET /admin/backup' => function () {
            $zip = make_backup();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zip) . '"');
            header('Content-Length: ' . filesize($zip));
            readfile($zip);
        },
    ];

    $routes += pages_admin_routes();
    $routes += short_admin_routes();

    foreach ($routes as $key => $handler) {
        [$verb, $pattern] = explode(' ', $key, 2);
        if ($verb === $method && preg_match('#^' . $pattern . '$#', $path, $m)) {
            $handler(...array_slice($m, 1));
            return;
        }
    }
    not_found();
}

/** Where to go after a small action: the page the button was on. */
function back_to(string $fallback): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $path = (string) parse_url($ref, PHP_URL_PATH);
    $host = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0];
    return (parse_url($ref, PHP_URL_HOST) === $host && str_starts_with($path, '/admin')) ? $path : $fallback;
}

function find_row(string $table, int $id): array
{
    $st = db()->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: not_found();
}

function admin_setup(bool $post): void
{
    ensure_setup_code();
    $error = null;
    if ($post) {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $user = trim((string) ($_POST['user'] ?? ''));
        $pass = (string) ($_POST['pass'] ?? '');
        $real = trim((string) file_get_contents(setup_code_file()));
        if ($real === '' || !hash_equals($real, $code)) {
            $error = 'That setup code does not match.';
        } elseif ($user === '') {
            $error = 'Pick a username.';
        } elseif ($err = password_problem($pass)) {
            $error = $err;
        } else {
            set_setting('admin_user', $user);
            set_setting('admin_pass_hash', password_hash($pass, PASSWORD_DEFAULT));
            @unlink(setup_code_file());
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            flash('Admin account created. Welcome!');
            redirect('/admin');
        }
    }
    admin_render('setup', ['error' => $error, 'title' => 'Set up admin', 'bare' => true]);
}

function admin_login(bool $post): void
{
    if (is_admin()) {
        redirect('/admin');
    }
    $error = null;
    $wait = lockout_remaining();
    if ($post && $wait === 0) {
        if (attempt_login(trim((string) ($_POST['user'] ?? '')), (string) ($_POST['pass'] ?? ''))) {
            redirect('/admin');
        }
        $wait = lockout_remaining();
        $error = 'Wrong username or password.';
    }
    if ($wait > 0) {
        $error = 'Too many wrong tries. Try again in ' . (int) ceil($wait / 60) . ' min.';
    }
    admin_render('login', ['error' => $error, 'title' => 'Log in', 'bare' => true]);
}

function change_password(): void
{
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    if (!password_verify($cur, setting('admin_pass_hash'))) {
        flash('Current password is wrong. Nothing changed.');
    } elseif ($err = password_problem($new)) {
        flash($err);
    } else {
        set_setting('admin_pass_hash', password_hash($new, PASSWORD_DEFAULT));
        flash('Password changed.');
    }
    redirect('/admin/settings/password');
}

function admin_dashboard(): void
{
    $week = db()->query("SELECT datetime('now', '-7 days')")->fetchColumn(); // DB times are UTC
    $q = fn (string $sql, array $args = []) => (function () use ($sql, $args) {
        $st = db()->prepare($sql);
        $st->execute($args);
        return $st;
    })();
    admin_render('dashboard', [
        'downloadsWeek' => (int) $q('SELECT COUNT(*) FROM downloads WHERE created_at >= ?', [$week])->fetchColumn(),
        'emailsWeek' => (int) $q('SELECT COUNT(*) FROM subscribers WHERE created_at >= ?', [$week])->fetchColumn(),
        'visitorsWeek' => (int) $q("SELECT COALESCE(SUM(n), 0) FROM stats WHERE kind = 'people' AND day >= ?", [date('Y-m-d', strtotime('-6 days'))])->fetchColumn(),
        'emailsTotal' => (int) $q('SELECT COUNT(*) FROM subscribers')->fetchColumn(),
        'top' => $q('SELECT f.title, f.id, COUNT(d.id) n FROM downloads d JOIN files f ON f.id = d.file_id
                     WHERE d.created_at >= ? GROUP BY f.id ORDER BY n DESC LIMIT 5', [db()->query("SELECT datetime('now', '-30 days')")->fetchColumn()])->fetchAll(),
        'tab' => 'home', 'title' => 'Dashboard',
    ]);
}

function export_emails(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="drmadeit-emails-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'wants_updates', 'signed_up', 'files_requested']);
    $rows = db()->query('SELECT s.email, s.opted_in, s.created_at, GROUP_CONCAT(DISTINCT f.title) files
        FROM subscribers s LEFT JOIN downloads d ON d.subscriber_id = s.id LEFT JOIN files f ON f.id = d.file_id
        GROUP BY s.id ORDER BY s.id');
    foreach ($rows as $r) {
        // Prefix cells that a spreadsheet would treat as a formula.
        $r = array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $r);
        fputcsv($out, [$r['email'], $r['opted_in'] ? 'yes' : 'no', $r['created_at'], $r['files']]);
    }
    fclose($out);
}

function admin_videos(): void
{
    admin_render('videos', [
        'videos' => youtube_feed(),
        'pinned' => video_pins(),
        'hidden' => video_hidden(),
        'tab' => 'more', 'title' => 'Videos',
    ]);
}

function save_videos(): void
{
    $do = (string) ($_POST['do'] ?? '');
    $id = (string) ($_POST['id'] ?? '');
    if ($do === 'channel') {
        $input = trim((string) ($_POST['channel'] ?? ''));
        $cid = youtube_channel_id($input);
        if ($input === '') {
            set_setting('youtube_channel_id', '');
            flash('Channel removed.');
        } elseif ($cid === null) {
            flash('Could not find that channel. Paste the channel ID (starts with UC) from YouTube → Settings → Advanced settings.');
        } else {
            set_setting('youtube_channel_id', $cid);
            @unlink(youtube_cache_file());
            flash('Channel saved.');
        }
    } elseif ($do === 'pin-url') {
        $vid = youtube_video_id((string) ($_POST['url'] ?? ''));
        if ($vid === null) {
            flash("That doesn't look like a YouTube video link.");
        } else {
            $pins = array_values(array_filter(video_pins(), fn ($p) => $p['id'] !== $vid));
            $pins[] = ['id' => $vid, 'title' => trim((string) ($_POST['title'] ?? '')) ?: (youtube_title($vid) ?? 'Watch on YouTube')];
            set_setting('video_pinned', json_encode($pins));
            flash('Video pinned.');
        }
    } elseif ($do === 'pin' && preg_match('/^[\w-]{11}$/', $id)) {
        $pins = video_pins();
        if (in_array($id, array_column($pins, 'id'), true)) {
            $pins = array_values(array_filter($pins, fn ($p) => $p['id'] !== $id));
            flash('Unpinned.');
        } else {
            $pins[] = ['id' => $id, 'title' => youtube_title($id) ?? 'Watch on YouTube'];
            flash('Pinned to the top.');
        }
        set_setting('video_pinned', json_encode($pins));
    } elseif ($do === 'hide' && preg_match('/^[\w-]{11}$/', $id)) {
        $hidden = video_hidden();
        $hidden = in_array($id, $hidden, true) ? array_values(array_diff($hidden, [$id])) : [...$hidden, $id];
        set_setting('video_hidden', json_encode($hidden));
        flash('Updated.');
    } elseif ($do === 'refresh') {
        @unlink(youtube_cache_file());
        flash('Refreshed from YouTube.');
    } elseif ($do === 'home') {
        set_setting('videos_on_home', empty($_POST['on']) ? '0' : '1');
        flash('Saved.');
    }
    redirect('/admin/videos');
}

/** The Settings screens listed on the More tab: key => [title, icon, short note]. */
const SETTINGS_SECTIONS = [
    'profile' => ['Profile & header', 'home', 'Name, bio, header photo'],
    'social' => ['Social links', 'link', 'TikTok, Instagram, YouTube…'],
    'email' => ['Email sending', 'mail', 'For free download links'],
    'amazon' => ['Amazon', 'link', 'Associates tracking ID'],
    'licenses' => ['File licenses', 'file', 'Personal / commercial wording'],
    'footer' => ['Footer', 'sliders', 'Disclosure line'],
    'password' => ['Password', 'sliders', 'Change your admin password'],
    'backup' => ['Backup', 'download', 'Download a copy of everything'],
];

const TEXT_SETTINGS = [
    'site_name', 'bio', 'footer_text', 'contact_email', 'social_music_label',
    'amazon_tag', 'mail_from', 'mail_from_name', 'smtp_host', 'smtp_port', 'smtp_user',
    'license_personal', 'license_commercial',
];
const URL_SETTINGS = [
    'social_tiktok', 'social_instagram', 'social_youtube', 'social_facebook', 'social_x', 'social_music',
];
const IMAGE_SETTINGS = ['logo_image', 'hero_image'];

function save_settings(string $section = 'profile'): void
{
    foreach (TEXT_SETTINGS as $k) {
        if (isset($_POST[$k])) {
            set_setting($k, trim((string) $_POST[$k]));
        }
    }
    foreach (URL_SETTINGS as $k) {
        if (isset($_POST[$k])) {
            set_setting($k, clean_url((string) $_POST[$k]));
        }
    }
    if (($_POST['smtp_pass'] ?? '') !== '') { // blank = keep the saved one
        set_setting('smtp_pass', (string) $_POST['smtp_pass']);
    }
    $errors = [];
    foreach (IMAGE_SETTINGS as $k) {
        try {
            if (!empty($_FILES[$k]['name'])) {
                $new = save_uploaded_image($_FILES[$k]);
                delete_image(setting($k));
                set_setting($k, $new);
            } elseif (!empty($_POST['remove_' . $k])) {
                delete_image(setting($k));
                set_setting($k, '');
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
    flash($errors ? implode(' ', $errors) : 'Saved.');
    redirect('/admin/settings/' . $section);
}
