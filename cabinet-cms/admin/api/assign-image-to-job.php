<?php
require_once '../auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$image_id = isset($input['image_id']) ? (int)$input['image_id'] : 0;
$job_id = isset($input['job_id']) ? (int)$input['job_id'] : 0;

if (!$image_id || !$job_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Verify job exists
    $stmt = $pdo->prepare("SELECT id FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }

    // Update image to link to job
    $stmt = $pdo->prepare("UPDATE images SET job_id = ? WHERE id = ?");
    $stmt->execute([$job_id, $image_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    log_error($e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
