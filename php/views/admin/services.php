<?php /* Услуги и цены. Данные: $admin, $AB, $today, $services */ ?>
<?php ob_start(); ?>

<div class="panel">
  <div class="panel-head">
    <h2>Список услуг (<?= count($services) ?>)</h2>
    <button class="btn btn-gold btn-sm" data-open="svcModal" onclick="resetSvc()">＋ Добавить услугу</button>
  </div>
  <table>
    <thead><tr><th>Порядок</th><th>Категория</th><th>Название</th><th>Цена</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($services as $srv): ?>
      <tr>
        <td><?= (int)$srv['sort_order'] ?></td>
        <td><span class="badge badge-off"><?= e($srv['category']) ?></span></td>
        <td><b><?= e($srv['title']) ?></b><br><small style="color:#8a8174"><?= e(mb_substr($srv['description'], 0, 90)) ?></small></td>
        <td style="white-space:nowrap"><b><?= e($srv['price']) ?></b></td>
        <td><?php if ($srv['is_active']): ?><span class="badge badge-on">Активна</span><?php else: ?><span class="badge badge-off">Скрыта</span><?php endif; ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm" data-open="svcModal" data-edit='<?= htmlspecialchars(json_encode($srv, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>✏️ Изменить</button>
          <form method="post" action="<?= e(url($AB . '/services/' . $srv['id'] . '/delete')) ?>" data-confirm="Удалить услугу «<?= e($srv['title']) ?>»?" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Модалка добавления/редактирования -->
<div class="modal-backdrop" id="svcModal">
  <div class="modal">
    <button type="button" class="modal-close">×</button>
    <h2 id="svcTitle">Новая услуга</h2>
    <form method="post" action="<?= e(url($AB . '/services/save')) ?>" id="svcForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="0">
      <div class="form-grid">
        <div class="field full"><label>Название *</label><input type="text" name="title" required placeholder="Например: Пошив шубы по меркам"></div>
        <div class="field full"><label>Описание</label><textarea name="description" placeholder="Краткое описание услуги"></textarea></div>
        <div class="field"><label>Цена</label><input type="text" name="price" placeholder="от 5 000 ₽"></div>
        <div class="field"><label>Категория</label>
          <select name="category">
            <?php foreach (['Пошив','Реставрация','Ремонт','Другое'] as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Порядок сортировки</label><input type="number" name="sort_order" value="0"></div>
        <div class="field" style="display:flex;align-items:end;padding-bottom:6px">
          <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> Отображать на сайте</label>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-gold" type="submit">Сохранить</button>
        <button class="btn" type="button" onclick="this.closest('.modal-backdrop').classList.remove('open')">Отмена</button>
      </div>
    </form>
  </div>
</div>

<script>
function resetSvc() {
  document.getElementById('svcTitle').textContent = 'Новая услуга';
  const f = document.getElementById('svcForm');
  f.querySelector('[name=id]').value = 0;
  f.reset();
}
document.querySelectorAll('#svcModal [data-edit]');
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-edit]');
  if (!btn || btn.dataset.open !== 'svcModal') return;
  document.getElementById('svcTitle').textContent = 'Редактирование услуги';
});
</script>

<?php $content = ob_get_clean();
$page = 'services'; $pageTitle = 'Услуги'; $pageHeading = 'Услуги и цены';
require __DIR__ . '/_base.php'; ?>
