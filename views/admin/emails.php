<a class="a-btn a-btn-block" href="/admin/emails.csv">Download all as CSV</a>
<p class="a-hint"><?= count($subs) ?> emails. ✓ = said yes to "Send me new files and builds". Only email people with ✓.</p>
<ul class="a-list">
<?php foreach ($subs as $s): ?>
  <li class="a-row" data-delete="/admin/emails/<?= (int) $s['id'] ?>/delete" data-confirm-delete="Delete <?= e($s['email']) ?> and their download history?">
    <span class="a-row-main"><span class="a-row-text">
      <b><?= $s['opted_in'] ? '✓ ' : '' ?><?= e($s['email']) ?></b>
      <small><?= e(date('M j, Y', strtotime($s['created_at'] . ' UTC'))) ?> · <?= (int) $s['n'] ?> download<?= $s['n'] == 1 ? '' : 's' ?></small>
    </span></span>
    <form method="post" action="/admin/emails/<?= (int) $s['id'] ?>/delete" data-confirm="Delete <?= e($s['email']) ?> and their download history?">
      <?= csrf_field() ?>
      <button class="a-icon-btn" aria-label="Delete <?= e($s['email']) ?>">✕</button>
    </form>
  </li>
<?php endforeach; ?>
</ul>
<?php if (!$subs): ?><p class="a-hint">No emails yet. They come in when people download free files.</p><?php endif; ?>
