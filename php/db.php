<?php
/* SQLite-схема + сид дефолтных данных (полный прейскурант со старого сайта) */

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        $dir = dirname($cfg['db_path']);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . $cfg['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function init_db(): void {
    $d = db();
    $d->exec("
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS services (
  id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '',
  price TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT 'Пошив',
  sort_order INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS gallery_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, image_path TEXT NOT NULL DEFAULT '',
  before_image_path TEXT NOT NULL DEFAULT '', after_image_path TEXT NOT NULL DEFAULT '',
  before_text TEXT NOT NULL DEFAULT '', after_text TEXT NOT NULL DEFAULT '',
  price TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')));
CREATE TABLE IF NOT EXISTS advantages (
  id INTEGER PRIMARY KEY AUTOINCREMENT, icon TEXT NOT NULL DEFAULT '✦', title TEXT NOT NULL, text TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT, author TEXT NOT NULL, rating INTEGER NOT NULL DEFAULT 5,
  text TEXT NOT NULL, is_public INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')));
CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, phone TEXT NOT NULL, email TEXT NOT NULL DEFAULT '',
  service TEXT NOT NULL DEFAULT '', message TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'new',
  pd_consent_at TEXT NOT NULL DEFAULT '', visit_date TEXT NOT NULL DEFAULT '', visit_time TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')));
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL);");

    // Дефолты
    $stmt = $d->prepare("INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)");
    foreach (DEFAULT_SETTINGS() as $k => $v) { $stmt->execute([$k, $v]); }

    if ((int)$d->query("SELECT COUNT(*) FROM services")->fetchColumn() === 0) {
        $ins = $d->prepare("INSERT INTO services(title,description,price,category,sort_order) VALUES(?,?,?,?,?)");
        foreach (DEFAULT_SERVICES() as $i => $s) { $ins->execute([$s[0], $s[1], $s[2], $s[3], $i]); }
    }
    if ((int)$d->query("SELECT COUNT(*) FROM advantages")->fetchColumn() === 0) {
        $ins = $d->prepare("INSERT INTO advantages(icon,title,text) VALUES(?,?,?)");
        foreach (DEFAULT_ADVANTAGES() as $a) { $ins->execute($a); }
    }
    if ((int)$d->query("SELECT COUNT(*) FROM gallery_items")->fetchColumn() === 0) {
        $ins = $d->prepare("INSERT INTO gallery_items(title,before_text,after_text,price) VALUES(?,?,?,?)");
        foreach (DEFAULT_GALLERY() as $g) { $ins->execute($g); }
    }
    if ((int)$d->query("SELECT COUNT(*) FROM admins")->fetchColumn() === 0) {
        $cfg = require __DIR__ . '/config.php';
        $d->prepare("INSERT INTO admins(login,password_hash) VALUES(?,?)")
          ->execute([$cfg['admin_login'], password_hash($cfg['admin_password'], PASSWORD_DEFAULT)]);
    }
}

function get_settings(): array {
    $rows = db()->query("SELECT key,value FROM settings")->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['key']] = $r['value']; }
    return $out;
}
function set_setting(string $k, string $v): void {
    db()->prepare("INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")
        ->execute([$k, $v]);
}
