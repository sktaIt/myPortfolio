<?php
/**
 * Read/write helpers over the content tables.
 *
 * COLLECTION_TABLES is the allowlist that makes dynamic table names safe: a
 * table name can never be a bound parameter, so it must be validated against a
 * fixed list before it reaches SQL. Nothing here interpolates request data.
 */
declare(strict_types=1);

const COLLECTION_TABLES = [
    'projects', 'certificates', 'skills', 'experience',
    'education', 'publications', 'activities',
];

/** @return array<string,array<string,mixed>> keyed by setting key */
function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($reload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $sql = 'SELECT key, value, type, label, section, sort FROM settings ORDER BY section, sort, key';
    foreach (db()->query($sql) as $row) {
        $cache[$row['key']] = $row;
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) ? (string) $all[$key]['value'] : $default;
}

/**
 * Only ever UPDATEs. Keys are created by migrations, so a bad key from the
 * admin affects zero rows instead of inserting junk.
 */
function setting_set(string $key, string $value): bool
{
    $stmt = db()->prepare('UPDATE settings SET value = :v WHERE key = :k');
    $stmt->execute([':v' => $value, ':k' => $key]);
    settings_all(true);
    return $stmt->rowCount() > 0;
}

/** @return array<string,array<int,array<string,mixed>>> settings grouped by section */
function settings_by_section(): array
{
    $grouped = [];
    foreach (settings_all() as $key => $row) {
        $grouped[$row['section']][] = $row;
    }
    return $grouped;
}

function is_collection(string $table): bool
{
    return in_array($table, COLLECTION_TABLES, true);
}

/** @return array<int,array<string,mixed>> */
function rows(string $table, bool $only_visible = true): array
{
    if (!is_collection($table)) {
        throw new InvalidArgumentException("Unknown collection: {$table}");
    }
    $sql = "SELECT * FROM {$table}"
         . ($only_visible ? ' WHERE visible = 1' : '')
         . ' ORDER BY sort ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function row(string $table, int $id): ?array
{
    if (!is_collection($table)) {
        throw new InvalidArgumentException("Unknown collection: {$table}");
    }
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** @return array<int,array<string,mixed>> enabled sections, in display order */
function sections_enabled(): array
{
    return db()->query('SELECT * FROM sections WHERE enabled = 1 ORDER BY sort ASC, key ASC')->fetchAll();
}

/** @return array<int,array<string,mixed>> every section, for the admin panel */
function sections_all(): array
{
    return db()->query('SELECT * FROM sections ORDER BY sort ASC, key ASC')->fetchAll();
}

function media(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM media WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Web-root-relative path for a media row, or null. */
function media_path(?int $id): ?string
{
    $m = media($id);
    return $m ? (string) $m['path'] : null;
}

/** @return array<int,array<string,mixed>> */
function media_list(?string $kind = null): array
{
    if ($kind !== null && !in_array($kind, ['picture', 'certificate'], true)) {
        throw new InvalidArgumentException("Unknown media kind: {$kind}");
    }
    if ($kind === null) {
        return db()->query('SELECT * FROM media ORDER BY created_at DESC, id DESC')->fetchAll();
    }
    $stmt = db()->prepare('SELECT * FROM media WHERE kind = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$kind]);
    return $stmt->fetchAll();
}

/** Skills grouped by their category label. */
function skills_by_category(): array
{
    $grouped = [];
    foreach (rows('skills') as $skill) {
        $grouped[$skill['category'] ?: 'Other'][] = $skill;
    }
    return $grouped;
}
