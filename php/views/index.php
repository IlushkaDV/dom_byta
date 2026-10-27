<?php /* Главная страница. Данные: $s, $services, $advantages, $gallery, $reviews, $priceTabs, $directions, $steps */ ?>
<?php ob_start(); ?>

<!-- ===== 1. ГЛАВНЫЙ ЭКРАН ===== -->
<section class="hero">
  <?php if (!empty($s['hero_image'])): ?>
  <div class="hero-bg" data-parallax="0.3" data-focus style="background-image:url('<?= e(url($s['hero_image'])) ?>')"></div>
  <?php else: ?>
  <div class="hero-bg" data-parallax="0.3"></div>
  <?php endif; ?>
  <?php if (!empty($s['hero_image_mobile'])): ?>
  <div class="hero-bg hero-bg-mobile" style="background-image:url('<?= e(url($s['hero_image_mobile'])) ?>')"></div>
  <?php endif; ?>
  <div class="hero-overlay"></div>
  <div class="container hero-content">
    <?php if (!empty($s['hero_kicker'])): ?><span class="hero-kicker reveal"><?= e($s['hero_kicker']) ?></span><?php endif; ?>
    <h1 class="hero-title"><span class="line"><span><?= e($s['hero_title']) ?></span></span></h1>
    <p class="hero-sub reveal reveal-d2"><?= e($s['hero_subtitle']) ?></p>
    <div class="hero-actions reveal reveal-d3">
      <a href="#order" class="btn btn-primary magnetic">Рассчитать стоимость по фото</a>
      <a href="#directions" class="btn btn-glass">Услуги и цены
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h18M3 12h18M3 16h12"/></svg>
      </a>
    </div>
  </div>
  <a href="#about" class="scroll-hint" aria-label="Листайте вниз"><i></i></a>
</section>

<!-- ===== 2. ЛИЦО АТЕЛЬЕ ===== -->
<section class="section section-cream about" id="about">
  <div class="container about-grid">
    <div class="about-media reveal">
      <div class="about-frame">
        <?php if (!empty($s['manager_photo'])): ?>
        <img class="about-img about-photo" src="<?= e(url($s['manager_photo'])) ?>" alt="<?= e($s['manager_name'] . ' — ' . $s['manager_role']) ?>">
        <?php else: ?>
        <div class="about-img about-placeholder" role="img" aria-label="Фото заведующей">
          <svg viewBox="0 0 120 120" width="96" height="96" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
            <circle cx="60" cy="44" r="20"/><path d="M22 104c6-22 20-32 38-32s32 10 38 32"/>
          </svg>
          <span>Фото заведующей<br><small>загрузите в админке → «Настройки сайта»</small></span>
        </div>
        <?php endif; ?>
        <div class="about-year"><b>с 1998</b><span>года в центре Хабаровска</span></div>
      </div>
    </div>
    <div class="about-body">
      <span class="section-kicker reveal">Лицо ателье</span>
      <h2 class="section-title reveal"><?= e($s['manager_title']) ?></h2>
      <div class="manager-quote reveal">
        <?php foreach (explode("\n", str_replace('\n', "\n", $s['manager_quote'])) as $para): if (trim($para) === '') continue; ?>
        <p><?= e(trim($para)) ?></p>
        <?php endforeach; ?>
      </div>
      <div class="manager-sign reveal">
        <div>
          <?php if (!empty($s['manager_signature'])): ?>
          <img class="signature-img" src="<?= e(url($s['manager_signature'])) ?>" alt="Подпись: <?= e($s['manager_name']) ?>">
          <?php else: ?>
          <svg class="signature" viewBox="0 0 340 96" role="img" aria-label="Подпись: <?= e($s['manager_name']) ?>">
            <path class="sig-ink" pathLength="1" d="M12 64 C 26 18, 44 12, 52 30 C 58 46, 48 62, 56 66 C 66 71, 76 42, 88 40 C 98 38, 96 60, 108 60 C 120 60, 124 34, 138 34 C 150 34, 146 58, 158 58 C 172 58, 178 28, 192 30 C 204 32, 198 56, 210 56 C 224 56, 230 34, 244 36 C 256 38, 250 60, 264 58 C 278 56, 286 36, 300 40 C 312 44, 310 60, 328 54"/>
            <path class="sig-flourish" pathLength="1" d="M16 78 C 90 92, 240 92, 322 74 C 250 88, 110 90, 30 82"/>
          </svg>
          <?php endif; ?>
          <b><?= e($s['manager_name']) ?></b>
          <span><?= e($s['manager_role']) ?></span>
        </div>
        <a href="tel:<?= e(preg_replace('/[^\d+]/u', '', $s['phone'])) ?>" class="manager-phone"><?= e($s['phone']) ?></a>
      </div>
    </div>
  </div>
