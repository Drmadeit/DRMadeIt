<?php
require_once '../auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Category name is required']);
    exit;
}

try {
    $slug = generate_slug($name);

    // Check if already exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo json_encode(['success' => true, 'category_id' => $existing['id'], 'exists' => true]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $slug]);

        echo json_encode(['success' => true, 'category_id' => $pdo->lastInsertId(), 'exists' => false]);
    }

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
