<?php /* Заявки. Данные: $admin, $AB, $today, $orders, $status_labels, $cur_status */ ?>
<?php ob_start(); ?>

<div class="filters">
  <a href="<?= e(url($AB . '/orders')) ?>"<?= $cur_status === '' ? ' class="active"' : '' ?>>Все</a>
  <?php foreach ($status_labels as $key => $label): ?>
  <a href="<?= e(url($AB . '/orders?status=' . urlencode((string)$key))) ?>"<?= $cur_status === $key ? ' class="active"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <?php if ($orders): ?>
  <table>
    <thead><tr><th>ID</th><th>Дата</th><th>Клиент</th><th>Контакты</th><th>Услуга</th><th>Комментарий</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td>#<?= (int)$o['id'] ?></td>
        <td><?= e(substr($o['created_at'], 0, 16)) ?></td>
        <td><b><?= e($o['name']) ?></b></td>
        <td>
          <a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a>
          <?php if (!empty($o['email'])): ?><br><a href="mailto:<?= e($o['email']) ?>" style="font-size:12px;color:#8a8174"><?= e($o['email']) ?></a><?php endif; ?>
        </td>
        <td><?= e($o['service'] ?: '—') ?></td>
        <td style="max-width:260px">
          <?php if (!empty($o['photo_path'])): ?>
          <a href="<?= e(url($o['photo_path'])) ?>" target="_blank" rel="noopener" title="Открыть в полном размере">
            <img src="<?= e(url($o['photo_path'])) ?>" alt="Фото изделия" class="order-photo">
          </a>
          <?php endif; ?>
          <?php if (!empty($o['visit_date']) || !empty($o['visit_time'])): ?>
          <div class="order-visit">📅 <?= e($o['visit_date']) ?><?= $o['visit_time'] ? ' в ' . e($o['visit_time']) : '' ?></div>
          <?php endif; ?>
          <?= e($o['message'] ?: '—') ?>
          <?php if (!empty($o['pd_consent_at'])): ?>
          <div class="order-consent">Согласие на ПД: <?= e($o['pd_consent_at']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= e($o['status']) ?>"><?= e($status_labels[$o['status']] ?? $o['status']) ?></span></td>
        <td>
          <form method="post" action="<?= e(url($AB . '/orders/' . $o['id'] . '/status')) ?>" style="display:flex;gap:6px;margin-bottom:6px">
            <?= csrf_field() ?>
            <select name="status" style="padding:6px 8px;border-radius:8px;border:1px solid rgba(32,27,21,.15);font:inherit;font-size:12.5px">
              <?php foreach ($status_labels as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $o['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm" type="submit">OK</button>
          </form>
          <form method="post" action="<?= e(url($AB . '/orders/' . $o['id'] . '/delete')) ?>" data-confirm="Удалить заявку #<?= (int)$o['id'] ?>?">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty">Заявок нет.</div>
  <?php endif; ?>
</div>

<?php $content = ob_get_clean();
$page = 'orders'; $pageTitle = 'Заявки'; $pageHeading = 'Заявки с сайта';
require __DIR__ . '/_base.php'; ?>