</section>

<!-- ===== 3. НАПРАВЛЕНИЯ ===== -->
<section class="section directions" id="directions">
  <div class="container">
    <span class="section-kicker center reveal">Что мы делаем</span>
    <h2 class="section-title center reveal">Четыре направления ателье</h2>
    <div class="dir-grid">
      <?php foreach ($directions as $i => $d): ?>
      <article class="dir-card reveal" data-delay="<?= $i * 90 ?>">
        <div class="dir-photo">
          <?php if (!empty($d['image'])): ?>
          <img src="<?= e(url($d['image'])) ?>" alt="<?= e($d['title']) ?>" loading="lazy">
          <?php else: ?>
          <span class="dir-photo-empty">Фото появится здесь —<br>загрузите в админке → «Настройки сайта»</span>
          <?php endif; ?>
        </div>
        <div class="dir-card-body">
          <h3><?= e($d['title']) ?></h3>
          <p><?= e($d['text']) ?></p>
          <button type="button" class="btn btn-dark dir-btn" data-open-prices="<?= e($d['tab']) ?>">Смотреть цены</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== 4. ЦЕНЫ — МОДАЛКА ===== -->
<div class="prices-modal" id="pricesModal" role="dialog" aria-modal="true" aria-label="Прайс-лист" hidden>
  <div class="prices-modal-backdrop" data-close-prices></div>
  <div class="prices-modal-panel">
    <button type="button" class="prices-modal-close" data-close-prices aria-label="Закрыть"><span class="btn-hole"></span><span class="btn-hole"></span></button>
    <span class="section-kicker">Прайс-лист</span>
    <h2 class="prices-modal-title" id="pricesModalTitle">Цены на основные услуги</h2>
    <p class="section-note">Цены указаны «от» — точную стоимость мастер назовёт после осмотра изделия.</p>

    <?php foreach ($priceTabs as $t): ?>
    <div class="tab-panel hidden" id="tab-<?= e($t['dir']['tab']) ?>">
      <div class="price-list">
        <?php foreach ($t['items'] as $srv): ?>
        <div class="price-row">
          <div class="price-info">
            <h3><?= e($srv['title']) ?></h3>
            <?php if (!empty($srv['description'])): ?><p><?= e($srv['description']) ?></p><?php endif; ?>
          </div>
          <div class="price-dots"></div>
          <div class="price-value"><?= e($srv['price']) ?></div>
          <a href="#order" class="price-order btn btn-mini" data-service="<?= e($srv['title']) ?>">Заказать</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="prices-note">
      <?php if (!empty($s['prices_note_1'])): ?><p><?= e($s['prices_note_1']) ?></p><?php endif; ?>
      <?php if (!empty($s['prices_note_2'])): ?><p><?= e($s['prices_note_2']) ?></p><?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== 5. ПРОЗРАЧНЫЕ УСЛОВИЯ ===== -->
