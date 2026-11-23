<?php
declare(strict_types=1);

/** Tables where "delete" only hides the row, with an Undo button. */
const SOFT_DELETE = ['pages' => 'Button', 'page_photos' => 'Photo'];

function soft_delete(string $table, int $id): void
{
    db()->prepare("UPDATE $table SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    $_SESSION['undo'] = ['table' => $table, 'id' => $id];
    flash(SOFT_DELETE[$table] . ' removed.');
}

function live_folders(int $exceptId = 0): array
{
    $st = db()->prepare("SELECT id, title FROM pages WHERE kind = 'folder' AND deleted_at IS NULL AND id != ? ORDER BY title");
    $st->execute([$exceptId]);
    return $st->fetchAll();
}

function admin_button(int $id): array
{
    $st = db()->prepare('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL');
    $st->execute([$id]);
    return $st->fetch() ?: not_found();
}

/** Every button, grouped the way the site shows them: Home, each folder, then address-only. */
function button_groups(): array
{
    $all = db()->query('SELECT * FROM pages WHERE deleted_at IS NULL ORDER BY sort_order, id')->fetchAll();
    $folders = [];
    foreach ($all as $b) {
        if ($b['kind'] === 'folder') {
            $folders[(int) $b['id']] = $b;
        }
    }
    $groups = ['home' => [], 'none' => []];
    foreach ($folders as $fid => $f) {
        $groups[$fid] = [];
    }
    foreach ($all as $b) {
        $key = match (true) {
            $b['place'] === 'home' => 'home',
            $b['place'] === 'folder' && isset($folders[(int) $b['parent_id']]) => (int) $b['parent_id'],
            default => 'none',
        };
        $groups[$key][] = $b;
    }
    return ['groups' => $groups, 'folders' => $folders];
}

function pages_admin_routes(): array
{
    $new = function (): array {
        $kind = isset(BUTTON_KINDS[$_GET['kind'] ?? '']) ? $_GET['kind'] : 'page';
        $in = (string) ($_GET['in'] ?? 'home');
        return ['id' => 0, 'kind' => $kind, 'title' => '', 'slug' => button_address('', 0), 'url' => '', 'body' => '', 'videos' => '', 'cover' => '',
            'thumb' => '', 'icon' => '', 'buttons' => '[]', 'visible' => 1, 'featured' => 0, 'clicks' => 0,
            'place' => ctype_digit($in) ? 'folder' : (in_array($in, ['home', 'none'], true) ? $in : 'home'),
            'parent_id' => ctype_digit($in) ? (int) $in : null];
    };
    return [
        'GET /admin/buttons' => fn () => admin_render('buttons', button_groups() + ['tab' => 'buttons', 'title' => 'Buttons']),
        'GET /admin/buttons/new' => fn () => admin_render('button_edit', ['page' => $new(), 'photos' => [], 'folders' => live_folders(),
            'tab' => 'buttons', 'title' => 'New button']),
        'GET /admin/buttons/(\d+)' => function ($id) {
            $b = admin_button((int) $id);
            admin_render('button_edit', ['page' => $b, 'photos' => page_photos((int) $b['id']), 'folders' => live_folders((int) $b['id']),
                'tab' => 'buttons', 'title' => $b['title']]);
        },
        'POST /admin/buttons/save' => fn () => save_button(),
        'POST /admin/buttons/(\d+)/delete' => function ($id) {
            $b = admin_button((int) $id);
            soft_delete('pages', (int) $b['id']);
            redirect('/admin/buttons');
        },
        'POST /admin/buttons/(\d+)/feature' => function ($id) {
            $b = admin_button((int) $id);
            db()->prepare('UPDATE pages SET featured = CASE WHEN id = ? THEN 1 - featured ELSE 0 END')->execute([$b['id']]);
            flash($b['featured'] ? 'No featured button now.' : '"' . $b['title'] . '" is featured (red outline).');
            redirect('/admin/buttons');
        },
        'POST /admin/page-photos/(\d+)/cover' => function ($id) {
            $ph = find_row('page_photos', (int) $id);
            db()->prepare('UPDATE pages SET cover = ? WHERE id = ?')->execute([$ph['image'], $ph['page_id']]);
            flash('Cover photo set.');
            redirect('/admin/buttons/' . $ph['page_id'] . '#photos');
        },
        'POST /admin/page-photos/(\d+)/crop' => function ($id) {
            // Re-cropped photo replaces the old one (the old file is kept, like every page photo).
            $ph = find_row('page_photos', (int) $id);
            $new = crop_upload();
            db()->prepare('UPDATE page_photos SET image = ? WHERE id = ?')->execute([$new, $ph['id']]);
            db()->prepare('UPDATE pages SET cover = ? WHERE id = ? AND cover = ?')->execute([$new, $ph['page_id'], $ph['image']]);
            flash('Photo cropped.');
            echo '{"ok":true}';
        },
        'POST /admin/page-photos/(\d+)/delete' => function ($id) {
            $ph = find_row('page_photos', (int) $id);
            db()->prepare("UPDATE pages SET cover = '' WHERE id = ? AND cover = ?")->execute([$ph['page_id'], $ph['image']]);
            soft_delete('page_photos', (int) $ph['id']);
            redirect('/admin/buttons/' . $ph['page_id'] . '#photos');
        },
        'POST /admin/undo' => function () {
            $table = (string) ($_POST['table'] ?? '');
            if (isset(SOFT_DELETE[$table])) {
                db()->prepare("UPDATE $table SET deleted_at = NULL WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
                flash('Undone. ' . SOFT_DELETE[$table] . ' is back.');
            }
            redirect(back_to('/admin/buttons'));
        },
    ];
}

function save_button(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? admin_button($id) : null;
    $back = $id ? "/admin/buttons/$id" : '/admin/buttons/new';
    $kind = isset(BUTTON_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'page';
    $title = trim((string) ($_POST['title'] ?? ''));
    $url = $kind === 'link' ? clean_url((string) ($_POST['url'] ?? '')) : ($old['url'] ?? '');
    try {
        if ($title === '') {
            throw new RuntimeException('Give the button a name.');
        }
        if ($kind === 'link' && $url === '') {
            throw new RuntimeException('Paste where the link should go (https://…).');
        }
        $thumb = handle_photo('photo', $old['thumb'] ?? '');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($back);
    }

    // Where it shows: home, inside a folder, or nowhere (just its address)
    $where = (string) ($_POST['where'] ?? 'home');
    $place = 'home';
    $parent = null;
    if ($where === 'none') {
        $place = 'none';
    } elseif (preg_match('/^f(\d+)$/', $where, $m) && (int) $m[1] !== $id) {
        $f = db()->prepare("SELECT 1 FROM pages WHERE id = ? AND kind = 'folder' AND deleted_at IS NULL");
        $f->execute([(int) $m[1]]);
        if ($f->fetchColumn()) {
            [$place, $parent] = ['folder', (int) $m[1]];
        }
    }

    // Address: typed word, or the short code. Old address keeps forwarding.
    $notes = [];
    $slug = button_address((string) ($_POST['slug'] ?? ''), $id);
    if ($slug !== ($old['slug'] ?? '') && ($owner = old_address_owner('/' . $slug, 'button', $id)) !== null && empty($_POST['reuse_ok'])) {
        $notes[] = "Didn't take /$slug: it used to be \"$owner\", and old links still go there.";
        $slug = $old['slug'] ?? button_address('', $id);
    }

    $buttons = [];
    foreach ((array) ($_POST['btn_label'] ?? []) as $i => $label) {
        $label = trim((string) $label);
        $bu = clean_url((string) ($_POST['btn_url'][$i] ?? ''));
        if ($label !== '' && $bu !== '') {
            $buttons[] = ['label' => $label, 'url' => $bu];
        }
    }
    $featured = $place === 'home' && !empty($_POST['featured']) ? 1 : 0;
    if ($featured) {
        db()->exec('UPDATE pages SET featured = 0');
    }
    $vals = [
        'kind' => $kind, 'title' => $title, 'slug' => $slug, 'url' => $url, 'place' => $place, 'parent_id' => $parent, 'thumb' => $thumb,
        'featured' => $featured, 'visible' => empty($_POST['visible']) ? 0 : 1,
        'body' => $kind === 'link' ? ($old['body'] ?? '') : trim((string) ($_POST['body'] ?? '')),
        'videos' => $kind === 'page' ? trim((string) ($_POST['videos'] ?? '')) : ($old['videos'] ?? ''),
        'buttons' => $kind === 'page' ? json_encode($buttons) : ($old['buttons'] ?? '[]'),
    ];
    $moved = !$old || $old['place'] !== $place || (int) $old['parent_id'] !== (int) $parent;
    if ($id) {
        $sets = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($vals)));
        db()->prepare("UPDATE pages SET $sets WHERE id = :id")->execute($vals + ['id' => $id]);
    } else {
        $cols = implode(', ', array_keys($vals));
        db()->prepare("INSERT INTO pages ($cols, sort_order) VALUES (:" . implode(', :', array_keys($vals)) . ', 0)')->execute($vals);
        $id = (int) db()->lastInsertId();
    }
    if ($moved) { // new or moved: last in its new spot
        db()->prepare('UPDATE pages SET sort_order = (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM pages WHERE place = ? AND parent_id IS ? AND id != ?) WHERE id = ?')
            ->execute([$place, $parent, $id, $id]);
    }
    if ($old && $old['slug'] !== $slug) {
        remember_old_address('/' . $old['slug'], 'button', $id);
        $notes[] = "New address /$slug. The old one (/{$old['slug']}) still works.";
    }
    db()->prepare('DELETE FROM redirects WHERE path = ?')->execute(['/' . $slug]); // it lives here now

    $errors = [];
    $added = [];
    if ($kind === 'page') {
        // New photos
        $photos = $_FILES['photos'] ?? null;
        if ($photos && is_array($photos['name'])) {
            $st = db()->prepare('INSERT INTO page_photos (page_id, image, sort_order) VALUES (?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM page_photos WHERE page_id = ?))');
            foreach ($photos['name'] as $i => $n) {
                if ($n === '') {
                    continue;
                }
                try {
                    $img = save_uploaded_image(['name' => $n, 'tmp_name' => $photos['tmp_name'][$i], 'error' => $photos['error'][$i], 'size' => $photos['size'][$i]]);
                    $st->execute([$id, $img, $id]);
                    $added[] = $img;
                } catch (RuntimeException $ex) {
                    $errors[] = "$n: " . $ex->getMessage();
                }
            }
        }
        if ($added && !empty($_POST['first_is_cover'])) {
            db()->prepare('UPDATE pages SET cover = ? WHERE id = ?')->execute([$added[0], $id]);
        }
        // TikTok: look up title + thumbnail once per link; keep what we already know.
        $oldMeta = json_decode($old['video_meta'] ?? '{}', true) ?: [];
        $meta = [];
        $bad = [];
        foreach (preg_split('/\R/', $vals['videos']) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$vurl, $vtitle] = parse_video_line($line);
            $v = video_link($vurl);
            if (!$v) {
                $bad[] = trim($line);
            } elseif ($v['provider'] === 'tiktok') {
                $meta[$vurl] = $oldMeta[$vurl] ?? tiktok_lookup($vurl);
                if (empty($meta[$vurl]['id']) && empty($meta[$vurl]['title']) && $vtitle === '') {
                    $errors[] = "Couldn't read a TikTok link right now. Type a title after it to label the card.";
                }
            }
        }
        db()->prepare('UPDATE pages SET video_meta = ? WHERE id = ?')->execute([json_encode($meta), $id]);
        if ($bad) {
            $errors[] = 'Not a link (should start with https://): "' . implode('", "', $bad) . '".';
        }
        if (isset($_POST['files_sent'])) { // "Files on this page" checklist
            $want = array_map('intval', (array) ($_POST['file_ids'] ?? []));
            db()->prepare('DELETE FROM page_files WHERE page_id = ?' . ($want ? ' AND file_id NOT IN (' . implode(',', $want) . ')' : ''))->execute([$id]);
            $ins = db()->prepare('INSERT OR IGNORE INTO page_files (page_id, file_id, sort_order) VALUES (?, ?, ?)');
            foreach ($want as $i => $fid) {
                $ins->execute([$id, $fid, $i]);
            }
        }
    }
    $msg = 'Saved.' . ($notes ? ' ' . implode(' ', $notes) : '') . ($errors ? ' But: ' . implode(' ', $errors) : '')
        . ($added ? ' Added ' . count($added) . ' photo' . (count($added) > 1 ? 's' : '') . '.' : '');
    flash($msg);
    redirect("/admin/buttons/$id");
}

/** Every file, with whether it's on this page (for the page editor's checklist). */
function files_for_page_pick(int $pageId): array
{
    $st = db()->prepare('SELECT f.id, f.title, f.status, f.type, f.price_cents,
            (SELECT 1 FROM page_files pf WHERE pf.page_id = ? AND pf.file_id = f.id) on_page
        FROM files f ORDER BY f.sort_order, f.id');
    $st->execute([$pageId]);
    return $st->fetchAll();
}

/** Copy a stored image (and its thumbnail) so a home button owns its own file. */
function copy_image(string $name): string
{
    if ($name === '' || !preg_match('/^([a-z0-9-]+)\.(webp|jpg|png)$/', $name, $m) || !is_file(UPLOAD_DIR . '/' . $name)) {
        return '';
    }
    $new = bin2hex(random_bytes(8));
    copy(UPLOAD_DIR . '/' . $name, UPLOAD_DIR . "/$new.{$m[2]}");
    if (is_file($sm = UPLOAD_DIR . "/{$m[1]}-sm.webp") && $m[2] === 'webp') {
        copy($sm, UPLOAD_DIR . "/$new-sm.webp");
    }
    return "$new.{$m[2]}";
}
