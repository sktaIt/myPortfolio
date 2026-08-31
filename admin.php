<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  admin.php — DELETE THIS FILE TO SHIP.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Everything the admin does lives here: the toolbar, the panels, the editing
 *  JavaScript, and the endpoints that write to the database. Nothing outside
 *  this file knows it exists, apart from one guarded require at the bottom of
 *  index.php. Delete this file and the site keeps every change you made,
 *  because the changes are in data/portfolio.db — not in here.
 *
 *  Two modes:
 *    included by index.php  → renders the toolbar and panels
 *    requested directly     → acts as the JSON endpoint (admin.php?action=…)
 */
declare(strict_types=1);

$admin_included = defined('ROOT_DIR');
if (!$admin_included) {
    require_once __DIR__ . '/config.php';
}

// ─── Gate ────────────────────────────────────────────────────────────────────
// Two independent conditions, either of which is false on a live host. This is
// belt and braces for the day you forget to delete the file.
$admin_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

if (!IS_DEV || !$admin_local) {
    if (!$admin_included) {
        http_response_code(404);
        exit;
    }
    return; // included: render nothing at all
}

// ─── Field definitions ───────────────────────────────────────────────────────
// The single source of truth for what the admin may write. A column not listed
// here cannot be reached by any request, whatever it claims. Table and column
// names only ever reach SQL after being matched against this map.
const ADMIN_FIELDS = [
    'projects' => [
        'title'       => 'text',     'slug'        => 'text',
        'summary'     => 'textarea', 'description' => 'textarea',
        'repo_url'    => 'url',      'live_url'    => 'url',
        'tags'        => 'text',     'media_id'    => 'image',
    ],
    'certificates' => [
        'title'         => 'text', 'issuer'         => 'text',
        'issue_date'    => 'text', 'expiry_date'    => 'text',
        'credential_id' => 'text', 'credential_url' => 'url',
        'file_path'     => 'file', 'media_id'       => 'image',
    ],
    'skills' => [
        'name' => 'text', 'category' => 'text', 'level' => 'number', 'icon' => 'text',
    ],
    'experience' => [
        'role'       => 'text', 'org'      => 'text',     'location' => 'text',
        'start_date' => 'text', 'end_date' => 'text',
        'bullets'    => 'textarea', 'url'   => 'url',
    ],
    'education' => [
        'qualification' => 'text', 'institution' => 'text', 'location' => 'text',
        'year'          => 'text', 'result'      => 'text', 'notes'    => 'textarea',
    ],
    'publications' => [
        'title' => 'text', 'venue' => 'text', 'year' => 'text',
        'url'   => 'url',  'summary' => 'textarea',
    ],
    'activities' => [
        'role'    => 'text',     'org' => 'text', 'period' => 'text',
        'bullets' => 'textarea', 'url' => 'url',
    ],
];

const ADMIN_LABELS = [
    'title' => 'Title', 'slug' => 'Slug', 'summary' => 'Summary',
    'description' => 'Description', 'repo_url' => 'Repository URL',
    'live_url' => 'Live URL', 'tags' => 'Tags (comma separated)',
    'media_id' => 'Image', 'issuer' => 'Issuer', 'issue_date' => 'Issued (YYYY-MM)',
    'expiry_date' => 'Expires (YYYY-MM, blank = never)', 'credential_id' => 'Credential ID',
    'credential_url' => 'Verification URL', 'file_path' => 'Certificate file',
    'name' => 'Name', 'category' => 'Category', 'level' => 'Level (0-100)',
    'icon' => 'Icon', 'role' => 'Role', 'org' => 'Organisation',
    'location' => 'Location', 'start_date' => 'Start (YYYY-MM)',
    'end_date' => 'End (YYYY-MM, blank = present)', 'bullets' => 'Bullets (one per line)',
    'url' => 'URL', 'qualification' => 'Qualification', 'institution' => 'Institution',
    'year' => 'Year', 'result' => 'Result (grade, band, score)', 'notes' => 'Notes',
    'venue' => 'Venue / journal', 'period' => 'Period',
];

const ADMIN_UPLOAD_MIMES = [
    'image/jpeg' => 'jpg', 'image/png' => 'png',
    'image/webp' => 'webp', 'image/gif' => 'gif',
    'application/pdf' => 'pdf',
];
// SVG is deliberately absent: it can carry script, and it would be served from
// your own origin. Convert to PNG before uploading.

const ADMIN_MAX_UPLOAD = 8 * 1024 * 1024; // 8 MB
const ADMIN_MAX_EDGE   = 2000;            // px, longest side after re-encoding

// ─── CSRF token ──────────────────────────────────────────────────────────────
/**
 * Derived from a secret stored in data/.dev, which deploy.sh never copies.
 * A session would need headers, and this file is included after output has
 * already started — so the token is derived, not stored in a session.
 *
 * The token travels in a custom header, which a cross-origin form cannot set.
 */
function admin_token(): string
{
    $secret = is_file(DEV_MARKER) ? trim((string) file_get_contents(DEV_MARKER)) : '';
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        file_put_contents(DEV_MARKER, $secret . "\n");
    }
    return hash('sha256', 'portfolio-admin|' . $secret);
}

function admin_assert_column(string $table, string $column): void
{
    if (!isset(ADMIN_FIELDS[$table][$column])) {
        throw new RuntimeException("Field not editable: {$table}.{$column}");
    }
}

// ─── Writes ──────────────────────────────────────────────────────────────────

