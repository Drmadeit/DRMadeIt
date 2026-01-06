<?php
/**
 * Universal Image Upload Handler
 * Handles resizing, thumbnails, and watermarks
 */

require_once 'auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$upload_type = $_POST['upload_type'] ?? ''; // 'hero', 'job', 'media'
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : null;

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$upload_dir = __DIR__ . '/../uploads/';

// Validate file
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images allowed.']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'full_' . uniqid() . '.' . $extension;
$thumb_filename = 'thumb_' . uniqid() . '.' . $extension;

try {
    // Load image
    switch ($mime_type) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($file['tmp_name']);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($file['tmp_name']);
            break;
    }

    if (!$image) {
        throw new Exception('Failed to load image');
    }

    $orig_width = imagesx($image);
    $orig_height = imagesy($image);

    // --- FULL SIZE IMAGE (max 2000px on longest side) ---
    $max_size = 2000;
    if ($orig_width > $max_size || $orig_height > $max_size) {
        if ($orig_width > $orig_height) {
            $new_width = $max_size;
            $new_height = (int)(($orig_height / $orig_width) * $max_size);
        } else {
            $new_height = $max_size;
            $new_width = (int)(($orig_width / $orig_height) * $max_size);
        }
    } else {
        $new_width = $orig_width;
        $new_height = $orig_height;
    }

    $resized = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency for PNG/GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
    }

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    // Apply watermark if enabled (and not hero images)
    if ($upload_type !== 'hero' && get_setting('enable_watermark', 0)) {
        $company_name = get_setting('company_name', '');
        if ($company_name) {
            $watermark_color = imagecolorallocatealpha($resized, 255, 255, 255, 50);
            $font_size = max(12, $new_width / 50);
            imagettftext($resized, $font_size, 0, 10, $new_height - 10, $watermark_color, __DIR__ . '/../assets/fonts/default.ttf', $company_name);
        }
    }

    // Save full size
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($resized, $upload_dir . $filename, 85);
            break;
        case 'png':
            imagepng($resized, $upload_dir . $filename, 8);
            break;
        case 'webp':
            imagewebp($resized, $upload_dir . $filename, 85);
            break;
        case 'gif':
            imagegif($resized, $upload_dir . $filename);
            break;
    }

    imagedestroy($resized);

    // --- THUMBNAIL (400px) ---
    $thumb_size = 400;
    $thumb_width = $thumb_size;
    $thumb_height = $thumb_size;

    // Calculate dimensions to maintain aspect ratio with black bars for portrait
    if ($orig_width > $orig_height) {
        // Landscape or square
        $scale = $thumb_size / $orig_width;
        $scaled_width = $thumb_size;
        $scaled_height = (int)($orig_height * $scale);
        $y_offset = (int)(($thumb_size - $scaled_height) / 2);
        $x_offset = 0;
    } else {
        // Portrait
        $scale = $thumb_size / $orig_height;
        $scaled_height = $thumb_size;
        $scaled_width = (int)($orig_width * $scale);
        $x_offset = (int)(($thumb_size - $scaled_width) / 2);
        $y_offset = 0;
    }

    $thumbnail = imagecreatetruecolor($thumb_size, $thumb_size);

    // Fill with black background
    $black = imagecolorallocate($thumbnail, 0, 0, 0);
    imagefill($thumbnail, 0, 0, $black);

    // Copy resized image onto center
    imagecopyresampled($thumbnail, $image, $x_offset, $y_offset, 0, 0, $scaled_width, $scaled_height, $orig_width, $orig_height);

    // Save thumbnail
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumbnail, $upload_dir . $thumb_filename, 85);
            break;
        case 'png':
            imagepng($thumbnail, $upload_dir . $thumb_filename, 8);
            break;
        case 'webp':
            imagewebp($thumbnail, $upload_dir . $thumb_filename, 85);
            break;
        case 'gif':
            imagegif($thumbnail, $upload_dir . $thumb_filename);
            break;
    }

    imagedestroy($thumbnail);
    imagedestroy($image);

    // Handle different upload types
    if ($upload_type === 'hero') {
        // For hero images, we don't save to database
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'thumbnail' => $thumb_filename
        ]);

    } elseif ($upload_type === 'job' && $job_id) {
        // Insert into images table linked to job
        $stmt = $pdo->prepare("INSERT INTO images (job_id, filename, thumbnail, uploaded_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$job_id, $filename, $thumb_filename]);

        echo json_encode([
            'success' => true,
            'image_id' => $pdo->lastInsertId(),
            'filename' => $filename,
            'thumbnail' => $thumb_filename
        ]);

    } elseif ($upload_type === 'media') {
        // Insert into images table without job (orphan image)
        $stmt = $pdo->prepare("INSERT INTO images (job_id, filename, thumbnail, uploaded_at) VALUES (NULL, ?, ?, NOW())");
        $stmt->execute([$filename, $thumb_filename]);

        echo json_encode([
            'success' => true,
            'image_id' => $pdo->lastInsertId(),
            'filename' => $filename,
            'thumbnail' => $thumb_filename
        ]);

    } else {
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'thumbnail' => $thumb_filename
        ]);
    }

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'error' => 'Failed to process image']);
}
