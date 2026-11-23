<?php
declare(strict_types=1);

/**
 * Shop calculator apps. Each one can be added to the home screen on its own and works offline.
 * They all share the /calc/ scope, so the ☰ menu moves between them without leaving the app,
 * and one offline worker (/calc/sw.js) saves every app, so the menu works with no signal too.
 *
 *   /calc/            Fractions
 *   /calc/<slug>      the others
 *   /toolbox          website page listing them all
 */
const APPS = [
    'fractions' => ['name' => 'Fractions', 'path' => '/calc/', 'blurb' => 'Add, subtract, multiply and divide feet, inches and fractions.', 'lengths' => true],
    'square' => ['name' => 'Square', 'path' => '/calc/square', 'blurb' => 'The diagonal that proves a corner or a box is square (3-4-5).', 'lengths' => true],
    'crown' => ['name' => 'Crown', 'path' => '/calc/crown', 'blurb' => 'Miter and bevel settings for crown molding at any corner.', 'lengths' => false],
    'spacing' => ['name' => 'Spacing', 'path' => '/calc/spacing', 'blurb' => 'Evenly space shelves, dividers, balusters or holes. Every mark.', 'lengths' => true],
    'board-feet' => ['name' => 'Board Feet', 'path' => '/calc/board-feet', 'blurb' => 'Board feet and cost, with waste.', 'lengths' => false],
    'stairs' => ['name' => 'Stairs', 'path' => '/calc/stairs', 'blurb' => 'Risers, treads and stringer length from total rise.', 'lengths' => true],
    'dxf' => ['name' => 'DXF Fix', 'path' => '/calc/dxf', 'blurb' => 'Join broken lines into closed shapes for CNC. Your file never leaves your phone.', 'lengths' => false],
];

/** Shared files every app needs offline, plus each app's icons (assets/icons/build.js makes the PNGs). */
const APP_FILES = ['js/calc.js', 'js/app.js', 'js/tape.js', 'js/dxf.js', 'js/stats.js', 'css/app.css'];

function app_assets(): array
{
    $files = APP_FILES;
    foreach (array_keys(APPS) as $slug) {
        $files[] = "img/apps/$slug-180.png";
        $files[] = "img/apps/$slug-192.png";
        $files[] = "icons/$slug-auto.svg";
    }
    return $files;
}

/** Old addresses that now live elsewhere. */
const APP_REDIRECTS = [
    '/fcalc' => '/calc/', '/frac' => '/calc/', '/fractions' => '/calc/', '/tape' => '/calc/', '/calc/fractions' => '/calc/',
    '/calc/board_feet' => '/calc/board-feet',
];

function apps_version(): string
{
    $parts = [];
    foreach (glob(ROOT . '/views/apps/*.php') ?: [] as $f) {
        $parts[] = filemtime($f);
    }
    foreach (app_assets() as $f) {
        $parts[] = @filemtime(ROOT . '/assets/' . $f);
    }
    $parts[] = filemtime(__FILE__);
    return substr(md5(implode('|', $parts)), 0, 10);
}

function apps_route(string $path): bool
{
    $lower = strtolower($path);
    if (isset(APP_REDIRECTS[$lower])) {
        header('Location: ' . APP_REDIRECTS[$lower], true, 301);
        return true;
    }
    if ($lower === '/toolbox') {
        render('toolbox', ['title' => 'Toolbox', 'description' => 'Free shop calculators you can add to your phone. They work with no signal.']);
        return true;
    }
    if ($path === '/calc') {
        $raw = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!str_ends_with($raw, '/')) {
            header('Location: /calc/', true, 301); // Fractions lives at /calc/ so the app scope covers it
            return true;
        }
        return render_app('fractions');
    }
    if ($path === '/calc/sw.js') {
        // text/javascript + no-store so neither the server nor Cloudflare keeps an old copy
        header('Content-Type: text/javascript; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('Expires: 0');
        $urls = array_merge(array_column(APPS, 'path'), array_map(fn ($s) => "/calc/manifest/$s.webmanifest", array_keys(APPS)), array_map('asset', app_assets()));
        echo 'const VERSION = ' . json_encode('apps-' . apps_version()) . ";\n";
        echo 'const FILES = ' . json_encode($urls, JSON_UNESCAPED_SLASHES) . ";\n";
        readfile(ROOT . '/assets/js/app-sw.js');
        return true;
    }
    if (preg_match('#^/calc/manifest/([a-z-]+)\.webmanifest$#', $path, $m) && isset(APPS[$m[1]])) {
        $app = APPS[$m[1]];
        header('Content-Type: application/manifest+json');
        header('Cache-Control: no-cache');
        echo json_encode([
            'id' => $app['path'],
            'name' => $app['name'],
            'short_name' => $app['name'],
            'description' => $app['blurb'],
            'start_url' => $app['path'],
            'scope' => '/calc/',
            'display' => 'standalone',
            'background_color' => '#0a0a0b',
            'theme_color' => '#0a0a0b',
            'icons' => [
                ['src' => asset("img/apps/{$m[1]}-192.png"), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset("img/apps/{$m[1]}-512.png"), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return true;
    }
    if (preg_match('#^/calc/([a-z-]+)$#', $path, $m) && isset(APPS[$m[1]]) && $m[1] !== 'fractions') {
        return render_app($m[1]);
    }
    return false;
}

function render_app(string $slug): bool
{
    header('Cache-Control: no-cache');
    render('apps/' . $slug, ['slug' => $slug, 'app' => APPS[$slug], 'version' => apps_version()], 'apps/shell');
    return true;
}

/** A box you tap to type into with the keypad. type: len (feet/inches/fractions) or num (plain number). */
function app_field(string $name, string $label, string $type = 'len', string $default = '', string $unit = ''): string
{
    return '<button type="button" class="field" data-field="' . e($name) . '" data-type="' . e($type) . '" data-default="' . e($default) . '">'
        . '<span class="f-label">' . e($label) . '</span><span class="f-val"></span>'
        . ($unit !== '' ? '<span class="f-unit">' . e($unit) . '</span>' : '') . '</button>';
}

/** A row of choices (only one on). $options: value => label. */
function app_choice(string $name, string $label, array $options, string $default): string
{
    $h = '<div class="choice" data-field="' . e($name) . '" data-type="choice" data-default="' . e($default) . '" role="group" aria-label="' . e($label) . '">'
        . '<span class="f-label">' . e($label) . '</span><span class="seg">';
    foreach ($options as $v => $l) {
        $h .= '<button type="button" data-v="' . e((string) $v) . '" aria-pressed="false">' . e($l) . '</button>';
    }
    return $h . '</span></div>';
}

/** A result tile. Big ones span the full width. */
function app_out(string $name, string $label, bool $big = false): string
{
    return '<div class="out' . ($big ? ' out-big' : '') . '"><span>' . e($label) . '</span><b data-out="' . e($name) . '">—</b></div>';
}
