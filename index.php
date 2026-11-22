<?php
declare(strict_types=1);

// Local dev server (php -S): let it serve real files under /assets directly.
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (str_starts_with($p, '/assets/') && is_file(__DIR__ . $p)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/calc.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

// One backup a day, made after the visitor already has their page.
register_shutdown_function(function () {
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (PHP_SAPI !== 'cli-server') {
        return; // can't detach from the visitor: skip rather than slow the page
    }
    maybe_nightly_backup();
});

$path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (str_starts_with($path, '/admin')) {
    require ROOT . '/app/admin.php';
    admin_route($path, $method);
    exit;
}

if (preg_match('#^/media/([^/]+)$#', $path, $m)) {
    serve_media($m[1]);
}

$q = function (string $sql, array $args = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
};

// ---- Home + About ----
if ($path === '/') {
    render('home', [
        'links' => $q('SELECT * FROM links WHERE visible = 1 ORDER BY sort_order, id')->fetchAll(),
        'videos' => setting('videos_on_home') === '1' ? videos_for_site(3) : [],
        'isHome' => true,
    ]);
} elseif ($path === '/about') {
    render('about', ['title' => setting('about_title', 'About'), 'ogImage' => setting('about_image') ? img_url(setting('about_image')) : '']);

// ---- Tool lists ----
} elseif ($path === '/tools') {
    render('tools', ['title' => 'Tools', 'lists' => $q(
        'SELECT l.*, (SELECT COUNT(*) FROM tool_items i WHERE i.list_id = l.id AND i.visible = 1) n,
                (SELECT image FROM tool_items i WHERE i.list_id = l.id AND i.visible = 1 AND image != \'\' ORDER BY sort_order, id LIMIT 1) first_image
         FROM tool_lists l WHERE visible = 1 ORDER BY sort_order, id')->fetchAll()]);
} elseif (preg_match('#^/tools/([a-z0-9-]+)$#', $path, $m)) {
    $list = $q('SELECT * FROM tool_lists WHERE slug = ? AND (visible = 1 OR ?)', [$m[1], is_admin() ? 1 : 0])->fetch() ?: not_found();
    $items = $q('SELECT * FROM tool_items WHERE list_id = ? AND visible = 1 ORDER BY sort_order, id', [$list['id']])->fetchAll();
    render('tool_list', ['title' => $list['title'], 'description' => $list['description'], 'list' => $list, 'items' => $items,
        'ogImage' => img_url($list['cover_image'] ?: ($items[0]['image'] ?? ''))]);

// ---- File shop ----
} elseif ($path === '/files') {
    $type = in_array($_GET['type'] ?? '', ['cnc', '3d'], true) ? $_GET['type'] : '';
    $price = in_array($_GET['price'] ?? '', ['free', 'paid'], true) ? $_GET['price'] : '';
    $where = ["status = 'published'"];
    $args = [];
    if ($type) {
        $where[] = 'type = ?';
        $args[] = $type;
    }
    if ($price) {
        $where[] = $price === 'free' ? 'price_cents = 0' : 'price_cents > 0';
    }
    render('files', ['title' => 'Files', 'type' => $type, 'price' => $price, 'files' => $q(
        'SELECT f.*, (SELECT image FROM file_images i WHERE i.file_id = f.id ORDER BY sort_order, id LIMIT 1) thumb
         FROM files f WHERE ' . implode(' AND ', $where) . ' ORDER BY featured DESC, sort_order, id', $args)->fetchAll()]);
} elseif (preg_match('#^/files/([a-z0-9-]+)$#', $path, $m)) {
    $file = find_published_file($m[1]);
    $images = file_images((int) $file['id']);
    render('file', ['file' => $file, 'images' => $images, 'title' => $file['title'],
        'description' => mb_strimwidth($file['description'], 0, 180, '…'), 'ogImage' => img_url($images[0] ?? '')]);
} elseif ($method === 'POST' && preg_match('#^/files/([a-z0-9-]+)/get$#', $path, $m)) {
    check_csrf();
    request_download(find_published_file($m[1]));
} elseif (preg_match('#^/download/([a-f0-9]{40})$#', $path, $m)) {
    serve_download($m[1]);

// ---- Videos ----
} elseif ($path === '/videos') {
    render('videos', ['title' => 'Videos', 'videos' => videos_for_site(12)]);

// ---- Calculators ----
} elseif ($path === '/calc') {
    render('calc/index', ['title' => 'Shop Calculators', 'description' => 'Free woodworking calculators and a DXF polyline fixer.']);
} elseif (preg_match('#^/calc/([a-z0-9-]+)$#', $path, $m) && isset(CALCS[$m[1]])) {
    render('calc/' . $m[1], ['title' => CALCS[$m[1]]['title'], 'description' => CALCS[$m[1]]['blurb'], 'calc' => $m[1]]);

// ---- Robots ----
} elseif ($path === '/robots.txt') {
    header('Content-Type: text/plain');
    echo "User-agent: *\nDisallow: /admin\nDisallow: /download/\n";
} elseif (!tape_route($path) && !pages_route($path)) { // pages and categories made in admin
    not_found();
}
