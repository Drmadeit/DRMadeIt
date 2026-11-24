<?php
declare(strict_types=1);

/**
 * Your email list, done the non-spammy way:
 *  - people choose: updates to a file they downloaded, and/or news about new files
 *  - every email has the mail app's own Unsubscribe button (List-Unsubscribe headers) plus one-tap links
 *  - you send by pressing a button in the file editor; nothing goes out on its own
 * Unsubscribe link: /u/<key>/all (everything) or /u/<key>/f<fileId> (just that file).
 */

function subscriber_key(int $subId): string
{
    $st = db()->prepare('SELECT unsub_key FROM subscribers WHERE id = ?');
    $st->execute([$subId]);
    $key = (string) $st->fetchColumn();
    if ($key === '') {
        $key = bin2hex(random_bytes(16));
        db()->prepare('UPDATE subscribers SET unsub_key = ? WHERE id = ?')->execute([$key, $subId]);
    }
    return $key;
}

function unsub_url(string $key, string $scope): string
{
    return base_url() . '/u/' . $key . '/' . $scope;
}

/** Headers that make Gmail / Apple Mail / Outlook show their own one-tap Unsubscribe. */
function unsub_headers(string $key, string $scope): array
{
    return ['List-Unsubscribe' => '<' . unsub_url($key, $scope) . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'];
}

function email_footer(string $key, ?int $fileId, bool $promo): string
{
    $lines = ["\n\n---"];
    if ($fileId !== null) {
        $lines[] = 'Stop emails about this file: ' . unsub_url($key, 'f' . $fileId);
    }
    $lines[] = 'Stop all emails: ' . unsub_url($key, 'all');
    $lines[] = "You don't have to report this as spam. Just tap Unsubscribe and you're off the list for good.";
    if ($promo && ($addr = trim(setting('mail_address'))) !== '') {
        $lines[] = setting('site_name', 'D.R. Made It') . ' · ' . $addr;
    }
    return implode("\n", $lines) . "\n";
}

/** People to email: watchers of one file, or everyone who wants news. */
function list_recipients(string $kind, int $fileId = 0): array
{
    $sql = $kind === 'watchers'
        ? 'SELECT s.* FROM subscribers s JOIN file_watchers w ON w.subscriber_id = s.id WHERE w.file_id = ?'
        : 'SELECT * FROM subscribers WHERE opted_in = 1';
    $st = db()->prepare($sql);
    $st->execute($kind === 'watchers' ? [$fileId] : []);
    return $st->fetchAll();
}

/** Send one email per person. Returns [sent, failed]. */
function send_list(array $people, string $subject, callable $body, string $scopeFor): array
{
    @set_time_limit(0);
    $sent = $failed = 0;
    foreach ($people as $p) {
        $key = subscriber_key((int) $p['id']);
        $scope = $scopeFor === 'all' ? 'all' : $scopeFor;
        if (send_mail($p['email'], $subject, $body($key), unsub_headers($key, $scope)) === null) {
            $sent++;
        } else {
            $failed++;
        }
    }
    return [$sent, $failed];
}

/** /u/<key>/<scope>: unsubscribe. GET shows a page that finishes it (link checkers that only "look" at links don't unsubscribe anyone). */
function unsub_route(string $path, string $method): bool
{
    if (!preg_match('#^/u/([a-f0-9]{32})/(all|f\d+)(/undo)?$#', $path, $m)) {
        return false;
    }
    [$key, $scope, $undo] = [$m[1], $m[2], !empty($m[3])];
    $st = db()->prepare('SELECT * FROM subscribers WHERE unsub_key = ?');
    $st->execute([$key]);
    $sub = $st->fetch();
    if (!$sub) {
        render('unsub', ['title' => 'Unsubscribe', 'state' => 'unknown']);
        return true;
    }
    $fileId = $scope === 'all' ? 0 : (int) substr($scope, 1);
    $fileTitle = '';
    if ($fileId) {
        $f = db()->prepare('SELECT title FROM files WHERE id = ?');
        $f->execute([$fileId]);
        $fileTitle = (string) $f->fetchColumn();
    }
    $vars = ['title' => 'Unsubscribe', 'key' => $key, 'scope' => $scope, 'fileTitle' => $fileTitle, 'email' => $sub['email']];
    if ($method !== 'POST') {
        render('unsub', $vars + ['state' => 'confirm']);
        return true;
    }
    if ($undo) {
        if ($scope === 'all') {
            db()->prepare('UPDATE subscribers SET opted_in = ? WHERE id = ?')->execute([(int) ($_POST['was_news'] ?? 0), $sub['id']]);
            foreach (array_filter(array_map('intval', explode(',', (string) ($_POST['was_files'] ?? '')))) as $fid) {
                db()->prepare('INSERT OR IGNORE INTO file_watchers (subscriber_id, file_id) VALUES (?, ?)')->execute([$sub['id'], $fid]);
            }
        } else {
            db()->prepare('INSERT OR IGNORE INTO file_watchers (subscriber_id, file_id) VALUES (?, ?)')->execute([$sub['id'], $fileId]);
        }
        render('unsub', $vars + ['state' => 'undone']);
        return true;
    }
    // Remember what they had, so Undo can put it back
    $w = db()->prepare('SELECT file_id FROM file_watchers WHERE subscriber_id = ?');
    $w->execute([$sub['id']]);
    $vars += ['wasNews' => (int) $sub['opted_in'], 'wasFiles' => implode(',', $w->fetchAll(PDO::FETCH_COLUMN))];
    if ($scope === 'all') {
        db()->prepare('UPDATE subscribers SET opted_in = 0 WHERE id = ?')->execute([$sub['id']]);
        db()->prepare('DELETE FROM file_watchers WHERE subscriber_id = ?')->execute([$sub['id']]);
    } else {
        db()->prepare('DELETE FROM file_watchers WHERE subscriber_id = ? AND file_id = ?')->execute([$sub['id'], $fileId]);
    }
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') { // the mail app's own button: no page needed
        http_response_code(200);
        echo 'Unsubscribed.';
        return true;
    }
    render('unsub', $vars + ['state' => 'done']);
    return true;
}
