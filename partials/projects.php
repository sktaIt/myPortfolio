<?php
declare(strict_types=1);
$projects = rows('projects');
?>
<section id="projects" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:projects.heading') ?>><?= e(setting('projects.heading')) ?></h2>
    <?php if (setting('projects.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:projects.intro') ?>><?= e(setting('projects.intro')) ?></p>
    <?php endif; ?>

    <div class="card-grid"<?= collection_attr('projects') ?>>
      <?php foreach ($projects as $project): ?>
        <?php
        $id    = (int) $project['id'];
        $image = img_src(isset($project['media_id']) ? (int) $project['media_id'] : null);
        $tags  = csv($project['tags']);
        ?>
        <article class="card"<?= row_attr('projects', $id) ?>>
          <div class="card-media" data-image-slot="row:projects:<?= $id ?>:media_id">
            <?php if ($image !== null): ?>
              <img src="<?= $image ?>" alt="<?= e($project['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="card-media-placeholder" aria-hidden="true"></div>
            <?php endif; ?>
          </div>

          <div class="card-body">
            <h3 class="card-title"<?= edit('row:projects:' . $id . ':title') ?>><?= e($project['title']) ?></h3>
            <p class="card-text"<?= edit('row:projects:' . $id . ':summary') ?>><?= e($project['summary']) ?></p>

            <?php if ($project['description'] !== ''): ?>
              <details class="card-more">
                <summary>More</summary>
                <div class="prose"<?= edit('row:projects:' . $id . ':description') ?>>
                  <?= paragraphs($project['description']) ?>
                </div>
              </details>
            <?php endif; ?>

            <?php if ($tags): ?>
              <ul class="tags">
                <?php foreach ($tags as $tag): ?>
                  <li class="tag"><?= e($tag) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if ($project['repo_url'] !== '' || $project['live_url'] !== ''): ?>
              <div class="card-links">
                <?php if ($project['live_url'] !== ''): ?>
                  <a class="link-arrow" href="<?= e($project['live_url']) ?>" target="_blank" rel="noopener noreferrer">Live<span aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($project['repo_url'] !== ''): ?>
                  <a class="link-arrow" href="<?= e($project['repo_url']) ?>" target="_blank" rel="noopener noreferrer">Code<span aria-hidden="true">→</span></a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (!$projects): ?>
        <p class="empty">No projects yet.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
