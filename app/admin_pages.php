<?php
declare(strict_types=1);

/** Tables where "delete" only hides the row, with an Undo button. */
const SOFT_DELETE = ['pages' => 'Page', 'categories' => 'Category', 'page_photos' => 'Photo'];

function soft_delete(string $table, int $id): void
{
    db()->prepare("UPDATE $table SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    $_SESSION['undo'] = ['table' => $table, 'id' => $id];
    flash(SOFT_DELETE[$table] . ' removed.');
}

function live_categories(): array
{
    return db()->query('SELECT c.*, (SELECT COUNT(*) FROM pages p WHERE p.category_id = c.id AND p.deleted_at IS NULL) n
        FROM categories c WHERE deleted_at IS NULL ORDER BY sort_order, id')->fetchAll();
}

function admin_page_row(int $id): array
{
    $st = db()->prepare('SELECT p.*, c.slug cat_slug, c.name cat_name FROM pages p
        LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL WHERE p.id = ? AND p.deleted_at IS NULL');
    $st->execute([$id]);
    return $st->fetch() ?: not_found();
}

function pages_admin_routes(): array
{
    return [
        'GET /admin/pages' => function () {
            $pages = db()->query('SELECT p.*, c.slug cat_slug FROM pages p
                LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
                WHERE p.deleted_at IS NULL ORDER BY p.sort_order, p.id')->fetchAll();
            admin_render('pages', ['cats' => live_categories(), 'pages' => $pages, 'tab' => 'pages', 'title' => 'Pages']);
        },
        'GET /admin/pages/new' => fn () => admin_render('page_edit', [
            'page' => ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'videos' => '', 'cover' => '', 'buttons' => '[]', 'visible' => 1,
                'category_id' => (int) ($_GET['category'] ?? 0) ?: null, 'cat_slug' => null],
            'photos' => [], 'cats' => live_categories(), 'tab' => 'pages', 'title' => 'New page']),
        'GET /admin/pages/(\d+)' => function ($id) {
            $page = admin_page_row((int) $id);
            admin_render('page_edit', ['page' => $page, 'photos' => page_photos((int) $page['id']), 'cats' => live_categories(),
                'tab' => 'pages', 'title' => $page['title']]);
        },
        'POST /admin/pages/save' => fn () => save_page(),
        'POST /admin/pages/(\d+)/delete' => function ($id) {
            $page = admin_page_row((int) $id);
            soft_delete('pages', (int) $page['id']);
            redirect('/admin/pages');
        },
        'POST /admin/pages/(\d+)/to-home' => function ($id) {
            $page = admin_page_row((int) $id);
            $img = copy_image(page_cover($page));
            db()->prepare('INSERT INTO links (title, url, image, icon, visible, sort_order) VALUES (?, ?, ?, ?, 1, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM links))')
                ->execute([$page['title'], page_url($page), $img, 'link']);
            flash('Added as the last button on the home page. Change its photo or order in Links.');
            redirect('/admin/pages/' . $page['id']);
        },
        'POST /admin/page-photos/(\d+)/cover' => function ($id) {
            $ph = find_row('page_photos', (int) $id);
            db()->prepare('UPDATE pages SET cover = ? WHERE id = ?')->execute([$ph['image'], $ph['page_id']]);
            flash('Cover photo set.');
            redirect('/admin/pages/' . $ph['page_id'] . '#photos');
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
            redirect('/admin/pages/' . $ph['page_id'] . '#photos');
        },

        'GET /admin/categories/new' => fn () => admin_render('category_edit', [
            'cat' => ['id' => 0, 'name' => '', 'slug' => '', 'intro' => '', 'cover' => '', 'visible' => 1], 'pages' => [],
            'tab' => 'pages', 'title' => 'New category']),
        'GET /admin/categories/(\d+)' => function ($id) {
            $cat = find_row('categories', (int) $id);
            if ($cat['deleted_at'] !== null) {
                not_found();
            }
            $st = db()->prepare('SELECT * FROM pages WHERE category_id = ? AND deleted_at IS NULL ORDER BY sort_order, id');
            $st->execute([$cat['id']]);
            admin_render('category_edit', ['cat' => $cat, 'pages' => $st->fetchAll(), 'tab' => 'pages', 'title' => $cat['name']]);
        },
        'POST /admin/categories/save' => fn () => save_category(),
        'POST /admin/categories/(\d+)/delete' => function ($id) {
            soft_delete('categories', (int) find_row('categories', (int) $id)['id']);
            redirect('/admin/pages');
        },

        'POST /admin/undo' => function () {
            $table = (string) ($_POST['table'] ?? '');
            if (isset(SOFT_DELETE[$table])) {
                db()->prepare("UPDATE $table SET deleted_at = NULL WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
                flash('Undone. ' . SOFT_DELETE[$table] . ' is back.');
            }
            redirect(back_to('/admin/pages'));
        },
    ];
}

function save_page(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? admin_page_row($id) : null;
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') {
        flash('Give the page a title.');
        redirect($id ? "/admin/pages/$id" : '/admin/pages/new');
    }
    $catId = (int) ($_POST['category_id'] ?? 0) ?: null;
    if ($catId !== null) {
        $c = db()->prepare('SELECT 1 FROM categories WHERE id = ? AND deleted_at IS NULL');
        $c->execute([$catId]);
        $catId = $c->fetchColumn() ? $catId : null;
    }
    $catSlug = '';
    if ($catId !== null) {
        $c = db()->prepare('SELECT slug FROM categories WHERE id = ?');
        $c->execute([$catId]);
        $catSlug = (string) $c->fetchColumn();
    }
    $buttons = [];
    foreach ((array) ($_POST['btn_label'] ?? []) as $i => $label) {
        $label = trim((string) $label);
        $url = clean_url((string) ($_POST['btn_url'][$i] ?? ''));
        if ($label !== '' && $url !== '') {
            $buttons[] = ['label' => $label, 'url' => $url];
        }
    }
    $vals = [
        'title' => $title,
        'slug' => unique_public_slug((string) ($_POST['slug'] ?? '') ?: $title, 'pages', $id, $catSlug),
        'category_id' => $catId,
        'body' => trim((string) ($_POST['body'] ?? '')),
        'videos' => trim((string) ($_POST['videos'] ?? '')),
        'buttons' => json_encode($buttons),
        'visible' => empty($_POST['visible']) ? 0 : 1,
    ];
    if ($id) {
        $sets = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($vals)));
        db()->prepare("UPDATE pages SET $sets WHERE id = :id")->execute($vals + ['id' => $id]);
    } else {
        db()->prepare('INSERT INTO pages (title, slug, category_id, body, videos, buttons, visible, sort_order)
            VALUES (:title, :slug, :category_id, :body, :videos, :buttons, :visible, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM pages))')->execute($vals);
        $id = (int) db()->lastInsertId();
    }

    // New photos
    $errors = [];
    $added = [];
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
        [$url, $title] = parse_video_line($line);
        $v = video_link($url);
        if (!$v) {
            $bad[] = trim($line);
        } elseif ($v['provider'] === 'tiktok') {
            $meta[$url] = $oldMeta[$url] ?? tiktok_lookup($url);
            if (empty($meta[$url]['id']) && empty($meta[$url]['title']) && $title === '') {
                $errors[] = "Couldn't read a TikTok link right now. Type a title after it to label the card.";
            }
        }
    }
    db()->prepare('UPDATE pages SET video_meta = ? WHERE id = ?')->execute([json_encode($meta), $id]);
    if ($bad) {
        $errors[] = 'Not a link (should start with https://): "' . implode('", "', $bad) . '".';
    }
    if (isset($_POST['files_sent'])) {
        // "Files on this page" checklist
        $want = array_map('intval', (array) ($_POST['file_ids'] ?? []));
        db()->prepare('DELETE FROM page_files WHERE page_id = ?' . ($want ? ' AND file_id NOT IN (' . implode(',', $want) . ')' : ''))->execute([$id]);
        $ins = db()->prepare('INSERT OR IGNORE INTO page_files (page_id, file_id, sort_order) VALUES (?, ?, ?)');
        foreach ($want as $i => $fid) {
            $ins->execute([$id, $fid, $i]);
        }
    }
    flash(($errors ? 'Saved, but: ' . implode(' ', $errors) : 'Saved.') . ($added ? ' Added ' . count($added) . ' photo' . (count($added) > 1 ? 's' : '') . '.' : ''));
    redirect("/admin/pages/$id");
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

function save_category(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_row('categories', $id) : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    try {
        if ($name === '') {
            throw new RuntimeException('Give the category a name.');
        }
        $cover = handle_photo('photo', $old['cover'] ?? '');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($id ? "/admin/categories/$id" : '/admin/categories/new');
    }
    $slug = unique_public_slug((string) ($_POST['slug'] ?? '') ?: $name, 'categories', $id);
    $intro = trim((string) ($_POST['intro'] ?? ''));
    $visible = empty($_POST['visible']) ? 0 : 1;
    if ($id) {
        db()->prepare('UPDATE categories SET name = ?, slug = ?, intro = ?, cover = ?, visible = ? WHERE id = ?')
            ->execute([$name, $slug, $intro, $cover, $visible, $id]);
    } else {
        db()->prepare('INSERT INTO categories (name, slug, intro, cover, visible, sort_order) VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM categories))')
            ->execute([$name, $slug, $intro, $cover, $visible]);
        $id = (int) db()->lastInsertId();
    }
    flash("Saved. Its page is /$slug");
    redirect("/admin/categories/$id");
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
