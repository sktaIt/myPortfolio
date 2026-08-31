<?php
declare(strict_types=1);
$certificates = rows('certificates');
?>
<section id="certificates" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:certificates.heading') ?>><?= e(setting('certificates.heading')) ?></h2>
    <?php if (setting('certificates.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:certificates.intro') ?>><?= e(setting('certificates.intro')) ?></p>
    <?php endif; ?>

    <div class="cert-grid"<?= collection_attr('certificates') ?>>
      <?php foreach ($certificates as $cert): ?>
        <?php
        $id      = (int) $cert['id'];
        $preview = img_src(isset($cert['media_id']) ? (int) $cert['media_id'] : null);
        $file    = (string) $cert['file_path'];
        $link    = $cert['credential_url'] !== '' ? (string) $cert['credential_url'] : $file;
        $expired = is_expired($cert['expiry_date'] !== '' ? (string) $cert['expiry_date'] : null);
        ?>
        <article class="cert<?= $expired ? ' cert-expired' : '' ?>"<?= row_attr('certificates', $id) ?>>
          <div class="cert-preview" data-image-slot="row:certificates:<?= $id ?>:media_id">
            <?php if ($preview !== null): ?>
              <img src="<?= $preview ?>" alt="<?= e($cert['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="cert-badge" aria-hidden="true">🎓</div>
            <?php endif; ?>
          </div>

          <div class="cert-body">
            <h3 class="cert-title"<?= edit('row:certificates:' . $id . ':title') ?>><?= e($cert['title']) ?></h3>
            <p class="cert-issuer"<?= edit('row:certificates:' . $id . ':issuer') ?>><?= e($cert['issuer']) ?></p>

            <p class="cert-meta">
              <?php if ($cert['issue_date'] !== ''): ?>
                <span><?= fmt_month((string) $cert['issue_date']) ?></span>
              <?php endif; ?>
              <?php if ($cert['expiry_date'] !== ''): ?>
                <span class="cert-expiry"><?= $expired ? 'Expired' : 'Expires' ?> <?= fmt_month((string) $cert['expiry_date']) ?></span>
              <?php endif; ?>
            </p>

            <?php if ($cert['credential_id'] !== ''): ?>
              <p class="cert-credential">ID <span<?= edit('row:certificates:' . $id . ':credential_id') ?>><?= e($cert['credential_id']) ?></span></p>
            <?php endif; ?>

            <?php if ($link !== ''): ?>
              <a class="link-arrow" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">
                <?= $cert['credential_url'] !== '' ? 'Verify' : 'View certificate' ?><span aria-hidden="true">→</span>
              </a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (!$certificates): ?>
        <p class="empty">No certificates yet.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
