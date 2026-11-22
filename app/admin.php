<?php
declare(strict_types=1);

function admin_render(string $view, array $vars = []): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    render('admin/' . $view, $vars, 'admin/layout');
}

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

    // ---- Links ----
    if ($path === '/admin' || $path === '/admin/links') {
        $links = db()->query('SELECT * FROM links ORDER BY sort_order, id')->fetchAll();
        admin_render('links', ['links' => $links, 'tab' => 'links', 'title' => 'Links']);
        return;
    }
    if ($path === '/admin/links/new') {
        admin_render('link_edit', ['link' => ['id' => 0, 'title' => '', 'url' => '', 'image' => '', 'icon' => '', 'featured' => 0, 'visible' => 1], 'tab' => 'links', 'title' => 'Add link']);
        return;
    }
    if (preg_match('#^/admin/links/(\d+)$#', $path, $m)) {
        $link = find_link((int) $m[1]);
        admin_render('link_edit', ['link' => $link, 'tab' => 'links', 'title' => 'Edit link']);
        return;
    }
    if ($path === '/admin/links/save' && $post) {
        save_link();
        return;
    }
    if ($path === '/admin/links/reorder' && $post) {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $st = db()->prepare('UPDATE links SET sort_order = ? WHERE id = ?');
        foreach ($ids as $i => $id) {
            $st->execute([$i, $id]);
        }
        header('Content-Type: application/json');
        echo '{"ok":true}';
        return;
    }
    if (preg_match('#^/admin/links/(\d+)/(toggle|feature|delete)$#', $path, $m) && $post) {
        $link = find_link((int) $m[1]);
        if ($m[2] === 'toggle') {
            db()->prepare('UPDATE links SET visible = 1 - visible WHERE id = ?')->execute([$link['id']]);
            flash($link['visible'] ? 'Hidden from home page.' : 'Showing on home page.');
        } elseif ($m[2] === 'feature') {
            db()->prepare('UPDATE links SET featured = CASE WHEN id = ? THEN 1 - featured ELSE 0 END')->execute([$link['id']]);
            flash($link['featured'] ? 'No featured link now.' : '"' . $link['title'] . '" is featured.');
        } else {
            db()->prepare('DELETE FROM links WHERE id = ?')->execute([$link['id']]);
            delete_image($link['image']);
            flash('Link deleted.');
        }
        redirect('/admin/links');
    }

    // ---- Settings ----
    if ($path === '/admin/settings') {
        if ($post) {
            save_settings();
        }
        admin_render('settings', ['tab' => 'settings', 'title' => 'Settings']);
        return;
    }
    if ($path === '/admin/password' && $post) {
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
        redirect('/admin/settings#password');
    }

    not_found();
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

function find_link(int $id): array
{
    $st = db()->prepare('SELECT * FROM links WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: not_found();
}

function save_link(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_link($id) : null;
    $title = trim((string) ($_POST['title'] ?? ''));
    $url = clean_url((string) ($_POST['url'] ?? ''));
    $image = $old['image'] ?? '';

    try {
        if ($title === '' || $url === '') {
            throw new RuntimeException('Add a title and a link (https://… or a page like /about).');
        }
        if (!empty($_FILES['photo']['name'])) {
            $new = save_uploaded_image($_FILES['photo']);
            delete_image($image);
            $image = $new;
        } elseif (!empty($_POST['remove_photo'])) {
            delete_image($image);
            $image = '';
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($id ? "/admin/links/$id" : '/admin/links/new');
    }

    $visible = empty($_POST['visible']) ? 0 : 1;
    $featured = empty($_POST['featured']) ? 0 : 1;
    if ($featured) {
        db()->exec('UPDATE links SET featured = 0');
    }
    if ($id) {
        db()->prepare('UPDATE links SET title = ?, url = ?, image = ?, featured = ?, visible = ? WHERE id = ?')
            ->execute([$title, $url, $image, $featured, $visible, $id]);
    } else {
        db()->prepare('INSERT INTO links (title, url, image, featured, visible, sort_order) VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM links))')
            ->execute([$title, $url, $image, $featured, $visible]);
    }
    flash('Saved.');
    redirect('/admin/links');
}

const TEXT_SETTINGS = [
    'site_name', 'bio', 'footer_text', 'about_title', 'about_text', 'contact_email', 'business_name', 'social_music_label',
];
const URL_SETTINGS = [
    'social_tiktok', 'social_instagram', 'social_youtube', 'social_facebook', 'social_x', 'social_music', 'business_url',
];
const IMAGE_SETTINGS = ['logo_image', 'hero_image', 'about_image'];

function save_settings(): void
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
    flash($errors ? implode(' ', $errors) : 'Settings saved.');
    redirect('/admin/settings');
}
