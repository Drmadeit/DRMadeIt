<?php
require_once '../auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$image_id = isset($input['image_id']) ? (int)$input['image_id'] : 0;

if (!$image_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid image ID']);
    exit;
}

try {
    // Get image filenames
    $stmt = $pdo->prepare("SELECT filename, thumbnail FROM images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();

    if (!$image) {
        echo json_encode(['success' => false, 'error' => 'Image not found']);
        exit;
    }

    // Delete files
    $upload_dir = __DIR__ . '/../../uploads/';

    if (file_exists($upload_dir . $image['filename'])) {
        unlink($upload_dir . $image['filename']);
    }

    if (file_exists($upload_dir . $image['thumbnail'])) {
        unlink($upload_dir . $image['thumbnail']);
    }

    // Delete from database (categories will be deleted via CASCADE)
    $stmt = $pdo->prepare("DELETE FROM images WHERE id = ?");
    $stmt->execute([$image_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
