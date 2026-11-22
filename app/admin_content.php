<?php
declare(strict_types=1);

/** Upload a new photo into $current, or remove it. Returns the image name to store. */
function handle_photo(string $field, string $current, string $removeField = 'remove_photo'): string
{
    if (!empty($_FILES[$field]['name'])) {
        $new = save_uploaded_image($_FILES[$field]);
        delete_image($current);
        return $new;
    }
    if (!empty($_POST[$removeField])) {
        delete_image($current);
        return '';
    }
    return $current;
}

function save_link(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_row('links', $id) : null;
    $title = trim((string) ($_POST['title'] ?? ''));
    $url = clean_url((string) ($_POST['url'] ?? ''));
    try {
        if ($title === '' || $url === '') {
            throw new RuntimeException('Add a title and a link (https://… or a page like /about).');
        }
        $image = handle_photo('photo', $old['image'] ?? '');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($id ? "/admin/links/$id" : '/admin/links/new');
    }
    $visible = empty($_POST['visible']) ? 0 : 1;
    $featured = empty($_POST['featured']) ? 0 : 1;
    if ($featured) {
        db()->exec('UPDATE links SET featured = 0');
    }
    if ($id) {
        db()->prepare('UPDATE links SET title = ?, url = ?, image = ?, featured = ?, visible = ? WHERE id = ?')
            ->execute([$title, $url, $image, $featured, $visible, $id]);
    } else {
        db()->prepare('INSERT INTO links (title, url, image, featured, visible, sort_order) VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM links))')
            ->execute([$title, $url, $image, $featured, $visible]);
    }
    flash('Saved.');
    redirect('/admin/links');
}

function save_tool_list(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_row('tool_lists', $id) : null;
    $title = trim((string) ($_POST['title'] ?? ''));
    try {
        if ($title === '') {
            throw new RuntimeException('Give the list a name.');
        }
        $cover = handle_photo('photo', $old['cover_image'] ?? '');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($id ? "/admin/tools/$id" : '/admin/tools/new');
    }
    $slug = unique_slug('tool_lists', (string) ($_POST['slug'] ?? '') ?: $title, $id);
    $desc = trim((string) ($_POST['description'] ?? ''));
    $visible = empty($_POST['visible']) ? 0 : 1;
    if ($id) {
        db()->prepare('UPDATE tool_lists SET title = ?, slug = ?, description = ?, cover_image = ?, visible = ? WHERE id = ?')
            ->execute([$title, $slug, $desc, $cover, $visible, $id]);
    } else {
        db()->prepare('INSERT INTO tool_lists (title, slug, description, cover_image, visible, sort_order) VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM tool_lists))')
            ->execute([$title, $slug, $desc, $cover, $visible]);
        $id = (int) db()->lastInsertId();
    }
    flash('List saved. Its page is /tools/' . $slug);
    redirect("/admin/tools/$id");
}

function save_tool_item(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_row('tool_items', $id) : null;
    $listId = (int) ($old['list_id'] ?? $_POST['list_id'] ?? 0);
    find_row('tool_lists', $listId);
    $name = trim((string) ($_POST['name'] ?? ''));
    $url = clean_url((string) ($_POST['amazon_url'] ?? ''));
    try {
        if ($name === '') {
            throw new RuntimeException('Give the tool a name.');
        }
        $image = handle_photo('photo', $old['image'] ?? '');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($id ? "/admin/items/$id" : "/admin/tools/$listId/items/new");
    }
    $note = trim((string) ($_POST['note'] ?? ''));
    $visible = empty($_POST['visible']) ? 0 : 1;
    if ($id) {
        db()->prepare('UPDATE tool_items SET name = ?, amazon_url = ?, note = ?, image = ?, visible = ? WHERE id = ?')
            ->execute([$name, $url, $note, $image, $visible, $id]);
    } else {
        db()->prepare('INSERT INTO tool_items (list_id, name, amazon_url, note, image, visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM tool_items WHERE list_id = ?))')
            ->execute([$listId, $name, $url, $note, $image, $visible, $listId]);
    }
    flash('Saved.');
    if (!empty($_POST['add_another'])) {
        redirect("/admin/tools/$listId/items/new");
    }
    redirect("/admin/tools/$listId");
}

