<?php
/**
 * Central configuration. Every entry point requires this file first.
 *
 * Deliberately contains no admin logic: this file is part of the shipped site,
 * and the shipped site must stand up with admin.php absent.
 */
declare(strict_types=1);

define('ROOT_DIR',         __DIR__);
define('DATA_DIR',         ROOT_DIR . '/data');
define('DB_PATH',          DATA_DIR . '/portfolio.db');
define('MIGRATIONS_DIR',   ROOT_DIR . '/migrations');
define('PICTURES_DIR',     ROOT_DIR . '/pictures');
define('CERTIFICATES_DIR', ROOT_DIR . '/certificates');
define('ADMIN_FILE',       ROOT_DIR . '/admin.php');

// Presence of data/.dev marks a development checkout. deploy.sh never copies it,
// so this is false on the live site even if admin.php were left behind.
define('DEV_MARKER', DATA_DIR . '/.dev');
define('IS_DEV', is_file(DEV_MARKER));

require_once ROOT_DIR . '/lib/db.php';
require_once ROOT_DIR . '/lib/content.php';
require_once ROOT_DIR . '/lib/render.php';

// migrations/ is excluded from deploys, so on the live site this is skipped
// entirely and the shipped .db is used exactly as uploaded.
if (is_dir(MIGRATIONS_DIR)) {
    db_migrate();
}
