/* ============ Ателье в Доме Быта — интерактив ============ */
(function () {
  "use strict";
  const $ = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Preloader + hero ---------- */
  window.addEventListener("load", () => {
    const pre = $("#preloader");
    if (pre) setTimeout(() => pre.classList.add("done"), 350);
    const hero = $(".hero");
    if (hero) setTimeout(() => hero.classList.add("ready"), 250);
  });
  document.addEventListener("DOMContentLoaded", () => {
    const hero = $(".hero");
    if (hero) setTimeout(() => hero.classList.add("ready"), 150);
  });

  /* ---------- Header scroll state ---------- */
  const header = $("#header");
  const heroBg = document.querySelector(".hero-bg[data-focus]");
  const onScroll = () => {
    if (header) header.classList.toggle("scrolled", window.scrollY > 40);
    // parallax
    if (!reduceMotion) {
      $$("[data-parallax]").forEach(el => {
        const speed = parseFloat(el.dataset.parallax) || 0.2;
        el.style.transform = `translateY(${window.scrollY * speed}px)`;
      });
    }
  };
  /* На телефонах background-size:cover сильно обрезает широкие фото —
     смещаем «правильную» область к центру кадра */
  const setHeroFocus = () => {
    if (!heroBg) return;
    if (window.innerWidth <= 640) {
      heroBg.style.setProperty("--hero-pos", "center 38%");
      heroBg.style.backgroundSize = "auto 115%";
    } else {
      heroBg.style.removeProperty("--hero-pos");
      heroBg.style.backgroundSize = "";
    }
  };
  window.addEventListener("resize", setHeroFocus, { passive: true });
  setHeroFocus();
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Burger / mobile menu ---------- */
  const burger = $("#burger");
  const mobileMenu = $("#mobileMenu");
  const mobileOverlay = $("#mobileOverlay");
  const setMenu = (open) => {
    if (!mobileMenu || !burger) return;
    mobileMenu.classList.toggle("open", open);
    burger.classList.toggle("open", open);
    document.body.classList.toggle("menu-open", open);
    mobileMenu.setAttribute("aria-hidden", String(!open));
  };
  if (burger && mobileMenu) {
    // preventDefault обязателен: на мобильных клик по кнопке внутри fixed-панели
    // без него может «съедаться» браузером (300ms tap delay)
    burger.addEventListener("click", (e) => {
      e.preventDefault(); e.stopPropagation();
      setMenu(!mobileMenu.classList.contains("open"));
    });
    if (mobileOverlay) mobileOverlay.addEventListener("click", () => setMenu(false));
    // закрытие по клику на любую ссылку/кнопку внутри меню
    mobileMenu.querySelectorAll("a, button").forEach(el =>
      el.addEventListener("click", () => setMenu(false))
    );
    // закрытие по Esc
    document.addEventListener("keydown", e => { if (e.key === "Escape") setMenu(false); });
    // при возврате на десктоп — сбросить состояние
    window.addEventListener("resize", () => { if (window.innerWidth > 1024) setMenu(false); });
  }

  /* ---------- Reveal on scroll ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const d = e.target.dataset.delay;
        if (d) e.target.style.setProperty("--rd", d + "ms");
        e.target.classList.add("in");
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  $$(".reveal").forEach(el => io.observe(el));

  /* ---------- Прайс: модальное окно (без переключения вкладок) ---------- */
  const pricesModal = $("#pricesModal");
  const pricesTitle = $("#pricesModalTitle");
  function openPrices(tabKey) {
    if (!pricesModal) return;
    // Без направления (кнопка в hero/шапке) — показываем все категории
    $$(".tab-panel").forEach(p => p.classList.toggle("hidden", !!tabKey && p.id !== "tab-" + tabKey));
    if (pricesTitle) {
      const btn = document.querySelector('[data-open-prices="' + tabKey + '"]');
      const card = btn && btn.closest(".dir-card");
      const h3 = card && card.querySelector("h3");
      pricesTitle.textContent = h3 ? "Цены — " + h3.textContent : "Цены на основные услуги";
    }
    pricesModal.hidden = false;
    const panel = pricesModal.querySelector(".prices-modal-panel");
    if (panel) panel.scrollTop = 0;
    requestAnimationFrame(() => pricesModal.classList.add("open"));
    document.body.style.overflow = "hidden";
  }
  function closePrices() {
    if (!pricesModal) return;
    pricesModal.classList.remove("open");
    document.body.style.overflow = "";
    setTimeout(() => { pricesModal.hidden = true; }, 320);
  }
  $$("[data-open-prices]").forEach(el => el.addEventListener("click", () => openPrices(el.dataset.openPrices)));
  $$("[data-close-prices]").forEach(el => el.addEventListener("click", closePrices));
  document.addEventListener("keydown", e => { if (e.key === "Escape") closePrices(); });
  // «Заказать» из прайса — закрываем модалку и уходим к форме
  $$(".price-order").forEach(a => a.addEventListener("click", () => { pricesModal && pricesModal.classList.remove("open"); closePrices(); }));

  /* ---------- Before/After slider ---------- */
  $$(".ba-slider").forEach(box => {
    let dragging = false;
    const setPos = (clientX) => {
      const r = box.getBoundingClientRect();
      let pct = ((clientX - r.left) / r.width) * 100;
      pct = Math.max(2, Math.min(98, pct));
      const before = $(".ba-before", box), handle = $(".ba-handle", box);
      if (before) before.style.width = pct + "%";
      if (handle) handle.style.left = pct + "%";
      box.setAttribute("aria-valuenow", Math.round(pct));
    };
    box.addEventListener("pointerdown", e => { dragging = true; box.setPointerCapture(e.pointerId); setPos(e.clientX); });
    box.addEventListener("pointermove", e => { if (dragging) setPos(e.clientX); });
    box.addEventListener("pointerup", () => dragging = false);
    box.addEventListener("pointercancel", () => dragging = false);
    box.addEventListener("keydown", e => {
      const cur = parseFloat(box.getAttribute("aria-valuenow")) || 50;
      if (e.key === "ArrowLeft") setPos(box.getBoundingClientRect().left + (cur - 5) / 100 * box.offsetWidth);
      if (e.key === "ArrowRight") setPos(box.getBoundingClientRect().left + (cur + 5) / 100 * box.offsetWidth);
    });
  });

  /* ---------- Reviews slider ---------- */
  const track = $("#reviewsTrack");
  if (track) {
    const cards = $$(".review-card", track);
    let idx = 0, timer = null;
    const go = i => {
      idx = (i + cards.length) % cards.length;
      track.style.transform = `translateX(-${idx * 100}%)`;
    };
    const auto = () => { if (!reduceMotion && cards.length > 1) timer = setInterval(() => go(idx + 1), 6000); };
    const prev = $("#revPrev"), next = $("#revNext");
    if (prev) prev.addEventListener("click", () => { clearInterval(timer); go(idx - 1); auto(); });
    if (next) next.addEventListener("click", () => { clearInterval(timer); go(idx + 1); auto(); });
    auto();
  }

  /* ---------- Order form helpers ---------- */
  // кнопка «Заказать» в прайсе подставляет услугу в форму
  $$(".price-order").forEach(btn => btn.addEventListener("click", () => {
    const sel = $("#serviceSelect");
    if (sel && btn.dataset.service) {
      for (const opt of sel.options) if (opt.value === btn.dataset.service) { sel.value = opt.value; break; }
    }
  }));
  // зона загрузки фото: кнопка выбора, drag&drop, имя файла
  const photo = $("#photoInput"), photoName = $("#photoName");
  const dropZone = $("#dropZone"), browseBtn = $("#browseBtn");
  const showFileName = () => {
    if (!photo || !photoName) return;
    if (photo.files.length) {
      photoName.textContent = photo.files[0].name;
      photoName.classList.add("show");
      dropZone && dropZone.classList.add("has-file");
    } else {
      photoName.textContent = "";
      photoName.classList.remove("show");
      dropZone && dropZone.classList.remove("has-file");
    }
  };
  if (photo) photo.addEventListener("change", showFileName);
  if (browseBtn && photo) browseBtn.addEventListener("click", () => photo.click());
  if (dropZone && photo) {
    ["dragenter", "dragover"].forEach(ev => dropZone.addEventListener(ev, e => {
      e.preventDefault(); dropZone.classList.add("dragover");
    }));
    ["dragleave", "drop"].forEach(ev => dropZone.addEventListener(ev, e => {
      e.preventDefault(); dropZone.classList.remove("dragover");
    }));
    dropZone.addEventListener("drop", e => {
      if (e.dataTransfer.files && e.dataTransfer.files.length) {
        photo.files = e.dataTransfer.files;
        showFileName();
      }
    });
  }
  // маска телефона
  const phoneInput = $("#phoneInput");
  if (phoneInput) phoneInput.addEventListener("input", () => {
    let v = phoneInput.value.replace(/\D/g, "");
    if (v.startsWith("8")) v = "7" + v.slice(1);
    if (v && !v.startsWith("7")) v = "7" + v;
    let out = "+7";
    if (v.length > 1) out += " (" + v.slice(1, 4);
    if (v.length >= 5) out += ") " + v.slice(4, 7);
    if (v.length >= 8) out += "-" + v.slice(7, 9);
    if (v.length >= 10) out += "-" + v.slice(9, 11);
    phoneInput.value = out;
  });
  // минимальная дата визита — сегодня
  const dateInput = $("#dateInput");
  if (dateInput) {
    const t = new Date();
    dateInput.min = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, "0")}-${String(t.getDate()).padStart(2, "0")}`;
  }

  /* ---------- Magnetic buttons ---------- */
  if (!reduceMotion && matchMedia("(pointer:fine)").matches) {
    $$(".magnetic").forEach(el => {
      el.addEventListener("mousemove", e => {
        const r = el.getBoundingClientRect();
        el.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .18}px, ${(e.clientY - r.top - r.height / 2) * .18}px)`;
      });
      el.addEventListener("mouseleave", () => el.style.transform = "");
    });
  }

  /* ---------- Tilt cards ---------- */
  if (!reduceMotion && matchMedia("(pointer:fine)").matches) {
    $$(".tilt").forEach(card => {
      card.addEventListener("mousemove", e => {
        const r = card.getBoundingClientRect();
        const rx = ((e.clientY - r.top) / r.height - .5) * -5;
        const ry = ((e.clientX - r.left) / r.width - .5) * 5;
        card.style.transform = `perspective(800px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-6px)`;
      });
      card.addEventListener("mouseleave", () => card.style.transform = "");
    });
  }

  /* ---------- Flash message from admin redirects ---------- */
  fetch(location.pathname + location.search, { method: "HEAD" }).catch(() => {});
})();
