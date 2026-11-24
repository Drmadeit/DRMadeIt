<?php
$row = fn (string $href, string $ic, string $label, string $note = '') =>
    '<a href="' . e($href) . '">' . icon($ic, 22) . '<span class="a-menu-text">' . e($label)
    . ($note !== '' ? '<small>' . e($note) . '</small>' : '') . '</span><span class="a-chev">' . icon('chevron', 18) . '</span></a>';
?>
<nav class="a-card a-menu" aria-label="Content">
  <?= $row('/admin/videos', 'youtube', 'Videos', 'YouTube channel, pins, home page videos') ?>
  <?= $row('/admin/emails', 'mail', 'Emails', 'People who downloaded files') ?>
  <?= $row('/admin/email-test', 'mail', 'Test emails', 'Send any email to yourself first') ?>
</nav>
