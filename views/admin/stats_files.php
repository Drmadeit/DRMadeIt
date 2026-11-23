<a class="a-back" href="/admin/stats">‹ Stats</a>
<nav class="a-chips" aria-label="Time period">
  <?php foreach ([7 => '7 days', 30 => '30 days', 0 => 'All time'] as $d => $label): ?>
    <a href="/admin/stats/<?= $which ?><?= $d === 7 ? '' : '?days=' . $d ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="a-stats a-stats-4">
  <div class="a-stat"><b><?= $totals['views'] ?></b><span>file page views</span></div>
  <div class="a-stat"><b><?= $totals['asked'] ?></b><span>asked by email</span></div>
  <div class="a-stat"><b><?= $totals['got'] ?></b><span>downloaded</span></div>
  <div class="a-stat"><b><?= $totals['mw'] ?></b><span>went to MakerWorld</span></div>
</div>
<?php if (!$rows): ?><p class="a-hint">Nothing yet in this time.</p><?php else: ?>
<ul class="a-list">
  <?php foreach ($rows as $r): ?>
    <li class="a-row"><a class="a-row-main" href="/admin/files/<?= (int) $r['id'] ?>"><span class="a-row-text">
      <b><?= e($r['title']) ?></b>
      <small><?= $r['views'] ?> view<?= $r['views'] === 1 ? '' : 's' ?> · <?= $r['asked'] ?> asked · <?= $r['got'] ?> downloaded<?= $r['mw'] ? ' · ' . $r['mw'] . ' to MakerWorld' : '' ?><?= $r['status'] === 'published' ? '' : ' · draft' ?></small>
    </span></a></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="a-hint">Views = people who opened the file's page. Asked = entered their email. Downloaded = opened the link from the email. MakerWorld = tapped "Get it on MakerWorld".</p>
