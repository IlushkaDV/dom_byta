<?php
/* Админ-панель: роуты и шаблоны. Вызывается из index.php:
   admin_get($path, $AB) для GET, admin_post($path, $AB) для POST. */

const ORDERS_STATUS_LABELS = ['new' => 'Новая', 'in_progress' => 'В работе', 'done' => 'Завершена', 'cancelled' => 'Отклонена'];

const SETTING_FIELDS = [
    ["site_name", "Название сайта"],
    ["tagline", "Короткий слоган (meta-описание)"],
    ["hero_kicker", "Надзаголовок на первом экране"],
    ["hero_title", "Заголовок на первом экране"],
    ["hero_subtitle", "Подзаголовок на первом экране"],
    ["about_text", "Текст раздела «О нас» (резервный)"],
    ["manager_title", "Заголовок блока «Лицо ателье»"],
    ["manager_quote", "Цитата заведующей (блок «Лицо ателье»)"],
    ["manager_photo", "Фото заведующей (блок «Лицо ателье»)"],
    ["manager_signature", "Рукописная подпись заведующей (PNG с прозрачным фоном)"],
    ["hero_image", "Фоновое фото первого экрана (десктоп, горизонтальное ≥1600px)"],
    ["hero_image_mobile", "Фото первого экрана для телефонов (вертикальное, 9:16 — необязательно)"],
    ["logo_image", "Логотип в шапке (PNG с прозрачным фоном)"],
    ["favicon_image", "Фавикон — иконка вкладки браузера (квадратный PNG, 512×512)"],
    ["dir_img_meh", "Фото карточки «Меховое ателье» (блок «Что мы делаем»)"],
    ["dir_img_kozha", "Фото карточки «Кожа и дублёнки»"],
    ["dir_img_poshiv", "Фото карточки «Верхняя одежда и лёгкое платье»"],
    ["dir_img_remont", "Фото карточки «Ремонт и трикотаж»"],
    ["manager_name", "Имя руководителя"],
    ["manager_role", "Должность руководителя"],
    ["manager_note", "Примечание о руководителе"],
    ["address", "Адрес"],
    ["phone", "Телефон ателье"],
    ["phone_tc", "Телефон ТЦ"],
    ["email", "E-mail"],
    ["hours_week", "График (будни)"],
    ["hours_weekend", "График (выходные)"],
];

const IMAGE_SETTINGS = ['manager_photo', 'manager_signature', 'hero_image', 'hero_image_mobile',
    'dir_img_meh', 'dir_img_kozha', 'dir_img_poshiv', 'dir_img_remont', 'logo_image', 'favicon_image'];

function aview(string $file, array $data = []): void {
    extract($data, EXTR_SKIP);
    require __DIR__ . '/views/admin/' . $file . '.php';
    exit;
}

