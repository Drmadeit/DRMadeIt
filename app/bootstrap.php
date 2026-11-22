<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Data lives OUTSIDE the web root so a deploy can never overwrite it.
// On Hostinger: ~/domains/<site>/public_html  ->  ~/domains/<site>/drmadeit-data
define('DATA_DIR', rtrim(getenv('DRM_DATA_DIR') ?: dirname(ROOT) . '/drmadeit-data', '/'));
define('UPLOAD_DIR', DATA_DIR . '/uploads');

$isDev = PHP_SAPI === 'cli-server' || getenv('DRM_DEV') === '1';
error_reporting(E_ALL);
date_default_timezone_set('America/New_York');
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');

foreach ([DATA_DIR, UPLOAD_DIR, DATA_DIR . '/files', DATA_DIR . '/cache', DATA_DIR . '/backups', DATA_DIR . '/sessions'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}
ini_set('error_log', DATA_DIR . '/php-error.log');

require ROOT . '/app/helpers.php';
require ROOT . '/app/db.php';
require ROOT . '/app/images.php';
require ROOT . '/app/auth.php';
require ROOT . '/app/mail.php';
require ROOT . '/app/shop.php';
require ROOT . '/app/youtube.php';
require ROOT . '/app/backup.php';
require ROOT . '/app/pages.php';
require ROOT . '/app/apps.php';

start_session();
