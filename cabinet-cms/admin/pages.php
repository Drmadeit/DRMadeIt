<?php
require_once 'auth-check.php';

$page_title = 'Pages';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $page_id = (int)$_GET['id'];

    try {
        // Check if it's the homepage
        $stmt = $pdo->prepare("SELECT is_homepage, hero_image FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();

        if ($page) {
            // Delete hero image file if exists
            if ($page['hero_image'] && file_exists(__DIR__ . '/../uploads/' . $page['hero_image'])) {
                unlink(__DIR__ . '/../uploads/' . $page['hero_image']);
            }

            // Delete page (categories will be deleted via CASCADE)
            $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$page_id]);

            header('Location: pages.php?deleted=1');
            exit;
        }
    } catch (Exception $e) {
        log_error($e->getMessage(), __FILE__, __LINE__);
        $error = "Failed to delete page.";
    }
}

// Get all pages
$stmt = $pdo->query("SELECT * FROM pages ORDER BY is_homepage DESC, created_at DESC");
$pages = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Pages</h1>
        <div class="header-actions">
            <a href="page-editor.php" class="btn btn-primary">+ Add New Page</a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Page deleted successfully.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <div class="card-grid">
        <!-- Add New Card (Always First) -->
        <a href="page-editor.php" class="grid-card add-card">
            <div class="add-icon">+</div>
            <div class="add-label">Add New Page</div>
        </a>

        <!-- Existing Pages -->
        <?php foreach ($pages as $page): ?>
            <div class="grid-card" data-id="<?= $page['id'] ?>">
                <div class="card-image">
                    <?php if ($page['hero_image']): ?>
                        <img src="../uploads/<?= esc_html($page['hero_image']) ?>" alt="<?= esc_html($page['title']) ?>">
                    <?php else: ?>
                        <div class="placeholder-image">📄</div>
                    <?php endif; ?>
                </div>

                <div class="card-content">
                    <h3 class="card-title">
                        <?php if ($page['is_homepage']): ?>
                            <span class="badge badge-home">🏠</span>
                        <?php endif; ?>
                        <?= esc_html($page['title']) ?>
                    </h3>
                    <div class="card-meta">
                        <span class="card-slug">/<?= esc_html($page['slug']) ?></span>
                        <?php if (!$page['is_public']): ?>
                            <span class="badge badge-private">Private</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-overlay">
                    <a href="page-editor.php?id=<?= $page['id'] ?>" class="overlay-btn">Edit</a>
                    <?php if (!$page['is_homepage']): ?>
                        <button class="delete-btn" onclick="deletePage(<?= $page['id'] ?>, '<?= esc_html($page['title']) ?>')">×</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($pages)): ?>
            <div class="empty-state-full">
                <div class="empty-icon">📄</div>
                <h2>No Pages Yet</h2>
                <p>Create your first page to get started!</p>
            </div>
        <?php endif; ?>
    </div>
</main>

</div>

<script>
function deletePage(id, title) {
    if (confirm(`Are you sure you want to delete "${title}"?\n\nThis action cannot be undone.`)) {
        window.location.href = `pages.php?action=delete&id=${id}`;
    }
}
</script>

</body>
</html>
