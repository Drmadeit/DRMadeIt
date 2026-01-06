<?php
require_once '../auth-check.php';

$image_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$image_id) {
    die('Invalid image ID');
}

try {
    $stmt = $pdo->prepare("SELECT filename FROM images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();

    if (!$image) {
        die('Image not found');
    }

    $file_path = __DIR__ . '/../../uploads/' . $image['filename'];

    if (!file_exists($file_path)) {
        die('Image file not found');
    }

    // Determine MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    die('Error loading image');
}
