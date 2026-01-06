<?php
/**
 * Admin Authentication Check
 * Include this at the top of every admin page
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    // Check for remember me cookie
    if (isset($_COOKIE['admin_remember'])) {
        require_once __DIR__ . '/../includes/db.php';

        $user_id = (int)$_COOKIE['admin_remember'];
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
        } else {
            // Invalid cookie, delete it
            setcookie('admin_remember', '', time() - 3600, '/');
            header('Location: login.php');
            exit;
        }
    } else {
        header('Location: login.php');
        exit;
    }
}

// Include database connection
require_once __DIR__ . '/../includes/db.php';

// Get current admin info
$current_admin_id = $_SESSION['admin_id'];
$current_admin_username = $_SESSION['admin_username'];

// Check if user still exists
$stmt = $pdo->prepare("SELECT id, is_primary FROM users WHERE id = ?");
$stmt->execute([$current_admin_id]);
$current_admin = $stmt->fetch();

if (!$current_admin) {
    // User was deleted
    session_destroy();
    setcookie('admin_remember', '', time() - 3600, '/');
    header('Location: login.php');
    exit;
}

$is_primary_admin = (bool)$current_admin['is_primary'];
