<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function base_url(): string
{
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null || $key === '__flush') {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = (string) $row['value'];
        }
        if ($key === '__flush') {
            return '';
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
    setting('__flush');
}

/** Render a view inside a layout. Views get $vars extracted. */
function render(string $view, array $vars = [], ?string $layout = 'layout'): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout === null) {
        echo $content;
        return;
    }
    require ROOT . '/views/' . $layout . '.php';
    if ($layout === 'layout') {
        stat_view();
    }
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function not_found(): never
{
    http_response_code(404);
    render('404', ['title' => 'Not found']);
    exit;
}

/** Turn plain text with blank lines into safe paragraphs. */
function paragraphs(string $text): string
{
    $out = '';
    foreach (preg_split('/\R{2,}/', trim($text)) as $p) {
        if ($p !== '') {
            $out .= '<p>' . nl2br(linkify($p), false) . '</p>';
        }
    }
    return $out;
}

/**
 * Safe HTML from plain text, with links made clickable:
 *   https://… , www.… , amzn.to/…  → the link itself
 *   [Router bit set](https://…)    → "Router bit set", linked
 * Amazon links get the Associates tag (if set), open in a new tab and are marked sponsored.
 */
function linkify(string $text): string
{
    $re = '~\[([^\]\n]{1,100})\]\(((?:https?://|www\.)[^\s()<>]+)\)'      // [words](link)
        . '|((?:https?://|www\.|amzn\.to/|a\.co/)[^\s<>]+)~i';             // bare link
    $out = '';
    $pos = 0;
    while (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $start = $m[0][1];
        $out .= e(substr($text, $pos, $start - $pos));
        if (($m[1][0] ?? '') !== '') {
            $label = $m[1][0];
            $url = $m[2][0];
            $trail = '';
        } else {
            $url = $m[3][0];
            $trail = '';
            while ($url !== '' && strpbrk(substr($url, -1), '.,!?;:)\'"') !== false) { // "see amzn.to/x." -> keep the dot as text
                $trail = substr($url, -1) . $trail;
                $url = substr($url, 0, -1);
            }
            $label = $url;
        }
        $href = preg_match('~^https?://~i', $url) ? $url : 'https://' . $url;
        $host = strtolower((string) parse_url($href, PHP_URL_HOST));
        $amazon = (bool) preg_match('/(^|\.)(amazon\.[a-z.]+|amzn\.to|a\.co)$/', $host);
        if ($amazon && function_exists('amazon_link')) {
            $href = amazon_link($href);
        }
        $ext = $host !== '' && $host !== strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $out .= '<a href="' . e($href) . '"' . ($ext ? ' target="_blank" rel="' . ($amazon ? 'sponsored nofollow noopener' : 'noopener') . '"' : '') . '>' . e($label) . '</a>' . e($trail);
        $pos = $start + strlen($m[0][0]);
    }
    return $out . e(substr($text, $pos));
}

function is_external(string $url): bool
{
    return (bool) preg_match('#^https?://#i', $url) && parse_url($url, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '');
}

/** Accept https://, http://, mailto:, or a site path like /about. Anything else is rejected. */
function clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(https?://|mailto:|/)#i', $url)) {
        return $url;
    }
    if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $url)) {
        return 'https://' . $url; // "amazon.com/shop/x" -> https://amazon.com/shop/x
    }
    return '';
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return '/assets/' . $path . '?v=' . $v;
}

function socials(): array
{
    $defs = [
        'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'youtube' => 'YouTube',
        'facebook' => 'Facebook', 'x' => 'X', 'music' => 'Music',
    ];
    $out = [];
    foreach ($defs as $key => $label) {
        $url = setting('social_' . $key);
        if ($url !== '') {
            $out[] = ['key' => $key, 'label' => $key === 'music' ? (setting('social_music_label') ?: 'Music') : $label, 'url' => $url];
        }
    }
    return $out;
}

function icon(string $name, int $size = 24): string
{
    static $paths = [
        'tiktok' => '<path fill="currentColor" d="M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-2.6-2.6c.3 0 .5 0 .8.1V9.7a5.8 5.8 0 1 0 5 5.7V9.1a7.4 7.4 0 0 0 4.3 1.4V7.4a4.3 4.3 0 0 1-3.3-1.6z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.4" cy="6.6" r="1.3" fill="currentColor"/>',
        'youtube' => '<path fill="currentColor" d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3z"/>',
        'facebook' => '<path fill="currentColor" d="M13.5 21v-8h2.7l.4-3.2h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.4a22 22 0 0 0-2.5-.1c-2.4 0-4.1 1.5-4.1 4.2v2.3H7.5V13h2.7v8z"/>',
        'x' => '<path fill="currentColor" d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"/>',
        'music' => '<path fill="currentColor" d="M9 17.5V6.2l11-2.2v11.5a3 3 0 1 1-2-2.8V7.2l-7 1.4v8.9a3 3 0 1 1-2-2.8z"/>',
        'ruler' => '<path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M3 16.5 16.5 3 21 7.5 7.5 21z"/><path fill="none" stroke="currentColor" stroke-width="1.8" d="M7 12.5l2 2M10 9.5l2 2M13 6.5l2 2"/>',
        'link' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'chevron' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/>',
        'grip' => '<g fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></g>',
        'eye' => '<path fill="none" stroke="currentColor" stroke-width="2" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>',
        'eye-off' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4M6.6 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'star' => '<path fill="currentColor" d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'sliders' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="10" cy="12" r="2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="18" r="2" fill="none" stroke="currentColor" stroke-width="2"/>',
        'home' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M3 11 12 4l9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
        'file' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M6 3h8l5 5v13H6z"/><path fill="none" stroke="currentColor" stroke-width="2" d="M14 3v5h5M9 13h7M9 17h7"/>',
        'download' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m-5-5 5 5 5-5M5 20h14"/>',
        'crop' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 2v14a2 2 0 0 0 2 2h14M2 6h14a2 2 0 0 1 2 2v14"/>',
        'folder' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M3 6.5A1.5 1.5 0 0 1 4.5 5H9l2 2.5h8.5A1.5 1.5 0 0 1 21 9v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z"/>',
        'chart' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 20h16M7 16v-4M12 16V6M17 16v-7"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" d="m3 7 9 6 9-6"/>',
    ];
    $p = $paths[$name] ?? $paths['link'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'item';
}
