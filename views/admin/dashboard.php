<?php if ($mailErr = setting('mail_error')): ?>
  <a class="a-alert" href="/admin/settings#email"><b>Download emails aren't sending.</b> <?= e($mailErr) ?><br>People still get their file on the page. Tap to fix in Settings → Email.</a>
<?php endif; ?>
<div class="a-stats">
  <div class="a-stat"><b><?= $downloadsWeek ?></b><span>downloads this week</span></div>
  <div class="a-stat"><b><?= $emailsWeek ?></b><span>new emails this week</span></div>
  <div class="a-stat"><b><?= $emailsTotal ?></b><span>emails total</span></div>
</div>

<section class="a-card a-form">
  <h2>Quick add</h2>
  <div class="a-grid2">
    <a class="a-btn" href="/admin/links/new">+ Link</a>
    <a class="a-btn" href="/admin/files/new">+ File</a>
    <a class="a-btn a-btn-outline" href="/admin/tools">+ Tool</a>
    <a class="a-btn a-btn-outline" href="/admin/tools/new">+ Tool list</a>
  </div>
</section>

<section class="a-card a-form">
  <h2>Top files (30 days)</h2>
  <?php if (!$top): ?><p class="a-hint">No downloads yet.</p><?php endif; ?>
  <ol class="a-top-files">
    <?php foreach ($top as $t): ?><li><a href="/admin/files/<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a> <small><?= (int) $t['n'] ?></small></li><?php endforeach; ?>
  </ol>
</section>

<nav class="a-card a-menu" aria-label="More">
  <a href="/admin/emails"><?= icon('mail', 22) ?> Emails <span class="a-chev"><?= icon('chevron', 18) ?></span></a>
  <a href="/admin/short"><?= icon('link', 22) ?> Short links <span class="a-chev"><?= icon('chevron', 18) ?></span></a>
  <a href="/admin/videos"><?= icon('youtube', 22) ?> Videos <span class="a-chev"><?= icon('chevron', 18) ?></span></a>
  <a href="/admin/settings"><?= icon('sliders', 22) ?> Settings &amp; backup <span class="a-chev"><?= icon('chevron', 18) ?></span></a>
</nav>
