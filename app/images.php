<?php
declare(strict_types=1);

const IMG_MAX = 1600;  // full size, longest side
const IMG_SMALL = 480; // thumbnails

/**
 * Save an uploaded photo: fix phone rotation, resize, store as WebP (full + "-sm" thumb).
 * Returns the stored file name, or throws with a message safe to show the admin.
 */
function save_uploaded_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($file['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photo is too big for the server upload limit.',
            default => 'The photo did not upload. Try again.',
        });
    }
    return process_image_file($file['tmp_name']);
}

/** Save image bytes downloaded from the web (e.g. a video thumbnail). Returns '' if they aren't an image. */
function save_image_bytes(string $bytes): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmp, $bytes);
    try {
        return process_image_file($tmp);
    } catch (RuntimeException) {
        return '';
    } finally {
        @unlink($tmp);
    }
}

function process_image_file(string $path): string
{
    $info = @getimagesize($path);
    $type = $info[2] ?? 0;
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        IMAGETYPE_GIF => @imagecreatefromgif($path),
        default => false,
    };
    if (!$src) {
        throw new RuntimeException('Use a JPG, PNG, WebP or GIF photo. (On iPhone, HEIC photos are converted automatically when you pick from Photos.)');
    }

    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($src, $angle, 0);
            imagedestroy($src);
            $src = $rotated;
        }
    }

    $name = bin2hex(random_bytes(8));
    write_webp($src, UPLOAD_DIR . "/$name.webp", IMG_MAX, 82);
    write_webp($src, UPLOAD_DIR . "/$name-sm.webp", IMG_SMALL, 78);
    imagedestroy($src);
    return "$name.webp";
}

function write_webp(GdImage $src, string $path, int $max, int $quality): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagewebp($dst, $path, $quality);
    imagedestroy($dst);
}

/** Public URL for a stored image. $small picks the thumbnail when one exists. */
function img_url(string $name, bool $small = false): string
{
    if ($name === '') {
        return '';
    }
    if ($small) {
        $sm = preg_replace('/\.webp$/', '-sm.webp', $name);
        if ($sm !== $name && is_file(UPLOAD_DIR . '/' . $sm)) {
            return '/media/' . $sm;
        }
    }
    return '/media/' . $name;
}

function delete_image(string $name): void
{
    if ($name === '' || !preg_match('/^[a-z0-9-]+\.(webp|jpg|png)$/', $name)) {
        return;
    }
    @unlink(UPLOAD_DIR . '/' . $name);
    @unlink(UPLOAD_DIR . '/' . preg_replace('/\.webp$/', '-sm.webp', $name));
}

/** Stream an image from the data folder (outside public_html). */
function serve_media(string $name): never
{
    if (!preg_match('/^[a-z0-9-]+\.(webp|jpg|png)$/', $name, $m) || !is_file($path = UPLOAD_DIR . '/' . $name)) {
        not_found();
    }
    header('Content-Type: ' . ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$m[1]]);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=31536000, immutable'); // names are random, never reused
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
