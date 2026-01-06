<?php
require_once '../auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$image_id = isset($input['image_id']) ? (int)$input['image_id'] : 0;
$category_ids = $input['category_ids'] ?? [];

if (!$image_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid image ID']);
    exit;
}

try {
    // Delete existing category assignments
    $stmt = $pdo->prepare("DELETE FROM image_categories WHERE image_id = ?");
    $stmt->execute([$image_id]);

    // Insert new assignments
    $stmt = $pdo->prepare("INSERT INTO image_categories (image_id, category_id) VALUES (?, ?)");
    foreach ($category_ids as $cat_id) {
        $stmt->execute([$image_id, (int)$cat_id]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
