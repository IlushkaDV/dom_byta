<?php /* Отзывы. Данные: $admin, $AB, $today, $reviews */ ?>
<?php ob_start(); ?>

<div class="panel">
  <div class="panel-head">
    <h2>Отзывы (<?= count($reviews) ?>)</h2>
    <button class="btn btn-gold btn-sm" data-open="revModal" onclick="resetRev()">＋ Добавить отзыв</button>
  </div>
  <table>
    <thead><tr><th>Автор</th><th>Оценка</th><th>Текст</th><th>Дата</th><th>Видимость</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($reviews as $r): ?>
      <tr>
        <td><b><?= e($r['author']) ?></b></td>
        <td style="color:#b98a44"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
        <td style="max-width:340px"><?= e($r['text']) ?></td>
        <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
        <td><?php if ($r['is_public']): ?><span class="badge badge-on">Опубликован</span><?php else: ?><span class="badge badge-off">Скрыт</span><?php endif; ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm" data-open="revModal" data-edit='<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>✏️ Изменить</button>
          <form method="post" action="<?= e(url($AB . '/reviews/' . $r['id'] . '/delete')) ?>" data-confirm="Удалить отзыв?" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-backdrop" id="revModal">
  <div class="modal">
    <button type="button" class="modal-close">×</button>
    <h2>Отзыв</h2>
    <form method="post" action="<?= e(url($AB . '/reviews/save')) ?>" id="revForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="0">
      <div class="form-grid">
        <div class="field"><label>Автор *</label><input type="text" name="author" required placeholder="Имя клиента"></div>
        <div class="field"><label>Оценка (1–5)</label><input type="number" name="rating" min="1" max="5" value="5"></div>
        <div class="field full"><label>Текст *</label><textarea name="text" required placeholder="Текст отзыва"></textarea></div>
        <div class="field" style="padding-top:6px">
          <label class="checkbox"><input type="checkbox" name="is_public" value="1" checked> Показывать на сайте</label>
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
function resetRev() {
  const f = document.getElementById('revForm');
  f.querySelector('[name=id]').value = 0;
  f.reset();
}
</script>

<?php $content = ob_get_clean();
$page = 'reviews'; $pageTitle = 'Отзывы'; $pageHeading = 'Отзывы клиентов';
require __DIR__ . '/_base.php'; ?>
