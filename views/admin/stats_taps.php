<nav class="a-chips" aria-label="Time period">
  <?php foreach ([7 => '7 days', 30 => '30 days', 0 => 'All time'] as $d => $label): ?>
    <a href="/admin/stats/<?= $which ?><?= $d === 7 ? '' : '?days=' . $d ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php
$rank = function (string $heading, array $rows, string $hint) {
    echo '<section class="a-card a-form"><h2>' . e($heading) . '</h2>';
    if (!$rows) {
        echo '<p class="a-hint">None in this time.</p></section>';
        return;
    }
    echo '<ol class="a-rank">';
    foreach ($rows as $r) {
        echo '<li><span class="a-rank-text"><b>' . e($r['label']) . '</b>' . ($r['sub'] !== '' ? '<small>' . e($r['sub']) . '</small>' : '') . '</span><span class="a-rank-n">' . $r['n'] . '</span></li>';
    }
    echo '</ol>' . ($hint ? '<p class="a-hint">' . e($hint) . '</p>' : '') . '</section>';
};
?>
<?php $rank('Buttons tapped', $buttons, 'Taps on your home page buttons and buttons inside folders.'); ?>
<?php $rank('Amazon links tapped', $amazon, 'Amazon links in your pages and file descriptions. Amazon\'s own report shows what was bought.'); ?>
<?php $rank('Short addresses used', $direct, 'People who went straight to an address like drmadeit.com/reaper (from a video description, QR code…).'); ?>
