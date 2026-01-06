<?php
require_once '../auth-check.php';

header('Content-Type: application/json');

$image_id = isset($_GET['image_id']) ? (int)$_GET['image_id'] : 0;

if (!$image_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid image ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT category_id FROM image_categories WHERE image_id = ?");
    $stmt->execute([$image_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
