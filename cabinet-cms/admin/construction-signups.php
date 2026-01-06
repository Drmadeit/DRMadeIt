<?php
require_once 'auth-check.php';

$page_title = 'Email Signups';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM construction_signups WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: construction-signups.php?deleted=1');
        exit;
    } catch (Exception $e) {
        log_error($e->getMessage(), __FILE__, __LINE__);
        $error = "Failed to delete signup.";
    }
}

// Handle export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $stmt = $pdo->query("SELECT email, subscribed_at FROM construction_signups ORDER BY subscribed_at DESC");
    $signups = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email-signups-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', 'Subscribed Date']);

    foreach ($signups as $signup) {
        fputcsv($output, [
            $signup['email'],
            date('Y-m-d H:i:s', strtotime($signup['subscribed_at']))
        ]);
    }

    fclose($output);
    exit;
}

// Get all signups
$stmt = $pdo->query("SELECT * FROM construction_signups ORDER BY subscribed_at DESC");
$signups = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Email Signups</h1>
        <div class="header-actions">
            <?php if (!empty($signups)): ?>
                <a href="?action=export" class="btn btn-secondary">Export CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <p class="page-description">
        Email addresses collected from the construction page signup form.
    </p>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Email deleted successfully.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <?php if (empty($signups)): ?>
        <div class="empty-state-full">
            <div class="empty-icon">📧</div>
            <h2>No Email Signups Yet</h2>
            <p>When visitors submit the construction page form, emails will appear here.</p>
        </div>
    <?php else: ?>
        <div class="form-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Email Address</th>
                        <th>Subscribed Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($signups as $signup): ?>
                        <tr>
                            <td><?= esc_html($signup['email']) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($signup['subscribed_at'])) ?></td>
                            <td>
                                <a href="?action=delete&id=<?= $signup['id'] ?>" class="btn-text-danger" onclick="return confirm('Delete this email?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

</div>

</body>
</html>
