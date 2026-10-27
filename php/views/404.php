<?php /* Страница 404. Данные: $s */ ?>
<?php http_response_code(404); ?>
<?php ob_start(); ?>

<section class="section" style="min-height:60vh;display:flex;align-items:center">
  <div class="container" style="text-align:center">
    <span class="section-kicker center">Ошибка 404</span>
    <h1 class="section-title center">Страница не найдена</h1>
    <p class="section-note center">Возможно, она была перешита на другой адрес.</p>
    <p style="margin-top:24px"><a href="<?= e(url('/')) ?>" class="btn btn-primary">← На главную</a></p>
  </div>
</section>

<?php $content = ob_get_clean(); require __DIR__ . '/_public_base.php'; ?>
