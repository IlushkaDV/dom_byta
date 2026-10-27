<?php
/* Ателье в Доме Быта — atelier-db.ru
   Единая точка входа: витрина + админка (секретный путь). Чистый PHP 8, SQLite. */

// На боевом хостинге не показываем ошибки/предупреждения посетителям
// (они ломают заголовки и куки). Логи ошибок — в логах сервера.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/defaults.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/admin.php';

session_start();
security_headers();
init_db();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
/* Определяем подпапку-префикс ТОЛЬКО по расположению index.php:
   SCRIPT_NAME = <префикс>/index.php. Иначе встроенный сервер PHP на запросах статики
   подставляет в SCRIPT_NAME сам путь файла (/assets/css/style.css), и префикс «/assets/css»
   ошибочно вырезался из URL. */
$scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$prefix = '';
if (PHP_SAPI !== 'cli') {
    $sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (substr($sn, -10) === '/index.php') {
        $prefix = rtrim(substr($sn, 0, -10), '/'); // '', '/site', '/sub/dir'
    }
}
if ($prefix !== '' && $prefix !== '/' && strpos($path, $prefix) === 0) {
    $rest = substr($path, strlen($prefix));
    $path = $rest === '' ? '/' : $rest;
}
$path = rtrim($path, '/') ?: '/';
$AB = admin_base();          // например /admin-a1b2c3d4
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

function view(string $file, array $data = []): void {
    extract($data, EXTR_SKIP);
    require dirname(__FILE__) . '/views/' . $file . '.php';
}

/* ---------- статика и загрузка заявок в админке ---------- */
if ($isPost) {
    if ($path === '/order') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || $phone === '') redirect(url('/?sent=error#order'));
        if (empty($_POST['consent'])) redirect(url('/?sent=noconsent#order'));
        $ip = client_ip();
        // Фото изделия (необязательно): сохраняем только валидное изображение
        $photoPath = '';
        if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $saved = save_upload($_FILES['photo']);
            if ($saved !== null) $photoPath = $saved;
        }
        db()->prepare("INSERT INTO orders(name,phone,email,service,message,visit_date,visit_time,pd_consent_at,photo_path)
                       VALUES(?,?,?,?,?,?,?,?,?)")
           ->execute([$name, $phone, trim($_POST['email'] ?? ''), trim($_POST['service'] ?? ''),
                      trim($_POST['message'] ?? ''), trim($_POST['visit_date'] ?? ''),
                      trim($_POST['visit_time'] ?? ''),
                      date('Y-m-d H:i') . " с IP {$ip}", $photoPath]);
        redirect(url('/?sent=ok#order'));
    }
    admin_post($path, $AB);
    http_response_code(404); exit('Не найдено');
}

/* ---------- GET: статические файлы, uploads, админка, витрина ---------- */
if ($path === '/favicon.ico') {
    $fav = cfg()['upload_dir'] . '/favicon/favicon.ico';
    if (is_file($fav)) { header('Content-Type: image/x-icon'); readfile($fav); exit; }
    redirect(url('/'));
}
foreach (['/assets', '/uploads'] as $pre) {
    if (strpos($path, $pre . '/') === 0) {
        $rel = substr($path, strlen($pre) + 1);
        $dir = $pre === '/assets' ? __DIR__ . '/assets' : cfg()['upload_dir'];
        $real = realpath($dir . '/' . $rel);
        $root = realpath($dir);
        if ($real === false || $root === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
            http_response_code(404); exit('Не найдено');
        }
        $types = ['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml',
                  'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp',
                  'gif'=>'image/gif','ico'=>'image/x-icon'];
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=604800');
        readfile($real); exit;
    }
}

/* ---------- витрина ---------- */
if ($path === '/') {
    $s = get_settings();
    $services = db()->query("SELECT * FROM services WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
    $advantages = db()->query("SELECT * FROM advantages ORDER BY id")->fetchAll();
    $gallery = db()->query("SELECT * FROM gallery_items ORDER BY id DESC LIMIT 6")->fetchAll();
    $reviews = db()->query("SELECT * FROM reviews WHERE is_public=1 ORDER BY created_at DESC, id DESC LIMIT 6")->fetchAll();

    // направления -> вкладки цен
    $groups = [
        'meh'    => ['Пошив меха', 'Пошив меха с перекроем', 'Ремонт меха'],
        'kozha'  => ['Пошив кожи', 'Ремонт кожи и дублёнок'],
        'poshiv' => ['Пошив верхней одежды', 'Пошив лёгкого платья'],
        'remont' => ['Ремонт верхней одежды', 'Ремонт лёгкой одежды'],
    ];
    $byCat = [];
    foreach ($services as $srv) { $byCat[$srv['category']][] = $srv; }
    $used = array_merge(...array_values($groups));
    $leftover = [];
    foreach ($byCat as $cat => $items) {
        if (!in_array($cat, $used, true)) $leftover = array_merge($leftover, $items);
    }
    $priceTabs = [];
    foreach (DEFAULT_DIRECTIONS() as $d) {
        $items = [];
        foreach ($groups[$d['tab']] ?? [] as $cat) $items = array_merge($items, $byCat[$cat] ?? []);
        if ($d['tab'] === 'remont') $items = array_merge($items, $leftover);
        if ($items) $priceTabs[] = ['dir' => $d, 'items' => $items];
    }

    // фото карточек направлений: настройка -> фото галереи той же категории -> любое
    $galImgs = [];
    foreach ($gallery as $g) {
        $galImgs[] = $g['before_image_path'] ?: $g['image_path'];
        $galImgs[] = $g['after_image_path'];
    }
    $fallback = '';
    foreach ($galImgs as $p) if ($p !== '') { $fallback = $p; break; }
    $directions = [];
    foreach (DEFAULT_DIRECTIONS() as $d) {
        $img = trim($s[$d['image_key']] ?? '');
        if ($img === '') {
            $row = db()->prepare("SELECT image_path, before_image_path FROM gallery_items WHERE category=? ORDER BY id DESC LIMIT 1");
            $row->execute([$d['title']]);
            $r = $row->fetch();
            if ($r) $img = $r['image_path'] ?: $r['before_image_path'];
        }
        if ($img === '') $img = $fallback;
        $d['image'] = $img;
        $directions[] = $d;
    }

    view('index', compact('s','services','advantages','gallery','reviews','priceTabs','directions') + ['steps' => DEFAULT_STEPS()]);
    exit;
}

if ($path === '/privacy') {
    view('privacy', ['s' => get_settings(), 'policy_updated' => '01.10.2026']);
    exit;
}

/* ---------- админка: GET ---------- */
if ($path === '/admin' || strpos($path, '/admin/') === 0) {
    http_response_code(404); exit('Не найдено');   // обычный /admin скрыт
}
if ($path === '/sitemap-yandex.xml') {
    require __DIR__ . '/sitemap-yandex.php'; exit;
}
if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php'; exit;
}
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    readfile(__DIR__ . '/robots.txt'); exit;
}
if ($path === $AB || strpos($path, $AB . '/') === 0) {
    admin_get($path, $AB);
    http_response_code(404); exit('Не найдено');
}

http_response_code(404);
view('404', ['s' => get_settings()]);
