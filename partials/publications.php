<?php
declare(strict_types=1);
$papers = rows('publications');
?>
<section id="publications" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:publications.heading') ?>><?= e(setting('publications.heading')) ?></h2>
    <?php if (setting('publications.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:publications.intro') ?>><?= e(setting('publications.intro')) ?></p>
    <?php endif; ?>

    <ol class="pub-list"<?= collection_attr('publications') ?>>
      <?php foreach ($papers as $paper): ?>
        <?php
        $id   = (int) $paper['id'];
        $meta = array_filter([(string) $paper['venue'], (string) $paper['year']]);
        ?>
        <li class="pub"<?= row_attr('publications', $id) ?>>
          <h3 class="pub-title"<?= edit('row:publications:' . $id . ':title') ?>><?= e($paper['title']) ?></h3>

          <?php if ($meta): ?>
            <p class="pub-meta"><?= e(implode(' · ', $meta)) ?></p>
          <?php endif; ?>

          <?php if ($paper['summary'] !== ''): ?>
            <p class="pub-summary"<?= edit('row:publications:' . $id . ':summary') ?>><?= e($paper['summary']) ?></p>
          <?php endif; ?>

          <?php if ($paper['url'] !== ''): ?>
            <a class="link-arrow" href="<?= e($paper['url']) ?>" target="_blank" rel="noopener noreferrer">Read the paper<span aria-hidden="true">→</span></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>

      <?php if (!$papers): ?>
        <p class="empty">No publications yet.</p>
      <?php endif; ?>
    </ol>
  </div>
</section>
