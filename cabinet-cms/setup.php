<?php
/**
 * Cabinet CMS - Setup Wizard
 * One-time installation script
 */

// Check if already installed
if (file_exists(__DIR__ . '/.installed')) {
    header('Location: admin/login.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === 1) {
        // Step 1: Database Configuration
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        $drop_existing = isset($_POST['drop_existing']);

        if (empty($db_name) || empty($db_user)) {
            $error = 'Database name and username are required.';
        } else {
            try {
                // Test connection
                $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Check if database exists
                $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
                $db_exists = $stmt->rowCount() > 0;

                if ($db_exists) {
                    // Check for existing tables
                    $pdo->exec("USE `$db_name`");
                    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
                    $tables_exist = $stmt->rowCount() > 0;

                    if ($tables_exist && !$drop_existing) {
                        $error = 'Database already contains CMS tables. Check "Drop existing tables" to reinstall.';
                    } else {
                        // Store config and move to step 2
                        session_start();
                        $_SESSION['setup_db'] = [
                            'host' => $db_host,
                            'name' => $db_name,
                            'user' => $db_user,
                            'pass' => $db_pass,
                            'drop' => $drop_existing
                        ];
                        header('Location: setup.php?step=2');
                        exit;
                    }
                } else {
                    // Create database
                    $pdo->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    session_start();
                    $_SESSION['setup_db'] = [
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass,
                        'drop' => false
                    ];
                    header('Location: setup.php?step=2');
                    exit;
                }

            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
        }
    }

    if ($step === 2) {
        // Step 2: Admin Account & API Keys
        session_start();

        $admin_username = trim($_POST['admin_username'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_email = trim($_POST['admin_email'] ?? '');
        $tinymce_key = trim($_POST['tinymce_key'] ?? '');

        // Validate
        if (empty($admin_username) || empty($admin_password) || empty($admin_email)) {
            $error = 'All admin fields are required.';
        } elseif (strlen($admin_password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $admin_password)) {
            $error = 'Password must contain at least one special character.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Install database
            try {
                $db = $_SESSION['setup_db'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Drop existing tables if requested
                if ($db['drop']) {
                    $tables = ['image_categories', 'page_categories', 'images', 'jobs', 'pages', 'categories',
                               'social_links', 'settings', 'users', 'construction_signups', 'error_log'];
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    }
                }

                // Create tables
                $schema = file_get_contents(__DIR__ . '/install/schema.sql');
                $pdo->exec($schema);

                // Insert default admin user
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, is_primary, created_at) VALUES (?, ?, ?, 1, NOW())");
                $stmt->execute([$admin_username, $admin_email, $hashed_password]);

                // Insert default settings
                $defaults = [
                    ['company_name', 'My Cabinet Company'],
                    ['contact_email', $admin_email],
                    ['phone_number', ''],
                    ['address', ''],
                    ['bg_color', '#ffffff'],
                    ['accent_color', '#2c5f8d'],
                    ['text_color', '#333333'],
                    ['enable_watermark', '0'],
                    ['seo_title', 'My Cabinet Portfolio'],
                    ['seo_description', 'Professional cabinet installation and remodeling services.'],
                    ['seo_keywords', 'cabinets, kitchen remodel, bathroom cabinets, custom cabinets'],
                    ['tinymce_key', $tinymce_key],
                    ['maintenance_mode', '1'], // Start in construction mode
                    ['construction_text', 'Our new website is under construction. Leave your email to be notified when we launch!']
                ];

                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                foreach ($defaults as $setting) {
                    $stmt->execute($setting);
                }

                // Insert social media placeholders
                $socials = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
                $stmt = $pdo->prepare("INSERT INTO social_links (platform, url) VALUES (?, '')");
                foreach ($socials as $platform) {
                    $stmt->execute([$platform]);
                }

                // Create config file
                $config_content = "<?php\n";
                $config_content .= "// Cabinet CMS Configuration\n";
                $config_content .= "// Generated on " . date('Y-m-d H:i:s') . "\n\n";
                $config_content .= "define('DB_HOST', '{$db['host']}');\n";
                $config_content .= "define('DB_NAME', '{$db['name']}');\n";
                $config_content .= "define('DB_USER', '{$db['user']}');\n";
                $config_content .= "define('DB_PASS', '" . addslashes($db['pass']) . "');\n\n";
                $config_content .= "// Timezone\n";
                $config_content .= "date_default_timezone_set('America/New_York');\n\n";
                $config_content .= "// Error reporting (set to 0 in production)\n";
                $config_content .= "error_reporting(E_ALL);\n";
                $config_content .= "ini_set('display_errors', 0);\n";
                $config_content .= "ini_set('log_errors', 1);\n";
                $config_content .= "ini_set('error_log', __DIR__ . '/error.log');\n";

                file_put_contents(__DIR__ . '/config.php', $config_content);

                // Create uploads directory
                if (!is_dir(__DIR__ . '/uploads')) {
                    mkdir(__DIR__ . '/uploads', 0755, true);
                }

                // Create .htaccess for uploads security
                file_put_contents(__DIR__ . '/uploads/.htaccess', "Options -Indexes\n");

                // Mark as installed
                file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));

                // Clear session
                unset($_SESSION['setup_db']);

                header('Location: setup.php?step=3');
                exit;

            } catch (PDOException $e) {
                $error = 'Installation failed: ' . $e->getMessage();
            }
        }
    }
}

