<?php
declare(strict_types=1);

/**
 * Tape Math: a fraction calculator you can add to the iPhone home screen.
 * Works offline through a service worker that caches the files below.
 *   /tape/                       the app
 *   /tape/sw.js                  offline worker (version changes whenever a file changes)
 *   /tape/manifest.webmanifest   app name + icons
 */
const TAPE_FILES = ['js/calc.js', 'js/tape.js', 'css/tape.css', 'img/tape-180.png', 'img/tape-192.png', 'img/tape-512.png'];

function tape_version(): string
{
    $parts = [filemtime(ROOT . '/views/tape.php')];
    foreach (TAPE_FILES as $f) {
        $parts[] = @filemtime(ROOT . '/assets/' . $f);
    }
    return substr(md5(implode('|', $parts)), 0, 10);
}

function tape_route(string $path): bool
{
    if ($path === '/tape') {
        $raw = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!str_ends_with($raw, '/')) {
            header('Location: /tape/', true, 301); // the app lives at /tape/ so the offline worker covers it
            return true;
        }
        header('Cache-Control: no-cache');
        render('tape', ['version' => tape_version()], null);
        return true;
    }
    if ($path === '/tape/sw.js') {
        // text/javascript + no-store so neither the server nor Cloudflare keeps an old copy
        header('Content-Type: text/javascript; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('Expires: 0');
        $urls = array_merge(['/tape/', '/tape/manifest.webmanifest'], array_map('asset', TAPE_FILES));
        echo "const VERSION = " . json_encode('tape-' . tape_version()) . ";\n";
        echo "const FILES = " . json_encode($urls, JSON_UNESCAPED_SLASHES) . ";\n";
        readfile(ROOT . '/assets/js/tape-sw.js');
        return true;
    }
    if ($path === '/tape/manifest.webmanifest') {
        header('Content-Type: application/manifest+json');
        header('Cache-Control: no-cache');
        echo json_encode([
            'name' => 'Tape Math',
            'short_name' => 'Tape Math',
            'description' => 'Fraction calculator for the shop by D.R. Made It',
            'start_url' => '/tape/',
            'scope' => '/tape/',
            'display' => 'standalone',
            'background_color' => '#0a0a0b',
            'theme_color' => '#0a0a0b',
            'icons' => [
                ['src' => asset('img/tape-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('img/tape-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return true;
    }
    return false;
}
