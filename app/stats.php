<?php
declare(strict_types=1);

/**
 * Private, cookie-free stats kept on the site itself: one counter row per day per thing.
 *   view   ref = page path         (counted on the server when a page is shown)
 *   people ref = ''                (first page view of the day from a phone/computer)
 *   btn    ref = button id         (tapped on the home page or in a folder, sent by stats.js)
 *   amazon ref = host/path         (any other Amazon link, e.g. typed in a description)
 *   mw     ref = file id           (tapped "Get it on MakerWorld" on a file page)
 *   app    ref = app slug          (calculator opened, works even from the home screen)
 *   go     ref = button id         (someone used a link button's own address, e.g. drmadeit.com/k7m)
 * File emails and downloads come from the downloads table, which already has their history.
 * Bots and the site owner (anyone who has logged in to admin on that browser) are skipped.
 */

const STAT_KINDS_FROM_BROWSER = ['btn', 'amazon', 'app', 'mw'];
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
        'btn' => ctype_digit($r) && stat_row_exists('pages', (int) $r),
        'mw' => ctype_digit($r) && stat_row_exists('files', (int) $r),
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

    $title = fn (string $id) => $q('SELECT title, kind, slug, url FROM pages WHERE id = ?', [(int) $id])[0] ?? null;
    $links = [];
    foreach ($top('btn') as $r) {
        $b = $title($r['ref']);
        $links[] = ['label' => $b['title'] ?? '(deleted button)', 'sub' => $b ? ($b['kind'] === 'link' ? $b['url'] : '/' . $b['slug']) : '', 'n' => (int) $r['n']];
    }
    $amazon = [];
    foreach ($top('amazon') as $r) {
        $amazon[] = ['label' => $r['ref'], 'sub' => '', 'n' => (int) $r['n']];
    }
    $pages = array_map(fn ($r) => ['label' => stat_page_label($r['ref']), 'sub' => $r['ref'], 'n' => (int) $r['n']], $top('view', 30));
    $apps = array_map(fn ($r) => ['label' => APPS[$r['ref']]['name'] ?? $r['ref'], 'sub' => '', 'n' => (int) $r['n']], $top('app'));
    $short = array_map(function ($r) use ($title) {
        $b = $title($r['ref']);
        return ['label' => $b ? '/' . $b['slug'] . ' · ' . $b['title'] : '(deleted link)', 'sub' => $b['url'] ?? '', 'n' => (int) $r['n']];
    }, $top('go'));

    $files = $q('SELECT f.id, f.title, COUNT(d.id) asked, SUM(d.uses > 0) got
        FROM downloads d JOIN files f ON f.id = d.file_id WHERE d.created_at >= ? GROUP BY f.id ORDER BY got DESC, asked DESC', [$sinceUtc]);

    // MakerWorld taps per file, added to the files list (and files only sent there show up too)
    $mw = array_column($top('mw', 200), 'n', 'ref');
    foreach ($files as &$f) {
        $f['mw'] = (int) ($mw[$f['id']] ?? 0);
        unset($mw[$f['id']]);
    }
    unset($f);
    foreach ($mw as $fid => $n) {
        $t = $q('SELECT title FROM files WHERE id = ?', [(int) $fid])[0]['title'] ?? null;
        if ($t !== null) {
            $files[] = ['id' => (int) $fid, 'title' => $t, 'asked' => 0, 'got' => 0, 'mw' => (int) $n];
        }
    }
    $emails = (int) ($q('SELECT COUNT(*) n FROM subscribers WHERE created_at >= ?', [$sinceUtc])[0]['n'] ?? 0);

    $first = $q('SELECT MIN(day) d FROM stats')[0]['d'] ?? null;
    return [
        'people' => $sum('people'), 'views' => $sum('view'),
        'taps' => $sum('btn'), 'amazonTaps' => $sum('amazon'),
        'downloads' => array_sum(array_map(fn ($f) => (int) $f['got'], $files)), 'emails' => $emails,
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
        (bool) preg_match('#^/([a-z0-9-]+)$#', $path, $m) => $one('SELECT title FROM pages WHERE slug = ?', $m[1]),
        default => null,
    };
    if ($title === null) { // an old address: name it after what it points to now
        $st = db()->prepare("SELECT CASE r.target WHEN 'file' THEN (SELECT title FROM files WHERE id = r.target_id) ELSE (SELECT title FROM pages WHERE id = r.target_id) END FROM redirects r WHERE r.path = ?");
        $st->execute([$path]);
        $title = ($t = $st->fetchColumn()) ? $t . ' (old address)' : null;
    }
    return $title ?? $path;
}

/** The focused pages behind the admin Home cards: visitors day by day, or what happened with each file. */
function stats_detail(string $which, int $days): array
{
    $since = $days ? date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) : '0000-00-00';
    $sinceUtc = $days ? gmdate('Y-m-d H:i:s', strtotime($since . ' 00:00:00')) : '0000-00-00';
    $q = function (string $sql, array $args = []): array {
        $st = db()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    };
    if ($which === 'visitors') {
        $daysList = $q("SELECT day, SUM(CASE WHEN kind = 'people' THEN n ELSE 0 END) people, SUM(CASE WHEN kind = 'view' THEN n ELSE 0 END) views,
                SUM(CASE WHEN kind = 'btn' THEN n ELSE 0 END) taps
            FROM stats WHERE day >= ? GROUP BY day ORDER BY day DESC", [$since]);
        $pages = array_map(fn ($r) => ['label' => stat_page_label($r['ref']), 'sub' => $r['ref'], 'n' => (int) $r['n']],
            $q("SELECT ref, SUM(n) n FROM stats WHERE kind = 'view' AND day >= ? GROUP BY ref ORDER BY n DESC LIMIT 15", [$since]));
        return ['daysList' => $daysList, 'pages' => $pages,
            'people' => array_sum(array_column($daysList, 'people')), 'views' => array_sum(array_column($daysList, 'views'))];
    }
    // Files: page views, email requests, downloads and MakerWorld taps for every file that had any
    $rows = [];
    foreach ($q('SELECT id, title, slug, status FROM files') as $f) {
        // its current address plus any old ones it used to have
        $views = (int) ($q("SELECT SUM(n) s FROM stats WHERE kind = 'view' AND day >= ? AND (ref = ? OR ref IN (SELECT path FROM redirects WHERE target = 'file' AND target_id = ?))",
            [$since, '/files/' . $f['slug'], $f['id']])[0]['s'] ?? 0);
        $mw = (int) ($q("SELECT SUM(n) s FROM stats WHERE kind = 'mw' AND ref = ? AND day >= ?", [(string) $f['id'], $since])[0]['s'] ?? 0);
        $d = $q('SELECT COUNT(*) asked, COALESCE(SUM(uses > 0), 0) got FROM downloads WHERE file_id = ? AND created_at >= ?', [$f['id'], $sinceUtc])[0];
        if ($views + $mw + (int) $d['asked'] > 0) {
            $rows[] = $f + ['views' => $views, 'mw' => $mw, 'asked' => (int) $d['asked'], 'got' => (int) $d['got']];
        }
    }
    usort($rows, fn ($a, $b) => [$b['got'] + $b['mw'], $b['views']] <=> [$a['got'] + $a['mw'], $a['views']]);
    return ['rows' => $rows, 'totals' => [
        'views' => array_sum(array_column($rows, 'views')), 'asked' => array_sum(array_column($rows, 'asked')),
        'got' => array_sum(array_column($rows, 'got')), 'mw' => array_sum(array_column($rows, 'mw'))]];
}
