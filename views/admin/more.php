<?php
$row = fn (string $href, string $ic, string $label, string $note = '') =>
    '<a href="' . e($href) . '">' . icon($ic, 22) . '<span class="a-menu-text">' . e($label)
    . ($note !== '' ? '<small>' . e($note) . '</small>' : '') . '</span><span class="a-chev">' . icon('chevron', 18) . '</span></a>';
$mailErr = setting('mail_error') !== '';
?>
<p class="a-group">Content</p>
<nav class="a-card a-menu" aria-label="Content">
  <?= $row('/admin/stats', 'chart', 'Stats', 'Visitors, taps, downloads') ?>
  <?= $row('/admin/videos', 'youtube', 'Videos', 'YouTube channel, pins') ?>
  <?= $row('/admin/short', 'link', 'Short links', 'drmadeit.com/anything') ?>
  <?= $row('/admin/emails', 'mail', 'Emails', 'People who downloaded files') ?>
</nav>

<p class="a-group">Settings</p>
<nav class="a-card a-menu" aria-label="Settings">
  <?php foreach (SETTINGS_SECTIONS as $key => [$label, $ic, $note]): ?>
    <?= $row('/admin/settings/' . $key, $ic, $label, $key === 'email' && $mailErr ? '⚠ Last email failed: tap to fix' : $note) ?>
  <?php endforeach; ?>
</nav>

<nav class="a-card a-menu" aria-label="Site">
  <?= $row('/', 'home', 'View site') ?>
</nav>
<form method="post" action="/admin/logout" class="a-danger">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-outline">Log out</button>
</form>
