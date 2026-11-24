<?php
declare(strict_types=1);

const LINK_HOURS = 48;
const LINK_USES = 5;
const REQUESTS_PER_HOUR = 5; // per email, stops someone spamming an inbox

function price_label(int $cents): string
{
    if ($cents <= 0) {
        return 'Free';
    }
    return '$' . ($cents % 100 === 0 ? number_format($cents / 100) : number_format($cents / 100, 2));
}

function type_label(string $type): string
{
    return $type === '3d' ? '3D print' : 'CNC';
}

function license_text(string $license): string
{
    return $license === 'commercial'
        ? (setting('license_commercial') ?: 'Commercial OK: you can sell what you make with this file. Please don\'t share or resell the file itself.')
        : (setting('license_personal') ?: 'Personal use: make it for yourself or as a gift. Please don\'t sell items made from it or share the file.');
}

function find_published_file(string $slug): array
{
    $st = db()->prepare("SELECT * FROM files WHERE slug = ? AND (status = 'published' OR ?)");
    $st->execute([strtolower($slug), is_admin() ? 1 : 0]); // admin can preview drafts
    $file = $st->fetch();
    if (!$file && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && redirect_route('/files/' . strtolower($slug))) {
        exit; // an old address: sent on to where the file lives now
    }
    return $file ?: not_found();
}

function file_images(int $fileId): array
{
    $st = db()->prepare('SELECT image FROM file_images WHERE file_id = ? ORDER BY sort_order, id');
    $st->execute([$fileId]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/** POST /files/<slug>/get — email-gated free download. */
function request_download(array $file): void
{
    if ($file['file_path'] === '') { // MakerWorld-only file: nothing to email
        redirect('/files/' . $file['slug']);
    }
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $state = ['file' => $file, 'images' => file_images((int) $file['id']), 'title' => $file['title'], 'email' => $email];

    // Bots fill the hidden "website" box; pretend it worked.
    if (!empty($_POST['website'])) {
        render('file', $state + ['sent' => true]);
        return;
    }
    if ($file['price_cents'] > 0 || $file['file_path'] === '') {
        not_found();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
        render('file', $state + ['formError' => 'Please enter a valid email address.']);
        return;
    }

    $optIn = empty($_POST['news']) && empty($_POST['updates']) ? 0 : 1; // "updates" = the old single checkbox
    db()->prepare('INSERT INTO subscribers (email, opted_in, unsub_key) VALUES (?, ?, ?) ON CONFLICT(email) DO UPDATE SET opted_in = MAX(opted_in, excluded.opted_in)')
        ->execute([$email, $optIn, bin2hex(random_bytes(16))]);
    $sub = db()->prepare('SELECT id FROM subscribers WHERE email = ?');
    $sub->execute([$email]);
    $subId = (int) $sub->fetchColumn();
    if (!empty($_POST['watch'])) {
        db()->prepare('INSERT OR IGNORE INTO file_watchers (subscriber_id, file_id) VALUES (?, ?)')->execute([$subId, $file['id']]);
    }

    $recent = db()->prepare("SELECT COUNT(*) FROM downloads WHERE subscriber_id = ? AND created_at >= datetime('now', '-1 hour')");
    $recent->execute([$subId]);
    if ((int) $recent->fetchColumn() >= REQUESTS_PER_HOUR) {
        render('file', $state + ['formError' => 'You already asked for a few links in the last hour. Check your inbox and spam folder.']);
        return;
    }

    $token = bin2hex(random_bytes(20));
    db()->prepare("INSERT INTO downloads (file_id, subscriber_id, token, expires_at) VALUES (?, ?, ?, datetime('now', ?))")
        ->execute([$file['id'], $subId, $token, '+' . LINK_HOURS . ' hours']);

    $link = base_url() . '/download/' . $token;
    $key = subscriber_key($subId);
    [$subject, $body] = download_email($file, $link, $key, !empty($_POST['watch']));
    $err = send_mail($email, $subject, $body, unsub_headers($key, 'all'));
    if ($err !== null) {
        // Don't lose the visitor: their email is already saved, so hand them the link right here.
        error_log("Download email to $email failed: $err");
        render('file', $state + ['directLink' => $link]);
        return;
    }
    render('file', $state + ['sent' => true]);
}

/** GET /download/<token> — stream the file if the link is still good. */
function serve_download(string $token): void
{
    $st = db()->prepare("SELECT d.*, f.file_path, f.title, f.slug, (d.expires_at < datetime('now')) expired
        FROM downloads d JOIN files f ON f.id = d.file_id WHERE d.token = ?");
    $st->execute([$token]);
    $d = $st->fetch();
    $problem = match (true) {
        !$d => 'This download link is not valid.',
        (bool) $d['expired'] => 'This download link has expired.',
        $d['uses'] >= LINK_USES => 'This download link has been used ' . LINK_USES . ' times already.',
        $d['file_path'] === '' || !is_file(DATA_DIR . '/files/' . $d['file_path']) => 'This file is not available right now.',
        default => null,
    };
    if ($problem !== null) {
        http_response_code(410);
        render('download_problem', ['title' => 'Download', 'problem' => $problem, 'slug' => $d['slug'] ?? null]);
        return;
    }
    db()->prepare('UPDATE downloads SET uses = uses + 1 WHERE id = ?')->execute([$d['id']]);

    $path = DATA_DIR . '/files/' . $d['file_path'];
    @set_time_limit(0);
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $d['file_path']) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    readfile($path);
}

function amazon_link(string $url): string
{
    $tag = setting('amazon_tag');
    $host = (string) parse_url($url, PHP_URL_HOST);
    if ($tag === '' || !preg_match('/(^|\.)amazon\.[a-z.]+$/', $host) || preg_match('/[?&]tag=/', $url)) {
        return $url;
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . 'tag=' . rawurlencode($tag);
}

/** "https://makerworld.com/en/models/123456-spice-rack?x" → ['id' => '123456', 'url' => clean link, 'guess' => 'Spice Rack'] */
function makerworld_model(string $line): ?array
{
    if (!preg_match('#https?://(?:www\.)?makerworld\.com(?:/[a-z]{2}(?:-[a-z]{2})?)?/models/(\d+)(-[A-Za-z0-9-]+)?#i', $line, $m)) {
        return null;
    }
    $slug = trim($m[2] ?? '', '-');
    return ['id' => $m[1], 'url' => 'https://makerworld.com/en/models/' . $m[1] . ($slug !== '' ? '-' . $slug : ''),
        'guess' => $slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : ''];
}

/** Title + cover photo from the model page, if MakerWorld lets the server read it. */
function makerworld_lookup(string $url): array
{
    $html = http_get($url, 10);
    if (!$html) {
        return [];
    }
    $meta = function (string $prop) use ($html): string {
        foreach (['property', 'name'] as $attr) {
            if (preg_match('#<meta[^>]+' . $attr . '=["\']' . preg_quote($prop, '#') . '["\'][^>]+content=["\']([^"\']+)#i', $html, $m)
                || preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+' . $attr . '=["\']' . preg_quote($prop, '#') . '["\']#i', $html, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
        }
        return '';
    };
    $title = trim(preg_replace('/\s*[-|–]\s*MakerWorld.*$/i', '', $meta('og:title')));
    return ['title' => $title, 'image' => $meta('og:image') ?: $meta('twitter:image'), 'description' => $meta('og:description')];
}
