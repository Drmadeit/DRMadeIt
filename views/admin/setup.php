<form method="post" class="a-card a-form">
  <?= csrf_field() ?>
  <p>Welcome! Create your admin login.</p>
  <p class="a-hint">To prove it's you, open <b>Hostinger → File Manager</b>, go to the <b>drmadeit-data</b> folder (next to public_html) and open <b>SETUP-CODE.txt</b>.</p>
  <?php if ($error): ?><p class="a-error"><?= e($error) ?></p><?php endif; ?>
  <label>Setup code <input name="code" required autocomplete="off" autocapitalize="characters"></label>
  <label>Username <input name="user" required autocomplete="username" autocapitalize="none" value="<?= e($_POST['user'] ?? '') ?>"></label>
  <label>Password <small>(12+ characters)</small> <input name="pass" type="password" required minlength="12" autocomplete="new-password"></label>
  <button class="a-btn">Create admin</button>
</form>
