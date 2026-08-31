<?php
declare(strict_types=1);
$grouped = skills_by_category();
?>
<section id="skills" class="section">
  <div class="wrap">
    <h2 class="section-title"<?= edit('setting:skills.heading') ?>><?= e(setting('skills.heading')) ?></h2>
    <?php if (setting('skills.intro') !== ''): ?>
      <p class="section-intro"<?= edit('setting:skills.intro') ?>><?= e(setting('skills.intro')) ?></p>
    <?php endif; ?>

    <div class="skills-groups"<?= collection_attr('skills') ?>>
      <?php foreach ($grouped as $category => $skills): ?>
        <div class="skills-group">
          <h3 class="skills-category"><?= e((string) $category) ?></h3>
          <ul class="skills-list">
            <?php foreach ($skills as $skill): ?>
              <li class="skill"<?= row_attr('skills', (int) $skill['id']) ?>>
                <div class="skill-head">
                  <span class="skill-name"<?= edit('row:skills:' . (int) $skill['id'] . ':name') ?>><?= e($skill['name']) ?></span>
                  <?php if ((int) $skill['level'] > 0): ?>
                    <span class="skill-level"><?= (int) $skill['level'] ?>%</span>
                  <?php endif; ?>
                </div>
                <?php if ((int) $skill['level'] > 0): ?>
                  <div class="skill-bar" role="img"
                       aria-label="<?= e($skill['name']) ?>: <?= (int) $skill['level'] ?> percent">
                    <span style="width: <?= max(0, min(100, (int) $skill['level'])) ?>%"></span>
                  </div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>

      <?php if (!$grouped): ?>
        <p class="empty">No skills yet.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
