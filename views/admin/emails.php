<nav class="a-chips" aria-label="Time period">
  <?php foreach ([7 => '7 days', 30 => '30 days', 0 => 'All'] as $d => $label): ?>
    <a href="/admin/emails<?= $d ? '?days=' . $d : '' ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<p class="a-hint"><?= count($subs) ?> email<?= count($subs) === 1 ? '' : 's' ?><?= $days ? " in the last $days days" : '' ?>. <b>Updates</b> = said yes to "Send me new files and builds"; only email those people. ✓ after a file = they downloaded it.</p>
<ul class="a-list">
<?php foreach ($subs as $s): ?>
  <li class="a-row" data-delete="/admin/emails/<?= (int) $s['id'] ?>/delete" data-confirm-delete="Delete <?= e($s['email']) ?> and their download history?">
    <span class="a-row-main"><span class="a-row-text">
      <b><?= e($s['email']) ?></b>
      <small><?= e(date('M j, Y', strtotime($s['created_at'] . ' UTC'))) ?><?= $s['opted_in'] ? ' · Updates' : '' ?> · <?= (int) $s['n'] ?> request<?= $s['n'] == 1 ? '' : 's' ?></small>
      <?php if ($s['files']): ?><small class="a-long"><?= e($s['files']) ?></small><?php endif; ?>
    </span></span>
    <form method="post" action="/admin/emails/<?= (int) $s['id'] ?>/delete" data-confirm="Delete <?= e($s['email']) ?> and their download history?">
      <?= csrf_field() ?>
      <button class="a-icon-btn" aria-label="Delete <?= e($s['email']) ?>">✕</button>
    </form>
  </li>
<?php endforeach; ?>
</ul>
<?php if (!$subs): ?><p class="a-hint"><?= $days ? 'No new emails in this time.' : 'No emails yet. They come in when people download free files.' ?></p><?php endif; ?>
<a class="a-btn a-btn-outline" href="/admin/emails.csv">Download all as CSV</a>