function admin_write(string $target, string $value): array
{
    $parts = explode(':', $target);

    if (($parts[0] ?? '') === 'setting' && count($parts) === 2) {
        if (!isset(settings_all()[$parts[1]])) {
            throw new RuntimeException("Unknown setting: {$parts[1]}");
        }
        setting_set($parts[1], $value);
        return ['target' => $target, 'value' => $value];
    }

    if (($parts[0] ?? '') === 'row' && count($parts) === 4) {
        [, $table, $id, $column] = $parts;
        admin_assert_column($table, $column);

        $type = ADMIN_FIELDS[$table][$column];
        $bind = $value;

        if ($type === 'number') {
            $bind = (string) max(0, min(100, (int) $value));
        } elseif ($column === 'media_id') {
            $bind = $value === '' ? null : (int) $value;
        }

        $stmt = db()->prepare("UPDATE {$table} SET {$column} = :v WHERE id = :id");
        $stmt->bindValue(':v', $bind, $bind === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return ['target' => $target, 'value' => (string) $bind];
    }

    throw new RuntimeException("Malformed target: {$target}");
}

/** Rewrites `sort` for a whole table so positions stay 10, 20, 30… */
function admin_resequence(string $table, array $ordered_ids): void
{
    $stmt = db()->prepare("UPDATE {$table} SET sort = :s WHERE id = :id");
    foreach (array_values($ordered_ids) as $index => $id) {
        $stmt->execute([':s' => ($index + 1) * 10, ':id' => (int) $id]);
    }
}

function admin_move(string $table, int $id, string $direction): void
{
    if (!is_collection($table)) {
        throw new RuntimeException("Unknown collection: {$table}");
    }
    $ids = array_map('intval', array_column(rows($table, false), 'id'));
    $pos = array_search($id, $ids, true);
    if ($pos === false) {
        throw new RuntimeException('Row not found');
    }
    $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($swap < 0 || $swap >= count($ids)) {
        return; // already at the end; nothing to do
    }
    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
    admin_resequence($table, $ids);
}

function admin_move_section(string $key, string $direction): void
{
    $keys = array_column(sections_all(), 'key');
    $pos  = array_search($key, $keys, true);
    if ($pos === false) {
        throw new RuntimeException('Section not found');
    }
    $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($swap < 0 || $swap >= count($keys)) {
        return;
    }
    [$keys[$pos], $keys[$swap]] = [$keys[$swap], $keys[$pos]];

    $stmt = db()->prepare('UPDATE sections SET sort = :s WHERE key = :k');
    foreach (array_values($keys) as $index => $section_key) {
        $stmt->execute([':s' => ($index + 1) * 10, ':k' => $section_key]);
    }
}

// ─── Uploads ─────────────────────────────────────────────────────────────────

function admin_slug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'file';
}

/**
 * Re-encodes a raster image through GD, which strips EXIF and anything else
 * appended to the file, and caps the longest edge. Returns false if GD cannot
 * read it — which is itself a useful signal that it is not really an image.
 */
function admin_reencode_image(string $path, string $ext): bool
{
    $image = @imagecreatefromstring((string) file_get_contents($path));
    if ($image === false) {
        return false;
    }

    $width  = imagesx($image);
    $height = imagesy($image);
    $longest = max($width, $height);

    if ($longest > ADMIN_MAX_EDGE) {
        $scale   = ADMIN_MAX_EDGE / $longest;
        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
        if ($resized !== false) {
            $image = $resized;
        }
    }

    imagealphablending($image, false);
    imagesavealpha($image, true);

    // No imagedestroy(): it is a no-op since PHP 8.0 and deprecated in 8.5,
    // where calling it emits a notice that would corrupt the JSON response.
    return match ($ext) {
        'jpg'  => imagejpeg($image, $path, 86),
        'png'  => imagepng($image, $path, 6),
        'webp' => imagewebp($image, $path, 86),
        'gif'  => imagegif($image, $path),
        default => false,
    };
}

function admin_upload(): array
{
    $kind = (string) ($_POST['kind'] ?? 'picture');
    if (!in_array($kind, ['picture', 'certificate'], true)) {
        throw new RuntimeException("Unknown upload kind: {$kind}");
    }

    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed or no file received');
    }
    if (($file['size'] ?? 0) > ADMIN_MAX_UPLOAD) {
        throw new RuntimeException('File is larger than 8 MB');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Not an uploaded file');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);
    if (!isset(ADMIN_UPLOAD_MIMES[$mime])) {
        throw new RuntimeException("Type not allowed: {$mime}");
    }
    $ext = ADMIN_UPLOAD_MIMES[$mime];

    if ($ext === 'pdf' && $kind !== 'certificate') {
        throw new RuntimeException('PDFs belong in the certificates folder');
    }

    $dir      = $kind === 'certificate' ? CERTIFICATES_DIR : PICTURES_DIR;
    $rel_dir  = $kind === 'certificate' ? 'certificates' : 'pictures';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    // Random suffix: the stored name never depends on what was uploaded.
    $base     = admin_slug(pathinfo((string) $file['name'], PATHINFO_FILENAME));
    $filename = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not store the file');
    }
    chmod($dest, 0644);

    if ($ext !== 'pdf' && !admin_reencode_image($dest, $ext)) {
        unlink($dest);
        throw new RuntimeException('That file is not a readable image');
    }

    $rel_path = $rel_dir . '/' . $filename;
    $size     = @getimagesize($dest);

    $stmt = db()->prepare(
        'INSERT INTO media (path, kind, alt, mime, bytes, width, height)
         VALUES (:path, :kind, :alt, :mime, :bytes, :w, :h)'
    );
    $stmt->execute([
        ':path'  => $rel_path,
        ':kind'  => $kind,
        ':alt'   => $base,
        ':mime'  => $mime,
        ':bytes' => filesize($dest) ?: null,
        ':w'     => $size ? $size[0] : null,
        ':h'     => $size ? $size[1] : null,
    ]);

    return [
        'id'   => (int) db()->lastInsertId(),
        'path' => $rel_path,
        'kind' => $kind,
    ];
}

function admin_media_delete(int $id): array
{
    $row = media($id);
    if (!$row) {
        throw new RuntimeException('Media not found');
    }

    // Only ever unlink inside the two media folders, and only after resolving
    // symlinks — a stored path must never be able to point elsewhere.
    $full = realpath(ROOT_DIR . '/' . $row['path']);
    $allowed = array_filter([realpath(PICTURES_DIR), realpath(CERTIFICATES_DIR)]);
    $inside = false;
    foreach ($allowed as $dir) {
        if ($full !== false && str_starts_with($full, $dir . DIRECTORY_SEPARATOR)) {
            $inside = true;
            break;
        }
    }
    if ($inside && is_file($full)) {
        unlink($full);
    }

    db()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    return ['id' => $id];
}

