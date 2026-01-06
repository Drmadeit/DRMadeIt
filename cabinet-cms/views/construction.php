<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc_html(get_setting('seo_title', 'Under Construction')) ?></title>
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

        .construction-container {
            text-align: center;
            max-width: 600px;
            background: white;
            padding: 60px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .construction-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        p {
            color: #7f8c8d;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .signup-form {
            display: flex;
            gap: 10px;
            max-width: 400px;
            margin: 0 auto;
        }

        .signup-form input[type="email"] {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }

        .signup-form input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .signup-form button {
            padding: 14px 28px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .signup-form button:hover {
            background: #5568d3;
        }

        .alert {
            margin-top: 15px;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .footer-text {
            margin-top: 40px;
            font-size: 13px;
            color: #999;
        }

        @media (max-width: 600px) {
            .construction-container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 26px;
            }

            .signup-form {
                flex-direction: column;
            }

            .signup-form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="construction-container">
        <div class="construction-icon">🏗️</div>
        <h1>Website Under Construction</h1>
        <p><?= nl2br(esc_html(get_setting('construction_text', 'Our new website is under construction. Leave your email to be notified when we launch!'))) ?></p>

        <form method="POST" class="signup-form">
            <input type="email" name="signup_email" placeholder="Enter your email..." required>
            <button type="submit">Notify Me</button>
        </form>

        <?php if (isset($signup_success)): ?>
            <div class="alert alert-success">
                ✅ Thank you! We'll notify you when we launch.
            </div>
        <?php elseif (isset($signup_exists)): ?>
            <div class="alert alert-info">
                You're already signed up!
            </div>
        <?php elseif (isset($signup_invalid)): ?>
            <div class="alert alert-error">
                Please enter a valid email address.
            </div>
        <?php elseif (isset($signup_error)): ?>
            <div class="alert alert-error">
                Something went wrong. Please try again.
            </div>
        <?php endif; ?>

        <p class="footer-text">
            <?= esc_html(get_setting('company_name', '')) ?>
            <?php if (!empty(get_setting('company_name', ''))): ?>— <?php endif; ?>
            Unless the admin forgot to assign a main page lol
        </p>
    </div>
</body>
</html>
