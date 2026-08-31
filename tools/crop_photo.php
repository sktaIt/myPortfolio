<?php
/**
 * Crops a square portrait for the site from any source photo, including HEIC.
 *
 *   php tools/crop_photo.php "pictures/my photo.HEIC" pictures/portrait.jpg \
 *        --cx=0.36 --cy=0.50 --size=0.65 --px=900
 *
 * --cx / --cy   centre of the crop, as a fraction of the image (0-1)
 * --size        side of the square, as a fraction of the image WIDTH
 * --px          output size in pixels (square)
 * --preview     also write a half-size copy next to the output for checking
 *
 * Orientation is normalised first. Phone photos carry an EXIF orientation flag
 * rather than storing rotated pixels — this one is 4032x3024 on disk but
 * displays as 3024x4032. Cropping without baking the rotation in means every
 * coordinate is against the wrong axis.
 *
 * HEIC is converted with sips (macOS). Everything else goes straight to GD.
 */
declare(strict_types=1);

$argvRest = array_slice($argv, 1);
$positional = array_values(array_filter($argvRest, static fn(string $a): bool => !str_starts_with($a, '--')));

if (count($positional) < 2) {
    fwrite(STDERR, "usage: php tools/crop_photo.php <source> <output.jpg> [--cx=] [--cy=] [--size=] [--px=]\n");
    exit(1);
}

[$source, $output] = $positional;

/** Reads --name=value from argv. */
function opt(array $argv, string $name, float $default): float
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return (float) substr($arg, strlen($name) + 3);
        }
    }
    return $default;
}

$cx      = opt($argvRest, 'cx', 0.5);
$cy      = opt($argvRest, 'cy', 0.5);
$sizeF   = opt($argvRest, 'size', 0.8);
$px      = (int) opt($argvRest, 'px', 900);
$preview = in_array('--preview', $argvRest, true);

if (!is_file($source)) {
    fwrite(STDERR, "source not found: {$source}\n");
    exit(1);
}

// ── Load, converting HEIC via sips if needed ────────────────────────────────
$ext  = strtolower(pathinfo($source, PATHINFO_EXTENSION));
$temp = null;

if (in_array($ext, ['heic', 'heif'], true)) {
    if (trim((string) shell_exec('command -v sips 2>/dev/null')) === '') {
        fwrite(STDERR, "HEIC needs sips (macOS). Convert to JPEG first.\n");
        exit(1);
    }
    $temp = tempnam(sys_get_temp_dir(), 'crop-') . '.jpg';
    exec(sprintf('sips -s format jpeg %s --out %s 2>/dev/null',
        escapeshellarg($source), escapeshellarg($temp)), $out, $status);
    if ($status !== 0 || !is_file($temp)) {
        fwrite(STDERR, "sips could not read {$source}\n");
        exit(1);
    }
    $load = $temp;
} else {
    $load = $source;
}

$image = @imagecreatefromstring((string) file_get_contents($load));
if ($image === false) {
    fwrite(STDERR, "could not decode {$source}\n");
    exit(1);
}

// ── Normalise orientation ───────────────────────────────────────────────────
$exif = @exif_read_data($load);
$orientation = (int) ($exif['Orientation'] ?? 1);

// imagerotate turns counter-clockwise, so the angles here are negated.
$rotation = match ($orientation) {
    3       => 180,
    6       => -90,
    8       => 90,
    default => 0,
};
if ($rotation !== 0) {
    $rotated = imagerotate($image, $rotation, 0);
    if ($rotated !== false) {
        $image = $rotated;
    }
}
if (in_array($orientation, [2, 5, 7, 4], true)) {
    imageflip($image, IMG_FLIP_HORIZONTAL); // mirrored variants
}

$width  = imagesx($image);
$height = imagesy($image);

// ── Crop ────────────────────────────────────────────────────────────────────
$side = (int) round($sizeF * $width);
$side = max(16, min($side, $width, $height));

$left = (int) round($cx * $width - $side / 2);
$top  = (int) round($cy * $height - $side / 2);

// Keep the square inside the frame rather than padding it with background.
$left = max(0, min($left, $width - $side));
$top  = max(0, min($top, $height - $side));

$cropped = imagecrop($image, ['x' => $left, 'y' => $top, 'width' => $side, 'height' => $side]);
if ($cropped === false) {
    fwrite(STDERR, "crop failed\n");
    exit(1);
}

$final = imagescale($cropped, $px, $px, IMG_BICUBIC_FIXED);
if ($final === false) {
    $final = $cropped;
}

imageinterlace($final, true); // progressive: renders early over a slow connection
if (!imagejpeg($final, $output, 88)) {
    fwrite(STDERR, "could not write {$output}\n");
    exit(1);
}

if ($preview) {
    $half = imagescale($final, (int) round($px / 2));
    if ($half !== false) {
        imagejpeg($half, preg_replace('/\.jpg$/', '', $output) . '-preview.jpg', 85);
    }
}

if ($temp !== null && is_file($temp)) {
    unlink($temp);
}

printf(
    "source %dx%d (orientation %d)\ncrop   %dx%d at x=%d y=%d\noutput %s  %dx%d  %.0fKB\n",
    $width, $height, $orientation,
    $side, $side, $left, $top,
    $output, $px, $px, filesize($output) / 1024
);
