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

if (str_starts_with($path, '/admin') && $path !== '/admin.webmanifest') {
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

// ---- Home (About is a regular page now, made in Admin → Pages) ----
if ($path === '/') {
    render('home', [
        'links' => $q('SELECT * FROM links WHERE visible = 1 ORDER BY sort_order, id')->fetchAll(),
        'videos' => setting('videos_on_home') === '1' ? videos_for_site(3) : [],
        'isHome' => true,
    ]);

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
    // One filter row: All · CNC · 3D print · Free · Paid. Only choices that would actually narrow the list are shown.
    $filters = ['cnc' => ['CNC', "type = 'cnc'"], '3d' => ['3D print', "type = '3d'"], 'free' => ['Free', 'price_cents = 0'], 'paid' => ['Paid', 'price_cents > 0']];
    $show = (string) ($_GET['show'] ?? $_GET['type'] ?? $_GET['price'] ?? ''); // old ?type= / ?price= links still work
    $show = isset($filters[$show]) ? $show : '';
    $total = (int) $q("SELECT COUNT(*) FROM files WHERE status = 'published'")->fetchColumn();
    $chips = [];
    foreach ($filters as $key => [$label, $cond]) {
        $n = (int) $q("SELECT COUNT(*) FROM files WHERE status = 'published' AND $cond")->fetchColumn();
        if (($n > 0 && $n < $total) || $key === $show) {
            $chips[$key] = $label;
        }
    }
    render('files', ['title' => 'Files', 'show' => $show, 'chips' => $chips, 'files' => $q(
        "SELECT f.*, (SELECT image FROM file_images i WHERE i.file_id = f.id ORDER BY sort_order, id LIMIT 1) thumb
         FROM files f WHERE status = 'published'" . ($show ? ' AND ' . $filters[$show][1] : '') . ' ORDER BY featured DESC, sort_order, id')->fetchAll()]);
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

// ---- Stats: taps sent by assets/js/stats.js ----
} elseif ($path === '/t' && $method === 'POST') {
    stats_beacon();

// ---- Admin as a home-screen app (fetched without the login cookie, so it skips the admin login check) ----
} elseif ($path === '/admin.webmanifest') {
    header('Content-Type: application/manifest+json');
    header('Cache-Control: no-cache');
    echo json_encode([
        'name' => setting('site_name', 'D.R. Made It') . ' Admin', 'short_name' => 'DR Admin', 'id' => '/admin',
        'start_url' => '/admin', 'scope' => '/admin', 'display' => 'standalone',
        'background_color' => '#000000', 'theme_color' => '#0a0a0b',
        'icons' => [['src' => asset('img/admin-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                    ['src' => asset('img/admin-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
    ], JSON_UNESCAPED_SLASHES);

// ---- Robots ----
} elseif ($path === '/robots.txt') {
    header('Content-Type: text/plain');
    echo "User-agent: *\nDisallow: /admin\nDisallow: /download/\nDisallow: /t\n";
} elseif (!apps_route($path) && !pages_route($path) && !short_link_route($path)) { // pages, categories, short links
    not_found();
}
