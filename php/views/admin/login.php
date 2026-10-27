<?php /* Вход в админку. Данные: $error, $AB */ ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход · Админ-панель ателье</title>
  <meta name="robots" content="noindex,nofollow">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🧵</text></svg>">
  <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>?v=20261022b">
</head>
<body class="login-body">
  <form class="login-card" method="post" action="<?= e(url($AB . '/login')) ?>">
    <div class="logo-badge">DB</div>
    <h1>Админ-панель ателье</h1>
    <p class="sub">Меховое ателье «Дом Быта» · Хабаровск</p>
    <?php if (!empty($error)): ?><div class="login-error"><?= e($error) ?></div><?php endif; ?>
    <div class="field" style="margin-bottom:14px">
      <label>Логин</label>
      <input type="text" name="login_name" autocomplete="username" required autofocus placeholder="admin">
    </div>
    <div class="field">
      <label>Пароль</label>
      <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
    </div>
    <button class="btn btn-gold" style="width:100%;justify-content:center;margin-top:22px" type="submit">Войти</button>
  </form>
</body>
</html>
