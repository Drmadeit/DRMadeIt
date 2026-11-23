<nav class="a-chips" aria-label="Time period">
  <?php foreach ([7 => '7 days', 30 => '30 days', 0 => 'All time'] as $d => $label): ?>
    <a href="/admin/stats/<?= $which ?><?= $d === 7 ? '' : '?days=' . $d ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="a-stats a-stats-4">
  <div class="a-stat"><b><?= $people ?></b><span>visitors</span></div>
  <div class="a-stat"><b><?= $views ?></b><span>page views</span></div>
  <div class="a-stat"><b><?= $people ? number_format($views / $people, 1) : 0 ?></b><span>pages each</span></div>
  <div class="a-stat"><b><?= count($daysList) ?></b><span>days with visits</span></div>
</div>
<section class="a-card a-form">
  <h2>Day by day</h2>
  <?php if (!$daysList): ?><p class="a-hint">No visits in this time.</p><?php else: ?>
  <table class="a-table">
    <thead><tr><th>Day</th><th>Visitors</th><th>Views</th><th>Taps</th></tr></thead>
    <tbody>
      <?php foreach ($daysList as $d): ?><tr><td><?= e(date('D M j', strtotime($d['day']))) ?></td><td><?= (int) $d['people'] ?></td><td><?= (int) $d['views'] ?></td><td><?= (int) $d['taps'] ?></td></tr><?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php if ($pages): ?>
<section class="a-card a-form">
  <h2>What they looked at</h2>
  <ol class="a-rank">
    <?php foreach ($pages as $p): ?><li><span class="a-rank-text"><b><?= e($p['label']) ?></b><?= $p['sub'] !== $p['label'] ? '<small>' . e($p['sub']) . '</small>' : '' ?></span><span class="a-rank-n"><?= $p['n'] ?></span></li><?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>
<p class="a-hint">A visitor is one phone or computer per day. Bots and you are left out.</p>
