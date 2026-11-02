<?php /* Галерея работ. Данные: $admin, $AB, $today, $items */ ?>
<?php ob_start(); ?>

<div class="panel">
  <div class="panel-head">
    <h2>Работы (<?= count($items) ?>)</h2>
    <div style="display:flex;gap:8px">
      <?php $needMigrate = false;
      foreach ($items as $g) {
          if (!empty($g['image_path']) && (empty($g['before_image_path']) || empty($g['after_image_path']))) $needMigrate = true;
      } ?>
      <?php if ($needMigrate): ?>
      <form method="post" action="<?= e(url($AB . '/gallery/migrate')) ?>" style="display:inline">
        <?= csrf_field() ?>
        <button class="btn btn-sm" type="submit" title="Перенести старые одиночные фото в слоты «До»/«После», чтобы появился слайдер сравнения">⟳ Перенести старые фото</button>
      </form>
      <?php endif; ?>
      <button class="btn btn-gold btn-sm" data-open="galModal" onclick="resetGal()">＋ Добавить работу</button>
    </div>
  </div>
  <table>
    <thead><tr><th>Фото «До»</th><th>Фото «После»</th><th>Название</th><th>До</th><th>После</th><th>Цена</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($items as $g): ?>
      <tr>
        <td><?php if (!empty($g['before_image_path'])): ?><img class="thumb" src="<?= e(url($g['before_image_path'])) ?>" alt=""><?php elseif (!empty($g['image_path'])): ?><img class="thumb" src="<?= e(url($g['image_path'])) ?>" alt=""><br><small style="color:#8a7f6e">общее</small><?php else: ?><div class="thumb-placeholder">нет фото</div><?php endif; ?></td>
        <td><?php if (!empty($g['after_image_path'])): ?><img class="thumb" src="<?= e(url($g['after_image_path'])) ?>" alt=""><?php elseif (!empty($g['image_path']) && !empty($g['before_image_path'])): ?><img class="thumb" src="<?= e(url($g['image_path'])) ?>" alt=""><br><small style="color:#8a7f6e">общее</small><?php else: ?><div class="thumb-placeholder">нет фото</div><?php endif; ?></td>
        <td><b><?= e($g['title']) ?></b></td>
        <td style="max-width:200px"><?= e(mb_substr($g['before_text'], 0, 80)) ?></td>
        <td style="max-width:200px"><?= e(mb_substr($g['after_text'], 0, 80)) ?></td>
        <td><?= e($g['price'] ?: '—') ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm" data-open="galModal" data-edit='<?= htmlspecialchars(json_encode($g, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>✏️ Изменить</button>
          <form method="post" action="<?= e(url($AB . '/gallery/' . $g['id'] . '/delete')) ?>" data-confirm="Удалить работу «<?= e($g['title']) ?>»?" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-backdrop" id="galModal">
  <div class="modal">
    <button type="button" class="modal-close">×</button>
    <h2>Работа в галерее</h2>
    <form method="post" action="<?= e(url($AB . '/gallery/save')) ?>" enctype="multipart/form-data" id="galForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="0">
      <div class="form-grid">
        <div class="field full"><label>Название *</label><input type="text" name="title" required placeholder="Например: Реставрация шубы"></div>
        <div class="field full"><label>Что было (до)</label><textarea name="before_text" placeholder="Описание состояния до работы. Не пишите «До:» — метка уже есть на сайте"></textarea></div>
        <div class="field full"><label>Что стало (после)</label><textarea name="after_text" placeholder="Результат или срок, например: Срок — 12 дней. Не пишите «После:»"></textarea></div>
        <div class="field"><label>Цена</label><input type="text" name="price" placeholder="2 000 ₽"></div>
        <div class="field"><label>Направление (для фото в блоке «Что мы делаем»)</label>
          <select name="category">
            <option value="">— не выбрано —</option>
            <?php foreach (['Меховое ателье','Кожа и дублёнки','Верхняя одежда и лёгкое платье','Ремонт и трикотаж'] as $c): ?>
            <option value="<?= e($c) ?>"><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Фото «До»</label>
          <img id="curBefore" class="thumb" src="" alt="" style="display:none;margin-bottom:6px">
          <input type="file" name="before_image" accept="image/*" style="padding-top:16px">
          <small class="hint">Если пустое — сохранится текущее фото</small>
        </div>
        <div class="field"><label>Фото «После»</label>
          <img id="curAfter" class="thumb" src="" alt="" style="display:none;margin-bottom:6px">
          <input type="file" name="after_image" accept="image/*" style="padding-top:16px">
          <small class="hint">Если пустое — сохранится текущее фото</small>
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
function resetGal() {
  const f = document.getElementById('galForm');
  f.querySelector('[name=id]').value = 0;
  f.reset();
  document.getElementById('curBefore').style.display = 'none';
  document.getElementById('curAfter').style.display = 'none';
}
document.querySelectorAll('[data-edit]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    let g; try { g = JSON.parse(btn.dataset.edit); } catch (e) { return; }
    if (btn.dataset.open !== 'galModal') return;
    const cb = document.getElementById('curBefore'), ca = document.getElementById('curAfter');
    const base = '<?= e(rtrim(url('/'), '/')) ?>/';
    const bPath = g.before_image_path || g.image_path || '';
    const aPath = g.after_image_path || g.image_path || '';
    cb.src = bPath ? base + bPath.replace(/^\//, '') : ''; cb.style.display = bPath ? '' : 'none';
    ca.src = aPath ? base + aPath.replace(/^\//, '') : ''; ca.style.display = aPath ? '' : 'none';
    const sel = document.querySelector('#galForm [name=category]');
    if (sel) sel.value = g.category || '';
  });
});
</script>

<?php $content = ob_get_clean();
$page = 'gallery'; $pageTitle = 'Галерея'; $pageHeading = 'Галерея работ';
require __DIR__ . '/_base.php'; ?>
