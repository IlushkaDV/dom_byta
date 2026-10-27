<?php
// Карта сайта в формате Яндекса: https://atelier-db.ru/sitemap-yandex.xml
// Подключается из index.php — все функции (site_url, e) уже загружены.

$base = rtrim(get_settings()['site_url'] ?? 'https://atelier-db.ru', '/');
$today = date('Y-m-d');

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc><?= e($base) ?>/sitemap.xml</loc>
    <lastmod><?= $today ?></lastmod>
  </sitemap>
</sitemapindex>
