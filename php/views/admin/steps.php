<?php /* Этапы работы (блок «Как мы работаем»). Данные: $admin, $AB, $today, $s, $steps */ ?>
<?php ob_start(); ?>

<div class="panel">
  <div class="panel-head"><h2>Заголовки блока</h2></div>
  <?php if (!empty($_GET['saved'])): ?><p class="muted" style="color:#1a7f37">Сохранено ✓</p><?php endif; ?>
  <form method="post" action="<?= e(url($AB . '/steps/text')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label>Надзаголовок (kicker)</label><input type="text" name="steps_kicker" value="<?= e($s['steps_kicker'] ?? '') ?>" placeholder="Как мы работаем"></div>
      <div class="field"><label>Заголовок</label><input type="text" name="steps_title" value="<?= e($s['steps_title'] ?? '') ?>" placeholder="Схема безопасной работы"></div>
      <div class="field full"><label>Подзаголовок</label><textarea name="steps_subtitle" rows="2"><?= e($s['steps_subtitle'] ?? '') ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-gold" type="submit">Сохранить заголовки</button></div>
  </form>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Этапы (<?= count($steps) ?>)</h2>
    <button class="btn btn-gold btn-sm" data-open="stepModal" onclick="resetStep()">＋ Добавить этап</button>
  </div>
  <table>
    <thead><tr><th>№</th><th>Название</th><th>Описание</th><th>Подпись</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($steps as $i => $st): ?>
      <tr>
        <td><b><?= $i + 1 ?></b></td>
        <td><b><?= e($st['title']) ?></b></td>
        <td style="max-width:380px"><?= e($st['text']) ?></td>
        <td class="muted"><?= e($st['meta']) ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="<?= e(url($AB . '/steps/' . $i . '/up')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm" <?= $i === 0 ? 'disabled' : '' ?>>↑</button></form>
          <form method="post" action="<?= e(url($AB . '/steps/' . $i . '/down')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm" <?= $i === count($steps) - 1 ? 'disabled' : '' ?>>↓</button></form>
          <button class="btn btn-sm" data-open="stepModal" data-edit='<?= htmlspecialchars(json_encode(['id' => $i] + $st, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>✏️ Изменить</button>
          <?php if (count($steps) > 1): ?>
          <form method="post" action="<?= e(url($AB . '/steps/' . $i . '/delete')) ?>" data-confirm="Удалить этот этап?" style="display:inline">
            <?= csrf_field() ?><button class="btn btn-danger" type="submit">Удалить</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted">Этапы показываются на сайте в блоке «Как мы работаем» именно в этом порядке. Иконка у карточки подбирается автоматически по номеру этапа.</p>
</div>

<div class="modal-backdrop" id="stepModal">
  <div class="modal">
    <button type="button" class="modal-close">×</button>
    <h2>Этап работы</h2>
    <form method="post" action="<?= e(url($AB . '/steps/save')) ?>" id="stepForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="-1">
      <div class="form-grid">
        <div class="field full"><label>Название этапа *</label><input type="text" name="title" required placeholder="Визит в ателье"></div>
        <div class="field full"><label>Описание</label><textarea name="text" rows="3" placeholder="Что происходит на этом шаге…"></textarea></div>
        <div class="field full"><label>Короткая подпись внизу карточки</label><input type="text" name="meta" placeholder="например: 6 этаж ТЦ «Дом Быта»"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-gold" type="submit">Сохранить</button>
        <button class="btn" type="button" onclick="this.closest('.modal-backdrop').classList.remove('open')">Отмена</button>
      </div>
    </form>
  </div>
</div>

<script>
function resetStep() {
  const f = document.getElementById('stepForm');
  f.querySelector('[name=id]').value = '-1';
  f.reset();
}
</script>

<?php $content = ob_get_clean();
$page = 'steps'; $pageTitle = 'Этапы'; $pageHeading = 'Этапы работы';
require __DIR__ . '/_base.php'; ?>
