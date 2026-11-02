<?php /* Условия и надбавки. Данные: $admin, $AB, $today, $advantages, $s, $text_fields */ ?>
<?php ob_start(); ?>

<div class="panel">
  <div class="panel-head">
    <h2>Карточки «Прозрачные условия» (<?= count($advantages) ?>)</h2>
    <button class="btn btn-gold btn-sm" data-open="advModal" onclick="resetAdv()">＋ Добавить карточку</button>
  </div>
  <table>
    <thead><tr><th>Иконка</th><th>Название</th><th>Текст</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($advantages as $a): ?>
      <tr>
        <td style="font-size:20px"><?= e($a['icon']) ?></td>
        <td><b><?= e($a['title']) ?></b></td>
        <td style="max-width:420px"><?= e($a['text']) ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm" data-open="advModal" data-edit='<?= htmlspecialchars(json_encode($a, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>✏️ Изменить</button>
          <form method="post" action="<?= e(url($AB . '/advantages/' . $a['id'] . '/delete')) ?>" data-confirm="Удалить карточку?" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <div class="panel-head"><h2>Тексты примечаний к ценам</h2></div>
  <p class="muted" style="margin-top:-6px">Эти тексты показываются под блоком цен на сайте.</p>
  <form method="post" action="<?= e(url($AB . '/advantages/text')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($text_fields as [$key, $label]): ?>
    <div class="field" style="margin-bottom:14px">
      <label><?= e($label) ?></label>
      <textarea name="<?= e($key) ?>" rows="2" placeholder="<?= e($label) ?>"><?= e($s[$key] ?? '') ?></textarea>
    </div>
    <?php endforeach; ?>
    <div class="form-actions">
      <button class="btn btn-gold" type="submit">Сохранить тексты</button>
    </div>
  </form>
</div>

<div class="modal-backdrop" id="advModal">
  <div class="modal">
    <button type="button" class="modal-close">×</button>
    <h2>Карточка условия</h2>
    <form method="post" action="<?= e(url($AB . '/advantages/save')) ?>" id="advForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="0">
      <div class="form-grid">
        <div class="field"><label>Иконка (символ эмодзи)</label><input type="text" name="icon" placeholder="⚡"></div>
        <div class="field"><label>Название *</label><input type="text" name="title" required placeholder="Срочный ремонт за 1 день"></div>
        <div class="field full"><label>Текст</label><textarea name="text" placeholder="Описание условия…"></textarea></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-gold" type="submit">Сохранить</button>
        <button class="btn" type="button" onclick="this.closest('.modal-backdrop').classList.remove('open')">Отмена</button>
      </div>
    </form>
  </div>
</div>

<script>
function resetAdv() {
  const f = document.getElementById('advForm');
  f.querySelector('[name=id]').value = 0;
  f.reset();
}
</script>

<?php $content = ob_get_clean();
$page = 'advantages'; $pageTitle = 'Условия'; $pageHeading = 'Условия и надбавки';
require __DIR__ . '/_base.php'; ?>
