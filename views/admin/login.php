<form method="post" action="/admin/login" class="a-card a-form">
  <?= csrf_field() ?>
  <?php if ($error): ?><p class="a-error"><?= e($error) ?></p><?php endif; ?>
  <label>Username <input name="user" required autocomplete="username" autocapitalize="none"></label>
  <label>Password <input name="pass" type="password" required autocomplete="current-password"></label>
  <button class="a-btn">Log in</button>
</form>
