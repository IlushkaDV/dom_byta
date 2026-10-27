<?php /* Общий каркас публичных страниц: $s, $content уже готовы; переменные $title,$desc */ ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? trim(($s['seo_title'] ?? '') ?: (($s['site_name'] ?? '') . ' — пошив и ремонт меха, кожи и одежды в Хабаровске'))) ?></title>
  <meta name="description" content="<?= e($desc ?? trim(($s['seo_description'] ?? '') ?: ($s['tagline'] ?? ''))) ?>">
  <meta name="keywords" content="<?= e($s['seo_keywords'] ?? 'ателье Хабаровск, пошив шуб, перекрой шубы, ремонт меха, ателье Дом Быта, пошив кожи, подгонка одежды Хабаровск') ?>">
  <?php $canon = rtrim($s['site_url'] ?? '', '/'); if ($canon !== ''): ?>
  <link rel="canonical" href="<?= e($canon . ($canonical_path ?? '/')) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($s['site_name'] ?? '') ?>">
  <meta property="og:title" content="<?= e($title ?? ($s['site_name'] ?? '')) ?>">
  <meta property="og:description" content="<?= e($desc ?? ($s['tagline'] ?? '')) ?>">
  <meta property="og:url" content="<?= e($canon . ($canonical_path ?? '/')) ?>">
  <?php endif; ?>
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#1A1A1A">
  <script type="application/ld+json"><?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'LocalBusiness',
      'name' => ($s['site_name'] ?? 'Ателье') . ' — ТЦ «Дом Быта»',
      'description' => $s['tagline'] ?? '',
      'url' => ($s['site_url'] ?? '') ?: null,
      'telephone' => $s['phone'] ?? '',
      'priceRange' => '₽₽',
      'address' => [
          '@type' => 'PostalAddress',
          'addressLocality' => $s['city'] ?? 'Хабаровск',
          'streetAddress' => $s['address'] ?? '',
          'addressCountry' => 'RU',
      ],
      'openingHours' => ['Mo-Fr 10:00-20:00', 'Sa-Su 10:00-19:00'],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <?php if (!empty($s['favicon_image'])): ?>
  <link rel="icon" href="<?= e(url('/uploads/favicon/favicon-32.png')) ?>?v=2">
  <link rel="apple-touch-icon" href="<?= e(url('/uploads/favicon/favicon-192.png')) ?>?v=2">
  <?php else: ?>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🧵</text></svg>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>?v=20261022d">
</head>
<body>

<div class="preloader" id="preloader"><span class="preloader-mark">DB</span><i></i></div>

<header class="header" id="header">
  <div class="container header-inner">
    <a href="<?= e(url('/')) ?>" class="logo<?= !empty($s['logo_image']) ? ' logo-img' : '' ?>">
      <?php if (!empty($s['logo_image'])): ?>
      <img src="<?= e(url($s['logo_image'])) ?>" alt="<?= e($s['site_name']) ?>" class="logo-pic">
      <?php else: ?>
      <span class="logo-mono">DB</span>
      <?php endif; ?>
      <span class="logo-text"><b>Ателье</b><small>Дом Быта · Хабаровск</small></span>
    </a>
    <nav class="nav" id="nav">
      <a href="#about">О нас</a>
      <button type="button" class="nav-prices-link" data-open-prices="">Услуги и цены</button>
      <a href="#gallery">Галерея</a>
      <a href="#contacts">Контакты</a>
    </nav>
    <div class="header-contacts">
      <a href="tel:<?= e(preg_replace('/[^\d+]/u', '', $s['phone'] ?? '')) ?>" class="btn btn-primary btn-phone"><?= e($s['phone']) ?></a>
    </div>
    <button class="burger" id="burger" aria-label="Меню"><i></i><i></i><i></i></button>
  </div>
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="mobile-menu" id="mobileMenu">
    <nav class="mobile-menu-inner">
      <a href="#about">О нас</a>
      <button type="button" class="nav-prices-link" data-open-prices="">Услуги и цены</button>
      <a href="#gallery">Галерея</a>
      <a href="#steps">Как мы работаем</a>
      <a href="#order">Записаться</a>
      <a href="#contacts">Контакты</a>
    </nav>
    <a href="tel:<?= e(preg_replace('/[^\d+]/u', '', $s['phone'] ?? '')) ?>" class="mobile-menu-phone"><?= e($s['phone']) ?></a>
    <div class="mobile-menu-hours"><?= e($s['hours'] ?? '') ?: 'Пн–Пт 10:00–20:00 · Сб–Вс 10:00–19:00' ?></div>
  </div>
