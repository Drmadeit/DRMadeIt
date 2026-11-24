<?php
$files = db()->query("SELECT id, title, status FROM files ORDER BY status = 'published' DESC, sort_order, id")->fetchAll();
$preview = $_SESSION['test_preview'] ?? null;
unset($_SESSION['test_preview']);
$kinds = ['download' => 'Download email', 'notify' => 'File was updated', 'announce' => 'New file announcement'];
?>
<a class="a-back" href="/admin/content">‹ Content</a>
<form method="post" action="/admin/email-test" class="a-card a-form">
  <?= csrf_field() ?>
  <p class="a-hint">Sends any of the site's emails to you, exactly the way visitors get them (with "[Test]" in the subject). Nobody else gets anything.</p>
  <label>Send to <input type="email" name="to" required value="<?= e(setting('test_email')) ?>" autocapitalize="none"></label>
  <label>Which email
    <select name="kind">
      <?php foreach ($kinds as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
    </select></label>
  <label>Use this file
    <select name="file">
      <?php foreach ($files as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['title']) ?><?= $f['status'] === 'published' ? '' : ' (draft)' ?></option><?php endforeach; ?>
    </select></label>
  <label>"What changed" note <small>(only for File was updated)</small> <input name="note" placeholder="Added a 1/2&quot; plywood version."></label>
  <button class="a-btn">Send test</button>
  <?php if (trim(setting('mail_address')) === ''): ?><p class="a-hint">The real "New file" emails stay off until you add a mailing address (Settings → Email sending). Testing it is fine.</p><?php endif; ?>
</form>

<?php if ($preview): ?>
<section class="a-card a-form">
  <h2>What was sent</h2>
  <p><b><?= e($preview['subject']) ?></b></p>
  <pre class="a-email-preview"><?= e($preview['body']) ?></pre>
  <p class="a-hint">Try the unsubscribe links in your inbox. They really work on your test address, and the page has an Undo.</p>
</section>
<?php endif; ?>