<section class="section advantages" id="advantages">
  <div class="container">
    <span class="section-kicker center reveal">Без сюрпризов</span>
    <h2 class="section-title center reveal">Прозрачные условия работы</h2>
    <div class="adv-grid">
      <?php foreach ($advantages as $i => $a): ?>
      <article class="adv-card tilt reveal" data-delay="<?= ($i % 3) * 100 ?>">
        <div class="adv-stitch" aria-hidden="true"></div>
        <div class="adv-icon"><?= e($a['icon']) ?></div>
        <h3><?= e($a['title']) ?></h3>
        <p><?= e($a['text']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>

    <?php $surcharges = array_values(array_filter([
        $s['prices_surcharge_1'], $s['prices_surcharge_2'], $s['prices_surcharge_3'],
        $s['prices_surcharge_4'], $s['prices_surcharge_5'], $s['prices_surcharge_6'],
        $s['prices_surcharge_7'], $s['prices_surcharge_8'], $s['prices_surcharge_9'],
    ])); ?>
    <?php if ($surcharges): ?>
    <div class="prices-terms-box reveal">
      <h4>Полный прейскурант надбавок</h4>
      <ul>
        <?php foreach ($surcharges as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===== 6. ГАЛЕРЕЯ ДО/ПОСЛЕ ===== -->
<section class="section gallery" id="gallery">
  <div class="container">
    <span class="section-kicker center reveal">Портфолио</span>
    <h2 class="section-title center reveal">Наши работы: до / после</h2>
    <p class="section-note reveal">Перетащите ползунок на фото, чтобы сравнить, что было и что стало.</p>
    <div class="gallery-grid">
      <?php foreach ($gallery as $i => $g):
        $has_pair = !empty($g['before_image_path']) && !empty($g['after_image_path']); ?>
      <article class="work-card tilt reveal" data-delay="<?= ($i % 3) * 100 ?>">
        <div class="work-img<?= $has_pair ? ' ba-slider' : '' ?>"<?= $has_pair ? ' tabindex="0" role="slider" aria-label="Перетащите, чтобы сравнить фото до и после" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"' : '' ?>>
          <?php if ($has_pair): ?>
          <img class="ba-after" src="<?= e(url($g['after_image_path'])) ?>" alt="<?= e($g['title']) ?> — после" loading="lazy">
          <div class="ba-before"><img src="<?= e(url($g['before_image_path'])) ?>" alt="<?= e($g['title']) ?> — до" loading="lazy"></div>
          <div class="ba-handle" aria-hidden="true"><span></span></div>
          <?php elseif (!empty($g['image_path'])): ?>
          <img src="<?= e(url($g['image_path'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy">
          <?php else: ?>
          <div class="work-placeholder" aria-hidden="true">
            <svg viewBox="0 0 120 96" width="110" height="88" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="14" y="10" width="92" height="76" rx="6"/><circle cx="38" cy="32" r="8"/>
              <path d="M14 66l26-22 20 16 16-12 30 24"/>
              <path d="M84 14c6 8 10 18 8 30-1 6-5 10-9 12" stroke-dasharray="3 5"/>
              <path d="M96 8l-14 34" stroke-width="2.6"/><circle cx="97" cy="6" r="2.6"/>
            </svg>
            <span class="ph-caption">Фото появится здесь<br><small>загрузите в админке → «Галерея»</small></span>
          </div>
          <?php endif; ?>
        </div>
        <div class="work-body">
          <h3><?= e($g['title']) ?></h3>
          <?php if (!empty($g['before_text'])): ?><p class="work-line"><span class="tag tag-before">До</span> <?= e($g['before_text']) ?></p><?php endif; ?>
          <?php if (!empty($g['after_text'])):
            $isTerm = mb_strtolower(mb_substr(trim($g['after_text']), 0, 4)) === 'срок'; ?>
          <p class="work-line"><span class="tag tag-after"><?= $isTerm ? 'Срок' : 'После' ?></span> <?= e($g['after_text']) ?></p><?php endif; ?>
          <?php if (!empty($g['price'])): ?><div class="work-price"><?= e($g['price']) ?></div><?php endif; ?>
        </div>
      </article>
      <?php endforeach;
      if (!$gallery): ?><p class="section-note">Работы скоро появятся здесь.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== 7. СХЕМА РАБОТЫ ===== -->
<section class="section steps-soft" id="steps">
  <div class="container">
    <span class="section-kicker center reveal">Как мы работаем</span>
    <h2 class="section-title center reveal">Схема безопасной работы</h2>
    <p class="section-subtitle center reveal">Четыре шага — от первой встречи до готового изделия. Ни один рубль и ни один миллиметр не теряются по дороге.</p>
    <div class="steps-wrap">
      <div class="steps-line">
        <?php $step_meta = ["6 этаж ТЦ «Дом Быта»", "мех режем только после примерки", "импортное оборудование", "даём гарантию на работу"]; ?>
        <?php foreach ($steps as $i => [$stitle, $stext]): ?>
        <div class="step-card reveal" data-delay="<?= $i * 90 ?>">
          <div class="step-top">
            <span class="step-num"><?= $i + 1 ?></span>
            <svg class="step-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <?php if ($i === 0): ?>
                <path d="M24 42s13-11.5 13-22A13 13 0 1 0 11 20c0 10.5 13 22 13 22Z"/><circle cx="24" cy="20" r="4.5"/>
              <?php elseif ($i === 1): ?>
                <circle cx="12" cy="36" r="5"/><circle cx="36" cy="36" r="5"/><path d="M15.5 32.5 36 8M32.5 32.5 12 8"/>
              <?php elseif ($i === 2): ?>
                <path d="M38 10 14 34l-4 4 4-4"/><path d="M38 10c2.5 2.5 2.5 5.5 0 8"/><path d="M10 38c-3-1-4-4-2-6s5-1 5 2c0 4-5 6-9 5"/>
              <?php else: ?>
                <path d="M24 6l14 5v10c0 9-6 16-14 19-8-3-14-10-14-19V11l14-5Z"/><path d="M17 24l5 5 9-10"/>
              <?php endif; ?>
            </svg>
          </div>
          <h3><?= e($stitle) ?></h3>
          <p><?= e($stext) ?></p>
          <span class="step-meta"><?= e($step_meta[$i] ?? '') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ===== ОТЗЫВЫ ===== -->
<?php if ($reviews): ?>
<section class="section reviews" id="reviews">
  <div class="container">
    <span class="section-kicker center reveal">Отзывы</span>
    <h2 class="section-title center reveal">Что говорят клиенты</h2>
    <div class="reviews-slider reveal">
      <div class="reviews-track" id="reviewsTrack">
        <?php foreach ($reviews as $r): ?>
        <blockquote class="review-card">
          <div class="stars"><?php for ($k = 0; $k < (int)$r['rating']; $k++) echo '★'; ?><span><?php for ($k = (int)$r['rating']; $k < 5; $k++) echo '☆'; ?></span></div>
          <p>«<?= e($r['text']) ?>»</p>
          <footer><b><?= e($r['author']) ?></b><time><?= e(substr($r['created_at'], 0, 10)) ?></time></footer>
        </blockquote>
        <?php endforeach; ?>
      </div>
      <div class="reviews-nav">
        <button id="revPrev" aria-label="Назад">←</button>
        <button id="revNext" aria-label="Вперёд">→</button>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== 8. ОНЛАЙН-ЗАПИСЬ ===== -->
<section class="section order" id="order">
  <div class="container order-grid">
    <div class="order-info">
      <span class="section-kicker reveal">Запись онлайн</span>
      <h2 class="section-title reveal">Запишитесь на консультацию<br>или пришлите фото для расчёта</h2>
      <p class="reveal">Выберите удобное время для визита к мастеру или прикрепите фото изделия для оценки стоимости онлайн. Мы перезвоним в рабочее время в течение 15 минут.</p>
      <ul class="order-steps">
        <?php foreach (['Заявка на сайте или по телефону', 'Осмотр изделия и снятие мерок на 6 этаже', 'Расчёт стоимости и точных сроков', 'Пошив / ремонт и выдача готового изделия'] as $i => $step): ?>
        <li class="reveal"><span class="order-step-num"><?= $i + 1 ?></span><span><?= e($step) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <form class="order-form reveal" method="post" action="<?= e(url('/order')) ?>" id="orderForm" enctype="multipart/form-data">
      <h3>Записаться на консультацию</h3>
      <label>Ваше имя *<input name="name" required placeholder="Как к вам обращаться"></label>
      <label>Телефон *<input name="phone" type="tel" required id="phoneInput" placeholder="+7 (___) ___-__-__"></label>
      <div class="form-row">
        <label>Дата визита<input name="visit_date" type="date" id="dateInput"></label>
        <label>Время
          <select name="visit_time" id="timeSelect">
            <option value="">— не важно —</option>
            <?php foreach (['10:00','11:30','13:00','14:30','16:00','17:30','19:00'] as $t): ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Интересующая услуга
        <select name="service" id="serviceSelect">
          <option value="">— выберите —</option>
          <?php foreach ($priceTabs as $t): foreach ($t['items'] as $srv): ?>
          <option value="<?= e($srv['title']) ?>"><?= e($srv['title']) ?></option>
          <?php endforeach; endforeach; ?>
          <option value="Другое">Другое</option>
        </select>
      </label>
      <div class="form-file-drop" id="dropZone">
        <input type="file" name="photo" id="photoInput" accept="image/*" hidden>
        <button type="button" class="file-browse-btn" id="browseBtn">Выбрать файл с устройства</button>
        <span class="file-hint">или перетащите фото сюда</span>
        <span class="file-name" id="photoName"></span>
        <button type="button" class="file-remove" id="photoRemove" title="Убрать файл" hidden>&times;</button>
      </div>
      <p class="file-caption"><em>Не обязательно: прикрепите фото изделия — так мастер сможет предварительно оценить стоимость.</em></p>
      <label>Комментарий<textarea name="message" rows="3" placeholder="Опишите задачу: что и из какого материала, сроки…"></textarea></label>
      <button class="btn btn-primary btn-block" type="submit">Записаться на консультацию</button>
      <label class="form-consent">
        <input type="checkbox" name="consent" value="1" required>
        <span>Я согласна с обработкой моих персональных данных в соответствии с <a href="<?= e(url('/privacy')) ?>" target="_blank" rel="noopener">Политикой обработки ПД</a> (152-ФЗ).</span>
      </label>
      <?php $sent = $_GET['sent'] ?? ''; ?>
      <?php if ($sent === 'ok'): ?>
      <div class="form-success show">✔ Спасибо! Заявка отправлена — мы скоро свяжемся с вами.</div>
      <?php elseif ($sent === 'error'): ?>
      <div class="form-success error show">Заполните имя и телефон, пожалуйста.</div>
      <?php elseif ($sent === 'noconsent'): ?>
      <div class="form-success error show">Отметьте, пожалуйста, согласие на обработку персональных данных.</div>
      <?php endif; ?>
    </form>
  </div>
</section>

<!-- ===== 9. КОНТАКТЫ И КАРТА ===== -->
<section class="section section-cream contacts" id="contacts">
  <div class="container contacts-grid">
    <div class="contacts-cards">
      <div class="contact-card reveal"><span class="section-kicker">Адрес</span><p><?= e($s['address']) ?></p></div>
      <div class="contact-card reveal"><span class="section-kicker">Телефоны</span>
        <p><a href="tel:+74212327282"><?= e($s['phone']) ?></a> — ателье<br><a href="tel:+74212328410"><?= e($s['phone_tc']) ?></a> — администрация ТЦ</p></div>
      <div class="contact-card reveal"><span class="section-kicker">Время работы</span><p><?= e($s['hours_week']) ?><br><?= e($s['hours_weekend']) ?></p></div>
      <div class="contact-card reveal"><span class="section-kicker">Почта</span><p><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></p></div>
    </div>
    <div class="map-frame reveal">
      <iframe title="Карта — ТЦ Дом Быта, Шеронова 92" src="https://yandex.ru/map-widget/v1/?text=%D0%A5%D0%B0%D0%B1%D0%B0%D1%80%D0%BE%D0%B2%D1%81%D0%BA%20%D1%83%D0%BB%D0%B8%D1%86%D0%B0%20%D0%A8%D0%B5%D1%80%D0%BE%D0%BD%D0%BE%D0%B2%D0%B0%2092&z=16" loading="lazy"></iframe>
    </div>
  </div>
</section>

<!-- SEO-текст (виден людям и поисковикам) -->
<section class="seo-text">
  <div class="container">
    <h2>Ателье по пошиву и ремонту одежды в Хабаровске</h2>
    <p>Меховое ателье в ТЦ «Дом Быта» (ул. Шеронова, 92, 6 этаж) работает для жителей Хабаровска и края с 10:00 до 19:00 по будням. Мы выполняем индивидуальный пошив шуб, жилетов и головных уборов из норки, мутона, каракуля и других видов меха, полный и частичный перекрой меховых изделий с заменой подклада, а также реставрацию и покраску старых шуб.</p>
    <p>Кроме меха мы шьём и ремонтируем изделия из натуральной кожи и дублёнок: куртки, жакеты, пальто, укорачивание, замена молний и подгонка по фигуре. Текстильный и трикотажный цех берёт на себя пошив пальто, костюмов, юбок, блуз и вечерних платьев, ремонт джинсов, восстановление вязаных изделий и бытовой ремонт одежды. Цены соответствуют официальному прейскуранту, позиции вне его — договорные; на детские изделия действует скидка 30%, возможен срочный ремонт за один день.</p>
    <p>Как найти нас: остановка «ЦУМ», ТЦ «Дом Быта», лифт до 6 этажа. Записаться на консультацию и примерку или отправить фото изделия для расчёта стоимости онлайн можно через форму на сайте или по телефону +7 (4212) 32-72-82.</p>
  </div>
</section>

<?php $content = ob_get_clean(); require __DIR__ . '/_public_base.php'; ?>
