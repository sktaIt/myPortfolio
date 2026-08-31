<?php
declare(strict_types=1);
$entries = rows('education');
?>
<section id="education" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:education.heading') ?>><?= e(setting('education.heading')) ?></h2>
    <?php if (setting('education.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:education.intro') ?>><?= e(setting('education.intro')) ?></p>
    <?php endif; ?>

    <div class="edu-list"<?= collection_attr('education') ?>>
      <?php foreach ($entries as $entry): ?>
        <?php $id = (int) $entry['id']; ?>
        <article class="edu"<?= row_attr('education', $id) ?>>
          <p class="edu-year"<?= edit('row:education:' . $id . ':year') ?>><?= e($entry['year']) ?></p>

          <div class="edu-body">
            <h3 class="edu-qual"<?= edit('row:education:' . $id . ':qualification') ?>><?= e($entry['qualification']) ?></h3>

            <?php if ($entry['institution'] !== '' || $entry['location'] !== ''): ?>
              <p class="edu-inst">
                <?php if ($entry['institution'] !== ''): ?>
                  <span<?= edit('row:education:' . $id . ':institution') ?>><?= e($entry['institution']) ?></span>
                <?php endif; ?>
                <?php if ($entry['location'] !== ''): ?>
                  <?php // the separator belongs to the location only when something precedes it ?>
                  <span class="<?= $entry['institution'] !== '' ? 'edu-loc' : '' ?>"<?= edit('row:education:' . $id . ':location') ?>><?= e($entry['location']) ?></span>
                <?php endif; ?>
              </p>
            <?php endif; ?>

            <?php if ($entry['result'] !== ''): ?>
              <p class="edu-result"<?= edit('row:education:' . $id . ':result') ?>><?= e($entry['result']) ?></p>
            <?php endif; ?>

            <?php if ($entry['notes'] !== ''): ?>
              <p class="edu-notes"<?= edit('row:education:' . $id . ':notes') ?>><?= e($entry['notes']) ?></p>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (!$entries): ?>
        <p class="empty">No education entries yet.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