/* ---------- GET ---------- */
function admin_get(string $path, string $AB): void {
    $sub = rtrim(substr($path, strlen($AB)), '/') ?: '';

    if ($sub === '/login') {
        if (current_admin()) redirect(url($AB));
        aview('login', ['error' => '', 'AB' => $AB]);
    }
    if ($sub === '/logout') {
        clear_admin_cookie();
        redirect(url($AB . '/login'));
    }

    $admin = current_admin();
    if (!$admin) redirect(url($AB . '/login'));
    $today = date('d.m.Y');

    if ($sub === '') {
        $d = db();
        $stats = [
            'orders_new'    => (int)$d->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
            'orders_total'  => (int)$d->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'services'      => (int)$d->query("SELECT COUNT(*) FROM services WHERE is_active=1")->fetchColumn(),
            'gallery'       => (int)$d->query("SELECT COUNT(*) FROM gallery_items")->fetchColumn(),
            'reviews'       => (int)$d->query("SELECT COUNT(*) FROM reviews WHERE is_public=1")->fetchColumn(),
        ];
        $recent_orders = $d->query("SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 5")->fetchAll();
        aview('dashboard', compact('admin', 'AB', 'today', 'stats', 'recent_orders'));
    }

    if ($sub === '/orders') {
        $status = $_GET['status'] ?? '';
        if (!array_key_exists($status, ORDERS_STATUS_LABELS)) $status = '';
        if ($status !== '') {
            $st = db()->prepare("SELECT * FROM orders WHERE status=? ORDER BY created_at DESC, id DESC");
            $st->execute([$status]);
            $orders = $st->fetchAll();
        } else {
            $orders = db()->query("SELECT * FROM orders ORDER BY created_at DESC, id DESC")->fetchAll();
        }
        aview('orders', compact('admin', 'AB', 'today', 'orders') + [
            'status_labels' => ORDERS_STATUS_LABELS, 'cur_status' => $status]);
    }

    if ($sub === '/services') {
        $services = db()->query("SELECT * FROM services ORDER BY sort_order, id")->fetchAll();
        aview('services', compact('admin', 'AB', 'today', 'services'));
    }

    if ($sub === '/advantages') {
        $advantages = db()->query("SELECT * FROM advantages ORDER BY id")->fetchAll();
        $text_fields = [['prices_note_1', 'Примечание к ценам 1'], ['prices_note_2', 'Примечание к ценам 2']];
        for ($i = 1; $i <= 9; $i++) $text_fields[] = ["prices_surcharge_$i", "Надбавка $i"];
        aview('advantages', compact('admin', 'AB', 'today', 'advantages', 'text_fields') + ['s' => get_settings()]);
    }

    if ($sub === '/gallery') {
        $items = db()->query("SELECT * FROM gallery_items ORDER BY id DESC")->fetchAll();
        aview('gallery', compact('admin', 'AB', 'today', 'items'));
    }

    if ($sub === '/reviews') {
        $reviews = db()->query("SELECT * FROM reviews ORDER BY created_at DESC, id DESC")->fetchAll();
        aview('reviews', compact('admin', 'AB', 'today', 'reviews'));
    }

    if ($sub === '/settings') {
        aview('settings', compact('admin', 'AB', 'today') + [
            's' => get_settings(), 'fields' => SETTING_FIELDS, 'image_keys' => IMAGE_SETTINGS]);
    }

    if ($sub === '/password') {
        aview('password', compact('admin', 'AB', 'today'));
    }
}

