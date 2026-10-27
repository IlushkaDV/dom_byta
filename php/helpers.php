<?php
/* Общие утилиты: вывод, редиректы, CSRF, сессии, rate-limit, загрузка файлов */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function cfg(): array { static $c = null; if ($c === null) $c = require __DIR__ . '/config.php'; return $c; }

function base_url(): string {
    // поддержка подпапки (например site.ru/atele/) и корня домена
    static $b = null;
    if ($b === null) {
        $sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // Префикс-подпапка определяется ТОЛЬКО по расположению index.php: SCRIPT_NAME = <префикс>/index.php.
        // На запросах статики (php -S или misconfig) в SCRIPT_NAME попадает путь самого файла —
        // тогда префикс считаем пустым, иначе он ошибочно вырезается из URL.
        $b = '';
        if (substr($sn, -10) === '/index.php') {
            $b = rtrim(substr($sn, 0, -10), '/');
        } elseif ($sn === '') {
            $b = ''; // CLI
        }
    }
    return $b;
}

function url(string $path = '/'): string { return base_url() . '/' . ltrim($path, '/'); }

function redirect(string $to): void {
    header('Location: ' . $to, true, 302);
    exit;
}

function security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://api-maps.yandex.ru; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https://*.yandex.ru https://*.ytimg.ru; "
        . "frame-src 'self' https://yandex.ru https://maps.yandex.ru; "
        . "connect-src 'self' https://api-maps.yandex.ru; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

// ---------- секретный ключ подписи cookie ----------
function secret_bytes(): string {
    $f = __DIR__ . '/data/.secret_key';
    if (is_readable($f)) {
        $k = trim(file_get_contents($f));
        if ($k !== '') return $k;
    }
    $k = bin2hex(random_bytes(32));
    @file_put_contents($f, $k);
    @chmod($f, 0600);
    return $k;
}

// ---------- сессия администратора (подписанная cookie, как в Python-версии) ----------
define('SESSION_TTL', 12 * 3600);

function make_session_token(string $login): string {
    $payload = $login . '|' . (time() + SESSION_TTL);
    $sig = hash_hmac('sha256', $payload, secret_bytes());
    return $payload . '.' . $sig;
}

function check_session_token(?string $token): ?string {
    if (!$token || substr_count($token, '.') !== 1) return null;
    [$payload, $sig] = explode('.', $token);
    $expected = hash_hmac('sha256', $payload, secret_bytes());
    if (!hash_equals($expected, $sig)) return null;
    $parts = explode('|', $payload);
    $login = $parts[0];
    $exp = (int)end($parts);
    if ($exp < time()) return null;
    return $login;
}

function current_admin(): ?string {
    return check_session_token($_COOKIE['atele_admin_session'] ?? null);
}

function set_admin_cookie(string $login): void {
    setcookie('atele_admin_session', make_session_token($login), [
        'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']), 'path' => '/',
    ]);
}

function clear_admin_cookie(): void {
    setcookie('atele_admin_session', '', time() - 3600, '/');
}

function require_admin(): string {
    $a = current_admin();
    if (!$a) redirect(url(admin_base() . '/login'));
    return $a;
}

