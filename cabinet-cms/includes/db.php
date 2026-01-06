<?php
/**
 * Database Connection
 */

// Load configuration
if (!file_exists(__DIR__ . '/../config.php')) {
    die('Configuration file not found. Please run setup.php first.');
}

require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die('Database connection failed. Please check your configuration.');
}

/**
 * Get a setting value from database
 */
function get_setting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update or insert a setting
 */
function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

/**
 * Generate URL-friendly slug
 */
function generate_slug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Log error to database and file
 */
function log_error($message, $file = '', $line = 0) {
    global $pdo;

    // Log to file
    error_log("[$file:$line] $message");

    // Log to database (optional)
    try {
        $stmt = $pdo->prepare("INSERT INTO error_log (error_type, error_message, file_path, line_number, occurred_at)
                               VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute(['Error', $message, $file, $line]);
    } catch (Exception $e) {
        // Silent fail if database logging fails
    }
}

/**
 * Sanitize output for HTML display
 */
function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Get base URL
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script = str_replace('/admin', '', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim($protocol . $host . $script, '/');
}
