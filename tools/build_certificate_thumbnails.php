<?php
/**
 * Builds a preview image for every file in certificates/ and wires it to the
 * matching certificate row.
 *
 *   php tools/build_certificate_thumbnails.php          # dry run
 *   php tools/build_certificate_thumbnails.php --write
 *
 * Why: the page shows a small preview per certificate. Without this, PDFs fall
 * back to a generic badge, and image certificates get served at full size — one
 * of them is 3 MB, which is a lot to download for a 64px thumbnail.
 *
 * `file_path` keeps pointing at the original, so the download is still the real
 * document at full quality. Only the preview is downscaled.
 *
 * PDF previews need poppler (`brew install poppler`). Without it, PDFs are
 * skipped and keep the badge fallback — the site still works.
 *
 * Safe to re-run: existing thumbnails are left alone unless --force is passed.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

const THUMB_DIR   = PICTURES_DIR . '/thumbs';
const THUMB_REL   = 'pictures/thumbs';
const THUMB_WIDTH = 700;

$write = in_array('--write', $argv, true);
$force = in_array('--force', $argv, true);

$has_pdftoppm = trim((string) shell_exec('command -v pdftoppm 2>/dev/null')) !== '';
if (!$has_pdftoppm) {
    fwrite(STDERR, "note: pdftoppm not found — PDF previews will be skipped.\n");
}

if ($write && !is_dir(THUMB_DIR)) {
    mkdir(THUMB_DIR, 0775, true);
}

/** Downscales a raster image into $dest as JPEG. */
function thumb_from_image(string $source, string $dest): bool
{
    $image = @imagecreatefromstring((string) file_get_contents($source));
    if ($image === false) {
        return false;
    }
    if (imagesx($image) > THUMB_WIDTH) {
        $scaled = imagescale($image, THUMB_WIDTH);
        if ($scaled !== false) {
            $image = $scaled;
        }
    }
    // Flatten onto white: a transparent PNG would go black as a JPEG.
    $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

    return imagejpeg($canvas, $dest, 82);
}

/** Renders page 1 of a PDF into $dest as JPEG. */
function thumb_from_pdf(string $source, string $dest): bool
{
    $prefix = preg_replace('/\.jpg$/', '', $dest);
    $cmd = sprintf(
        'pdftoppm -jpeg -jpegopt quality=82 -singlefile -f 1 -l 1 -scale-to %d %s %s 2>/dev/null',
        THUMB_WIDTH,
        escapeshellarg($source),
        escapeshellarg((string) $prefix)
    );
    exec($cmd, $output, $status);
    return $status === 0 && is_file($dest);
}

$files = glob(CERTIFICATES_DIR . '/*') ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$built = 0;
$linked = 0;

foreach ($files as $source) {
    $name = basename($source);
    // Leading dot or underscore means "not a certificate": deploy.sh skips these too.
    if (!is_file($source) || $name[0] === '.' || $name[0] === '_') {
        continue;
    }

    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        continue;
    }
    if ($ext === 'pdf' && !$has_pdftoppm) {
        printf("  skip   %-52s (no pdftoppm)\n", $name);
        continue;
    }

    $stem      = pathinfo($source, PATHINFO_FILENAME);
    $thumb_rel = THUMB_REL . '/' . $stem . '.jpg';
    $thumb_abs = THUMB_DIR . '/' . $stem . '.jpg';
    $cert_rel  = 'certificates/' . $name;

    $exists = is_file($thumb_abs);
    printf("  %-6s %-52s -> %s\n", $exists && !$force ? 'have' : 'build', $name, $thumb_rel);

    if (!$write) {
        continue;
    }

    if (!$exists || $force) {
        $ok = $ext === 'pdf'
            ? thumb_from_pdf($source, $thumb_abs)
            : thumb_from_image($source, $thumb_abs);

        if (!$ok) {
            fwrite(STDERR, "  FAILED to build preview for {$name}\n");
            continue;
        }
        $built++;
    }

    // Register the thumbnail, then point the certificate row at it.
    $size = @getimagesize($thumb_abs);
    db()->prepare(
        'INSERT INTO media (path, kind, alt, mime, bytes, width, height)
         VALUES (:path, :kind, :alt, :mime, :bytes, :w, :h)
         ON CONFLICT(path) DO UPDATE SET
            bytes = excluded.bytes, width = excluded.width, height = excluded.height'
    )->execute([
        ':path'  => $thumb_rel,
        ':kind'  => 'picture',
        ':alt'   => $stem,
        ':mime'  => 'image/jpeg',
        ':bytes' => filesize($thumb_abs) ?: null,
        ':w'     => $size ? $size[0] : null,
        ':h'     => $size ? $size[1] : null,
    ]);

    $media_id = (int) db()->query(
        'SELECT id FROM media WHERE path = ' . db()->quote($thumb_rel)
    )->fetchColumn();

    $stmt = db()->prepare('UPDATE certificates SET media_id = :m WHERE file_path = :f');
    $stmt->execute([':m' => $media_id, ':f' => $cert_rel]);
    $linked += $stmt->rowCount();
}

echo $write
    ? "\nBuilt {$built} preview(s), linked {$linked} certificate row(s).\n"
    : "\nDry run. Re-run with --write to build.\n";
