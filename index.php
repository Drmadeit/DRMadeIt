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

switch ($path) {
    case '/':
        $links = db()->query('SELECT * FROM links WHERE visible = 1 ORDER BY sort_order, id')->fetchAll();
        render('home', ['links' => $links, 'isHome' => true]);
        break;

    case '/about':
        render('about', ['title' => setting('about_title', 'About')]);
        break;

    default:
        not_found();
}
