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
function unique_public_slug(string $want, string $table, int $exceptId): string
{
    $base = slugify($want);
    if (in_array($base, RESERVED_SLUGS, true)) {
        $base .= '-page';
    }
    $slug = $base;
    for ($i = 2; ; $i++) {
        $p = db()->prepare('SELECT 1 FROM pages WHERE slug = ? AND NOT (? = \'pages\' AND id = ?)');
        $p->execute([$slug, $table, $exceptId]);
        $c = db()->prepare('SELECT 1 FROM categories WHERE slug = ? AND NOT (? = \'categories\' AND id = ?)');
        $c->execute([$slug, $table, $exceptId]);
        if (!$p->fetchColumn() && !$c->fetchColumn()) {
            return $slug;
        }
        $slug = "$base-$i";
    }
}