</header>

<main>
<?= $content ?>
</main>

<footer class="footer">
  <div class="container footer-grid">
    <div>
      <div class="logo logo-footer"><span class="logo-text"><b><?= e($s['site_name']) ?></b><small><?= e($s['tagline']) ?></small></span></div>
      <p class="footer-desc">Авторский пошив, реставрация и ремонт меховой и другой одежды. Работаем для вас в ТЦ «Дом Быта» с любовью к каждому изделию.</p>
    </div>
    <div>
      <h4>Разделы</h4>
      <a href="#about">О нас</a>
      <a href="#directions">Что мы делаем</a>
      <a href="#gallery">Наши работы</a>
      <a href="#order">Заявка</a>
    </div>
    <div>
      <h4>Контакты</h4>
      <p><?= e($s['address']) ?></p>
      <p><a href="tel:#"><?= e($s['phone']) ?></a><br><a href="tel:#"><?= e($s['phone_tc']) ?></a></p>
      <p><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></p>
    </div>
    <div>
      <h4>Время работы</h4>
      <p><?= e($s['hours_week']) ?><br><?= e($s['hours_weekend']) ?></p>
      <p class="footer-note">ВС — выходной<br>Ждём вас на 6 этаже</p>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?: 2026 ?> Ателье в ТЦ «Дом Быта», Хабаровск · <a href="<?= e(url('/privacy')) ?>" class="footer-privacy">Политика обработки персональных данных</a></span>
  </div>

  <!-- ===== Разделитель: игла с ниткой прошивает «строчку» при прокрутке ===== -->
  <div class="seam" aria-hidden="true">
    <svg viewBox="0 0 1200 60" preserveAspectRatio="none" class="seam-svg">
      <path class="seam-stitch" d="M0,30 C100,8 200,52 300,30 S500,8 600,30 S800,52 900,30 S1100,8 1200,30"/>
    </svg>
    <div class="seam-needle">
      <svg viewBox="0 0 44 44" width="44" height="44" fill="none">
        <circle cx="22" cy="7" r="3.4" stroke="currentColor" stroke-width="2"/>
        <path d="M22 11v22l-2.4 6h4.8L22 33" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        <path d="M22 11c-5 4-7 9-6 14" stroke="#d9b87c" stroke-width="1.6" stroke-dasharray="2.5 4" stroke-linecap="round"/>
      </svg>
    </div>
  </div>
</footer>

<a href="tel:<?= e(preg_replace('/[^\d+]/u', '', $s['phone'] ?? '')) ?>" class="floating-call" aria-label="Позвонить">
  <svg viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.6 15.6 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2z"/></svg>
</a>

<div class="cookie-banner" id="cookieBanner" role="dialog" aria-label="Согласие на использование cookie" hidden>
  <div class="container cookie-inner">
    <div class="cookie-text">
      <b>Мы используем файлы cookie</b>
      <span>Сайт применяет только технологические файлы cookie (для работы формы записи и входа в панель управления). Рекламные и аналитические трекеры не используются. Продолжая работу с сайтом или нажимая «Принять», вы соглашаетесь с их обработкой. Отказ ничего не ломает — просто сохранится ваш выбор.</span>
      <a href="<?= e(url('/privacy')) ?>" class="cookie-link">Политика обработки персональных данных</a>
    </div>
    <div class="cookie-actions">
      <button type="button" class="btn btn-primary" id="cookieAccept">Принять</button>
      <button type="button" class="btn btn-ghost" id="cookieDecline">Отклонить</button>
    </div>
  </div>
</div>

<script>
/* Cookie-баннер: показываем, если выбор ещё не сделан */
(function () {
  try {
    if (!localStorage.getItem('cookie_consent')) document.getElementById('cookieBanner').hidden = false;
    document.getElementById('cookieAccept').addEventListener('click', function () {
      localStorage.setItem('cookie_consent', 'accepted');
      localStorage.setItem('cookie_consent_at', new Date().toISOString());
      document.getElementById('cookieBanner').hidden = true;
    });
    document.getElementById('cookieDecline').addEventListener('click', function () {
      localStorage.setItem('cookie_consent', 'declined');
      document.getElementById('cookieBanner').hidden = true;
    });
  } catch (e) {}
})();
</script>
<script src="<?= e(url('/assets/js/main.js')) ?>?v=20261022d"></script>
</body>
</html>