// Get stored DB config for step 2
if ($step === 2) {
    session_start();
    if (!isset($_SESSION['setup_db'])) {
        header('Location: setup.php?step=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cabinet CMS - Setup Wizard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .setup-header {
            background: #2c5f8d;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .setup-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .setup-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .progress-bar {
            background: rgba(255,255,255,0.2);
            height: 4px;
            margin-top: 20px;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            background: white;
            height: 100%;
            transition: width 0.3s;
        }

        .setup-content {
            padding: 40px;
        }

        .step-title {
            font-size: 20px;
            color: #2c5f8d;
            margin-bottom: 10px;
        }

        .step-description {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #2c5f8d;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #2c5f8d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            background: #1e4a6b;
        }

        .btn-full {
            width: 100%;
        }

        .success-icon {
            text-align: center;
            font-size: 60px;
            color: #4caf50;
            margin-bottom: 20px;
        }

        .success-message {
            text-align: center;
        }

        .success-message h2 {
            color: #2c5f8d;
            margin-bottom: 10px;
        }

        .success-message p {
            color: #666;
            margin-bottom: 30px;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🏗️ Cabinet CMS Setup</h1>
            <p>Let's get your portfolio website up and running</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= ($step / 3) * 100 ?>%;"></div>
            </div>
        </div>

        <div class="setup-content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <h2 class="step-title">Step 1: Database Configuration</h2>
                <p class="step-description">Enter your MySQL database credentials. The installer will create the database if it doesn't exist.</p>

                <form method="POST">
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" name="db_host" value="localhost" required>
                        <div class="help-text">Usually "localhost"</div>
                    </div>

                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="db_name" value="" required>
                        <div class="help-text">Will be created if it doesn't exist</div>
                    </div>

                    <div class="form-group">
                        <label>Database Username</label>
                        <input type="text" name="db_user" value="" required>
                    </div>

                    <div class="form-group">
                        <label>Database Password</label>
                        <input type="password" name="db_pass" value="">
                        <div class="help-text">Leave blank if no password</div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="drop_existing" id="drop_existing">
                            <label for="drop_existing">Drop existing tables and reinstall (⚠️ This will delete all data)</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-full">Continue to Step 2 →</button>
                </form>

            <?php elseif ($step === 2): ?>
                <h2 class="step-title">Step 2: Admin Account & API Keys</h2>
                <p class="step-description">Create your admin account and configure API keys.</p>

                <form method="POST">
                    <div class="form-group">
                        <label>Admin Username</label>
                        <input type="text" name="admin_username" required>
                    </div>

                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="admin_email" required>
                        <div class="help-text">Used for password resets and notifications</div>
                    </div>

                    <div class="form-group">
                        <label>Admin Password</label>
                        <input type="password" name="admin_password" required>
                        <div class="help-text">Min 8 characters, must include a special character</div>
                    </div>

                    <div class="form-group">
                        <label>TinyMCE API Key (Optional)</label>
                        <input type="text" name="tinymce_key" value="">
                        <div class="help-text">Get free key at <a href="https://www.tiny.cloud/auth/signup/" target="_blank">tiny.cloud</a> - Can be added later in Settings</div>
                    </div>

                    <button type="submit" class="btn btn-full">Install Cabinet CMS 🚀</button>
                </form>

            <?php elseif ($step === 3): ?>
                <div class="success-icon">✅</div>
                <div class="success-message">
                    <h2>Installation Complete!</h2>
                    <p>Your Cabinet CMS is ready to use. Your site is in construction mode by default.</p>
                    <a href="admin/login.php" class="btn btn-full">Go to Admin Login →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
