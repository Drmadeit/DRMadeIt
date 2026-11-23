<?php
$host = preg_replace('#^https?://#', '', base_url());
$kindIcon = ['page' => 'file', 'link' => 'link', 'folder' => 'folder'];
$list = function (array $rows, bool $home) use ($kindIcon) {
    echo '<ul class="a-list" data-sortable="/admin/reorder/pages">';
    foreach ($rows as $b) {
        $img = button_image($b);
        $sub = BUTTON_KINDS[$b['kind']] . ' · /' . $b['slug'];
        if ($b['kind'] === 'link') {
            $sub .= ' → ' . preg_replace('#^https?://(www\.)?#', '', $b['url']);
        }
        $what = strtolower(BUTTON_KINDS[$b['kind']]);
        echo '<li class="a-row' . ($b['visible'] ? '' : ' is-hidden') . '" data-id="' . (int) $b['id'] . '" data-uses="' . address_uses(page_url($b), (int) $b['id']) . '" data-delete="/admin/buttons/' . (int) $b['id'] . '/delete" data-confirm-delete="'
            . e(delete_warning($what, address_uses(page_url($b), (int) $b['id']))) . '">'
            . '<button type="button" class="a-grip" aria-label="Drag to reorder ' . e($b['title']) . '">' . icon('grip', 22) . '</button>'
            . '<a class="a-row-main" href="/admin/buttons/' . (int) $b['id'] . '">'
            . ($img ? '<img src="' . e($img) . '" alt="" width="48" height="48">' : '<span class="a-thumb-empty">' . icon($b['icon'] ?: $kindIcon[$b['kind']], 22) . '</span>')
            . '<span class="a-row-text"><b>' . e($b['title']) . '</b><small>' . ($b['visible'] ? '' : 'Hidden · ') . e($sub)
            . ($b['kind'] === 'link' && $b['clicks'] ? ' · ' . (int) $b['clicks'] . ' clicks' : '') . '</small></span></a>';
        if ($home) {
            echo '<form method="post" action="/admin/buttons/' . (int) $b['id'] . '/feature">' . csrf_field()
                . '<button class="a-icon-btn' . ($b['featured'] ? ' on' : '') . '" aria-pressed="' . ($b['featured'] ? 'true' : 'false') . '" aria-label="Feature ' . e($b['title']) . '">' . icon('star', 22) . '</button></form>';
        }
        echo '<form method="post" action="/admin/toggle/pages/' . (int) $b['id'] . '">' . csrf_field()
            . '<button class="a-icon-btn" aria-label="' . ($b['visible'] ? 'Hide ' : 'Show ') . e($b['title']) . '">' . icon($b['visible'] ? 'eye' : 'eye-off', 22) . '</button></form></li>';
    }
    echo '</ul>';
};
?>
<a class="a-btn a-btn-block" href="/admin/buttons/new">+ Add button</a>
<div data-select-list="/admin/buttons/bulk" data-select-noun="button">
<div class="a-list-head"><p class="a-hint"><span class="hint-touch">Swipe left to delete.</span><span class="hint-mouse">Point at a button to delete it.</span> Select cleans up several at once.</p><button type="button" class="a-link-btn" data-select-toggle>Select</button></div>

<p class="a-group"><span>Home page</span><a href="/admin/buttons/new?in=home">+ Add here</a></p>
<?php if ($groups['home']): $list($groups['home'], true); else: ?><p class="a-hint">No buttons on the home page yet.</p><?php endif; ?>

<?php foreach ($folders as $fid => $f): ?>
  <p class="a-group"><a class="a-group-folder" href="/admin/buttons/<?= (int) $fid ?>"><?= icon('folder', 16) ?> <?= e($f['title']) ?></a><a href="/admin/buttons/new?in=<?= (int) $fid ?>">+ Add here</a></p>
  <?php if ($groups[$fid]): $list($groups[$fid], false); else: ?><p class="a-hint">Empty folder.</p><?php endif; ?>
<?php endforeach; ?>

<?php if ($groups['none']): ?>
  <p class="a-group"><span>Address only <small>(not shown anywhere)</small></span><a href="/admin/buttons/new?in=none&amp;kind=link">+ Add here</a></p>
  <?php $list($groups['none'], false); ?>
<?php endif; ?>

<div class="a-select-bar" hidden>
  <button type="button" class="a-link-btn" data-select-all>Select all</button>
  <button type="button" data-bulk="show">Show</button>
  <button type="button" data-bulk="hide">Hide</button>
  <button type="button" data-bulk="delete">Delete <span data-select-count>0</span></button>
</div>
</div>
<p class="a-hint">Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder. <span class="a-inline-icon"><?= icon('star', 16) ?></span> = featured (red outline). <span class="a-inline-icon"><?= icon('eye', 16) ?></span> = show / hide.
  Every button has its own address, like <?= e($host) ?>/k7m.</p>