function save_file_item(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_row('files', $id) : null;
    $back = $id ? "/admin/files/$id" : '/admin/files/new';
    $title = trim((string) ($_POST['title'] ?? ''));
    $price = (int) round(((float) str_replace(['$', ','], '', (string) ($_POST['price'] ?? '0'))) * 100);
    $buyUrl = clean_url((string) ($_POST['buy_url'] ?? ''));
    $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
    $filePath = $old['file_path'] ?? '';

    try {
        if ($title === '') {
            throw new RuntimeException('Give the file a title.');
        }
        if (!empty($_FILES['download']['name'])) {
            $filePath = save_download_file($_FILES['download']);
        } elseif (isset($_POST['existing_file']) && $_POST['existing_file'] !== '__keep') {
            $pick = basename((string) $_POST['existing_file']);
            $filePath = ($pick !== '' && is_file(DATA_DIR . '/files/' . $pick)) ? $pick : '';
        }
        if ($status === 'published' && $price === 0 && $filePath === '') {
            $status = 'draft';
            flash('Saved as DRAFT: a free file needs a download file before it can go live.');
        }
        if ($status === 'published' && $price > 0 && $buyUrl === '') {
            $status = 'draft';
            flash('Saved as DRAFT: a paid file needs a Buy link before it can go live.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($back);
    }

    $type = in_array($_POST['type'] ?? '', ['cnc', '3d'], true) ? $_POST['type'] : 'cnc';
    $license = in_array($_POST['license'] ?? '', ['personal', 'commercial'], true) ? $_POST['license'] : 'personal';
    $vals = [
        'title' => $title,
        'slug' => unique_slug('files', (string) ($_POST['slug'] ?? '') ?: $title, $id),
        'type' => $type,
        'price_cents' => max(0, $price),
        'buy_url' => $buyUrl,
        'description' => trim((string) ($_POST['description'] ?? '')),
        'formats' => strtoupper(trim(preg_replace('/[\s,;\/]+/', ', ', (string) ($_POST['formats'] ?? '')), ', ')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'license' => $license,
        'video_url' => clean_url((string) ($_POST['video_url'] ?? '')),
        'file_path' => $filePath,
        'status' => $status,
        'featured' => empty($_POST['featured']) ? 0 : 1,
    ];
    if ($id) {
        $sets = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($vals)));
        db()->prepare("UPDATE files SET $sets WHERE id = :id")->execute($vals + ['id' => $id]);
    } else {
        $cols = implode(', ', array_keys($vals));
        $ph = ':' . implode(', :', array_keys($vals));
        db()->prepare("INSERT INTO files ($cols, sort_order) VALUES ($ph, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM files))")->execute($vals);
        $id = (int) db()->lastInsertId();
    }

    // New photos (several at once)
    $errors = [];
    $photos = $_FILES['photos'] ?? null;
    if ($photos && is_array($photos['name'])) {
        $st = db()->prepare('INSERT INTO file_images (file_id, image, sort_order) VALUES (?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM file_images WHERE file_id = ?))');
        foreach ($photos['name'] as $i => $n) {
            if ($n === '') {
                continue;
            }
            try {
                $one = ['name' => $n, 'tmp_name' => $photos['tmp_name'][$i], 'error' => $photos['error'][$i], 'size' => $photos['size'][$i]];
                $st->execute([$id, save_uploaded_image($one), $id]);
            } catch (RuntimeException $ex) {
                $errors[] = "$n: " . $ex->getMessage();
            }
        }
    }
    if (!flash_pending()) {
        flash($errors ? 'Saved, but some photos failed. ' . implode(' ', $errors) : 'Saved.');
    }
    redirect("/admin/files/$id");
}

function flash_pending(): bool
{
    return isset($_SESSION['flash']);
}


function unique_slug(string $table, string $want, int $exceptId): string
{
    $base = slugify($want);
    $slug = $base;
    $st = db()->prepare("SELECT 1 FROM $table WHERE slug = ? AND id != ?");
    for ($i = 2; ; $i++) {
        $st->execute([$slug, $exceptId]);
        if (!$st->fetchColumn()) {
            return $slug;
        }
        $slug = "$base-$i";
    }
}

/** Store a download file in drmadeit-data/files, keeping a readable name. */
function save_download_file(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'That file is bigger than the upload limit (' . upload_limit() . '). Upload it with Hostinger File Manager into drmadeit-data/files, then pick it from the list.'
            : 'The file did not upload. Try again.');
    }
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string) $file['name']));
    $name = ltrim($name, '.-') ?: 'download.zip';
    if (preg_match('/\.(php\d?|phtml|phar|htaccess)$/i', $name)) {
        throw new RuntimeException('That file type is not allowed.');
    }
    $dest = DATA_DIR . '/files/' . $name;
    $info = pathinfo($name);
    for ($i = 2; is_file($dest); $i++) {
        $dest = DATA_DIR . '/files/' . $info['filename'] . "-$i" . (isset($info['extension']) ? '.' . $info['extension'] : '');
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the file on the server.');
    }
    return basename($dest);
}

