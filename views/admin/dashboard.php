<?php if ($mailErr = setting('mail_error')): ?>
  <a class="a-alert" href="/admin/settings/email"><b>Download emails aren't sending.</b> <?= e($mailErr) ?><br>People still get their file on the page. Tap to fix.</a>
<?php endif; ?>
<div class="a-stats a-stats-4 a-stats-link">
  <a class="a-stat" href="/admin/stats/visitors"><b><?= $visitorsWeek ?></b><span>visitors this week</span></a>
  <a class="a-stat" href="/admin/stats/files"><b><?= $downloadsWeek ?></b><span>downloads this week</span></a>
  <a class="a-stat" href="/admin/emails?days=7"><b><?= $emailsWeek ?></b><span>new emails this week<?= $emailsTotal ? ' · ' . $emailsTotal . ' total' : '' ?></span></a>
  <a class="a-stat" href="/admin/stats/taps"><b><?= $tapsWeek ?></b><span>link taps this week</span></a>
</div>

<section class="a-card a-form">
  <h2>Quick add</h2>
  <div class="a-grid2">
    <a class="a-btn" href="/admin/buttons/new">+ Button</a>
    <a class="a-btn" href="/admin/files/new">+ File</a>
    <a class="a-btn a-btn-outline a-span2" href="/admin/files/import">Update from MakerWorld</a>
  </div>
</section>

<section class="a-card a-form">
  <h2>Top files (30 days)</h2>
  <?php if (!$top): ?><p class="a-hint">No downloads yet.</p><?php endif; ?>
  <ol class="a-top-files">
    <?php foreach ($top as $t): ?><li><a href="/admin/files/<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a> <small><?= (int) $t['n'] ?></small></li><?php endforeach; ?>
  </ol>
</section>


