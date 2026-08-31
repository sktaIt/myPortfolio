<?php
declare(strict_types=1);
$entries = rows('experience');
?>
<section id="experience" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:experience.heading') ?>><?= e(setting('experience.heading')) ?></h2>
    <?php if (setting('experience.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:experience.intro') ?>><?= e(setting('experience.intro')) ?></p>
    <?php endif; ?>

    <ol class="timeline"<?= collection_attr('experience') ?>>
      <?php foreach ($entries as $entry): ?>
        <?php
        $id      = (int) $entry['id'];
        $bullets = lines($entry['bullets']);
        ?>
        <li class="timeline-item"<?= row_attr('experience', $id) ?>>
          <div class="timeline-marker" aria-hidden="true"></div>
          <div class="timeline-body">
            <p class="timeline-dates"><?= e(date_range((string) $entry['start_date'], $entry['end_date'] !== '' ? (string) $entry['end_date'] : null)) ?></p>
            <h3 class="timeline-role"<?= edit('row:experience:' . $id . ':role') ?>><?= e($entry['role']) ?></h3>
            <p class="timeline-org">
              <span<?= edit('row:experience:' . $id . ':org') ?>><?= e($entry['org']) ?></span>
              <?php if ($entry['location'] !== ''): ?>
                <span class="<?= $entry['org'] !== '' ? 'timeline-location' : '' ?>"<?= edit('row:experience:' . $id . ':location') ?>><?= e($entry['location']) ?></span>
              <?php endif; ?>
            </p>

            <?php if ($bullets): ?>
              <ul class="timeline-bullets"<?= edit('row:experience:' . $id . ':bullets') ?>>
                <?php foreach ($bullets as $bullet): ?>
                  <li><?= e($bullet) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>

      <?php if (!$entries): ?>
        <p class="empty">No experience entries yet.</p>
      <?php endif; ?>
    </ol>
  </div>
</section>
