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

function save_file_item(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? find_row('files', $id) : null;
    $back = $id ? "/admin/files/$id" : '/admin/files/new';
    $title = trim((string) ($_POST['title'] ?? ''));
    $paid = ($_POST['kind'] ?? '') === 'paid';
    $price = $paid ? (int) round(((float) str_replace(['$', ','], '', (string) ($_POST['price'] ?? '0'))) * 100) : 0;
    $buyUrl = clean_url((string) ($_POST['buy_url'] ?? ''));
    // Publish / Save draft / Unpublish buttons; plain Save (or Enter) keeps the current status.
    $status = match ($_POST['action'] ?? 'save') {
        'publish' => 'published',
        'draft' => 'draft',
        default => $old['status'] ?? 'draft',
    };
    $filePath = $old['file_path'] ?? '';
    $newFile = false;

    try {
        if (!empty($_FILES['download']['name'])) {
            $filePath = save_download_file($_FILES['download']);
            $newFile = true;
        } elseif (isset($_POST['existing_file']) && $_POST['existing_file'] !== '__keep') {
            $pick = basename((string) $_POST['existing_file']);
            $filePath = ($pick !== '' && is_file(DATA_DIR . '/files/' . $pick)) ? $pick : '';
            $newFile = $filePath !== '';
        }
        if ($title === '' && $filePath !== '') {
            $title = title_from_filename($filePath); // "spice-rack_v2.zip" -> "Spice Rack V2"
        }
        if ($title === '') {
            throw new RuntimeException('Give the file a title (or upload the file and it uses the file name).');
        }
        if ($status === 'published' && !$paid && $filePath === '' && trim((string) ($_POST['makerworld_url'] ?? '')) === '') {
            $status = 'draft';
            flash('Saved as a DRAFT: upload the download file (or add its MakerWorld link), then tap Publish.');
        }
        if ($status === 'published' && $paid && ($price <= 0 || $buyUrl === '')) {
            $status = 'draft';
            flash('Saved as a DRAFT: a paid file needs a price and a Buy link, then tap Publish.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage());
        redirect($back);
    }

    $formats = trim((string) ($_POST['formats'] ?? ''));
    if ($filePath !== '' && ($formats === '' || $newFile)) {
        $formats = implode(', ', detect_formats(DATA_DIR . '/files/' . $filePath)) ?: $formats;
    }
    $_POST['formats'] = $formats;

    $type = in_array($_POST['type'] ?? '', ['cnc', '3d'], true) ? $_POST['type'] : 'cnc';
    $license = in_array($_POST['license'] ?? '', ['personal', 'commercial'], true) ? $_POST['license'] : 'personal';
    $vals = [
        'title' => $title,
        'slug' => file_address((string) ($_POST['slug'] ?? ''), $id, $old),
        'type' => $type,
        'price_cents' => max(0, $price),
        'buy_url' => $buyUrl,
        'makerworld_url' => clean_url((string) ($_POST['makerworld_url'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'formats' => strtoupper(trim(preg_replace('/[\s,;\/]+/', ', ', (string) ($_POST['formats'] ?? '')), ', ')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'license' => $license,
        'video_url' => clean_url((string) ($_POST['video_url'] ?? '')),
        'file_path' => $filePath,
        'status' => $status,
        'featured' => empty($_POST['featured']) ? 0 : 1,
    ];
    if ($old && $old['slug'] !== $vals['slug']) {
        remember_old_address('/files/' . $old['slug'], 'file', $id);
    }
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
        $msg = match (true) {
            $status === 'published' && ($old['status'] ?? '') !== 'published' => 'Published! It\'s live in your file shop.',
            $status === 'draft' && ($old['status'] ?? '') === 'published' => 'Unpublished. It\'s a draft again.',
            default => 'Saved.',
        };
        $msg .= ($GLOBALS['addr_note'] ?? '') . (($old && $old['slug'] !== $vals['slug']) ? " New address /files/{$vals['slug']}. The old one still works." : '');
        $msg .= place_file($id, $status);
        if ($status === 'published' && !isset($_POST['placement_sent'])) {
            $msg .= show_files_button();
        }
        flash($errors ? $msg . ' But some photos failed: ' . implode(' ', $errors) : $msg);
    }
    redirect("/admin/files/$id");
}

/**
 * "Show it on" in the file editor: the pages this file appears on, and a home page button for it.
 * Returns a short note for the saved message.
 */
function place_file(int $fileId, string $status): string
{
    if (!isset($_POST['placement_sent'])) {
        return '';
    }
    $file = find_row('files', $fileId);
    $url = '/files/' . $file['slug'];

    $want = array_map('intval', (array) ($_POST['page_ids'] ?? []));
    db()->prepare('DELETE FROM page_files WHERE file_id = ?' . ($want ? ' AND page_id NOT IN (' . implode(',', $want) . ')' : ''))->execute([$fileId]);
    $ins = db()->prepare('INSERT OR IGNORE INTO page_files (page_id, file_id, sort_order) VALUES (?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM page_files WHERE page_id = ?))');
    foreach ($want as $pid) {
        $ins->execute([$pid, $fileId, $pid]);
    }

    // Home page button: one link button pointing at this file. Keep it if the address changed.
    $oldUrl = (string) ($_POST['old_url'] ?? '');
    if ($oldUrl !== '' && $oldUrl !== $url) {
        db()->prepare("UPDATE pages SET url = ? WHERE kind = 'link' AND url = ?")->execute([$url, $oldUrl]);
    }
    $st = db()->prepare("SELECT * FROM pages WHERE kind = 'link' AND url = ? AND place = 'home' AND deleted_at IS NULL");
    $st->execute([$url]);
    $link = $st->fetch();
    $note = '';
    if (!empty($_POST['on_home']) && !$link) {
        db()->prepare("INSERT INTO pages (kind, title, slug, url, place, icon, visible, sort_order)
            VALUES ('link', ?, ?, ?, 'home', 'file', ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM pages WHERE place = 'home'))")
            ->execute([$file['title'], button_address('', 0), $url, $status === 'published' ? 1 : 0]);
        $note = $status === 'published' ? ' Added a button on the home page.' : ' A home page button is ready and shows when you publish.';
    } elseif (empty($_POST['on_home']) && $link) {
        db()->prepare('DELETE FROM pages WHERE id = ?')->execute([$link['id']]);
        delete_image($link['thumb']);
        $note = ' Removed its home page button.';
    } elseif ($link) {
        // Follow the file: hidden while it's a draft, showing when it's live.
        db()->prepare('UPDATE pages SET visible = ? WHERE id = ?')->execute([$status === 'published' ? 1 : 0, $link['id']]);
    }
    if ($want) {
        $note .= ' Showing on ' . count($want) . ' page' . (count($want) > 1 ? 's' : '') . ($status === 'published' ? '.' : ' once published.');
    }
    return $note;
}

/** Every page (with its category), and whether this file is on it — for the "Show it on" list. */
function pages_for_file_pick(int $fileId): array
{
    $st = db()->prepare("SELECT p.id, p.title, c.title cat,
            (SELECT 1 FROM page_files pf WHERE pf.page_id = p.id AND pf.file_id = ?) on_page
        FROM pages p LEFT JOIN pages c ON c.id = p.parent_id AND p.place = 'folder' AND c.deleted_at IS NULL
        WHERE p.kind = 'page' AND p.deleted_at IS NULL ORDER BY c.title IS NOT NULL, c.title, p.sort_order, p.id");
    $st->execute([$fileId]);
    return $st->fetchAll();
}

/** Make sure the home page has a button to the file shop once something is live. Returns a note for the message. */
function show_files_button(): string
{
    $link = db()->query("SELECT id, visible, place FROM pages WHERE kind = 'link' AND url = '/files' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetch();
    if ($link) {
        return ''; // you've already got one (even hidden on purpose)
    }
    db()->prepare("INSERT INTO pages (kind, title, slug, url, place, icon, visible, sort_order)
        VALUES ('link', 'Stuff I Design and Sell', ?, '/files', 'home', 'file', 1, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM pages WHERE place = 'home'))")
        ->execute([button_address('', 0)]);
    return ' Your file shop button is now showing on the home page.';
}

function title_from_filename(string $name): string
{
    $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $name);
    return ucwords(trim(preg_replace('/[\s_\-.]+/', ' ', $base)));
}

/** File formats inside a download: its own extension, or the extensions inside a zip. */
function detect_formats(string $path): array
{
    $known = ['dxf', 'svg', 'crv', 'crv3d', 'stl', '3mf', 'step', 'stp', 'obj', 'dwg', 'pdf', 'ai', 'eps', 'f3d', 'skp', 'c2d', 'lbrn', 'lbrn2', 'gcode', 'nc', 'tap', 'ngc'];
    $ext = fn (string $n) => strtolower(pathinfo($n, PATHINFO_EXTENSION));
    $found = [];
    if ($ext($path) === 'zip' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if (!str_starts_with(basename($n), '.') && !str_contains($n, '__MACOSX')) {
                    $found[] = $ext($n);
                }
            }
            $zip->close();
        }
    } else {
        $found[] = $ext($path);
    }
    $out = [];
    foreach (array_unique($found) as $e) {
        if (in_array($e, $known, true)) {
            $out[] = in_array($e, ['gcode', 'nc', 'tap', 'ngc'], true) ? 'G-code' : strtoupper($e);
        }
    }
    return array_values(array_unique($out));
}

function flash_pending(): bool
{
    return isset($_SESSION['flash']);
}


/** A file's address after /files/: the word you typed, or a short code. Won't steal an old address without asking. */
function file_address(string $want, int $id, ?array $old): string
{
    $taken = function (string $slug) use ($id): bool {
        $st = db()->prepare('SELECT 1 FROM files WHERE slug = ? AND id != ?');
        $st->execute([$slug, $id]);
        return (bool) $st->fetchColumn();
    };
    if (trim(preg_replace('/[^a-z0-9]+/i', '', $want)) === '') {
        return $old['slug'] ?? new_code($taken);
    }
    $slug = slugify($want);
    if ($slug !== ($old['slug'] ?? '') && old_address_owner('/files/' . $slug, 'file', $id) !== null && empty($_POST['reuse_ok'])) {
        $GLOBALS['addr_note'] = " Didn't take /files/$slug: it used to be another file, and old links still go there.";
        return $old['slug'] ?? new_code($taken);
    }
    $base = $slug;
    for ($i = 2; $taken($slug); $i++) {
        $slug = "$base-$i";
    }
    db()->prepare('DELETE FROM redirects WHERE path = ?')->execute(['/files/' . $slug]);
    return $slug;
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

/** Paste MakerWorld model links (one per line): each becomes a draft file with its MakerWorld button. */
const IMPORT_VERSION = 6; // shown on the import screen so we can tell which code the server is running

function import_makerworld(): void
{
    @set_time_limit(300);
    $made = $skipped = $bad = $updated = 0;
    $noPhoto = [];
    $type = ($_POST['type'] ?? '3d') === 'cnc' ? 'cnc' : '3d';
    // What the Grab MakerWorld bookmark copies: MAKERWORLD:[{url, title, img, description}, …]
    // Older copies / plain links: one model per MakerWorld link (line breaks often get lost when pasting on a phone).
    $text = trim((string) ($_POST['links'] ?? ''));
    $items = [];
    $list = str_contains($text, 'MAKERWORLD:') ? makerworld_json($text) : null;
    if ($list === null && str_contains($text, 'MAKERWORLD:')) {
        flash("Couldn't read the list the bookmark copied (it got changed on the way). Nothing was imported. Send a screenshot of the import box to Claude.");
        redirect('/admin/files/import');
    }
    if ($list !== null) {
        foreach ($list as $it) {
            if (is_array($it)) {
                $items[] = ['line' => (string) ($it['url'] ?? ''), 'title' => trim((string) ($it['title'] ?? '')), 'image' => (string) ($it['img'] ?? ''),
                    'description' => trim((string) ($it['description'] ?? ''))];
            }
        }
    } else {
        foreach (preg_split('#(?=https?://(?:www\.)?makerworld\.com/)#i', $text) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $items[] = ['line' => $line, 'title' => html_entity_decode($parts[1] ?? '', ENT_QUOTES | ENT_HTML5), 'image' => $parts[2] ?? '', 'description' => ''];
        }
    }
    foreach ($items as $it) {
        if (trim($it['line']) === '') {
            continue;
        }
        $m = makerworld_model($it['line']);
        if (!$m) {
            $bad++;
            continue;
        }
        $info = $it['title'] !== '' ? $it : makerworld_lookup($m['url']) + ['image' => $it['image'], 'description' => ''];
        $title = ($info['title'] ?? '') ?: ($m['guess'] ?: 'MakerWorld model ' . $m['id']);
        $desc = (string) ($info['description'] ?? '');

        $dup = db()->prepare("SELECT * FROM files WHERE makerworld_url LIKE ?");
        $dup->execute(['%/models/' . $m['id'] . '%']);
        if ($old = $dup->fetch()) {
            // Already here: fill in what an earlier import couldn't (real title, description, photo)
            $fixed = false;
            if ($old['title'] !== $title && ($it['title'] !== '' || str_starts_with($old['title'], 'MakerWorld model '))) {
                db()->prepare('UPDATE files SET title = ? WHERE id = ?')->execute([$title, $old['id']]);
                $fixed = true;
            }
            if ($old['description'] === '' && $desc !== '') {
                db()->prepare('UPDATE files SET description = ? WHERE id = ?')->execute([$desc, $old['id']]);
                $fixed = true;
            }
            $has = db()->prepare('SELECT 1 FROM file_images WHERE file_id = ?');
            $has->execute([$old['id']]);
            if (!$has->fetchColumn() && ($img = makerworld_photo((string) ($info['image'] ?? ''))) !== '') {
                db()->prepare('INSERT INTO file_images (file_id, image, sort_order) VALUES (?, ?, 0)')->execute([$old['id'], $img]);
                $fixed = true;
            }
            $fixed ? $updated++ : $skipped++;
            continue;
        }
        db()->prepare("INSERT INTO files (title, slug, type, price_cents, description, license, makerworld_url, status, sort_order)
            VALUES (?, ?, ?, 0, ?, 'personal', ?, 'draft', (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM files))")
            ->execute([$title, file_address('', 0, null), $type, $desc, $m['url']]);
        $id = (int) db()->lastInsertId();
        if (($img = makerworld_photo((string) ($info['image'] ?? ''))) !== '') {
            db()->prepare('INSERT INTO file_images (file_id, image, sort_order) VALUES (?, ?, 0)')->execute([$id, $img]);
        } else {
            $noPhoto[] = $title;
        }
        $made++;
    }
    $msg = $made ? "Added $made file" . ($made > 1 ? 's' : '') . ' as drafts. Check each one, then Publish.' : 'Nothing new added.';
    if ($updated) {
        $msg .= " Filled in the title, description or photo on $updated you already had.";
    }
    if ($skipped) {
        $msg .= " Skipped $skipped already in your shop.";
    }
    if ($bad) {
        $msg .= " $bad line" . ($bad > 1 ? "s weren't" : " wasn't") . ' a MakerWorld model link.';
    }
    if ($noPhoto) {
        $msg .= ' No photo found for: ' . implode(', ', array_slice($noPhoto, 0, 5)) . (count($noPhoto) > 5 ? '…' : '') . '. Add a photo to those.';
    }
    flash($msg);
    redirect('/admin/files');
}

/** Keep a copy of a MakerWorld cover photo on your server; if that fails, show it straight from MakerWorld. */
function makerworld_photo(string $url): string
{
    if (!preg_match('#^https://[a-z0-9.-]+\.(bblmw\.com|makerworld\.com)/#i', $url)) {
        return '';
    }
    $bytes = http_get($url, 10, 'https://makerworld.com/');
    $saved = '';
    if ($bytes && strlen($bytes) < 8_000_000) {
        try {
            $saved = save_image_bytes($bytes);
        } catch (Throwable) {
            $saved = '';
        }
    }
    return $saved !== '' ? $saved : $url;
}

/**
 * Read what the Grab MakerWorld bookmark copied. Phones sometimes change it on the way
 * (curly quotes, escaped slashes, odd spaces), so try a few repairs before giving up.
 */
function makerworld_json(string $text): ?array
{
    $raw = trim(substr($text, strpos($text, 'MAKERWORLD:') + 11));
    $start = strpos($raw, '[');
    $end = strrpos($raw, ']');
    if ($start === false || $end === false) {
        return null;
    }
    $raw = substr($raw, $start, $end - $start + 1);
    $tries = [
        $raw,
        str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{2033}"], '"', $raw),
        stripslashes($raw),
        str_replace(["\u{00A0}", "\u{2028}", "\u{2029}"], [' ', '\n', '\n'], str_replace(["\u{201C}", "\u{201D}"], '"', $raw)),
        html_entity_decode($raw, ENT_QUOTES | ENT_HTML5),
    ];
    foreach ($tries as $t) {
        $list = json_decode($t, true, 16, JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_array($list)) {
            return $list;
        }
    }
    // Last resort: pull each model's pieces out by name
    $q = '["\x{201C}\x{201D}]';
    $field = fn (string $name) => $q . $name . $q . '\s*:\s*' . $q . '((?:[^"\\\\\x{201C}\x{201D}]|\\\\.)*)' . $q;
    preg_match_all('/' . $field('url') . '\s*,\s*' . $field('title') . '\s*,\s*' . $field('img') . '(?:\s*,\s*' . $field('description') . ')?/u', $raw, $m, PREG_SET_ORDER);
    $unq = fn (string $s) => (string) (json_decode('"' . $s . '"') ?? stripcslashes($s));
    $out = [];
    foreach ($m as $one) {
        $out[] = ['url' => $unq($one[1]), 'title' => $unq($one[2]), 'img' => $unq($one[3]), 'description' => $unq($one[4] ?? '')];
    }
    return $out ?: null;
}
