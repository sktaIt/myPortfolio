<?php
declare(strict_types=1);
$hero_image = setting('hero.image');
$name       = setting('site.title', 'Portfolio');
?>
<section id="hero" class="section hero">
  <div class="wrap hero-inner">
    <div class="hero-copy">
      <p class="eyebrow"<?= edit('setting:hero.eyebrow') ?>><?= e(setting('hero.eyebrow')) ?></p>
      <h1 class="hero-title"<?= edit('setting:hero.title') ?>><?= e(setting('hero.title')) ?></h1>
      <p class="lead"<?= edit('setting:hero.subtitle') ?>><?= e(setting('hero.subtitle')) ?></p>

      <div class="hero-actions">
        <?php if (setting('hero.cta_label') !== ''): ?>
          <a class="btn btn-primary" href="<?= e(setting('hero.cta_url', '#')) ?>"<?= edit('setting:hero.cta_label') ?>>
            <?= e(setting('hero.cta_label')) ?>
          </a>
        <?php endif; ?>
        <?php if (setting('hero.cta2_label') !== ''): ?>
          <a class="btn btn-ghost" href="<?= e(setting('hero.cta2_url', '#')) ?>"<?= edit('setting:hero.cta2_label') ?>>
            <?= e(setting('hero.cta2_label')) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="hero-media" data-image-slot="setting:hero.image">
      <?php if ($hero_image !== ''): ?>
        <img src="<?= e($hero_image) ?>" alt="<?= e($name) ?>" width="420" height="420" loading="eager">
      <?php else: ?>
        <div class="avatar-placeholder" aria-hidden="true"><?= e(initials($name)) ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>
