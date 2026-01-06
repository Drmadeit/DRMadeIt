<?php
/**
 * Admin Logout
 */
session_start();
session_destroy();

// Delete remember me cookie
setcookie('admin_remember', '', time() - 3600, '/');

header('Location: login.php');
exit;
