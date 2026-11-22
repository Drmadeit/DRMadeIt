<?php
declare(strict_types=1);

/**
 * Short links: drmadeit.com/<code> → any long link (Amazon, YouTube, Payhip…), counting clicks.
 * Codes share the top-level addresses with pages and categories, so the admin won't allow a clash.
 */

/** Returns true if it handled the request. Runs after pages, so a page always wins. */
function short_link_route(string $path): bool
{
    if (!preg_match('#^/([A-Za-z0-9-]{1,40})$#', $path, $m)) {
        return false;
    }
    $st = db()->prepare('SELECT id, url FROM short_links WHERE code = ?');
    $st->execute([strtolower($m[1])]);
    $link = $st->fetch();
    if (!$link) {
        return false;
    }
    db()->prepare('UPDATE short_links SET clicks = clicks + 1 WHERE id = ?')->execute([$link['id']]);
    header('Cache-Control: no-store'); // so every click is counted
    header('X-Robots-Tag: noindex');
    header('Location: ' . $link['url'], true, 302);
    return true;
}

/** Why a code can't be used, or null if it's free. */
function short_code_problem(string $code, int $exceptId = 0): ?string
{
    if (!preg_match('/^[a-z0-9-]{1,40}$/', $code)) {
        return 'Use only letters, numbers and dashes.';
    }
    if (in_array($code, RESERVED_SLUGS, true) || in_array($code, ['about', 'robots-txt'], true)) {
        return "\"$code\" is already a page on the site. Pick another word.";
    }
    foreach (['pages' => 'a page', 'categories' => 'a category'] as $table => $what) {
        $st = db()->prepare("SELECT 1 FROM $table WHERE slug = ?");
        $st->execute([$code]);
        if ($st->fetchColumn()) {
            return "\"$code\" is already $what on your site. Pick another word.";
        }
    }
    $st = db()->prepare('SELECT 1 FROM short_links WHERE code = ? AND id != ?');
    $st->execute([$code, $exceptId]);
    return $st->fetchColumn() ? "\"$code\" is already one of your short links." : null;
}

/** A short random code nobody is using, like "k7m2". */
function random_short_code(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // no 0/o, 1/l/i: easy to read and type
    for ($len = 4; ; $len++) {
        for ($try = 0; $try < 20; $try++) {
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            if (short_code_problem($code) === null) {
                return $code;
            }
        }
    }
}

function short_admin_routes(): array
{
    return [
        'GET /admin/short' => fn () => admin_render('short', [
            'links' => db()->query('SELECT * FROM short_links ORDER BY id DESC')->fetchAll(),
            'tab' => 'more', 'title' => 'Short links']),
        'POST /admin/short/save' => function () {
            $id = (int) ($_POST['id'] ?? 0);
            $url = clean_url((string) ($_POST['url'] ?? ''));
            $code = strtolower(trim((string) ($_POST['code'] ?? ''), " /\t"));
            $code = preg_replace('/[\s_]+/', '-', $code);
            if ($url === '') {
                flash('Paste the long link first (it should start with https://).');
                redirect('/admin/short');
            }
            if ($code === '') {
                $code = random_short_code();
            } elseif ($err = short_code_problem($code, $id)) {
                flash($err);
                redirect('/admin/short');
            }
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($id) {
                db()->prepare('UPDATE short_links SET code = ?, url = ?, note = ? WHERE id = ?')->execute([$code, $url, $note, $id]);
            } else {
                db()->prepare('INSERT INTO short_links (code, url, note) VALUES (?, ?, ?)')->execute([$code, $url, $note]);
            }
            $_SESSION['short_new'] = $code;
            flash('Ready: ' . preg_replace('#^https?://#', '', base_url()) . '/' . $code);
            redirect('/admin/short');
        },
        'POST /admin/short/(\d+)/delete' => function ($id) {
            db()->prepare('DELETE FROM short_links WHERE id = ?')->execute([(int) $id]);
            flash('Short link deleted.');
            redirect('/admin/short');
        },
    ];
}
