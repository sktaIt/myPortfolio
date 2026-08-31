<?php
declare(strict_types=1);
$about_image = setting('about.image');
?>
<section id="about" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:about.heading') ?>><?= e(setting('about.heading')) ?></h2>

    <div class="about-grid<?= $about_image === '' ? ' about-grid-single' : '' ?>">
      <div class="prose"<?= edit('setting:about.body') ?>>
        <?= paragraphs(setting('about.body')) ?>
      </div>

      <?php if ($about_image !== ''): ?>
        <div class="about-media" data-image-slot="setting:about.image">
          <img src="<?= e($about_image) ?>" alt="<?= e(setting('site.title')) ?>" loading="lazy">
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
