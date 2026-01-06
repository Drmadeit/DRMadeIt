<?php
require_once 'auth-check.php';

$page_title = 'Jobs';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $job_id = (int)$_GET['id'];

    try {
        // Get all images for this job
        $stmt = $pdo->prepare("SELECT filename, thumbnail FROM images WHERE job_id = ?");
        $stmt->execute([$job_id]);
        $images = $stmt->fetchAll();

        // Delete image files
        foreach ($images as $img) {
            if (file_exists(__DIR__ . '/../uploads/' . $img['filename'])) {
                unlink(__DIR__ . '/../uploads/' . $img['filename']);
            }
            if (file_exists(__DIR__ . '/../uploads/' . $img['thumbnail'])) {
                unlink(__DIR__ . '/../uploads/' . $img['thumbnail']);
            }
        }

        // Delete job (images will be deleted via CASCADE)
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);

        header('Location: jobs.php?deleted=1');
        exit;

    } catch (Exception $e) {
        log_error($e->getMessage(), __FILE__, __LINE__);
        $error = "Failed to delete job.";
    }
}

// Get all jobs with image counts
$stmt = $pdo->query("
    SELECT j.*, COUNT(i.id) as image_count,
           (SELECT i2.thumbnail FROM images i2 WHERE i2.job_id = j.id ORDER BY i2.sort_order LIMIT 1) as first_image
    FROM jobs j
    LEFT JOIN images i ON j.id = i.job_id
    GROUP BY j.id
    ORDER BY j.created_at DESC
");
$jobs = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Jobs</h1>
        <div class="header-actions">
            <a href="job-editor.php" class="btn btn-primary">+ Add New Job</a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Job and all associated images deleted successfully.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <div class="card-grid">
        <!-- Add New Card (Always First) -->
        <a href="job-editor.php" class="grid-card add-card">
            <div class="add-icon">+</div>
            <div class="add-label">Add New Job</div>
        </a>

        <!-- Existing Jobs -->
        <?php foreach ($jobs as $job): ?>
            <div class="grid-card" data-id="<?= $job['id'] ?>">
                <div class="card-image">
                    <?php if ($job['first_image']): ?>
                        <img src="../uploads/<?= esc_html($job['first_image']) ?>" alt="<?= esc_html($job['name']) ?>">
                    <?php else: ?>
                        <div class="placeholder-image">💼</div>
                    <?php endif; ?>
                </div>

                <div class="card-content">
                    <h3 class="card-title"><?= esc_html($job['name']) ?></h3>
                    <div class="card-meta">
                        <span class="card-count"><?= $job['image_count'] ?> photo<?= $job['image_count'] != 1 ? 's' : '' ?></span>
                    </div>
                </div>

                <div class="card-overlay">
                    <a href="job-editor.php?id=<?= $job['id'] ?>" class="overlay-btn">Edit</a>
                    <button class="delete-btn" onclick="deleteJob(<?= $job['id'] ?>, '<?= esc_html($job['name']) ?>', <?= $job['image_count'] ?>)">×</button>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($jobs)): ?>
            <div class="empty-state-full">
                <div class="empty-icon">💼</div>
                <h2>No Jobs Yet</h2>
                <p>Create your first portfolio project!</p>
            </div>
        <?php endif; ?>
    </div>
</main>

</div>

<script>
function deleteJob(id, name, imageCount) {
    let message = `Are you sure you want to delete "${name}"?`;
    if (imageCount > 0) {
        message += `\n\nThis will also delete ${imageCount} image${imageCount != 1 ? 's' : ''}.`;
    }
    message += '\n\nThis action cannot be undone.';

    if (confirm(message)) {
        window.location.href = `jobs.php?action=delete&id=${id}`;
    }
}
</script>

</body>
</html>
