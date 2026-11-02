<?php /* Дашборд. Данные: $admin, $AB, $today, $stats, $recent_orders */ ?>
<?php ob_start(); ?>

<div class="stat-grid">
  <div class="stat-card gold"><b><?= (int)$stats['orders_new'] ?></b><span>новых заявок</span></div>
  <div class="stat-card"><b><?= (int)$stats['orders_total'] ?></b><span>заявок всего</span></div>
  <div class="stat-card"><b><?= (int)$stats['services'] ?></b><span>активных услуг</span></div>
  <div class="stat-card"><b><?= (int)$stats['gallery'] ?></b><span>работ в галерее</span></div>
  <div class="stat-card green"><b><?= (int)$stats['reviews'] ?></b><span>публичных отзывов</span></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Последние заявки</h2>
    <a href="<?= e(url($AB . '/orders')) ?>" class="btn btn-sm">Все заявки →</a>
  </div>
  <?php if ($recent_orders): ?>
  <table>
    <thead><tr><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Услуга</th><th>Статус</th></tr></thead>
    <tbody>
      <?php foreach ($recent_orders as $o): ?>
      <tr>
        <td><?= e(substr($o['created_at'], 0, 16)) ?></td>
        <td><b><?= e($o['name']) ?></b></td>
        <td><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></td>
        <td><?= e($o['service'] ?: '—') ?></td>
        <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(ORDERS_STATUS_LABELS[$o['status']] ?? $o['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty">Заявок пока нет — они появятся здесь после отправки формы с сайта.</div>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>Быстрые действия</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a data-open="svcModal" href="<?= e(url($AB . '/services')) ?>" class="btn btn-gold btn-sm">＋ Добавить услугу</a>
    <a data-open="addGalleryModal" href="<?= e(url($AB . '/gallery')) ?>" class="btn btn-sm">＋ Добавить работу в галерею</a>
    <a data-open="addReviewModal" href="<?= e(url($AB . '/reviews')) ?>" class="btn btn-sm">＋ Добавить отзыв</a>
    <a href="<?= e(url($AB . '/settings')) ?>" class="btn btn-sm">⚙ Настройки сайта</a>
  </div>
</div>

<?php $content = ob_get_clean();
$page = 'dashboard'; $pageTitle = 'Дашборд'; $pageHeading = 'Дашборд';
require __DIR__ . '/_base.php'; ?>
