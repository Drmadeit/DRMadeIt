<?php
require_once 'auth-check.php';

$page_title = 'Dashboard';

// Get stats
$stmt = $pdo->query("SELECT COUNT(*) as count FROM jobs");
$total_jobs = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM images");
$total_images = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM pages WHERE is_public = 1");
$total_pages = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM construction_signups");
$total_signups = $stmt->fetch()['count'];

// Recent jobs
$stmt = $pdo->query("SELECT id, name, created_at FROM jobs ORDER BY created_at DESC LIMIT 5");
$recent_jobs = $stmt->fetchAll();

// Recent pages
$stmt = $pdo->query("SELECT id, title, updated_at FROM pages ORDER BY updated_at DESC LIMIT 5");
$recent_pages = $stmt->fetchAll();

// Get maintenance mode status
$maintenance_mode = (bool)get_setting('maintenance_mode', 0);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <div class="header-actions">
            <a href="../index.php" target="_blank" class="btn btn-secondary">View Site ↗</a>
        </div>
    </div>

    <?php if ($maintenance_mode): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Maintenance Mode Active</strong>
            Your site is showing the construction page. Disable it in <a href="settings.php?section=theme">Theme Settings</a>.
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">💼</div>
            <div class="stat-content">
                <div class="stat-value"><?= $total_jobs ?></div>
                <div class="stat-label">Active Jobs</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🖼️</div>
            <div class="stat-content">
                <div class="stat-value"><?= $total_images ?></div>
                <div class="stat-label">Total Photos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📄</div>
            <div class="stat-content">
                <div class="stat-value"><?= $total_pages ?></div>
                <div class="stat-label">Published Pages</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📧</div>
            <div class="stat-content">
                <div class="stat-value"><?= $total_signups ?></div>
                <div class="stat-label">Email Signups</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>

            <div class="quick-actions">
                <a href="job-editor.php" class="action-card">
                    <div class="action-icon">➕</div>
                    <div class="action-title">Add New Job</div>
                    <div class="action-desc">Create a new portfolio project</div>
                </a>

                <a href="page-editor.php" class="action-card">
                    <div class="action-icon">📝</div>
                    <div class="action-title">Add New Page</div>
                    <div class="action-desc">Create a new site page</div>
                </a>

                <a href="media-library.php" class="action-card">
                    <div class="action-icon">📤</div>
                    <div class="action-title">Upload Images</div>
                    <div class="action-desc">Add images to media library</div>
                </a>

                <a href="settings.php?section=theme" class="action-card">
                    <div class="action-icon">🎨</div>
                    <div class="action-title">Customize Theme</div>
                    <div class="action-desc">Change colors and branding</div>
                </a>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Jobs</h2>
                <a href="jobs.php" class="section-link">View All →</a>
            </div>

            <?php if (empty($recent_jobs)): ?>
                <p class="empty-state">No jobs yet. <a href="job-editor.php">Create your first job</a></p>
            <?php else: ?>
                <div class="recent-list">
                    <?php foreach ($recent_jobs as $job): ?>
                        <a href="job-editor.php?id=<?= $job['id'] ?>" class="recent-item">
                            <div class="recent-title"><?= esc_html($job['name']) ?></div>
                            <div class="recent-meta"><?= date('M j, Y', strtotime($job['created_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Pages</h2>
                <a href="pages.php" class="section-link">View All →</a>
            </div>

            <?php if (empty($recent_pages)): ?>
                <p class="empty-state">No pages yet. <a href="page-editor.php">Create your first page</a></p>
            <?php else: ?>
                <div class="recent-list">
                    <?php foreach ($recent_pages as $page): ?>
                        <a href="page-editor.php?id=<?= $page['id'] ?>" class="recent-item">
                            <div class="recent-title"><?= esc_html($page['title']) ?></div>
                            <div class="recent-meta">Updated <?= date('M j, Y', strtotime($page['updated_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>
</body>
</html>
