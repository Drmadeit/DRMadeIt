<?php
/**
 * Admin Login Page
 */
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';

// Handle forgot password request
if (isset($_GET['action']) && $_GET['action'] === 'forgot') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);

                // Send email
                $reset_link = get_base_url() . "/admin/login.php?action=reset&token=$token";
                $subject = "Password Reset Request";
                $message = "Hi {$user['username']},\n\n";
                $message .= "Click the link below to reset your password:\n";
                $message .= "$reset_link\n\n";
                $message .= "This link expires in 1 hour.\n\n";
                $message .= "If you didn't request this, please ignore this email.";

                $headers = "From: " . get_setting('contact_email', 'no-reply@' . $_SERVER['HTTP_HOST']);

                if (mail($email, $subject, $message, $headers)) {
                    $success = "Password reset link sent to your email.";
                } else {
                    $error = "Failed to send email. Please contact administrator.";
                }
            } else {
                // Don't reveal if email exists or not (security)
                $success = "If that email exists, a reset link has been sent.";
            }
        } else {
            $error = "Invalid email address.";
        }
    }
}

// Handle password reset
if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['token'])) {
    $token = $_GET['token'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            $error = "Password must contain at least one special character.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Verify token
            $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                $stmt->execute([$hashed, $user['id']]);

                $success = "Password reset successful! You can now log in.";
                $_GET['action'] = null; // Show login form
            } else {
                $error = "Invalid or expired reset token.";
            }
        }
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];

            if ($remember) {
                // Set cookie for 30 days
                setcookie('admin_remember', $user['id'], time() + (30 * 24 * 60 * 60), '/');
            }

            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Cabinet CMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #2c5f8d 0%, #1e4a6b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
        }

        .login-header {
            background: #2c5f8d;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-content {
            padding: 40px;
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
            padding: 12px 15px;
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
            display: block;
            width: 100%;
            padding: 12px;
            background: #2c5f8d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            text-align: center;
            text-decoration: none;
        }

        .btn:hover {
            background: #1e4a6b;
        }

        .forgot-link {
            text-align: center;
            margin-top: 15px;
        }

        .forgot-link a {
            color: #2c5f8d;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Login</h1>
            <p>Cabinet CMS</p>
        </div>

        <div class="login-content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= esc_html($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc_html($success) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'forgot'): ?>
                <!-- Forgot Password Form -->
                <h2 style="margin-bottom: 10px; color: #2c5f8d;">Forgot Password</h2>
                <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Enter your email to receive a reset link.</p>

                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required autofocus>
                    </div>

                    <button type="submit" class="btn">Send Reset Link</button>
                </form>

                <div class="back-link">
                    <a href="login.php">← Back to Login</a>
                </div>

            <?php elseif (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['token'])): ?>
                <!-- Reset Password Form -->
                <h2 style="margin-bottom: 10px; color: #2c5f8d;">Reset Password</h2>
                <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Enter your new password.</p>

                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required autofocus>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn">Reset Password</button>
                </form>

            <?php else: ?>
                <!-- Login Form -->
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">Remember me</label>
                        </div>
                    </div>

                    <button type="submit" class="btn">Login</button>
                </form>

                <div class="forgot-link">
                    <a href="?action=forgot">Forgot your password?</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
