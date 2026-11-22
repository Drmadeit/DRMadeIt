<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Data lives OUTSIDE the web root so a deploy can never overwrite it.
// On Hostinger: ~/domains/<site>/public_html  ->  ~/domains/<site>/drmadeit-data
define('DATA_DIR', rtrim(getenv('DRM_DATA_DIR') ?: dirname(ROOT) . '/drmadeit-data', '/'));
define('UPLOAD_DIR', DATA_DIR . '/uploads');

$isDev = PHP_SAPI === 'cli-server' || getenv('DRM_DEV') === '1';
error_reporting(E_ALL);
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

start_session();