/** Files sitting in drmadeit-data/files (e.g. big zips added with File Manager). */
function data_files(): array
{
    $out = [];
    foreach (glob(DATA_DIR . '/files/*') ?: [] as $f) {
        if (is_file($f)) {
            $out[basename($f)] = filesize($f);
        }
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function upload_limit(): string
{
    $toBytes = function (string $v): int {
        $n = (int) $v;
        return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    };
    $min = min($toBytes((string) ini_get('upload_max_filesize')), $toBytes((string) ini_get('post_max_size')));
    return human_size($min);
}

function human_size(int $bytes): string
{
    foreach (['GB' => 1 << 30, 'MB' => 1 << 20, 'KB' => 1 << 10] as $u => $n) {
        if ($bytes >= $n) {
            return round($bytes / $n, 1) . " $u";
        }
    }
    return "$bytes B";
}

/** Save a cropped photo sent by the cropper (fetch upload). Answers JSON errors itself. */
function crop_upload(): string
{
    header('Content-Type: application/json');
    try {
        return save_uploaded_image($_FILES['photo'] ?? []);
    } catch (RuntimeException $ex) {
        http_response_code(400);
        echo json_encode(['error' => $ex->getMessage()]);
        exit;
    }
}

/**
 * Photo picker block used on edit screens. Picking a photo opens the cropper ($aspect: original, 1, 4/3, 16/9, 3/4);
 * a saved photo gets Crop (re-crop it) and Remove buttons.
 */
function photo_field(string $name, string $current, string $label = 'Photo', string $hint = '', string $removeName = 'remove_photo', string $aspect = 'original'): string
{
    $h = '<div class="a-photo"><span class="a-label">' . e($label) . '</span>';
    if ($current !== '') {
        $h .= '<img src="' . e(img_url($current, true)) . '" alt="Current photo" width="96" height="96">';
    }
    if ($hint !== '') {
        $h .= '<small class="a-hint">' . e($hint) . '</small>';
    }
    $h .= '<label class="a-file"><span class="a-btn a-btn-outline">' . ($current !== '' ? 'Change photo' : 'Add photo') . '</span>'
        . '<input type="file" name="' . e($name) . '" accept="image/*" data-preview data-crop="' . e($aspect) . '"></label>';
    if ($current !== '') {
        $h .= '<button type="button" class="a-btn a-btn-outline" data-crop-existing="' . e($name) . '" data-src="' . e(img_url($current)) . '" data-aspect="' . e($aspect) . '">Crop</button>';
        // A plain button: one tap removes the photo and saves, and pressing Enter in a text box never triggers it.
        $h .= '<button type="button" class="a-btn a-btn-outline a-remove" data-remove-photo="' . e($removeName) . '" data-confirm-text="Remove this photo?">Remove photo</button>';
    }
    return $h . '</div>';
}
