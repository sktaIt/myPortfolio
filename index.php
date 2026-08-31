<?php
/**
 * The public page. Renders entirely from data/portfolio.db.
 *
 * The last line of <body> is the only reference to admin.php in the whole
 * shipped site. With the file deleted, is_file() is false and nothing about the
 * admin — markup, script, or endpoint — exists on the server.
 */
declare(strict_types=1);

require __DIR__ . '/config.php';

$mode     = setting('theme.default_mode', 'dark') === 'light' ? 'light' : 'dark';
$sections = sections_enabled();
$name     = setting('site.title', 'Portfolio');
$tagline  = setting('site.tagline');
?>
<!doctype html>
<html lang="en" data-theme="<?= e($mode) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($name) ?><?= $tagline !== '' ? ' — ' . e($tagline) : '' ?></title>
<meta name="description" content="<?= e(setting('seo.description')) ?>">
<meta property="og:title" content="<?= e($name) ?>">
<meta property="og:description" content="<?= e(setting('seo.description')) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="' . css_value(setting('theme.accent', '#6366f1')) . '"/><text x="16" y="22" font-family="system-ui" font-size="16" font-weight="700" fill="#fff" text-anchor="middle">' . e(initials($name)) . '</text></svg>') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
<style><?= theme_css() ?></style>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="top">
  <div class="wrap header-inner">
    <a class="brand" href="#top">
      <span class="brand-mark" aria-hidden="true"><?= e(initials($name)) ?></span>
      <span<?= edit('setting:site.title') ?>><?= e($name) ?></span>
    </a>

    <nav class="site-nav" aria-label="Sections">
      <?php foreach ($sections as $section): ?>
        <?php if ($section['key'] === 'hero') { continue; } ?>
        <a href="#<?= e($section['key']) ?>"><?= e($section['title']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" id="theme-toggle" type="button"
              aria-label="Toggle colour scheme" title="Toggle colour scheme">
        <span class="icon-sun" aria-hidden="true">☀</span>
        <span class="icon-moon" aria-hidden="true">☾</span>
      </button>
      <button class="icon-btn nav-toggle" id="nav-toggle" type="button"
              aria-label="Toggle navigation" aria-expanded="false">☰</button>
    </div>
  </div>
</header>

<main id="main">
<?php
foreach ($sections as $section) {
    // basename() so a section key can never walk out of partials/
    $partial = ROOT_DIR . '/partials/' . basename((string) $section['key']) . '.php';
    if (is_file($partial)) {
        include $partial;
    }
}
?>
</main>

<footer class="site-footer">
  <div class="wrap footer-inner">
    <p<?= edit('setting:footer.text') ?>><?= e(setting('footer.text')) ?></p>
    <p class="footer-meta">&copy; <?= date('Y') ?> <?= e($name) ?></p>
  </div>
</footer>

<script src="<?= asset('assets/js/site.js') ?>"></script>
<?php
// ── The mechanic ─────────────────────────────────────────────────────────────
// Delete admin.php to ship. Content lives in the database, so it stays.
if (is_file(ADMIN_FILE)) {
    require ADMIN_FILE;
}
?>
</body>
</html>
