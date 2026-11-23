<div class="a-btn-row"><a class="a-btn" href="/admin/files/new">+ New file</a><a class="a-btn a-btn-outline" href="/admin/files/import">Update from MakerWorld</a></div>

<div data-select-list="/admin/files/bulk">
  <div class="a-list-head">
    <p class="a-hint">Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder. Swipe right to publish or unpublish, left to delete.</p>
    <?php if ($files): ?><button type="button" class="a-link-btn" data-select-toggle>Select</button><?php endif; ?>
  </div>
  <ul class="a-list" data-sortable="/admin/reorder/files">
  <?php foreach ($files as $f): $live = $f['status'] === 'published'; $uses = address_uses('/files/' . $f['slug']); ?>
    <li class="a-row<?= $live ? '' : ' is-hidden' ?>" data-id="<?= (int) $f['id'] ?>" data-uses="<?= $uses ?>"
      data-delete="/admin/files/<?= (int) $f['id'] ?>/delete" data-confirm-delete="<?= e(delete_warning('file', $uses)) ?>"
      data-right="/admin/files/<?= (int) $f['id'] ?>/status" data-right-label="<?= $live ? 'Unpublish' : 'Publish' ?>" data-right-tone="<?= $live ? 'grey' : '' ?>">
      <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($f['title']) ?>"><?= icon('grip', 22) ?></button>
      <a class="a-row-main" href="/admin/files/<?= (int) $f['id'] ?>">
        <?php if ($f['thumb']): ?><img src="<?= e(img_url($f['thumb'], true)) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('file', 22) ?></span><?php endif; ?>
        <span class="a-row-text"><b><?= e($f['title']) ?></b>
          <small><?= $live ? 'Live' : 'Draft' ?> · <?= e(type_label($f['type'])) ?> · <?= e(price_label((int) $f['price_cents'])) ?><?= $f['featured'] ? ' · ★' : '' ?><?= ($f['makerworld_url'] ?? '') !== '' ? ' · MakerWorld' : '' ?></small></span>
      </a>
    </li>
  <?php endforeach; ?>
  </ul>
  <div class="a-select-bar" hidden>
    <button type="button" class="a-link-btn" data-select-all>Select all</button>
    <button type="button" data-bulk="publish">Publish</button>
    <button type="button" data-bulk="draft">Unpublish</button>
    <button type="button" data-bulk="delete">Delete <span data-select-count>0</span></button>
  </div>
</div>
<?php if (!$files): ?><p class="a-hint">No files yet.</p><?php endif; ?>
<a class="a-btn a-btn-outline" href="/admin/emails">Emails from free downloads</a>
