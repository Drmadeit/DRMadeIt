<a class="a-btn a-btn-block" href="/admin/files/new">+ New file</a>
<p class="a-hint">Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder the shop.</p>
<ul class="a-list" data-sortable="/admin/reorder/files">
<?php foreach ($files as $f): ?>
  <li class="a-row<?= $f['status'] === 'published' ? '' : ' is-hidden' ?>" data-id="<?= (int) $f['id'] ?>">
    <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($f['title']) ?>"><?= icon('grip', 22) ?></button>
    <a class="a-row-main" href="/admin/files/<?= (int) $f['id'] ?>">
      <?php if ($f['thumb']): ?><img src="<?= e(img_url($f['thumb'], true)) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('file', 22) ?></span><?php endif; ?>
      <span class="a-row-text"><b><?= e($f['title']) ?></b>
        <small><?= $f['status'] === 'published' ? 'Live' : 'Draft' ?> · <?= e(type_label($f['type'])) ?> · <?= e(price_label((int) $f['price_cents'])) ?><?= $f['featured'] ? ' · ★' : '' ?></small></span>
    </a>
  </li>
<?php endforeach; ?>
</ul>
<?php if (!$files): ?><p class="a-hint">No files yet.</p><?php endif; ?>
<a class="a-btn a-btn-outline" href="/admin/emails">Emails from free downloads</a>
