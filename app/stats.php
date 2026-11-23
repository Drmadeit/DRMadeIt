<?php
declare(strict_types=1);

/**
 * Private, cookie-free stats kept on the site itself: one counter row per day per thing.
 *   view   ref = page path         (counted on the server when a page is shown)
 *   people ref = ''                (first page view of the day from a phone/computer)
 *   link   ref = home link id      (tapped, sent by stats.js)
 *   tool   ref = tool item id      (Amazon button on a tool list)
 *   amazon ref = host/path         (any other Amazon link, e.g. typed in a description)
 *   app    ref = app slug          (calculator opened, works even from the home screen)
 *   short  ref = short link code
 * File emails and downloads come from the downloads table, which already has their history.
 * Bots and the site owner (anyone who has logged in to admin on that browser) are skipped.
 */

const STAT_KINDS_FROM_BROWSER = ['link', 'tool', 'amazon', 'app'];
const STAT_OWNER_COOKIE = 'drm_me';

function stat_day(): string
{
    return date('Y-m-d'); // site time (Eastern)
}

function stats_skip(): bool
{
    static $skip = null;
    if ($skip !== null) {
        return $skip;
    }
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $purpose = strtolower(($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ($_SERVER['HTTP_PURPOSE'] ?? '') . ($_SERVER['HTTP_X_PURPOSE'] ?? ''));
    return $skip = is_admin()
        || isset($_COOKIE[STAT_OWNER_COOKIE])
        || str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')
        || strlen($ua) < 20
        || (bool) preg_match('/bot|crawl|spider|slurp|archiver|facebookexternalhit|meta-external|embedly|preview|monitor|uptime|pingdom|lighthouse|headless|phantom|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|httpclient|scrapy|semrush|ahrefs|bytespider|gptbot|claude|perplexity|whatsapp|telegram|discord|skype|slack/i', $ua);
}

function stat_add(string $kind, string $ref = ''): void
{
    if (stats_skip()) {
        return;
    }
    try {
        db()->prepare('INSERT INTO stats (day, kind, ref, n) VALUES (?, ?, ?, 1)
            ON CONFLICT(day, kind, ref) DO UPDATE SET n = n + 1')->execute([stat_day(), $kind, mb_substr($ref, 0, 200)]);
    } catch (Throwable $e) {
        error_log('stats: ' . $e->getMessage()); // never break a page over a counter
    }
}

/** A page was shown: count the view, and the person if it's their first page today. */
function stat_view(): void
{
    $path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if (stats_skip() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || http_response_code() !== 200) {
        return;
    }
    stat_add('view', strtolower($path));
    stat_person();
}

/** Counts each phone/computer once a day without cookies: a daily-salted hash that's thrown away tomorrow. */
function stat_person(): void
{
    $day = stat_day();
    $salt = setting('stats_salt');
    if ($salt === '') {
        set_setting('stats_salt', $salt = bin2hex(random_bytes(16)));
    }
    $h = hash('sha256', $salt . $day . ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    try {
        $st = db()->prepare('INSERT OR IGNORE INTO stat_seen (h, day) VALUES (?, ?)');
        $st->execute([$h, $day]);
        if ($st->rowCount() === 1) {
            stat_add('people');
            if (random_int(1, 50) === 1) {
                db()->prepare('DELETE FROM stat_seen WHERE day < ?')->execute([$day]);
            }
        }
    } catch (Throwable $e) {
        error_log('stats: ' . $e->getMessage());
    }
}

/** POST /t from stats.js (sendBeacon): k=link|tool|amazon|app, r=id/slug/url. Always 204. */
function stats_beacon(): void
{
    http_response_code(204);
    header('Cache-Control: no-store');
    $k = (string) ($_POST['k'] ?? '');
    $r = trim((string) ($_POST['r'] ?? ''));
    if (!in_array($k, STAT_KINDS_FROM_BROWSER, true) || $r === '' || strlen($r) > 300) {
        return;
    }
    $ok = match ($k) {
        'link' => ctype_digit($r) && stat_row_exists('links', (int) $r),
        'tool' => ctype_digit($r) && stat_row_exists('tool_items', (int) $r),
        'app' => isset(APPS[$r]),
        'amazon' => (bool) preg_match('~^(?:[a-z0-9-]+\.)*(?:amazon\.[a-z.]+|amzn\.to|a\.co)/~i', $r = stat_amazon_ref($r)),
    };
    if ($ok) {
        stat_add($k, $r);
        if ($k === 'app') {
            stat_person();
        }
    }
}

function stat_row_exists(string $table, int $id): bool
{
    $st = db()->prepare("SELECT 1 FROM $table WHERE id = ?");
    $st->execute([$id]);
    return (bool) $st->fetchColumn();
}

/** amazon.com/Some-Name/dp/B0ABC123?tag=… → amazon.com/dp/B0ABC123 so the same product adds up. */
function stat_amazon_ref(string $url): string
{
    $host = strtolower(preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST)));
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (preg_match('~/(?:dp|gp/product|gp/aw/d)/([A-Z0-9]{10})~i', $path, $m)) {
        $path = '/dp/' . strtoupper($m[1]);
    }
    return $host . rtrim($path, '/');
}

/** Everything the admin Stats screen shows, for the last $days days (0 = all time). */
function stats_report(int $days): array
{
    $since = $days ? date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) : '0000-00-00';
    $sinceUtc = $days ? gmdate('Y-m-d H:i:s', strtotime($since . ' 00:00:00')) : '0000-00-00';
    $q = function (string $sql, array $args = []): array {
        $st = db()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    };
    $sum = fn (string $kind) => (int) ($q('SELECT SUM(n) s FROM stats WHERE kind = ? AND day >= ?', [$kind, $since])[0]['s'] ?? 0);
    $top = fn (string $kind, int $limit = 50) => $q('SELECT ref, SUM(n) n FROM stats WHERE kind = ? AND day >= ? GROUP BY ref ORDER BY n DESC, ref LIMIT ' . $limit, [$kind, $since]);

    // Daily bars (by month for all time)
    $byMonth = $days === 0 || $days > 90;
    $rows = $q('SELECT ' . ($byMonth ? 'substr(day, 1, 7)' : 'day') . " d, SUM(CASE WHEN kind = 'people' THEN n END) people, SUM(CASE WHEN kind = 'view' THEN n END) views
        FROM stats WHERE day >= ? GROUP BY d ORDER BY d", [$since]);
    $series = [];
    if (!$byMonth) {
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[date('Y-m-d', strtotime("-$i days"))] = ['people' => 0, 'views' => 0];
        }
    }
    foreach ($rows as $r) {
        $series[$r['d']] = ['people' => (int) $r['people'], 'views' => (int) $r['views']];
    }

    $links = [];
    foreach ($top('link') as $r) {
        $l = $q('SELECT title, url FROM links WHERE id = ?', [(int) $r['ref']])[0] ?? null;
        $links[] = ['label' => $l['title'] ?? '(deleted link)', 'sub' => $l['url'] ?? '', 'n' => (int) $r['n']];
    }
    $amazon = [];
    foreach ($top('tool') as $r) {
        $t = $q('SELECT i.name, l.title list FROM tool_items i LEFT JOIN tool_lists l ON l.id = i.list_id WHERE i.id = ?', [(int) $r['ref']])[0] ?? null;
        $amazon[] = ['label' => $t['name'] ?? '(deleted tool)', 'sub' => $t ? 'Tool list: ' . $t['list'] : '', 'n' => (int) $r['n']];
    }
    foreach ($top('amazon') as $r) {
        $amazon[] = ['label' => $r['ref'], 'sub' => 'Link in your text', 'n' => (int) $r['n']];
    }
    usort($amazon, fn ($a, $b) => $b['n'] <=> $a['n']);

    $pages = array_map(fn ($r) => ['label' => stat_page_label($r['ref']), 'sub' => $r['ref'], 'n' => (int) $r['n']], $top('view', 30));
    $apps = array_map(fn ($r) => ['label' => APPS[$r['ref']]['name'] ?? $r['ref'], 'sub' => '', 'n' => (int) $r['n']], $top('app'));
    $short = array_map(fn ($r) => ['label' => '/' . $r['ref'], 'sub' => '', 'n' => (int) $r['n']], $top('short'));

    $files = $q('SELECT f.id, f.title, COUNT(d.id) asked, SUM(d.uses > 0) got
        FROM downloads d JOIN files f ON f.id = d.file_id WHERE d.created_at >= ? GROUP BY f.id ORDER BY got DESC, asked DESC', [$sinceUtc]);

    $first = $q('SELECT MIN(day) d FROM stats')[0]['d'] ?? null;
    return [
        'people' => $sum('people'), 'views' => $sum('view'),
        'taps' => $sum('link'), 'amazonTaps' => $sum('tool') + $sum('amazon'),
        'downloads' => array_sum(array_map(fn ($f) => (int) $f['got'], $files)),
        'series' => $series, 'byMonth' => $byMonth,
        'pages' => $pages, 'links' => $links, 'amazon' => array_slice($amazon, 0, 50), 'files' => $files, 'apps' => $apps, 'short' => $short,
        'since' => $first,
    ];
}

/** "/files/spice-rack" → "Spice Rack" (falls back to the address). */
function stat_page_label(string $path): string
{
    static $fixed = ['/' => 'Home', '/files' => 'File shop', '/tools' => 'Tool lists', '/videos' => 'Videos', '/toolbox' => 'Toolbox (calculators)'];
    if (isset($fixed[$path])) {
        return $fixed[$path];
    }
    $one = function (string $sql, string $arg): ?string {
        $st = db()->prepare($sql);
        $st->execute([$arg]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    };
    $title = match (true) {
        (bool) preg_match('#^/files/([a-z0-9-]+)$#', $path, $m) => $one('SELECT title FROM files WHERE slug = ?', $m[1]),
        (bool) preg_match('#^/tools/([a-z0-9-]+)$#', $path, $m) => $one('SELECT title FROM tool_lists WHERE slug = ?', $m[1]),
        (bool) preg_match('#^/[a-z0-9-]+/([a-z0-9-]+)$#', $path, $m) => $one('SELECT title FROM pages WHERE slug = ?', $m[1]),
        (bool) preg_match('#^/([a-z0-9-]+)$#', $path, $m) => $one('SELECT title FROM pages WHERE slug = ?', $m[1])
            ?? $one('SELECT name FROM categories WHERE slug = ?', $m[1]),
        default => null,
    };
    return $title ?? $path;
}
