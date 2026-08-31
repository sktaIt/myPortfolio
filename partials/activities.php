<?php
declare(strict_types=1);
$entries = rows('activities');
?>
<section id="activities" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:activities.heading') ?>><?= e(setting('activities.heading')) ?></h2>
    <?php if (setting('activities.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:activities.intro') ?>><?= e(setting('activities.intro')) ?></p>
    <?php endif; ?>

    <div class="act-grid"<?= collection_attr('activities') ?>>
      <?php foreach ($entries as $entry): ?>
        <?php
        $id      = (int) $entry['id'];
        $bullets = lines($entry['bullets']);
        ?>
        <article class="act"<?= row_attr('activities', $id) ?>>
          <h3 class="act-role"<?= edit('row:activities:' . $id . ':role') ?>><?= e($entry['role']) ?></h3>

          <p class="act-meta">
            <?php if ($entry['org'] !== ''): ?>
              <span<?= edit('row:activities:' . $id . ':org') ?>><?= e($entry['org']) ?></span>
            <?php endif; ?>
            <?php if ($entry['period'] !== ''): ?>
              <span class="<?= $entry['org'] !== '' ? 'act-period' : '' ?>"<?= edit('row:activities:' . $id . ':period') ?>><?= e($entry['period']) ?></span>
            <?php endif; ?>
          </p>

          <?php if ($bullets): ?>
            <ul class="act-bullets"<?= edit('row:activities:' . $id . ':bullets') ?>>
              <?php foreach ($bullets as $bullet): ?>
                <li><?= e($bullet) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($entry['url'] !== ''): ?>
            <a class="link-arrow" href="<?= e($entry['url']) ?>" target="_blank" rel="noopener noreferrer">More<span aria-hidden="true">→</span></a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <?php if (!$entries): ?>
        <p class="empty">No activities yet.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