function admin_backup(): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'portfolio-backup-');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the archive');
    }

    // Flush the WAL so the copied .db is complete on its own.
    db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $zip->addFile(DB_PATH, 'data/portfolio.db');

    foreach ([['pictures', PICTURES_DIR], ['certificates', CERTIFICATES_DIR]] as [$label, $dir]) {
        foreach (glob($dir . '/*') ?: [] as $path) {
            if (is_file($path)) {
                $zip->addFile($path, $label . '/' . basename($path));
            }
        }
    }
    $zip->close();

    $name = 'portfolio-backup-' . date('Y-m-d-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

// ─── Router (direct requests only) ───────────────────────────────────────────

function admin_handle(string $action): array
{
    $table = (string) ($_POST['table'] ?? '');
    $id    = (int) ($_POST['id'] ?? 0);

    switch ($action) {
        case 'save':
            return admin_write((string) ($_POST['target'] ?? ''), (string) ($_POST['value'] ?? ''));

        case 'create':
            if (!is_collection($table)) {
                throw new RuntimeException("Unknown collection: {$table}");
            }
            db()->exec("INSERT INTO {$table} DEFAULT VALUES");
            $new_id = (int) db()->lastInsertId();
            $max = (int) db()->query("SELECT COALESCE(MAX(sort), 0) FROM {$table}")->fetchColumn();
            db()->prepare("UPDATE {$table} SET sort = ? WHERE id = ?")->execute([$max + 10, $new_id]);
            return ['id' => $new_id];

        case 'delete':
            if (!is_collection($table)) {
                throw new RuntimeException("Unknown collection: {$table}");
            }
            db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            return ['id' => $id];

        case 'toggle':
            if (!is_collection($table)) {
                throw new RuntimeException("Unknown collection: {$table}");
            }
            db()->prepare("UPDATE {$table} SET visible = 1 - visible WHERE id = ?")->execute([$id]);
            return ['id' => $id];

        case 'move':
            admin_move($table, $id, (string) ($_POST['direction'] ?? 'up'));
            return ['id' => $id];

        case 'section_toggle':
            db()->prepare('UPDATE sections SET enabled = 1 - enabled WHERE key = ?')
                ->execute([(string) ($_POST['key'] ?? '')]);
            return ['key' => (string) ($_POST['key'] ?? '')];

        case 'section_move':
            admin_move_section((string) ($_POST['key'] ?? ''), (string) ($_POST['direction'] ?? 'up'));
            return ['key' => (string) ($_POST['key'] ?? '')];

        case 'upload':
            return admin_upload();

        case 'media_delete':
            return admin_media_delete($id);

        case 'media_alt':
            db()->prepare('UPDATE media SET alt = :a WHERE id = :id')
                ->execute([':a' => (string) ($_POST['value'] ?? ''), ':id' => $id]);
            return ['id' => $id];

        default:
            throw new RuntimeException("Unknown action: {$action}");
    }
}

$admin_action = (string) ($_GET['action'] ?? '');

if (!$admin_included && $admin_action !== '') {
    // Anything PHP prints before the payload would make the response
    // unparseable as JSON. Keep logging it, just never inline it.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    $sent = (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('POST required');
        }
        if (!hash_equals(admin_token(), $sent)) {
            throw new RuntimeException('Bad or missing admin token');
        }

        if ($admin_action === 'backup') {
            admin_backup(); // streams a zip and exits
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => admin_handle($admin_action)]);
    } catch (Throwable $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (!$admin_included) {
    // Direct GET with no action: nothing to show.
    http_response_code(404);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
//  From here down: the UI, rendered only when included by a page.
// ═══════════════════════════════════════════════════════════════════════════

/** Every editable target on the page, with its raw value and input type. */
$admin_values = [];
$admin_types  = [];

foreach (settings_all() as $key => $row) {
    $admin_values['setting:' . $key] = (string) $row['value'];
    $admin_types['setting:' . $key]  = (string) $row['type'];
}
foreach (COLLECTION_TABLES as $table) {
    foreach (rows($table, false) as $row) {
        foreach (ADMIN_FIELDS[$table] as $column => $type) {
            $target = "row:{$table}:{$row['id']}:{$column}";
            $admin_values[$target] = (string) ($row[$column] ?? '');
            $admin_types[$target]  = $type;
        }
    }
}

$admin_json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
                  | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$admin_field_meta = [];
foreach (ADMIN_FIELDS as $table => $columns) {
    foreach ($columns as $column => $type) {
        $admin_field_meta[$table][$column] = [
            'type'  => $type,
            'label' => ADMIN_LABELS[$column] ?? ucfirst(str_replace('_', ' ', $column)),
        ];
    }
}
?>
<style id="admin-styles">
/* Scoped to .adm-* so nothing here can collide with the site's own stylesheet. */
.adm-bar, .adm-panel, .adm-modal, .adm-toast { font: 500 13px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }

.adm-bar {
  position: fixed; right: 16px; bottom: 16px; z-index: 9000;
  display: flex; gap: 4px; padding: 6px;
  background: #14161d; border: 1px solid #2b3040; border-radius: 12px;
  box-shadow: 0 10px 32px rgb(0 0 0 / .45); color: #e6e8ef;
}
.adm-bar button {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 11px; border-radius: 8px; cursor: pointer;
  background: transparent; color: #b9c0d0; border: 1px solid transparent;
  font: inherit; white-space: nowrap;
}
.adm-bar button:hover { background: #1e2230; color: #fff; }
.adm-bar button.is-on { background: #6366f1; color: #fff; }
.adm-dot { width: 7px; height: 7px; border-radius: 50%; background: #f59e0b; }
body.adm-editing .adm-dot { background: #22c55e; }

/* Click-to-edit affordance, only while edit mode is on */
body.adm-editing [data-edit] {
  cursor: text; outline: 1px dashed rgb(99 102 241 / .55);
  outline-offset: 3px; border-radius: 3px;
}
body.adm-editing [data-edit]:hover { outline-style: solid; background: rgb(99 102 241 / .1); }
body.adm-editing [data-edit][contenteditable="true"] {
  outline: 2px solid #6366f1; background: rgb(99 102 241 / .12);
}
body.adm-editing [data-row] { position: relative; outline: 1px dotted rgb(148 163 184 / .4); outline-offset: 6px; }

/* Sits inside the row, not above it: several site cards use overflow:hidden
   and would clip a toolbar positioned outside their box. */
.adm-rowbar {
  position: absolute; top: 6px; right: 6px; z-index: 20;
  display: none; gap: 2px; padding: 3px;
  background: #14161d; border: 1px solid #2b3040; border-radius: 8px;
  box-shadow: 0 6px 18px rgb(0 0 0 / .4);
}
body.adm-editing .adm-rowbar { display: flex; }
.adm-rowbar button {
  width: 26px; height: 24px; padding: 0; cursor: pointer; font: inherit; font-size: 12px;
  background: transparent; color: #b9c0d0; border: 0; border-radius: 5px;
}
.adm-rowbar button:hover { background: #2a3040; color: #fff; }
.adm-rowbar button.adm-danger:hover { background: #b91c1c; color: #fff; }

.adm-add {
  display: none; align-items: center; gap: 6px; margin-top: 14px;
  padding: 9px 14px; cursor: pointer; font: inherit; font-weight: 600;
  background: transparent; color: #6366f1;
  border: 1px dashed #6366f1; border-radius: 9px;
}
body.adm-editing .adm-add { display: inline-flex; }
.adm-add:hover { background: rgb(99 102 241 / .12); }

.adm-panel {
  position: fixed; top: 0; right: 0; bottom: 0; z-index: 9100;
  width: min(430px, 100%); overflow-y: auto; display: none;
  padding: 18px 18px 90px; color: #e6e8ef;
  background: #101219; border-left: 1px solid #2b3040;
  box-shadow: -18px 0 44px rgb(0 0 0 / .4);
}
.adm-panel.is-open { display: block; }
.adm-panel h2 { margin: 0 0 14px; font-size: 15px; letter-spacing: .01em; color: #fff; }
.adm-panel h3 {
  margin: 22px 0 10px; font-size: 11px; text-transform: uppercase;
  letter-spacing: .08em; color: #7c8598;
}
.adm-close {
  position: absolute; top: 14px; right: 14px;
  width: 28px; height: 28px; cursor: pointer; font: inherit; font-size: 16px;
  background: transparent; color: #7c8598; border: 1px solid #2b3040; border-radius: 7px;
}
.adm-close:hover { color: #fff; background: #1e2230; }

.adm-field { margin-bottom: 12px; }
.adm-field label { display: block; margin-bottom: 5px; font-size: 11.5px; color: #8d95a8; }
.adm-field input[type="text"], .adm-field input[type="url"], .adm-field input[type="email"],
.adm-field input[type="number"], .adm-field textarea, .adm-field select {
  width: 100%; padding: 8px 10px; font: inherit;
  background: #191c26; color: #e6e8ef; border: 1px solid #2b3040; border-radius: 7px;
}
.adm-field textarea { min-height: 96px; resize: vertical; line-height: 1.55; }
.adm-field input:focus, .adm-field textarea:focus, .adm-field select:focus {
  outline: 0; border-color: #6366f1; box-shadow: 0 0 0 3px rgb(99 102 241 / .22);
}
.adm-field input[type="color"] {
  width: 44px; height: 32px; padding: 2px; background: #191c26;
  border: 1px solid #2b3040; border-radius: 7px; cursor: pointer; vertical-align: middle;
}
.adm-inline { display: flex; gap: 8px; align-items: center; }
.adm-inline input[type="text"] { flex: 1; }

.adm-btn {
  padding: 8px 13px; cursor: pointer; font: inherit; font-weight: 600;
  background: #6366f1; color: #fff; border: 0; border-radius: 8px;
}
.adm-btn:hover { background: #4f52e0; }
.adm-btn-ghost { background: #191c26; color: #b9c0d0; border: 1px solid #2b3040; }
.adm-btn-ghost:hover { background: #232838; color: #fff; }

.adm-list { list-style: none; margin: 0; padding: 0; }
.adm-list li {
  display: flex; align-items: center; gap: 8px; padding: 8px 10px; margin-bottom: 6px;
  background: #171a24; border: 1px solid #262b3a; border-radius: 8px;
}
.adm-list li span { flex: 1; }
.adm-list li.is-off { opacity: .45; }

.adm-mediagrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.adm-media {
  position: relative; cursor: pointer; overflow: hidden;
  background: #171a24; border: 1px solid #262b3a; border-radius: 8px;
}
.adm-media:hover { border-color: #6366f1; }
.adm-media img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.adm-media-pdf { display: grid; place-items: center; aspect-ratio: 1; font-size: 26px; }
.adm-media-name {
  padding: 5px 6px; font-size: 10px; color: #8d95a8;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.adm-media-del {
  position: absolute; top: 4px; right: 4px; width: 20px; height: 20px;
  cursor: pointer; font-size: 11px; line-height: 1;
  background: rgb(0 0 0 / .65); color: #fff; border: 0; border-radius: 5px;
}
.adm-media-del:hover { background: #b91c1c; }

.adm-modal {
  position: fixed; inset: 0; z-index: 9200; display: none;
  place-items: center; padding: 20px; background: rgb(4 6 12 / .7);
}
.adm-modal.is-open { display: grid; }
.adm-modal-box {
  width: min(560px, 100%); max-height: 84vh; overflow-y: auto;
  padding: 20px; color: #e6e8ef;
  background: #101219; border: 1px solid #2b3040; border-radius: 14px;
  box-shadow: 0 24px 60px rgb(0 0 0 / .5);
}
.adm-modal-box h2 { margin: 0 0 16px; font-size: 15px; color: #fff; }
.adm-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }

.adm-toast {
  position: fixed; left: 50%; bottom: 22px; z-index: 9300;
  padding: 9px 16px; transform: translate(-50%, 14px);
  background: #14161d; color: #e6e8ef;
  border: 1px solid #2b3040; border-radius: 9px;
  box-shadow: 0 10px 30px rgb(0 0 0 / .45);
  opacity: 0; pointer-events: none; transition: opacity .18s ease, transform .18s ease;
}
.adm-toast.is-on { opacity: 1; transform: translate(-50%, 0); }
.adm-toast.is-error { border-color: #b91c1c; color: #fecaca; }

.adm-hint { margin: 0 0 14px; font-size: 11.5px; line-height: 1.55; color: #7c8598; }
.adm-hint code { color: #a5b4fc; }
</style>

<div class="adm-bar" id="adm-bar">
  <button type="button" id="adm-edit-toggle" title="Toggle edit mode (E)">
    <span class="adm-dot"></span> Edit
  </button>
  <button type="button" data-panel="content">Content</button>
  <button type="button" data-panel="sections">Sections</button>
  <button type="button" data-panel="media">Media</button>
  <button type="button" id="adm-backup">Backup</button>
</div>

<!-- Content panel: every setting, grouped, with the widget its `type` implies -->
<aside class="adm-panel" id="adm-panel-content" aria-hidden="true">
  <button class="adm-close" type="button" data-close>&times;</button>
  <h2>Content &amp; theme</h2>
  <p class="adm-hint">Changes save as you type. Everything here lives in <code>data/portfolio.db</code>.</p>
  <?php foreach (settings_by_section() as $section_name => $fields): ?>
    <h3><?= e((string) $section_name) ?></h3>
    <?php foreach ($fields as $field): ?>
      <?php $target = 'setting:' . $field['key']; ?>
      <div class="adm-field">
        <label for="adm-f-<?= e($field['key']) ?>"><?= e($field['label']) ?></label>
        <?php if ($field['type'] === 'textarea'): ?>
          <textarea id="adm-f-<?= e($field['key']) ?>" data-target="<?= e($target) ?>"><?= e($field['value']) ?></textarea>
        <?php elseif ($field['type'] === 'color'): ?>
          <div class="adm-inline">
            <input type="color" value="<?= e($field['value']) ?>" data-target="<?= e($target) ?>" data-sync>
            <input type="text" id="adm-f-<?= e($field['key']) ?>" value="<?= e($field['value']) ?>" data-target="<?= e($target) ?>" data-sync>
          </div>
        <?php elseif ($field['type'] === 'image'): ?>
          <div class="adm-inline">
            <input type="text" id="adm-f-<?= e($field['key']) ?>" value="<?= e($field['value']) ?>"
                   data-target="<?= e($target) ?>" placeholder="pictures/…">
            <button class="adm-btn adm-btn-ghost" type="button"
                    data-pick="<?= e($target) ?>" data-kind="picture">Choose</button>
          </div>
        <?php elseif ($field['type'] === 'select'): ?>
          <select id="adm-f-<?= e($field['key']) ?>" data-target="<?= e($target) ?>">
            <?php foreach (['dark' => 'Dark', 'light' => 'Light'] as $value => $label): ?>
              <option value="<?= e($value) ?>"<?= $field['value'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="<?= $field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text') ?>"
                 id="adm-f-<?= e($field['key']) ?>" value="<?= e($field['value']) ?>" data-target="<?= e($target) ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</aside>

<!-- Sections panel: enable, disable, reorder whole sections -->
<aside class="adm-panel" id="adm-panel-sections" aria-hidden="true">
  <button class="adm-close" type="button" data-close>&times;</button>
  <h2>Sections</h2>
  <p class="adm-hint">Turn sections off or reorder them. Order here is the order on the page.</p>
  <ul class="adm-list">
    <?php foreach (sections_all() as $section): ?>
      <li class="<?= (int) $section['enabled'] ? '' : 'is-off' ?>">
        <span><?= e($section['title'] ?: $section['key']) ?></span>
        <button class="adm-btn adm-btn-ghost" type="button" data-section-move="up"   data-key="<?= e($section['key']) ?>" title="Move up">↑</button>
        <button class="adm-btn adm-btn-ghost" type="button" data-section-move="down" data-key="<?= e($section['key']) ?>" title="Move down">↓</button>
        <button class="adm-btn adm-btn-ghost" type="button" data-section-toggle data-key="<?= e($section['key']) ?>">
          <?= (int) $section['enabled'] ? 'On' : 'Off' ?>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>

<!-- Media panel -->
<aside class="adm-panel" id="adm-panel-media" aria-hidden="true">
  <button class="adm-close" type="button" data-close>&times;</button>
  <h2>Media</h2>
  <p class="adm-hint">
    Pictures go to <code>pictures/</code>, certificates to <code>certificates/</code>.
    Images are re-encoded on upload, which strips metadata. PDFs are allowed for
    certificates only. Max 8&nbsp;MB.
  </p>
  <div class="adm-inline" style="margin-bottom:16px">
    <button class="adm-btn" type="button" data-upload="picture">Upload picture</button>
    <button class="adm-btn adm-btn-ghost" type="button" data-upload="certificate">Upload certificate</button>
  </div>

  <?php foreach (['picture' => 'Pictures', 'certificate' => 'Certificates'] as $kind => $heading): ?>
    <h3><?= e($heading) ?></h3>
    <?php $items = media_list($kind); ?>
    <?php if (!$items): ?>
      <p class="adm-hint">Nothing uploaded yet.</p>
    <?php else: ?>
      <div class="adm-mediagrid">
        <?php foreach ($items as $item): ?>
          <div class="adm-media" data-media-id="<?= (int) $item['id'] ?>" data-media-path="<?= e($item['path']) ?>">
            <?php if (($item['mime'] ?? '') === 'application/pdf'): ?>
              <div class="adm-media-pdf">📄</div>
            <?php else: ?>
              <img src="<?= e($item['path']) ?>" alt="<?= e($item['alt']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="adm-media-name"><?= e(basename((string) $item['path'])) ?></div>
            <button class="adm-media-del" type="button" data-media-del="<?= (int) $item['id'] ?>" title="Delete">&times;</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</aside>

<div class="adm-modal" id="adm-modal" aria-hidden="true"><div class="adm-modal-box" id="adm-modal-box"></div></div>
<div class="adm-toast" id="adm-toast"></div>
<input type="file" id="adm-file" hidden>

<script id="admin-script">
(function () {
  'use strict';

  var TOKEN  = <?= json_encode(admin_token(), $admin_json_flags) ?>;
  var VALUES = <?= json_encode($admin_values, $admin_json_flags) ?>;
  var TYPES  = <?= json_encode($admin_types, $admin_json_flags) ?>;
  var FIELDS = <?= json_encode($admin_field_meta, $admin_json_flags) ?>;
  var MEDIA  = <?= json_encode([
      'picture'     => array_map(static fn(array $m): array => ['id' => (int) $m['id'], 'path' => $m['path'], 'mime' => $m['mime']], media_list('picture')),
      'certificate' => array_map(static fn(array $m): array => ['id' => (int) $m['id'], 'path' => $m['path'], 'mime' => $m['mime']], media_list('certificate')),
  ], $admin_json_flags) ?>;

  /* Stripping a trailing "s" turns education into "educatio" and activities
     into "activitie". Name them explicitly instead. */
  var SINGULAR = {
    projects: 'project', certificates: 'certificate', skills: 'skill',
    experience: 'role', education: 'qualification', publications: 'publication',
    activities: 'activity'
  };
  function singular(table) { return SINGULAR[table] || table; }

  var body    = document.body;
  var toastEl = document.getElementById('adm-toast');
  var modal   = document.getElementById('adm-modal');
  var modalBox= document.getElementById('adm-modal-box');
  var fileEl  = document.getElementById('adm-file');
  var toastTimer;

  function toast(message, isError) {
    toastEl.textContent = message;
    toastEl.classList.toggle('is-error', !!isError);
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2200);
  }

  /* Every write goes through here. The token rides in a custom header, which a
     cross-origin form cannot set. */
  function api(action, fields) {
    var data = new FormData();
    Object.keys(fields || {}).forEach(function (key) { data.append(key, fields[key]); });

    return fetch('admin.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'X-Admin-Token': TOKEN },
      body: data
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.ok) { throw new Error(payload.error || 'Request failed'); }
        return payload.data;
      });
    }).catch(function (error) {
      toast(error.message, true);
      throw error;
    });
  }

  function save(target, value) {
    return api('save', { target: target, value: value }).then(function (data) {
      VALUES[target] = data.value;
      toast('Saved');
      return data;
    });
  }

  /* Panel and modal inputs save on a debounce. Reloading while a debounce is
     still pending would throw away the last keystrokes, so every reload flushes
     first and waits for the writes to land. */
  var debounceTimers = {};
  var pendingValues  = {};

  function flushPending() {
    var writes = Object.keys(pendingValues).map(function (target) {
      clearTimeout(debounceTimers[target]);
      var value = pendingValues[target];
      delete pendingValues[target];
      return save(target, value);
    });
    return Promise.all(writes);
  }

  function reloadSoon() {
    return flushPending()
      .catch(function () { /* the toast has already reported it */ })
      .then(function () { location.reload(); });
  }

  /* ── Edit mode ─────────────────────────────────────────────────────────── */

  var editToggle = document.getElementById('adm-edit-toggle');
  var EDIT_KEY = 'portfolio-admin-edit';

  function setEditMode(on) {
    body.classList.toggle('adm-editing', on);
    editToggle.classList.toggle('is-on', on);
    try { localStorage.setItem(EDIT_KEY, on ? '1' : '0'); } catch (err) { /* ignore */ }
  }

  editToggle.addEventListener('click', function () {
    setEditMode(!body.classList.contains('adm-editing'));
  });

  try { setEditMode(localStorage.getItem(EDIT_KEY) === '1'); } catch (err) { setEditMode(false); }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'e' && (event.metaKey || event.ctrlKey) && event.shiftKey) {
      event.preventDefault();
      setEditMode(!body.classList.contains('adm-editing'));
    }
    if (event.key === 'Escape') { closeModal(); closePanels(); }
  });

  /* ── Click to edit ─────────────────────────────────────────────────────── */

  document.addEventListener('click', function (event) {
    if (!body.classList.contains('adm-editing')) { return; }
    if (event.target.closest('.adm-bar, .adm-panel, .adm-modal, .adm-rowbar, .adm-add')) { return; }

    // Images carry a slot attribute instead of data-edit: clicking one in edit
    // mode opens the picker for whatever field backs it.
    var slot = event.target.closest('[data-image-slot]');
    if (slot) {
      event.preventDefault();
      event.stopPropagation();
      openMediaPicker(slot.getAttribute('data-image-slot'), 'picture');
      return;
    }

    var el = event.target.closest('[data-edit]');
    if (!el) { return; }

    // In edit mode a click means "edit this", never "follow this link".
    event.preventDefault();
    event.stopPropagation();
    beginEdit(el);
  }, true);

  function beginEdit(el) {
    var target = el.getAttribute('data-edit');
    var type   = TYPES[target] || 'text';

    // Anything that is not a single line of plain text gets a proper input.
    if (type !== 'text' && type !== 'url' && type !== 'email' && type !== 'number') {
      openFieldModal(target, type);
      return;
    }
    if (el.isContentEditable) { return; }

    var original = el.textContent;
    el.setAttribute('contenteditable', 'true');
    el.focus();

    var range = document.createRange();
    range.selectNodeContents(el);
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);

    function finish(commit) {
      el.removeAttribute('contenteditable');
      el.removeEventListener('blur', onBlur);
      el.removeEventListener('keydown', onKey);

      var next = el.textContent.trim();
      if (!commit) { el.textContent = original; return; }
      if (next === original.trim()) { return; }

      save(target, next).catch(function () { el.textContent = original; });
    }

    function onBlur() { finish(true); }
    function onKey(event) {
      if (event.key === 'Enter') { event.preventDefault(); el.blur(); }
      if (event.key === 'Escape') { event.preventDefault(); finish(false); }
    }

    el.addEventListener('blur', onBlur);
    el.addEventListener('keydown', onKey);
  }

  /* ── Modals ────────────────────────────────────────────────────────────── */

  function openModal(html) {
    modalBox.innerHTML = html;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    var first = modalBox.querySelector('textarea, input, select');
    if (first) { first.focus(); }
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    modalBox.innerHTML = '';
  }

  modal.addEventListener('click', function (event) {
    if (event.target === modal || event.target.hasAttribute('data-close')) { closeModal(); }
  });

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function inputFor(target, type, value, id) {
    var safe = escapeHtml(value);
    if (type === 'textarea') {
      return '<textarea id="' + id + '" data-target="' + escapeHtml(target) + '">' + safe + '</textarea>';
    }
    if (type === 'color') {
      return '<div class="adm-inline">'
           + '<input type="color" value="' + safe + '" data-target="' + escapeHtml(target) + '" data-sync>'
           + '<input type="text" id="' + id + '" value="' + safe + '" data-target="' + escapeHtml(target) + '" data-sync></div>';
    }
    if (type === 'image' || type === 'file') {
      var kind = type === 'file' ? 'certificate' : 'picture';
      return '<div class="adm-inline">'
           + '<input type="text" id="' + id + '" value="' + safe + '" data-target="' + escapeHtml(target) + '">'
           + '<button class="adm-btn adm-btn-ghost" type="button" data-pick="' + escapeHtml(target)
           + '" data-kind="' + kind + '">Choose</button></div>';
    }
    if (type === 'number') {
      return '<input type="number" min="0" max="100" id="' + id + '" value="' + safe
           + '" data-target="' + escapeHtml(target) + '">';
    }
    return '<input type="text" id="' + id + '" value="' + safe + '" data-target="' + escapeHtml(target) + '">';
  }

  function openFieldModal(target, type) {
    var value = VALUES[target] || '';
    openModal(
      '<h2>Edit</h2><div class="adm-field">' + inputFor(target, type, value, 'adm-modal-input') + '</div>'
      + '<div class="adm-modal-actions">'
      + '<button class="adm-btn adm-btn-ghost" type="button" data-close>Cancel</button>'
      + '<button class="adm-btn" type="button" data-modal-save="' + escapeHtml(target) + '">Save</button></div>'
    );
  }

  /* Full editor for one collection row: every column, not only what the page
     happens to render. */
  function openRowModal(table, id) {
    var meta = FIELDS[table] || {};
    var html = '<h2>Edit ' + escapeHtml(singular(table)) + '</h2>';

    Object.keys(meta).forEach(function (column, index) {
      var target = 'row:' + table + ':' + id + ':' + column;
      var value  = VALUES[target] || '';
      var inputId = 'adm-row-' + index;
      html += '<div class="adm-field"><label for="' + inputId + '">' + escapeHtml(meta[column].label) + '</label>'
            + inputFor(target, meta[column].type, value, inputId) + '</div>';
    });

    html += '<div class="adm-modal-actions">'
          + '<button class="adm-btn adm-btn-ghost" type="button" data-close>Close</button>'
          + '<button class="adm-btn" type="button" data-modal-save-all>Save all</button></div>';
    openModal(html);
  }

  /* ── Saving from panels and modals ─────────────────────────────────────── */

  function scheduleSave(target, value, immediate) {
    clearTimeout(debounceTimers[target]);
    if (immediate) {
      delete pendingValues[target];
      save(target, value);
      return;
    }
    pendingValues[target] = value;
    debounceTimers[target] = setTimeout(function () {
      delete pendingValues[target];
      save(target, value);
    }, 550);
  }

  document.addEventListener('input', function (event) {
    var el = event.target;
    var target = el.getAttribute && el.getAttribute('data-target');
    if (!target || !el.closest('.adm-panel, .adm-modal')) { return; }

    // Colour pickers come in pairs; keep the swatch and the hex field in step.
    if (el.hasAttribute('data-sync')) {
      var partners = el.parentNode.querySelectorAll('[data-target="' + target + '"][data-sync]');
      Array.prototype.forEach.call(partners, function (partner) {
        if (partner !== el) { partner.value = el.value; }
      });
    }
    scheduleSave(target, el.value, false);
  });

  document.addEventListener('change', function (event) {
    var el = event.target;
    var target = el.getAttribute && el.getAttribute('data-target');
    if (target && el.tagName === 'SELECT' && el.closest('.adm-panel, .adm-modal')) {
      scheduleSave(target, el.value, true);
    }
  });

  /* ── Global click routing ──────────────────────────────────────────────── */

  document.addEventListener('click', function (event) {
    var el = event.target;

    var panelBtn = el.closest('[data-panel]');
    if (panelBtn) { openPanel(panelBtn.getAttribute('data-panel')); return; }

    if (el.closest('[data-close]')) { closeModal(); closePanels(); return; }

    var modalSave = el.closest('[data-modal-save]');
    if (modalSave) {
      var target = modalSave.getAttribute('data-modal-save');
      var input  = modalBox.querySelector('[data-target="' + target + '"]');
      save(target, input ? input.value : '').then(reloadSoon);
      return;
    }

    if (el.closest('[data-modal-save-all]')) {
      var inputs = modalBox.querySelectorAll('[data-target]');
      var seen = {};
      var writes = [];
      Array.prototype.forEach.call(inputs, function (input) {
        var key = input.getAttribute('data-target');
        if (seen[key]) { return; }
        seen[key] = true;
        writes.push(save(key, input.value));
      });
      Promise.all(writes).then(reloadSoon).catch(function () { /* toast already shown */ });
      return;
    }

    var pick = el.closest('[data-pick]');
    if (pick) { openMediaPicker(pick.getAttribute('data-pick'), pick.getAttribute('data-kind')); return; }

    var rowBtn = el.closest('[data-row-action]');
    if (rowBtn) {
      var action = rowBtn.getAttribute('data-row-action');
      var table  = rowBtn.getAttribute('data-table');
      var rowId  = rowBtn.getAttribute('data-id');

      if (action === 'edit')   { openRowModal(table, rowId); return; }
      if (action === 'delete') {
        if (!confirm('Delete this entry? This cannot be undone.')) { return; }
        api('delete', { table: table, id: rowId }).then(reloadSoon);
        return;
      }
      if (action === 'toggle') { api('toggle', { table: table, id: rowId }).then(reloadSoon); return; }
      if (action === 'up' || action === 'down') {
        api('move', { table: table, id: rowId, direction: action }).then(reloadSoon);
        return;
      }
    }

    var addBtn = el.closest('[data-add]');
    if (addBtn) { api('create', { table: addBtn.getAttribute('data-add') }).then(reloadSoon); return; }

    var sectionToggle = el.closest('[data-section-toggle]');
    if (sectionToggle) {
      api('section_toggle', { key: sectionToggle.getAttribute('data-key') }).then(reloadSoon);
      return;
    }

    var sectionMove = el.closest('[data-section-move]');
    if (sectionMove) {
      api('section_move', {
        key: sectionMove.getAttribute('data-key'),
        direction: sectionMove.getAttribute('data-section-move')
      }).then(reloadSoon);
      return;
    }

    var uploadBtn = el.closest('[data-upload]');
    if (uploadBtn) { startUpload(uploadBtn.getAttribute('data-upload')); return; }

    var mediaDel = el.closest('[data-media-del]');
    if (mediaDel) {
      event.stopPropagation();
      if (!confirm('Delete this file? Anything using it will lose its image.')) { return; }
      api('media_delete', { id: mediaDel.getAttribute('data-media-del') }).then(reloadSoon);
      return;
    }
  });

  document.getElementById('adm-backup').addEventListener('click', function () {
    toast('Building backup…');
    fetch('admin.php?action=backup', { method: 'POST', headers: { 'X-Admin-Token': TOKEN } })
      .then(function (response) {
        if (!response.ok) { throw new Error('Backup failed'); }
        return response.blob();
      })
      .then(function (blob) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'portfolio-backup.zip';
        link.click();
        URL.revokeObjectURL(url);
        toast('Backup downloaded');
      })
      .catch(function (error) { toast(error.message, true); });
  });

  /* ── Panels ────────────────────────────────────────────────────────────── */

  function closePanels() {
    Array.prototype.forEach.call(document.querySelectorAll('.adm-panel'), function (panel) {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
    });
  }

  function openPanel(name) {
    var panel = document.getElementById('adm-panel-' + name);
    if (!panel) { return; }
    var isOpen = panel.classList.contains('is-open');
    closePanels();
    if (!isOpen) {
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
    }
  }

  /* ── Uploads ───────────────────────────────────────────────────────────── */

  var pendingUpload = null;

  function startUpload(kind, onDone) {
    pendingUpload = { kind: kind, onDone: onDone };
    fileEl.value = '';
    fileEl.accept = kind === 'certificate' ? 'image/*,application/pdf' : 'image/*';
    fileEl.click();
  }

  fileEl.addEventListener('change', function () {
    if (!pendingUpload || !fileEl.files.length) { return; }
    var job = pendingUpload;
    pendingUpload = null;

    var data = new FormData();
    data.append('file', fileEl.files[0]);
    data.append('kind', job.kind);
    toast('Uploading…');

    fetch('admin.php?action=upload', {
      method: 'POST', headers: { 'X-Admin-Token': TOKEN }, body: data
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.ok) { throw new Error(payload.error || 'Upload failed'); }
        return payload.data;
      });
    }).then(function (uploaded) {
      toast('Uploaded');
      if (job.onDone) { job.onDone(uploaded); } else { reloadSoon(); }
    }).catch(function (error) { toast(error.message, true); });
  });

  /* ── Media picker ──────────────────────────────────────────────────────── */

  /* One delegated handler, registered once below. Registering a fresh listener
     each time the picker opened would stack them on the surviving modal element
     and fire a single pick several times over. */
  var pickerState = null;

  /* Columns named media_id store the row id; everything else stores the path.
     That is the only difference between the two kinds of image field. */
  function applyPick(target, item) {
    var value = /:media_id$/.test(target) ? String(item.id) : item.path;

    var openInput = modalBox.querySelector('[data-target="' + target + '"]')
                 || document.querySelector('.adm-panel [data-target="' + target + '"]');
    if (openInput) { openInput.value = value; }

    return save(target, value);
  }

  function openMediaPicker(target, kind) {
    var items = MEDIA[kind] || [];
    pickerState = { target: target, items: items };

    var html = '<h2>Choose ' + (kind === 'certificate' ? 'a certificate' : 'a picture') + '</h2>';

    html += '<div class="adm-modal-actions" style="justify-content:flex-start;margin:0 0 14px">'
          + '<button class="adm-btn" type="button" data-picker-upload="' + kind + '">Upload new</button>'
          + '<button class="adm-btn adm-btn-ghost" type="button" data-picker-clear>Clear</button></div>';

    if (!items.length) {
      html += '<p class="adm-hint">Nothing uploaded yet.</p>';
    } else {
      html += '<div class="adm-mediagrid">';
      items.forEach(function (item) {
        var thumb = item.mime === 'application/pdf'
          ? '<div class="adm-media-pdf">📄</div>'
          : '<img src="' + escapeHtml(item.path) + '" alt="">';
        html += '<div class="adm-media" data-picker-item="' + item.id + '">' + thumb
              + '<div class="adm-media-name">' + escapeHtml(item.path.split('/').pop()) + '</div></div>';
      });
      html += '</div>';
    }

    html += '<div class="adm-modal-actions">'
          + '<button class="adm-btn adm-btn-ghost" type="button" data-close>Cancel</button></div>';
    openModal(html);
  }

  function finishPick(target, item) {
    pickerState = null;
    applyPick(target, item).then(function () {
      closeModal();
      reloadSoon();
    }).catch(function () { /* the toast has already reported it */ });
  }

  modalBox.addEventListener('click', function (event) {
    if (!pickerState) { return; }
    var state = pickerState;

    var choice = event.target.closest('[data-picker-item]');
    if (choice) {
      var id = parseInt(choice.getAttribute('data-picker-item'), 10);
      var item = state.items.filter(function (candidate) { return candidate.id === id; })[0];
      if (item) { finishPick(state.target, item); }
      return;
    }

    if (event.target.closest('[data-picker-clear]')) {
      finishPick(state.target, { id: '', path: '' });
      return;
    }

    var uploadBtn = event.target.closest('[data-picker-upload]');
    if (uploadBtn) {
      pickerState = null;
      startUpload(uploadBtn.getAttribute('data-picker-upload'), function (uploaded) {
        finishPick(state.target, uploaded);
      });
    }
  });

  /* ── Inject row controls and add buttons ───────────────────────────────── */

  Array.prototype.forEach.call(document.querySelectorAll('[data-row]'), function (row) {
    var parts = row.getAttribute('data-row').split(':');
    var table = parts[0];
    var id    = parts[1];

    var bar = document.createElement('div');
    bar.className = 'adm-rowbar';
    bar.innerHTML =
        btn('edit',   '✎', 'Edit all fields', table, id)
      + btn('up',     '↑', 'Move up',         table, id)
      + btn('down',   '↓', 'Move down',       table, id)
      + btn('toggle', '👁', 'Show / hide',     table, id)
      + btn('delete', '🗑', 'Delete',          table, id, 'adm-danger');
    row.appendChild(bar);
  });

  function btn(action, glyph, title, table, id, extra) {
    return '<button type="button" class="' + (extra || '') + '" data-row-action="' + action
         + '" data-table="' + table + '" data-id="' + id + '" title="' + title + '">' + glyph + '</button>';
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-collection]'), function (container) {
    var table = container.getAttribute('data-collection');
    var add = document.createElement('button');
    add.type = 'button';
    add.className = 'adm-add';
    add.setAttribute('data-add', table);
    add.textContent = '+ Add ' + singular(table);
    container.parentNode.insertBefore(add, container.nextSibling);
  });
})();
</script>
