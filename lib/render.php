<?php
/**
 * Presentation helpers used by index.php and the partials.
 *
 * edit()/row_attr()/collection_attr() emit inert data attributes. They are
 * always rendered, including on the live site, where they are a few dead bytes
 * that nothing reads. That is what lets admin.php attach its controls without
 * a single admin branch anywhere in the templates.
 */
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Marks an element as editable: edit('setting:hero.title') */
function edit(string $target): string
{
    return ' data-edit="' . e($target) . '"';
}

/** Marks a collection row: row_attr('projects', 12) */
function row_attr(string $table, int $id): string
{
    return ' data-row="' . e($table . ':' . $id) . '"';
}

/** Marks a container that rows can be added to / sorted within. */
function collection_attr(string $table): string
{
    return ' data-collection="' . e($table) . '"';
}

/** Cache-busted URL for a local asset. */
function asset(string $relative): string
{
    $path = ROOT_DIR . '/' . ltrim($relative, '/');
    $version = is_file($path) ? (string) filemtime($path) : '0';
    return e($relative) . '?v=' . $version;
}

/**
 * Strips anything that could break out of a CSS declaration. These values come
 * from the local admin, not the internet, but a stray brace would silently
 * wreck the stylesheet and be miserable to debug.
 */
function css_value(string $value): string
{
    return trim(preg_replace('/[^A-Za-z0-9#%,\.\s\-\(\)\'"]/', '', $value) ?? '');
}

/** Builds the :root custom properties from every theme.* setting. */
function theme_css(): string
{
    $skip = ['default_mode'];
    $declarations = [];

    foreach (settings_all() as $key => $row) {
        if (strncmp($key, 'theme.', 6) !== 0) {
            continue;
        }
        $name = substr($key, 6);
        if (in_array($name, $skip, true)) {
            continue;
        }
        $value = css_value((string) $row['value']);
        if ($value === '') {
            continue;
        }
        $declarations[] = '--' . str_replace('_', '-', $name) . ':' . $value;
    }

    return $declarations ? ':root{' . implode(';', $declarations) . '}' : '';
}

/** Splits a textarea value into trimmed, non-empty lines. */
function lines(?string $text): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
}

/** Splits a comma-separated field (tags) into trimmed values. */
function csv(?string $text): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $text))));
}

/** Renders a plain-text block as paragraphs, escaping as it goes. */
function paragraphs(?string $text): string
{
    $blocks = preg_split('/\R{2,}/', trim((string) $text)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        if (trim($block) === '') {
            continue;
        }
        $html .= '<p>' . nl2br(e(trim($block))) . '</p>';
    }
    return $html;
}

/** '2024-03' or '2024-03-01' -> 'Mar 2024'. Returns '' for empty input. */
function fmt_month(?string $iso): string
{
    if (!$iso || !preg_match('/^(\d{4})-(\d{2})/', $iso, $m)) {
        return $iso ? e($iso) : '';
    }
    $timestamp = mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]);
    return $timestamp === false ? e($iso) : date('M Y', $timestamp);
}

/** Formats a start/end pair, treating an empty end date as ongoing. */
function date_range(?string $start, ?string $end): string
{
    $from = fmt_month($start);
    $to   = $end ? fmt_month($end) : 'Present';
    if ($from === '') {
        return $to === 'Present' ? '' : $to;
    }
    return $from . ' — ' . $to;
}

/** True when a certificate has expired relative to today. */
function is_expired(?string $expiry): bool
{
    if (!$expiry) {
        return false;
    }
    $ts = strtotime($expiry);
    return $ts !== false && $ts < time();
}

/** Best available image URL for a media id, or null. */
function img_src(?int $media_id): ?string
{
    $path = media_path($media_id);
    return $path ? e($path) : null;
}

/** Initials for the avatar placeholder when no picture is set. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out !== '' ? $out : '?';
}