// ---------- CSRF ----------
function csrf_token(string $login): string {
    return substr(hash_hmac('sha256', "csrf|$login", secret_bytes()), 0, 32);
}
function csrf_field(): string {
    $a = current_admin();
    return '<input type="hidden" name="_csrf" value="' . e($a ? csrf_token($a) : '') . '">';
}
function check_csrf(): void {
    $a = current_admin();
    if (!$a || !hash_equals(csrf_token($a), $_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('Сессия устарела, обновите страницу');
    }
}

// ---------- rate limit входа (файловый счётчик, переживает рестарт PHP) ----------
function client_ip(): string {
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd) return trim(explode(',', $fwd)[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '?';
}
function login_blocked(string $ip): bool {
    return count_login_fails($ip) >= 8;
}
function fails_file(string $ip): string {
    return sys_get_temp_dir() . '/atele_fails_' . md5($ip);
}
function count_login_fails(string $ip): int {
    $f = fails_file($ip);
    if (!is_readable($f)) return 0;
    $now = time();
    $list = [];
    foreach (explode(',', file_get_contents($f)) as $t) {
        $t = (int)$t;
        if ($t && $now - $t < 900) $list[] = $t;
    }
    return count($list);
}
function login_fail(string $ip): void {
    $now = time();
    $list = array_values(array_filter(explode(',', file_get_contents(fails_file($ip)) ?: ''),
        fn($t) => $now - (int)$t < 900));
    $list[] = (string)$now;
    @file_put_contents(fails_file($ip), implode(',', $list));
}
function login_ok(string $ip): void { @unlink(fails_file($ip)); }

// ---------- секретный путь админки ----------
function admin_base(): string {
    static $b = null;
    if ($b !== null) return $b;
    $env = trim(getenv('ATELLE_ADMIN_PATH') ?: '', '/');
    if ($env !== '') return $b = '/' . $env;
    $f = __DIR__ . '/data/.admin_path';
    if (is_readable($f)) {
        $saved = trim(file_get_contents($f));
        if ($saved !== '') return $b = '/' . $saved;
    }
    $secret = 'admin-' . bin2hex(random_bytes(4));
    @file_put_contents($f, $secret);
    @chmod($f, 0600);
    return $b = '/' . $secret;
}

// ---------- загрузка изображений ----------
function validate_image(string $tmpPath): bool {
    $info = @getimagesize($tmpPath);   // false для SVG/HTML/мусора — ровно то, что нужно
    if ($info === false) return false;
    $ext = strtolower(pathinfo($tmpPath, PATHINFO_EXTENSION));
    if ($ext === 'svg') return false;
    return true;
}

function save_upload(array $fileField): ?string {
    // $fileField — элемент $_FILES
    if (($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($fileField['size'] ?? 0) > cfg()['max_upload']) return null;
    $ext = strtolower(pathinfo($fileField['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) return null;
    if (!validate_image($fileField['tmp_name'])) return null;
    $fname = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = cfg()['upload_dir'] . '/' . $fname;
    if (!move_uploaded_file($fileField['tmp_name'], $dest)) return null;
    return cfg()['upload_url'] . '/' . $fname;
}

function remove_upload(?string $path): void {
    if (!$path || strpos($path, cfg()['upload_url'] . '/') !== 0) return;
    $real = cfg()['upload_dir'] . '/' . basename($path);
    if (is_file($real)) @unlink($real);
}

// favicon: из загруженного PNG делаем 16/32/48/192 + .ico в uploads/favicon/
function make_favicon(string $srcUrlPath): void {
    $src = cfg()['upload_dir'] . '/' . basename($srcUrlPath);
    if (!is_file($src) || !function_exists('imagecreatetruecolor')) return;
    $im = @imagecreatefromstring(file_get_contents($src));
    if (!$im) return;
    $w = imagesx($im); $h = imagesy($im);
    $side = min($w, $h);
    $dir = cfg()['upload_dir'] . '/favicon';
    @mkdir($dir, 0775, true);
    foreach ([16, 32, 48, 192] as $sz) {
        $out = imagecreatetruecolor($sz, $sz);
        imagealphablending($out, false); imagesavealpha($out, true);
        imagecopyresampled($out, $im, 0, 0, (int)(($w-$side)/2), (int)(($h-$side)/2), $sz, $sz, $side, $side);
        imagepng($out, "$dir/favicon-$sz.png");
        imagedestroy($out);
    }
    // .ico 32x32 (контейнер: PNG внутри ICO — поддерживается всеми браузерами)
    $pngData = @file_get_contents("$dir/favicon-32.png");
    if ($pngData !== false) {
        $ico = @fopen("$dir/favicon.ico", 'wb');
        if ($ico) {
            fwrite($ico, pack('v', 0) . pack('v', 1) . pack('v', 1)
                 . pack('C', 32) . pack('C', 32) . pack('C', 0) . pack('C', 0)
                 . pack('v', 1) . pack('v', 32)
                 . pack('V', strlen($pngData)) . pack('V', 22)
                 . $pngData);
            fclose($ico);
        }
    }
    imagedestroy($im);
}

// подпись: обрезаем прозрачные поля
function trim_signature(string $urlPath): void {
    $src = cfg()['upload_dir'] . '/' . basename($urlPath);
    if (!is_file($src)) return;
    $im = @imagecreatefromstring(file_get_contents($src));
    if (!$im) return;
    $w = imagesx($im); $h = imagesy($im);
    $bbox = null; $x0 = $w; $y0 = $h; $x1 = -1; $y1 = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $a = (imagecolorat($im, $x, $y) >> 24) & 0x7F;
            if ($a < 100) { // непрозрачный пиксель
                if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x;
                if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y;
            }
        }
    }
    if ($x1 < 0 || ($x1 - $x0) < 40 || ($y1 - $y0) < 20) return;
    $pad = 8;
    $cx = max(0, $x0 - $pad); $cy = max(0, $y0 - $pad);
    $cw = min($w, $x1 + $pad + 1) - $cx; $ch = min($h, $y1 + $pad + 1) - $cy;
    $out = imagecreatetruecolor($cw, $ch);
    imagealphablending($out, false); imagesavealpha($out, true);
    $trans = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $trans);
    imagecopy($out, $im, 0, 0, $cx, $cy, $cw, $ch);
    imagepng($out, $src);
    imagedestroy($im); imagedestroy($out);
}
