<?php /* Настройки сайта. Данные: $admin, $AB, $today, $s, $fields, $image_keys */ ?>
<?php ob_start(); ?>

<div class="panel">
  <h2>Контент публичного сайта</h2>
  <p style="color:#8a8174;font-size:13.5px;margin-bottom:20px">Изменения сразу отображаются на сайте после сохранения.</p>
  <?php if (!empty($_GET['saved'])): ?><div class="login-error" style="background:#e6f4e6;color:#2c6a2c;border-color:#bfe0bf;margin-bottom:16px">Настройки сохранены ✔</div><?php endif; ?>
  <form method="post" action="<?= e(url($AB . '/settings')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?php foreach ($fields as [$key, $label]):
        $isImg = in_array($key, $image_keys, true);
        $isFull = in_array($key, ['about_text','hero_subtitle','manager_quote','hero_title','hero_kicker'], true) || $isImg; ?>
      <div class="field<?= $isFull ? ' full' : '' ?>">
        <label><?= e($label) ?></label>
        <?php if ($isImg): ?>
          <?php if (!empty($s[$key])): ?>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px">
            <img src="<?= e(url($s[$key])) ?>" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:12px;border:1px solid #e5ddcf">
            <label style="font-weight:400;font-size:13px;display:flex;gap:6px;align-items:center">
              <input type="checkbox" name="<?= e($key) ?>_remove" value="1"> Удалить это фото
            </label>
          </div>
          <?php else: ?>
          <p style="color:#8a8174;font-size:13px;margin-bottom:8px">Фото ещё не загружено.</p>
          <?php endif; ?>
          <input type="file" name="<?= e($key) ?>" accept="image/*">
          <?php if ($key === 'manager_signature'): ?>
          <p style="color:#8a8174;font-size:12.5px;margin-top:6px">Загрузите PNG с прозрачным фоном — он отображается на сайте как есть, без анимации (пустые поля обрезаются автоматически).</p>
          <?php endif; ?>
          <?php if ($key === 'logo_image'): ?>
          <p style="color:#8a8174;font-size:12.5px;margin-top:6px">PNG с прозрачным фоном. Рекомендуемая высота 80–160px. Подпись «Ателье · Дом Быта» останется рядом с логотипом.</p>
          <?php endif; ?>
          <?php if ($key === 'favicon_image'): ?>
          <p style="color:#8a8174;font-size:12.5px;margin-top:6px">Квадратное изображение (лучше PNG 512×512). Автоматически будут созданы иконки 16/32/48/192 px и .ico для вкладки браузера.</p>
          <?php endif; ?>
        <?php elseif ($key === 'about_text'): ?>
          <textarea name="<?= e($key) ?>" rows="8"><?= e($s[$key] ?? '') ?></textarea>
        <?php elseif (in_array($key, ['hero_subtitle', 'manager_quote'], true)): ?>
          <textarea name="<?= e($key) ?>" rows="4"><?= e($s[$key] ?? '') ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= e($key) ?>" value="<?= e($s[$key] ?? '') ?>">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="form-actions">
      <button class="btn btn-gold" type="submit">Сохранить настройки</button>
    </div>
  </form>
</div>

<?php $content = ob_get_clean();
$page = 'settings'; $pageTitle = 'Настройки'; $pageHeading = 'Настройки сайта';
require __DIR__ . '/_base.php'; ?>
