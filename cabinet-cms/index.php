<?php
/**
 * Cabinet CMS - Frontend Router
 */

require_once __DIR__ . '/includes/db.php';

// Get request URI
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$base_path = rtrim($script_name, '/');

// Remove base path from request
$path = str_replace($base_path, '', $request_uri);
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');

// Parse query parameters
$category_filter = $_GET['category'] ?? null;

// Check maintenance mode
$maintenance_mode = (bool)get_setting('maintenance_mode', 0);

// Handle construction page email signup
if (isset($_POST['signup_email']) && $maintenance_mode) {
    $email = filter_var(trim($_POST['signup_email']), FILTER_VALIDATE_EMAIL);

    if ($email) {
        try {
            // Check if already signed up
            $stmt = $pdo->prepare("SELECT id FROM construction_signups WHERE email = ?");
            $stmt->execute([$email]);

            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO construction_signups (email, subscribed_at) VALUES (?, NOW())");
                $stmt->execute([$email]);

                // Send notification email to admin
                $admin_email = get_setting('contact_email', '');
                if ($admin_email) {
                    $subject = "New Construction Page Signup";
                    $message = "A visitor signed up for launch notifications:\n\nEmail: $email\nDate: " . date('Y-m-d H:i:s');
                    mail($admin_email, $subject, $message, "From: no-reply@{$_SERVER['HTTP_HOST']}");
                }

                $signup_success = true;
            } else {
                $signup_exists = true;
            }
        } catch (Exception $e) {
            log_error($e->getMessage(), __FILE__, __LINE__);
            $signup_error = true;
        }
    } else {
        $signup_invalid = true;
    }
}

// If maintenance mode, show construction page (unless accessing setup.php)
if ($maintenance_mode && $path !== 'setup.php') {
    include __DIR__ . '/views/construction.php';
    exit;
}

// Route handling
if (empty($path) || $path === 'index.php') {
    // Homepage
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE is_homepage = 1 AND is_public = 1");
    $stmt->execute();
    $page = $stmt->fetch();

    if ($page) {
        $page_type = 'page';
        include __DIR__ . '/views/page.php';
    } else {
        // No homepage set, show default message
        $page_type = 'default';
        include __DIR__ . '/views/default-home.php';
    }

} elseif (strpos($path, 'job/') === 0) {
    // Job view
    $job_slug = str_replace('job/', '', $path);
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE slug = ?");
    $stmt->execute([$job_slug]);
    $job = $stmt->fetch();

    if ($job) {
        include __DIR__ . '/views/job.php';
    } else {
        include __DIR__ . '/views/404.php';
    }

} else {
    // Page view
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND is_public = 1");
    $stmt->execute([$path]);
    $page = $stmt->fetch();

    if ($page) {
        $page_type = 'page';
        include __DIR__ . '/views/page.php';
    } else {
        include __DIR__ . '/views/404.php';
    }
}
