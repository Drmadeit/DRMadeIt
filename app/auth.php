<?php
declare(strict_types=1);

const LOCKOUT_TRIES = 5;
const LOCKOUT_SECONDS = 900; // 15 minutes
const SESSION_DAYS = 30;     // stay logged in on the phone

function start_session(): void
{
    session_save_path(DATA_DIR . '/sessions');
    ini_set('session.gc_maxlifetime', (string) (SESSION_DAYS * 86400));
    ini_set('session.use_strict_mode', '1');
    session_name('drm');
    session_set_cookie_params([
        'lifetime' => SESSION_DAYS * 86400,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_admin(): bool
{
    return ($_SESSION['admin'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('/admin/login');
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Seconds until login is allowed again (0 = allowed). Counts all failures: there is one admin. */
function lockout_remaining(): int
{
    $since = time() - LOCKOUT_SECONDS;
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([$since]);
    $row = db()->query('SELECT COUNT(*) n, MIN(at) first FROM login_attempts')->fetch();
    if ((int) $row['n'] < LOCKOUT_TRIES) {
        return 0;
    }
    return max(1, (int) $row['first'] + LOCKOUT_SECONDS - time());
}

function attempt_login(string $user, string $pass): bool
{
    $ok = hash_equals(setting('admin_user'), $user)
        && password_verify($pass, setting('admin_pass_hash'));
    if (!$ok) {
        db()->prepare('INSERT INTO login_attempts (ip, at) VALUES (?, ?)')->execute([client_ip(), time()]);
        return false;
    }
    db()->exec('DELETE FROM login_attempts');
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    return true;
}

function admin_exists(): bool
{
    return setting('admin_pass_hash') !== '';
}

/**
 * First-run setup is protected by a one-time code written to the data folder,
 * so nobody who finds the site before David can claim the admin account.
 */
function setup_code_file(): string
{
    return DATA_DIR . '/SETUP-CODE.txt';
}

function ensure_setup_code(): void
{
    if (!is_file(setup_code_file())) {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        file_put_contents(setup_code_file(), $code . "\n");
        chmod(setup_code_file(), 0600);
    }
}

function password_problem(string $pass): ?string
{
    if (strlen($pass) < 12) {
        return 'Use at least 12 characters. A short sentence works well.';
    }
    return null;
}
