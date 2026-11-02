<?php /* Смена пароля. Данные: $admin, $AB, $today */ ?>
<?php ob_start(); ?>

<div class="panel" style="max-width:520px">
  <h2>Изменить пароль администратора «<?= e($admin) ?>»</h2>
  <?php $err = $_GET['err'] ?? ''; ?>
  <?php if ($err === 'old'): ?><div class="login-error" style="margin-bottom:14px">Текущий пароль указан неверно.</div><?php endif; ?>
  <?php if ($err === 'short'): ?><div class="login-error" style="margin-bottom:14px">Новый пароль должен быть не короче 6 символов и совпадать с подтверждением.</div><?php endif; ?>
  <?php if (!empty($_GET['saved'])): ?><div class="login-error" style="background:#e6f4e6;color:#2c6a2c;border-color:#bfe0bf;margin-bottom:14px">Пароль изменён ✔</div><?php endif; ?>
  <form method="post" action="<?= e(url($AB . '/password')) ?>">
    <?= csrf_field() ?>
    <div class="field" style="margin-bottom:14px">
      <label>Текущий пароль</label>
      <input type="password" name="old" required autocomplete="current-password">
    </div>
    <div class="field" style="margin-bottom:14px">
      <label>Новый пароль (мин. 6 символов)</label>
      <input type="password" name="new" required minlength="6" autocomplete="new-password">
    </div>
    <div class="field">
      <label>Повторите новый пароль</label>
      <input type="password" name="confirm" required minlength="6" autocomplete="new-password">
    </div>
    <div class="form-actions">
      <button class="btn btn-gold" type="submit">Сменить пароль</button>
    </div>
  </form>
</div>

<?php $content = ob_get_clean();
$page = 'password'; $pageTitle = 'Смена пароля'; $pageHeading = 'Смена пароля';
require __DIR__ . '/_base.php'; ?>
