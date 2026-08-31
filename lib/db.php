<?php
/**
 * SQLite connection and the migration runner.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // WAL keeps reads fast while the admin writes. Checkpoint before deploying
    // so the .db file you upload is self-contained (see deploy.sh).
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    return $pdo;
}

/**
 * Applies any migrations/*.sql not yet recorded, in filename order.
 *
 * Running this on boot means a schema change made locally travels inside the
 * .db file when you deploy — there is nothing to run on the host.
 */
function db_migrate(): void
{
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version    TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )'
    );

    $applied = array_flip(
        $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)
    );

    $files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (isset($applied[$version])) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            continue;
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException("Migration {$version} failed: " . $e->getMessage(), 0, $e);
        }
    }
}
