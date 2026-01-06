<?php
require_once 'auth-check.php';

$page_title = 'Manage Users';

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';

// Handle add user
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = "Password must contain at least one special character.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already exists.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, is_primary, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->execute([$username, $email, $hashed]);
                $success = "User added successfully!";
                $action = 'list';
            }
        } catch (Exception $e) {
            log_error($e->getMessage(), __FILE__, __LINE__);
            $error = "Failed to add user.";
        }
    }
}

// Handle delete user
if ($action === 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Check if it's the primary admin
    $stmt = $pdo->prepare("SELECT is_primary FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && $user['is_primary']) {
        $error = "Cannot delete the primary admin account.";
    } elseif ($user_id === $current_admin_id) {
        $error = "Cannot delete your own account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = "User deleted successfully.";
        } catch (Exception $e) {
            log_error($e->getMessage(), __FILE__, __LINE__);
            $error = "Failed to delete user.";
        }
    }
    $action = 'list';
}

// Handle change password
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$current_admin_id]);
    $user = $stmt->fetch();

    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error = "Password must contain at least one special character.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $current_admin_id]);
            $success = "Password changed successfully!";
        } catch (Exception $e) {
            log_error($e->getMessage(), __FILE__, __LINE__);
            $error = "Failed to change password.";
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY is_primary DESC, created_at DESC");
$users = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Manage Users</h1>
        <?php if ($action === 'list'): ?>
            <div class="header-actions">
                <a href="?action=add" class="btn btn-primary">+ Add New User</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc_html($success) ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="form-card">
            <h2>Admin Accounts</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?= esc_html($user['username']) ?>
                                <?php if ($user['id'] === $current_admin_id): ?>
                                    <span class="badge badge-info">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc_html($user['email']) ?></td>
                            <td>
                                <?php if ($user['is_primary']): ?>
                                    <span class="badge badge-primary">Primary Admin</span>
                                <?php else: ?>
                                    <span class="badge">Admin</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?php if (!$user['is_primary'] && $user['id'] !== $current_admin_id): ?>
                                    <a href="?action=delete&id=<?= $user['id'] ?>" class="btn-text-danger" onclick="return confirm('Delete this user?')">Delete</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-card">
            <h2>Change Your Password</h2>
            <form method="POST" action="?action=change_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required class="form-control">
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required class="form-control">
                    <div class="help-text">Min 8 characters, must include a special character</div>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>

    <?php elseif ($action === 'add'): ?>
        <div class="form-card">
            <h2>Add New Admin User</h2>

            <form method="POST">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required class="form-control">
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required class="form-control">
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required class="form-control">
                    <div class="help-text">Min 8 characters, must include a special character</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add User</button>
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</main>

</div>

</body>
</html>
