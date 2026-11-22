<?php
declare(strict_types=1);

const BACKUPS_KEEP = 14;

/** Zip the database + uploaded photos into drmadeit-data/backups. Returns the zip path. */
function make_backup(): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The PHP zip extension is off. Turn it on in Hostinger → PHP Configuration → Extensions.');
    }
    $dir = DATA_DIR . '/backups';
    $snapshot = $dir . '/site-snapshot.sqlite';
    @unlink($snapshot);
    db()->exec('VACUUM INTO ' . db()->quote($snapshot)); // consistent copy even while the site is in use

    $zipPath = $dir . '/backup-' . date('Y-m-d-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the backup file.');
    }
    $zip->addFile($snapshot, 'site.sqlite');
    foreach (glob(UPLOAD_DIR . '/*') ?: [] as $f) {
        if (is_file($f)) {
            $zip->addFile($f, 'uploads/' . basename($f));
        }
    }
    $zip->addFromString('README.txt', "Backup of " . base_url() . " made " . date('r') . ".\n"
        . "To restore: put site.sqlite and the uploads folder back into drmadeit-data/.\n"
        . "Download files (drmadeit-data/files) are not included: keep your originals.\n");
    $zip->close();
    @unlink($snapshot);

    $all = glob($dir . '/backup-*.zip') ?: [];
    sort($all);
    foreach (array_slice($all, 0, max(0, count($all) - BACKUPS_KEEP)) as $old) {
        @unlink($old);
    }
    return $zipPath;
}

/** Runs after a page is sent. Makes one backup a day without needing a cron job. */
function maybe_nightly_backup(): void
{
    $all = glob(DATA_DIR . '/backups/backup-*.zip') ?: [];
    $newest = $all ? max(array_map('filemtime', $all)) : 0;
    if (time() - $newest < 86400) {
        return;
    }
    $lock = fopen(DATA_DIR . '/backups/.lock', 'c');
    if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
        try {
            make_backup();
        } catch (Throwable $ex) {
            error_log('Backup failed: ' . $ex->getMessage());
        }
        flock($lock, LOCK_UN);
    }
}
