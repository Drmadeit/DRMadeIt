<?php
$periods = [7 => '7 days', 30 => '30 days', 0 => 'All time'];
$max = max([1, ...array_column($series, 'people')]);
$list = function (string $heading, array $rows, string $unit, string $empty) {
    echo '<section class="a-card a-form"><h2>' . e($heading) . '</h2>';
    if (!$rows) {
        echo '<p class="a-hint">' . e($empty) . '</p></section>';
        return;
    }
    $top = max(1, $rows[0]['n'] ?? 1);
    echo '<ol class="a-rank">';
    foreach ($rows as $r) {
        echo '<li><span class="a-rank-bar" style="width:' . round($r['n'] / $top * 100) . '%"></span><span class="a-rank-text"><b>' . e($r['label']) . '</b>'
            . ($r['sub'] !== '' && $r['sub'] !== $r['label'] ? '<small>' . e($r['sub']) . '</small>' : '') . '</span>'
            . '<span class="a-rank-n">' . number_format($r['n']) . '<small> ' . e($r['n'] === 1 ? rtrim($unit, 's') : $unit) . '</small></span></li>';
    }
    echo '</ol></section>';
};
$fmtDay = fn (string $d) => strlen($d) === 7 ? date('M y', strtotime($d . '-01')) : date('D M j', strtotime($d));
?>
<nav class="a-chips" aria-label="Time period">
  <?php foreach ($periods as $d => $label): ?>
    <a href="/admin/stats<?= $d === 30 ? '' : '?days=' . $d ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="a-stats a-stats-4">
  <div class="a-stat"><b><?= number_format($people) ?></b><span>visitors</span></div>
  <div class="a-stat"><b><?= number_format($views) ?></b><span>page views</span></div>
  <div class="a-stat"><b><?= number_format($taps) ?></b><span>button taps</span></div>
  <div class="a-stat"><b><?= number_format($emails) ?></b><span>new emails</span></div>
</div>

<?php if ($series): ?>
<section class="a-card a-form">
  <h2>Visitors <?= $byMonth ? 'by month' : 'by day' ?></h2>
  <div class="a-bars<?= count($series) > 14 ? ' is-dense' : '' ?>" role="list">
    <?php foreach ($series as $d => $v): ?>
      <span role="listitem" class="a-bar" title="<?= e($fmtDay($d)) ?>: <?= $v['people'] ?> visitors, <?= $v['views'] ?> views" aria-label="<?= e($fmtDay($d)) ?>: <?= $v['people'] ?> visitors">
        <i style="height:<?= $v['people'] ? max(4, round($v['people'] / $max * 100)) : 0 ?>%"></i></span>
    <?php endforeach; ?>
  </div>
  <p class="a-bars-ends"><span><?= e($fmtDay(array_key_first($series))) ?></span><span>busiest: <?= $max ?></span><span><?= e($fmtDay(array_key_last($series))) ?></span></p>
</section>
<?php endif; ?>

<?php
$q = $days === 30 ? '?days=30' : ($days === 0 ? '?days=0' : '');
$row = fn (string $href, string $ic, string $label, string $note) =>
    '<a href="' . e($href) . '">' . icon($ic, 22) . '<span class="a-menu-text">' . e($label) . '<small>' . e($note) . '</small></span><span class="a-chev">' . icon('chevron', 18) . '</span></a>';
?>
<nav class="a-card a-menu" aria-label="Details">
  <?= $row('/admin/stats/visitors' . $q, 'eye', 'Visitors', 'Day by day, and what they looked at') ?>
  <?= $row('/admin/stats/files' . $q, 'download', 'File activity', 'Views, emails, downloads, MakerWorld per file') ?>
  <?= $row('/admin/stats/taps' . $q, 'link', 'Link taps', 'Buttons, Amazon links, short addresses') ?>
  <?= $row('/admin/emails' . ($days === 7 || $days === 30 ? '?days=' . $days : ''), 'mail', 'Emails', 'Who signed up and what they got') ?>
</nav>

<?php if ($apps) $list('Calculators opened', $apps, 'opens', ''); ?>

<p class="a-hint a-center">Counting since <?= $since ? e(date('M j, Y', strtotime($since))) : 'today' ?>. Bots and you are left out, and no cookies are used.</p>