/* ---------- POST ---------- */
function admin_post(string $path, string $AB): void {
    $sub = rtrim(substr($path, strlen($AB)), '/') ?: '';

    /* вход: не требует ни CSRF, ни сессии */
    if ($sub === '/login') {
        $ip = client_ip();
        if (login_blocked($ip)) {
            http_response_code(429);
            aview('login', ['error' => 'Слишком много попыток входа. Подождите 15 минут.', 'AB' => $AB]);
        }
        $login = trim($_POST['login_name'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        $cfg = cfg();
        $row = db()->prepare("SELECT * FROM admins WHERE login=?");
        $row->execute([$login]);
        $u = $row->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            login_ok($ip);
            set_admin_cookie($login);
            redirect(url($AB));
        }
        login_fail($ip);
        aview('login', ['error' => 'Неверный логин или пароль.', 'AB' => $AB]);
    }

    /* всё остальное — только под админом + CSRF */
    $admin = current_admin();
    if (!$admin) { http_response_code(403); exit('Нет доступа'); }
    check_csrf();

    if (preg_match('#^/orders/(\d+)/status$#', $sub, $m)) {
        $status = $_POST['status'] ?? '';
        if (array_key_exists($status, ORDERS_STATUS_LABELS)) {
            $st = db()->prepare("UPDATE orders SET status=? WHERE id=?");
            $st->execute([$status, (int)$m[1]]);
        }
        redirect(url($AB . '/orders'));
    }
    if (preg_match('#^/orders/(\d+)/delete$#', $sub, $m)) {
        $st = db()->prepare("DELETE FROM orders WHERE id=?");
        $st->execute([(int)$m[1]]);
        redirect(url($AB . '/orders'));
    }

    if ($sub === '/services/save') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { http_response_code(400); exit('Название обязательно'); }
        $id = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $vals = [$title, trim($_POST['description'] ?? ''), trim($_POST['price'] ?? ''),
                 trim($_POST['category'] ?? 'Пошив') ?: 'Пошив', (int)($_POST['sort_order'] ?? 0), $active];
        $d = db();
        if ($id) {
            $d->prepare("UPDATE services SET title=?, description=?, price=?, category=?, sort_order=?, is_active=? WHERE id=?")
              ->execute(array_merge($vals, [$id]));
        } else {
            $d->prepare("INSERT INTO services(title, description, price, category, sort_order, is_active) VALUES(?,?,?,?,?,?)")
              ->execute($vals);
        }
        redirect(url($AB . '/services'));
    }
    if (preg_match('#^/services/(\d+)/delete$#', $sub, $m)) {
        db()->prepare("DELETE FROM services WHERE id=?")->execute([(int)$m[1]]);
        redirect(url($AB . '/services'));
    }

    if ($sub === '/advantages/save') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { http_response_code(400); exit('Название обязательно'); }
        $icon = mb_substr(trim($_POST['icon'] ?? ''), 0, 8) ?: '✦';
        $text = trim($_POST['text'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $d = db();
        if ($id) $d->prepare("UPDATE advantages SET icon=?, title=?, text=? WHERE id=?")->execute([$icon, $title, $text, $id]);
        else     $d->prepare("INSERT INTO advantages(icon, title, text) VALUES(?,?,?)")->execute([$icon, $title, $text]);
        redirect(url($AB . '/advantages'));
    }
    if (preg_match('#^/advantages/(\d+)/delete$#', $sub, $m)) {
        db()->prepare("DELETE FROM advantages WHERE id=?")->execute([(int)$m[1]]);
        redirect(url($AB . '/advantages'));
    }
    if ($sub === '/advantages/text') {
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'prices_note_') === 0 || strpos($k, 'prices_surcharge_') === 0) {
                set_setting($k, trim((string)$v));
            }
        }
        redirect(url($AB . '/advantages?saved=1'));
    }

    if ($sub === '/gallery/save') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { http_response_code(400); exit('Название обязательно'); }
        $id = (int)($_POST['id'] ?? 0);
        $beforeText = trim($_POST['before_text'] ?? '');
        $afterText  = trim($_POST['after_text'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $newImg    = save_upload($_FILES['image'] ?? []);
        $newBefore = save_upload($_FILES['before_image'] ?? []);
        $newAfter  = save_upload($_FILES['after_image'] ?? []);
        $d = db();
        if ($id) {
            $st = $d->prepare("SELECT * FROM gallery_items WHERE id=?");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) { http_response_code(404); exit('Работа не найдена'); }
            $img    = $newImg    !== null ? $newImg    : $row['image_path'];
            $before = $newBefore !== null ? $newBefore : $row['before_image_path'];
            $after  = $newAfter  !== null ? $newAfter  : $row['after_image_path'];
            if ($before === '' && $after === '' && $img !== '') { $before = $after = $img; }
            $d->prepare("UPDATE gallery_items SET title=?, before_text=?, after_text=?, price=?, image_path=?, before_image_path=?, after_image_path=?, category=? WHERE id=?")
              ->execute([$title, $beforeText, $afterText, $price, $img, $before, $after, $category, $id]);
        } else {
            $b = $newBefore ?: ''; $a = $newAfter ?: '';
            $img = $newImg ?: '';
            if (!$b && !$a && $img) { $b = $a = $img; }
            $d->prepare("INSERT INTO gallery_items(title, before_text, after_text, price, image_path, before_image_path, after_image_path, category) VALUES(?,?,?,?,?,?,?,?)")
              ->execute([$title, $beforeText, $afterText, $price, $img, $b, $a, $category]);
        }
        // нормализация подписей До/После/Срок
        foreach ($d->query("SELECT id, after_text FROM gallery_items")->fetchAll() as $r) {
            $at = trim((string)$r['after_text']);
            if ($at === '') continue;
            $fixed = preg_replace('/^\s*(до|после)\s*[:–-]\s*/ui', '', $at);
            if (preg_match('/^готово\s+через\s+(.+)$/iu', $fixed, $mm)) $fixed = 'Срок — ' . $mm[1];
            elseif (preg_match('/^срок\b/iu', $fixed) && mb_substr($fixed, 0, 4) !== 'Срок') $fixed = 'Срок' . mb_substr($fixed, 4);
            if ($fixed !== $at) $d->prepare("UPDATE gallery_items SET after_text=? WHERE id=?")->execute([$fixed, $r['id']]);
        }
        redirect(url($AB . '/gallery'));
    }
    if ($sub === '/gallery/migrate') {
        $moved = 0;
        $d = db();
        foreach ($d->query("SELECT id, image_path, before_image_path, after_image_path FROM gallery_items")->fetchAll() as $r) {
            $img = $r['image_path'] ?: '';
            $b = $r['before_image_path'] ?: ''; $a = $r['after_image_path'] ?: '';
            if ($img && (!$b || !$a)) {
                $d->prepare("UPDATE gallery_items SET before_image_path=?, after_image_path=? WHERE id=?")
                  ->execute([$b ?: $img, $a ?: $img, $r['id']]);
                $moved++;
            }
        }
        redirect(url($AB . '/gallery?saved=1'));
    }
    if (preg_match('#^/gallery/(\d+)/delete$#', $sub, $m)) {
        $d = db();
        $st = $d->prepare("SELECT image_path, before_image_path, after_image_path FROM gallery_items WHERE id=?");
        $st->execute([(int)$m[1]]);
        $row = $st->fetch();
        $d->prepare("DELETE FROM gallery_items WHERE id=?")->execute([(int)$m[1]]);
        if ($row) foreach ([$row['image_path'], $row['before_image_path'], $row['after_image_path']] as $p) remove_upload($p);
        redirect(url($AB . '/gallery'));
    }

    if ($sub === '/reviews/save') {
        $author = trim($_POST['author'] ?? '');
        $text = trim($_POST['text'] ?? '');
        if ($author === '' || $text === '') { http_response_code(400); exit('Автор и текст обязательны'); }
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $pub = isset($_POST['is_public']) ? 1 : 0;
        $id = (int)($_POST['id'] ?? 0);
        $d = db();
        if ($id) $d->prepare("UPDATE reviews SET author=?, rating=?, text=?, is_public=? WHERE id=?")->execute([$author, $rating, $text, $pub, $id]);
        else     $d->prepare("INSERT INTO reviews(author, rating, text, is_public) VALUES(?,?,?,?)")->execute([$author, $rating, $text, $pub]);
        redirect(url($AB . '/reviews'));
    }
    if (preg_match('#^/reviews/(\d+)/delete$#', $sub, $m)) {
        db()->prepare("DELETE FROM reviews WHERE id=?")->execute([(int)$m[1]]);
        redirect(url($AB . '/reviews'));
    }

    if ($sub === '/settings') {
        $d = db();
        foreach (SETTING_FIELDS as [$key, $label]) {
            if (in_array($key, IMAGE_SETTINGS, true)) {
                $newPath = save_upload($_FILES[$key] ?? []);
                if ($newPath !== null) {
                    $old = get_settings()[$key] ?? '';
                    set_setting($key, $newPath);
                    if ($old) remove_upload($old);
                    if ($key === 'manager_signature') trim_signature($newPath);
                    if ($key === 'favicon_image') make_favicon($newPath);
                }
                if (!empty($_POST["{$key}_remove"])) {
                    $old = get_settings()[$key] ?? '';
                    set_setting($key, '');
                    if ($old) remove_upload($old);
                }
            } elseif (array_key_exists($key, $_POST)) {
                set_setting($key, trim((string)$_POST[$key]));
            }
        }
        redirect(url($AB . '/settings?saved=1'));
    }

    if ($sub === '/password') {
        $old = (string)($_POST['old'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');
        $st = $d = db()->prepare("SELECT * FROM admins WHERE login=?");
        $st->execute([$admin]);
        $u = $st->fetch();
        if (!$u || !password_verify($old, $u['password_hash'])) {
            redirect(url($AB . '/password?err=old'));
        }
        if (mb_strlen($new) < 6 || $new !== $confirm) {
            redirect(url($AB . '/password?err=short'));
        }
        db()->prepare("UPDATE admins SET password_hash=? WHERE id=?")
           ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        redirect(url($AB . '/password?saved=1'));
    }
}
