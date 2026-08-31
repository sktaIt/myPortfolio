<?php
/**
 * Imports whatever is sitting in certificates/ into the database.
 *
 *   php tools/import_certificates.php          # dry run, shows what it would do
 *   php tools/import_certificates.php --write   # actually insert
 *
 * Safe to re-run: files already recorded in `media` are skipped, so drop new
 * certificates into the folder and run it again.
 *
 * Titles are guessed from filenames and are meant to be corrected in the admin
 * panel afterwards — a filename is rarely the name you want on the page.
 *
 * This is a dev tool. deploy.sh does not copy tools/.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

// Name fragments to strip out of filenames when building a title. Filenames
// usually repeat the holder's name, which is noise on the page.
$strip_names = ['ahmed', 'rafat', 'abdelaziz', 'soliman', 'elkilany'];

$months = [
    'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
    'may' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08',
    'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
];

/** Pulls an issue date out of the filename if one is recognisable. */
function guess_date(string $stem, array $months): string
{
    // 2023-01 …
    if (preg_match('/\b(20\d{2})[-_](\d{2})\b/', $stem, $m)) {
        return $m[1] . '-' . $m[2];
    }
    // Jan 2021 / January 2021
    if (preg_match('/\b([a-z]{3})[a-z]*\.?\s+(20\d{2})\b/i', $stem, $m)
        && isset($months[strtolower($m[1])])) {
        return $m[2] . '-' . $months[strtolower($m[1])];
    }
    // Jan22 / May22
    if (preg_match('/\b([a-z]{3})(\d{2})\b/i', $stem, $m) && isset($months[strtolower($m[1])])) {
        return '20' . $m[2] . '-' . $months[strtolower($m[1])];
    }
    return '';
}

/** Turns a filename into something presentable. */
function guess_title(string $stem, array $strip_names, array $months): string
{
    $text = str_replace(['_', '-'], ' ', $stem);

    // Drop ddmmyyyy stamps and bare years, then the holder's name.
    $text = preg_replace('/\b\d{8}\b/', ' ', $text) ?? $text;
    $text = preg_replace('/\b(20\d{2})[-\s](\d{2})\b/', ' ', $text) ?? $text;
    $text = preg_replace('/\b[a-z]{3}\d{2}\b/i', ' ', $text) ?? $text;

    $words = preg_split('/\s+/', $text) ?: [];
    $kept  = [];
    foreach ($words as $word) {
        $clean = strtolower(trim($word, " \t,.-"));
        if ($clean === '' || in_array($clean, $strip_names, true)) {
            continue;
        }
        if (preg_match('/^\d+$/', $clean) && strlen($clean) >= 4) {
            continue; // stray year
        }
        if (isset($months[substr($clean, 0, 3)]) && strlen($clean) <= 9) {
            continue; // month word already captured as the date
        }
        $kept[] = trim($word, " \t,.-");
    }

    $title = trim(implode(' ', $kept));
    if ($title === '') {
        $title = 'Certificate';
    }

    // Normalise a few conventions, then title-case what is left.
    $title = preg_replace('/\bcertif\b/i', 'Certificate', $title) ?? $title;
    $title = preg_replace('/\bcert\b/i', 'Certificate', $title) ?? $title;
    $title = ucwords(strtolower($title));
    $title = preg_replace('/\bIcee\b/', 'ICEE', $title) ?? $title;

    if (!preg_match('/certificate/i', $title)) {
        $title .= ' Certificate';
    }
    return $title;
}

/** Issuer, only where the filename actually names one. */
function guess_issuer(string $stem): string
{
    $known = ['huawei' => 'Huawei', 'icee' => 'ICEE', 'coursera' => 'Coursera', 'udemy' => 'Udemy'];
    foreach ($known as $needle => $label) {
        if (stripos($stem, $needle) !== false) {
            return $label;
        }
    }
    return '';
}

$write = in_array('--write', $argv, true);
$mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
          'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

$existing = array_flip(
    db()->query("SELECT path FROM media WHERE kind = 'certificate'")->fetchAll(PDO::FETCH_COLUMN)
);
$existing_files = array_flip(
    db()->query('SELECT file_path FROM certificates')->fetchAll(PDO::FETCH_COLUMN)
);

$files = glob(CERTIFICATES_DIR . '/*') ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$sort = (int) db()->query('SELECT COALESCE(MAX(sort), 0) FROM certificates')->fetchColumn();
$imported = 0;

foreach ($files as $path) {
    // Leading dot or underscore means "not a certificate": deploy.sh skips these too.
    if (!is_file($path) || in_array(basename($path)[0], ['.', '_'], true)) {
        continue;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!isset($mimes[$ext])) {
        printf("  skip  %s (unsupported type)\n", basename($path));
        continue;
    }

    $rel = 'certificates/' . basename($path);
    if (isset($existing[$rel]) || isset($existing_files[$rel])) {
        printf("  have  %s\n", basename($path));
        continue;
    }

    $stem   = pathinfo($path, PATHINFO_FILENAME);
    $title  = guess_title($stem, $strip_names, $months);
    $issuer = guess_issuer($stem);
    $date   = guess_date($stem, $months);
    $sort  += 10;

    printf("  add   %-46s -> %-34s %-8s %s\n", basename($path), $title, $date ?: '—', $issuer ?: '');

    if (!$write) {
        continue;
    }

    $size    = $ext === 'pdf' ? false : @getimagesize($path);
    $stmt = db()->prepare(
        'INSERT INTO media (path, kind, alt, mime, bytes, width, height)
         VALUES (:path, :kind, :alt, :mime, :bytes, :w, :h)'
    );
    $stmt->execute([
        ':path'  => $rel,
        ':kind'  => 'certificate',
        ':alt'   => $title,
        ':mime'  => $mimes[$ext],
        ':bytes' => filesize($path) ?: null,
        ':w'     => $size ? $size[0] : null,
        ':h'     => $size ? $size[1] : null,
    ]);
    $media_id = (int) db()->lastInsertId();

    // Images double as their own preview; a PDF has no thumbnail so the card
    // falls back to the badge in partials/certificates.php.
    db()->prepare(
        'INSERT INTO certificates (title, issuer, issue_date, file_path, media_id, sort, visible)
         VALUES (:title, :issuer, :issued, :file, :media, :sort, 1)'
    )->execute([
        ':title'  => $title,
        ':issuer' => $issuer,
        ':issued' => $date,
        ':file'   => $rel,
        ':media'  => $ext === 'pdf' ? null : $media_id,
        ':sort'   => $sort,
    ]);

    $imported++;
}

echo $write
    ? "\nImported {$imported} certificate(s). Review the titles in the admin panel.\n"
    : "\nDry run. Re-run with --write to import.\n";
