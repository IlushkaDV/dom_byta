<?php /* Каркас админки. Данные: $admin, $AB, $today, $pageTitle, $pageHeading, $content */ ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Админ-панель') ?> · Ателье «Дом Быта»</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <?php if (!empty(get_settings()['favicon_image'])): ?>
  <link rel="icon" href="<?= e(url('/uploads/favicon/favicon-32.png')) ?>?v=2">
  <?php else: ?>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🧵</text></svg>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>?v=20261022b">
</head>
<body>
<div class="admin-layout">
  <aside class="sidebar">
    <div class="side-logo"><span>DB</span> Ателье · Админ</div>
    <nav class="side-nav">
      <?php
      $items = [
        ['', 'dashboard', '◧', 'Дашборд'],
        ['/orders', 'orders', '☰', 'Заявки'],
        ['/services', 'services', '✂', 'Услуги и цены'],
        ['/advantages', 'advantages', '◆', 'Условия и надбавки'],
        ['/gallery', 'gallery', '▦', 'Галерея работ'],
        ['/reviews', 'reviews', '★', 'Отзывы'],
        ['/settings', 'settings', '⚙', 'Настройки сайта'],
        ['/password', 'password', '🔑', 'Смена пароля'],
      ];
      foreach ($items as [$slug, $key, $ic, $label]): ?>
      <a href="<?= e(url($AB . $slug)) ?>"<?= ($page ?? '') === $key ? ' class="active"' : '' ?>><i><?= $ic ?></i> <?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-footer">
      <a href="<?= e(url('/')) ?>" target="_blank" class="side-view">↗ Открыть сайт</a>
      <a href="<?= e(url($AB . '/logout')) ?>" class="side-logout">Выйти (<?= e($admin) ?>)</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="topbar">
      <h1><?= e($pageHeading ?? '') ?></h1>
      <span class="topbar-date"><?= e($today) ?></span>
    </header>
    <div class="content">
<?= $content ?>
    </div>
  </div>
</div>
<script src="<?= e(url('/assets/js/admin.js')) ?>?v=20261022b"></script>
</body>
</html>
