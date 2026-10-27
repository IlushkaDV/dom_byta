<?php
/* Формирует sitemap.xml по реальным страницам сайта. Кэшируется на 1 час. */
header('Content-Type: application/xml; charset=utf-8');
$cache = sys_get_temp_dir() . '/sitemap_' . md5(__FILE__) . '.xml';
if (is_file($cache) && filemtime($cache) > time() - 3600) {
    readfile($cache); exit;
}
$s = get_settings();
$base = rtrim($s['site_url'] ?? '', '/');
if ($base === '') $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
$today = date('Y-m-d');
$urls = [
    ['/', '1.0', 'weekly'],
    ['/privacy', '0.3', 'yearly'],
];
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as [$p, $pr, $ch]) {
    $xml .= "  <url><loc>" . htmlspecialchars($base . $p) . "</loc><lastmod>$today</lastmod><changefreq>$ch</changefreq><priority>$pr</priority></url>\n";
}
$xml .= "</urlset>\n";
file_put_contents($cache, $xml);
echo $xml;
