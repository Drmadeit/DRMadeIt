<?php
declare(strict_types=1);

/**
 * Buttons made in admin. Everything lives at one short address: drmadeit.com/<slug>
 *   kind page    your own page (text, photos, videos, files)
 *   kind link    goes somewhere else (Amazon, MakerWorld… or any address); /<slug> forwards there and counts the click
 *   kind folder  a page of more buttons
 *   place home | folder (inside parent_id) | none (just the address)
 * Old addresses are kept in the redirects table, so links people shared never break.
 */
const RESERVED_SLUGS = ['admin', 'tools', 'files', 'videos', 'calc', 'media', 'download', 'assets', 'robots-txt', 'index-php', 'sitemap', 'p', 'page', 'pages', 'tape', 'fractions', 'fcalc', 'frac', 'toolbox', 't', 'about-us'];
const BUTTON_KINDS = ['page' => 'Page', 'link' => 'Link', 'folder' => 'Folder'];

/** A short code like k7m: at least one letter and one number, no look-alikes (0 o 1 l i). */
function new_code(callable $taken): string
{
    $letters = 'abcdefghjkmnpqrstuvwxyz';
    $digits = '23456789';
    $all = $letters . $digits;
    for ($n = 0; ; $n++) {
        $len = $n < 200 ? 3 : 4;
        $c = [$letters[random_int(0, 22)], $digits[random_int(0, 7)]];
        while (count($c) < $len) {
            $c[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($c);
        $code = implode('', $c);
        if (!$taken($code)) {
            return $code;
        }
    }
}

function live_button(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL AND (visible = 1 OR ?)');
    $st->execute([$slug, is_admin() ? 1 : 0]);
    return $st->fetch() ?: null;
}

/** The button's own address on this site. */
function page_url(array $page): string
{
    return '/' . $page['slug'];
}

/** Where tapping the button goes: link buttons go straight to their target (with the Amazon tag), the rest to their page. */
function button_href(array $b): string
{
    return $b['kind'] === 'link' ? amazon_link($b['url']) : page_url($b);
}

/** The folder a button sits in, if it's live. */
function button_parent(array $b): ?array
{
    if ($b['place'] !== 'folder' || !$b['parent_id']) {
        return null;
    }
    $st = db()->prepare("SELECT * FROM pages WHERE id = ? AND kind = 'folder' AND deleted_at IS NULL");
    $st->execute([$b['parent_id']]);
    return $st->fetch() ?: null;
}

/** Live buttons in a place, in order. */
function buttons_in(string $place, ?int $parentId = null): array
{
    $st = db()->prepare('SELECT * FROM pages WHERE place = ? AND (? IS NULL OR parent_id = ?) AND deleted_at IS NULL AND visible = 1 ORDER BY sort_order, id');
    $st->execute([$place, $parentId, $parentId]);
    return $st->fetchAll();
}

/** Photo for a button: its own button photo, else (pages/folders) the cover, first photo or first video. */
function button_image(array $b): string
{
    if ($b['thumb'] !== '') {
        return img_url($b['thumb'], true);
    }
    if ($b['kind'] === 'link') {
        if (preg_match('#^/files/([a-z0-9-]+)$#', $b['url'], $m)) {
            $st = db()->prepare('SELECT i.image FROM files f JOIN file_images i ON i.file_id = f.id WHERE f.slug = ? ORDER BY i.sort_order, i.id LIMIT 1');
            $st->execute([$m[1]]);
            return ($img = $st->fetchColumn()) ? img_url((string) $img, true) : '';
        }
        return '';
    }
    if ($b['kind'] === 'folder') {
        if ($b['cover'] !== '') {
            return img_url($b['cover'], true);
        }
        foreach (buttons_in('folder', (int) $b['id']) as $child) {
            if ($child['kind'] !== 'folder' && ($img = button_image($child))) {
                return $img;
            }
        }
        return '';
    }
    return page_card_image($b);
}

/** Live shop files picked to show on this page, with their cover photo. */
function page_files(int $pageId): array
{
    $st = db()->prepare("SELECT f.*, (SELECT image FROM file_images i WHERE i.file_id = f.id ORDER BY sort_order, id LIMIT 1) thumb
        FROM page_files pf JOIN files f ON f.id = pf.file_id
        WHERE pf.page_id = ? AND f.status = 'published' ORDER BY pf.sort_order, f.sort_order, f.id");
    $st->execute([$pageId]);
    return $st->fetchAll();
}

function page_photos(int $pageId): array
{
    $st = db()->prepare('SELECT * FROM page_photos WHERE page_id = ? AND deleted_at IS NULL ORDER BY sort_order, id');
    $st->execute([$pageId]);
    return $st->fetchAll();
}

/** Cover = the chosen photo, else the first photo. */
function page_cover(array $page): string
{
    if ($page['cover'] !== '') {
        return $page['cover'];
    }
    $st = db()->prepare('SELECT image FROM page_photos WHERE page_id = ? AND deleted_at IS NULL ORDER BY sort_order, id LIMIT 1');
    $st->execute([$page['id']]);
    return (string) ($st->fetchColumn() ?: '');
}

const VIDEO_SITES = [
    'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'link' => 'Video',
];

/** One line of the video box: "link" or "link  your title". */
function parse_video_line(string $line): array
{
    $parts = preg_split('/\s+/', trim($line), 2);
    $title = trim(ltrim($parts[1] ?? '', "|-–— \t"));
    return [$parts[0] ?? '', $title];
}

/** Which site a video link is from, and its ID. */
function video_link(string $url): ?array
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    if ($id = youtube_video_id($url)) {
        return ['provider' => 'youtube', 'id' => $id, 'url' => 'https://www.youtube.com/watch?v=' . $id];
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $provider = match (true) {
        (bool) preg_match('/(^|\.)tiktok\.com$/', $host) => 'tiktok',
        (bool) preg_match('/(^|\.)(instagram\.com|instagr\.am)$/', $host) => 'instagram',
        (bool) preg_match('/(^|\.)(facebook\.com|fb\.watch|fb\.com)$/', $host) => 'facebook',
        default => 'link',
    };
    return ['provider' => $provider, 'id' => $provider === 'tiktok' ? tiktok_video_id($url) : null, 'url' => $url];
}

function tiktok_video_id(string $url): ?string
{
    return preg_match('#tiktok\.com/(?:@[^/]+/video|v|embed(?:/v2)?|player/v1)/(\d{8,25})#i', $url, $m) ? $m[1] : null;
}

/**
 * Videos from the page's video box, in order. YouTube plays on the page;
 * everything else is a card that opens the video on its own site.
 */
function page_videos(array $page): array
{
    $meta = json_decode($page['video_meta'] ?? '{}', true) ?: [];
    $out = [];
    $seen = [];
    foreach (preg_split('/\R/', (string) ($page['videos'] ?? '')) as $line) {
        [$url, $title] = parse_video_line($line);
        $v = video_link($url);
        if (!$v) {
            continue;
        }
        $m = $meta[$url] ?? [];
        $v = array_merge($v, array_intersect_key($m, array_flip(['id', 'url', 'title', 'thumb'])));
        $v += ['title' => '', 'thumb' => ''];
        if ($title !== '') {
            $v['title'] = $title; // your own title wins
        }
        $v['site'] = VIDEO_SITES[$v['provider']];
        $key = $v['provider'] . ($v['id'] ?: $v['url']);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $v;
        }
    }
    return $out;
}

/** Look up a TikTok link once: real address, title, and a saved copy of the thumbnail. */
function tiktok_lookup(string $url): array
{
    $info = ['url' => $url, 'id' => tiktok_video_id($url), 'title' => '', 'thumb' => ''];
    if (!$info['id'] && ($final = http_final_url($url))) { // short links like vm.tiktok.com/abc
        $info['url'] = strtok($final, '?');
        $info['id'] = tiktok_video_id($final);
    }
    $json = http_get('https://www.tiktok.com/oembed?url=' . rawurlencode($info['url']));
    $data = $json ? json_decode($json, true) : null;
    if (is_array($data)) {
        $info['title'] = mb_strimwidth(trim((string) ($data['title'] ?? '')), 0, 120, '…');
        if (!empty($data['thumbnail_url']) && ($bytes = http_get((string) $data['thumbnail_url']))) {
            $info['thumb'] = save_image_bytes($bytes); // TikTok's own thumbnail links expire, so keep a copy
        }
    }
    return $info;
}

/** Card image for a page: cover photo, else first photo, else first video thumbnail. */
function page_card_image(array $page): string
{
    if ($img = page_cover($page)) {
        return img_url($img, true);
    }
    foreach (page_videos($page) as $v) {
        if ($v['provider'] === 'youtube') {
            return youtube_thumb($v['id']);
        }
        if ($v['thumb']) {
            return img_url($v['thumb'], true);
        }
    }
    return '';
}

function page_buttons(array $page): array
{
    $b = json_decode($page['buttons'] ?: '[]', true);
    return is_array($b) ? $b : [];
}

/** Returns true if it handled the request. */
function pages_route(string $path): bool
{
    $parts = explode('/', trim($path, '/'));
    if (count($parts) !== 1 || !preg_match('/^[a-z0-9-]+$/i', $parts[0])) {
        return false;
    }
    $slug = strtolower($parts[0]); // K7M works too
    $b = live_button($slug);
    if (!$b) {
        return false;
    }
    if ($b['kind'] === 'link') {
        db()->prepare('UPDATE pages SET clicks = clicks + 1 WHERE id = ?')->execute([$b['id']]);
        stat_add('go', (string) $b['id']);
        header('Cache-Control: no-store'); // so every click is counted
        header('X-Robots-Tag: noindex');
        header('Location: ' . button_href($b), true, 302);
        return true;
    }
    if ($slug !== $parts[0]) {
        redirect(page_url($b));
    }
    if ($b['kind'] === 'folder') {
        $items = buttons_in('folder', (int) $b['id']);
        render('category', ['cat' => $b, 'pages' => $items, 'title' => $b['title'], 'description' => $b['body'],
            'ogImage' => $b['cover'] !== '' ? img_url($b['cover']) : '']);
        return true;
    }
    return show_page($b);
}

function show_page(array $page): bool
{
    $photos = page_photos((int) $page['id']);
    render('page', ['page' => $page, 'parent' => button_parent($page), 'photos' => $photos, 'cover' => page_cover($page), 'title' => $page['title'],
        'description' => mb_strimwidth(preg_replace('/\s+/', ' ', $page['body']), 0, 180, '…'),
        'ogImage' => img_url(page_cover($page))]);
    return true;
}

/** Old addresses (renamed buttons and files, old category/page and tool list links) forward to where things live now. */
function redirect_route(string $path): bool
{
    $st = db()->prepare('SELECT * FROM redirects WHERE path = ?');
    $st->execute([strtolower($path)]);
    $r = $st->fetch();
    if (!$r) {
        return false;
    }
    if ($r['target'] === 'file') {
        $f = db()->prepare("SELECT slug FROM files WHERE id = ?");
        $f->execute([$r['target_id']]);
        $slug = $f->fetchColumn();
        $to = $slug === false ? null : '/files/' . $slug;
    } else {
        $b = db()->prepare('SELECT slug FROM pages WHERE id = ? AND deleted_at IS NULL');
        $b->execute([$r['target_id']]);
        $slug = $b->fetchColumn();
        $to = $slug === false ? null : '/' . $slug;
    }
    if ($to === null || $to === strtolower($path)) {
        return false;
    }
    header('Location: ' . $to, true, 301);
    return true;
}

/** Remember an address that's no longer used, so it keeps forwarding. */
function remember_old_address(string $path, string $target, int $id): void
{
    if ($path === '' || $path === '/' || $path === '/files/') {
        return;
    }
    db()->prepare('INSERT INTO redirects (path, target, target_id) VALUES (?, ?, ?)
        ON CONFLICT(path) DO UPDATE SET target = excluded.target, target_id = excluded.target_id, created_at = CURRENT_TIMESTAMP')
        ->execute([strtolower($path), $target, $id]);
}

/** Something else used to live at this address (so old links would now land on the new thing). */
function old_address_owner(string $path, string $target, int $id): ?string
{
    $st = db()->prepare('SELECT target, target_id FROM redirects WHERE path = ?');
    $st->execute([strtolower($path)]);
    $r = $st->fetch();
    if (!$r || ($r['target'] === $target && (int) $r['target_id'] === $id)) {
        return null;
    }
    $t = db()->prepare($r['target'] === 'file' ? 'SELECT title FROM files WHERE id = ?' : 'SELECT title FROM pages WHERE id = ?');
    $t->execute([$r['target_id']]);
    return (string) ($t->fetchColumn() ?: 'something you deleted');
}

/** Address for a button: what was typed (cleaned), or a new short code. Never a built-in address or another button's. */
function button_address(string $want, int $exceptId): string
{
    $taken = function (string $slug) use ($exceptId): bool {
        if (in_array($slug, RESERVED_SLUGS, true) || isset(APPS[$slug])) {
            return true;
        }
        $st = db()->prepare('SELECT 1 FROM pages WHERE slug = ? AND id != ?');
        $st->execute([$slug, $exceptId]);
        return (bool) $st->fetchColumn();
    };
    if (trim(preg_replace('/[^a-z0-9]+/i', '', $want)) === '') {
        return new_code($taken);
    }
    $slug = slugify($want);
    $base = $slug;
    for ($i = 2; $taken($slug); $i++) {
        $slug = "$base-$i";
    }
    return $slug;
}

/** How many times an address was used in the last 30 days (page views, taps on its button, clicks through it). */
function address_uses(string $path, ?int $buttonId = null): int
{
    $since = date('Y-m-d', strtotime('-30 days'));
    $st = db()->prepare("SELECT COALESCE(SUM(n), 0) FROM stats WHERE day >= ? AND ((kind = 'view' AND ref = ?) OR (kind IN ('btn', 'go') AND ref = ?))");
    $st->execute([$since, strtolower($path), $buttonId === null ? '-' : (string) $buttonId]);
    return (int) $st->fetchColumn();
}

/** The delete confirmation, with the warning when people actually use the thing. */
function delete_warning(string $what, int $uses): string
{
    return $uses > 0
        ? "Hey fuckface, you're about to piss people off. $uses " . ($uses === 1 ? 'person' : 'people') . " used this $what in the last month. Delete anyway?"
        : "Delete this $what?";
}

/** Old addresses that belong to something else, as word => what it used to be (for the "you're about to take this" warning). */
function old_addresses(string $prefix, string $target, int $id): array
{
    $st = db()->prepare("SELECT path, target, target_id FROM redirects WHERE path LIKE ? AND NOT (target = ? AND target_id = ?)");
    $st->execute([$prefix . '%', $target, $id]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $word = substr($r['path'], strlen($prefix));
        if ($word === '' || str_contains($word, '/')) {
            continue;
        }
        $t = db()->prepare($r['target'] === 'file' ? 'SELECT title FROM files WHERE id = ?' : 'SELECT title FROM pages WHERE id = ?');
        $t->execute([$r['target_id']]);
        $out[$word] = (string) ($t->fetchColumn() ?: 'something you deleted');
    }
    return $out;
}
