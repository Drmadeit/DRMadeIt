<?php
declare(strict_types=1);

/**
 * Pages and categories made in admin.
 *   /<category>          a category: cards for each page in it
 *   /<category>/<page>   a page in a category
 *   /<page>              a page with no category
 * Page and category addresses share one namespace and can't use the site's built-in addresses.
 */
const RESERVED_SLUGS = ['admin', 'about', 'tools', 'files', 'videos', 'calc', 'media', 'download', 'assets', 'robots-txt', 'index-php', 'sitemap', 'p', 'page', 'pages'];

function live_category(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL AND (visible = 1 OR ?)');
    $st->execute([$slug, is_admin() ? 1 : 0]);
    return $st->fetch() ?: null;
}

function live_page(string $slug): ?array
{
    $st = db()->prepare('SELECT p.*, c.slug cat_slug, c.name cat_name FROM pages p
        LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
        WHERE p.slug = ? AND p.deleted_at IS NULL AND (p.visible = 1 OR ?)');
    $st->execute([$slug, is_admin() ? 1 : 0]);
    return $st->fetch() ?: null;
}

/** The one true address of a page. */
function page_url(array $page): string
{
    return !empty($page['cat_slug']) ? '/' . $page['cat_slug'] . '/' . $page['slug'] : '/' . $page['slug'];
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
    if (count($parts) === 1 && preg_match('/^[a-z0-9-]+$/', $parts[0])) {
        if ($cat = live_category($parts[0])) {
            $st = db()->prepare('SELECT p.*, ? cat_slug FROM pages p WHERE category_id = ? AND deleted_at IS NULL AND (visible = 1 OR ?) ORDER BY sort_order, id');
            $st->execute([$cat['slug'], $cat['id'], is_admin() ? 1 : 0]);
            $pages = $st->fetchAll();
            render('category', ['cat' => $cat, 'pages' => $pages, 'title' => $cat['name'], 'description' => $cat['intro'],
                'ogImage' => img_url($cat['cover'] ?: ($pages ? page_cover($pages[0]) : ''))]);
            return true;
        }
        if ($page = live_page($parts[0])) {
            return show_page($page, $path);
        }
    }
    if (count($parts) === 2 && preg_match('/^[a-z0-9-]+$/', $parts[1]) && ($page = live_page($parts[1]))) {
        return show_page($page, $path);
    }
    return false;
}

function show_page(array $page, string $path): bool
{
    $url = page_url($page);
    if ($url !== $path) { // category changed or old link: send to the current address
        header('Location: ' . $url, true, 301);
        return true;
    }
    $photos = page_photos((int) $page['id']);
    render('page', ['page' => $page, 'photos' => $photos, 'cover' => page_cover($page), 'title' => $page['title'],
        'description' => mb_strimwidth(preg_replace('/\s+/', ' ', $page['body']), 0, 180, '…'),
        'ogImage' => img_url(page_cover($page))]);
    return true;
}

/** Slug unique across pages AND categories, and not a built-in address. */
function unique_public_slug(string $want, string $table, int $exceptId, string $prefix = ''): string
{
    $base = slugify($want);
    if (in_array($base, RESERVED_SLUGS, true)) {
        $base .= '-page';
    }
    $taken = function (string $slug) use ($table, $exceptId): bool {
        $p = db()->prepare('SELECT 1 FROM pages WHERE slug = ? AND NOT (? = \'pages\' AND id = ?)');
        $p->execute([$slug, $table, $exceptId]);
        $c = db()->prepare('SELECT 1 FROM categories WHERE slug = ? AND NOT (? = \'categories\' AND id = ?)');
        $c->execute([$slug, $table, $exceptId]);
        return (bool) ($p->fetchColumn() || $c->fetchColumn());
    };
    // Name taken and the page is in a category? "builds" in TikTok becomes "tiktok-builds" (nicer than builds-2).
    if ($prefix !== '' && $taken($base) && !str_starts_with($base, $prefix . '-')) {
        $base = $prefix . '-' . $base;
    }
    $slug = $base;
    for ($i = 2; ; $i++) {
        if (!$taken($slug)) {
            return $slug;
        }
        $slug = "$base-$i";
    }
}
