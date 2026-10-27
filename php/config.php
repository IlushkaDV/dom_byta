<?php
/* Конфигурация. Значения можно переопределить в private-config.php
   (он не отдаётся наружу, лежит вне public_html или рядом с index.php). */

$ROOT = __DIR__;

// Пароль администратора по умолчанию при первом запуске.
// Смените сразу после первого входа! Можно задать так: putenv('ATELLE_ADMIN_PASSWORD=...')
// или в private-config.php: $ADMIN_PASSWORD = 'надёжный_пароль';
if (file_exists($ROOT . '/private-config.php')) {
    require $ROOT . '/private-config.php';
}

return [
    'db_path'        => getenv('DB_PATH') ?: ($ROOT . '/data/atele.sqlite'),
    'upload_dir'     => $ROOT . '/uploads',
    'upload_url'     => '/uploads',
    'admin_password' => getenv('ATELLE_ADMIN_PASSWORD') ?: ($ADMIN_PASSWORD ?? 'admin123'),
    'admin_login'    => getenv('ATELLE_ADMIN_LOGIN') ?: 'admin',
    'max_upload'     => 15 * 1024 * 1024,
];
