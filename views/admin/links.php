<a class="a-btn a-btn-block" href="/admin/links/new">+ Add link</a>
<p class="a-hint">Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder. Tap a link to edit it.</p>

<ul class="a-list" data-sortable="/admin/reorder/links">
<?php foreach ($links as $l): ?>
  <li class="a-row<?= $l['visible'] ? '' : ' is-hidden' ?>" data-id="<?= (int) $l['id'] ?>">
    <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($l['title']) ?>"><?= icon('grip', 22) ?></button>
    <a class="a-row-main" href="/admin/links/<?= (int) $l['id'] ?>">
      <?php if ($l['image']): ?>
        <img src="<?= e(img_url($l['image'], true)) ?>" alt="" width="48" height="48">
      <?php else: ?>
        <span class="a-thumb-empty"><?= icon($l['icon'] ?: 'link', 22) ?></span>
      <?php endif; ?>
      <span class="a-row-text">
        <b><?= e($l['title']) ?></b>
        <small><?= $l['visible'] ? '' : 'Hidden · ' ?><?= e($l['url']) ?></small>
      </span>
    </a>
    <form method="post" action="/admin/links/<?= (int) $l['id'] ?>/feature">
      <?= csrf_field() ?>
      <button class="a-icon-btn<?= $l['featured'] ? ' on' : '' ?>" aria-label="<?= $l['featured'] ? 'Unfeature' : 'Feature' ?> <?= e($l['title']) ?>" aria-pressed="<?= $l['featured'] ? 'true' : 'false' ?>"><?= icon('star', 22) ?></button>
    </form>
    <form method="post" action="/admin/toggle/links/<?= (int) $l['id'] ?>">
      <?= csrf_field() ?>
      <button class="a-icon-btn" aria-label="<?= $l['visible'] ? 'Hide' : 'Show' ?> <?= e($l['title']) ?>"><?= icon($l['visible'] ? 'eye' : 'eye-off', 22) ?></button>
    </form>
  </li>
<?php endforeach; ?>
</ul>
<?php if (!$links): ?><p class="a-hint">No links yet.</p><?php endif; ?>
<p class="a-hint"><span class="a-inline-icon"><?= icon('star', 16) ?></span> = featured (red outline on the home page). <span class="a-inline-icon"><?= icon('eye', 16) ?></span> = show / hide.</p>
