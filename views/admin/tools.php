<a class="a-btn a-btn-block" href="/admin/tools/new">+ New tool list</a>
<p class="a-hint">Tap a list to add tools to it. Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder.</p>
<ul class="a-list" data-sortable="/admin/reorder/tool_lists">
<?php foreach ($lists as $l): ?>
  <li class="a-row<?= $l['visible'] ? '' : ' is-hidden' ?>" data-id="<?= (int) $l['id'] ?>">
    <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($l['title']) ?>"><?= icon('grip', 22) ?></button>
    <a class="a-row-main" href="/admin/tools/<?= (int) $l['id'] ?>">
      <?php if ($l['cover_image']): ?><img src="<?= e(img_url($l['cover_image'], true)) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('ruler', 22) ?></span><?php endif; ?>
      <span class="a-row-text"><b><?= e($l['title']) ?></b><small><?= $l['visible'] ? '' : 'Hidden · ' ?><?= (int) $l['n'] ?> tools · /tools/<?= e($l['slug']) ?></small></span>
    </a>
    <form method="post" action="/admin/toggle/tool_lists/<?= (int) $l['id'] ?>">
      <?= csrf_field() ?>
      <button class="a-icon-btn" aria-label="<?= $l['visible'] ? 'Hide' : 'Show' ?> <?= e($l['title']) ?>"><?= icon($l['visible'] ? 'eye' : 'eye-off', 22) ?></button>
    </form>
  </li>
<?php endforeach; ?>
</ul>
<p class="a-hint">All lists show at <a href="/tools" target="_blank">/tools</a>. To put one on the home page, add a link to <code>/tools/its-name</code> in Links.</p>
