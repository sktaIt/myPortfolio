<?php
declare(strict_types=1);

$email = setting('contact.email');
$links = array_filter([
    'GitHub'   => setting('contact.github'),
    'LinkedIn' => setting('contact.linkedin'),
    'X'        => setting('contact.x'),
]);
$cv    = setting('contact.cv_url');
$phone = setting('contact.phone');
?>
<section id="contact" class="section section-contact">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:contact.heading') ?>><?= e(setting('contact.heading')) ?></h2>
    <?php if (setting('contact.body') !== ''): ?>
      <p class="section-intro"<?= edit('setting:contact.body') ?>><?= e(setting('contact.body')) ?></p>
    <?php endif; ?>

    <?php if ($email !== ''): ?>
      <a class="btn btn-primary btn-lg" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
    <?php endif; ?>

    <?php if ($phone !== ''): ?>
      <p class="contact-phone">
        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>"><?= e($phone) ?></a>
      </p>
    <?php endif; ?>

    <?php if ($links || $cv !== ''): ?>
      <ul class="social">
        <?php foreach ($links as $label => $url): ?>
          <li><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer me"><?= e($label) ?></a></li>
        <?php endforeach; ?>
        <?php if ($cv !== ''): ?>
          <li><a href="<?= e($cv) ?>" target="_blank" rel="noopener noreferrer">Download CV</a></li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
