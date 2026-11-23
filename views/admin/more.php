<?php
$row = fn (string $href, string $ic, string $label, string $note = '') =>
    '<a href="' . e($href) . '">' . icon($ic, 22) . '<span class="a-menu-text">' . e($label)
    . ($note !== '' ? '<small>' . e($note) . '</small>' : '') . '</span><span class="a-chev">' . icon('chevron', 18) . '</span></a>';
$mailErr = setting('mail_error') !== '';
?>
<nav class="a-card a-menu" aria-label="Settings">
  <?php foreach (SETTINGS_SECTIONS as $key => [$label, $ic, $note]): ?>
    <?= $row('/admin/settings/' . $key, $ic, $label, $key === 'email' && $mailErr ? '⚠ Last email failed: tap to fix' : $note) ?>
  <?php endforeach; ?>
</nav>

<div class="a-card a-form a-install">
  <h2>Put the admin on your phone</h2>
  <p class="a-hint">iPhone: in Safari tap <b>Share</b> (square with the arrow) → <b>Add to Home Screen</b>. Android: Chrome menu ⋮ → <b>Add to Home screen</b>. It opens like an app. Log in once there and it stays logged in as long as you use it at least once a month.</p>
</div>

<nav class="a-card a-menu" aria-label="Site">
  <?= $row('/', 'home', 'View site') ?>
</nav>
<form method="post" action="/admin/logout" class="a-danger">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-outline">Log out</button>
</form>
