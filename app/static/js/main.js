/* ============ Меховое ателье — интерактив ============ */
(() => {
  const prefersReduced = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- Preloader ---- */
  const pre = document.getElementById("preloader");
  const hidePre = () => pre && pre.classList.add("done");
  window.addEventListener("load", () => setTimeout(hidePre, 300));
  setTimeout(hidePre, 2500); // страховка

  /* ---- Header scroll state ---- */
  const header = document.getElementById("header");
  const heroEl = document.querySelector(".hero");
  const onScroll = () => {
    if (!header) return;
    // порог = низ первого экрана (чтобы светлая шапка оставалась, пока hero в зоне видимости)
    const threshold = heroEl ? Math.max(heroEl.offsetHeight - 90, 200) : 40;
    header.classList.toggle("scrolled", scrollY > threshold);
  };
  addEventListener("scroll", onScroll, { passive: true });
  addEventListener("resize", onScroll);
  onScroll();

  /* ---- Mobile menu ---- */
  const burger = document.getElementById("burger");
  const mobile = document.getElementById("mobileMenu");
  if (burger && mobile) {
    burger.addEventListener("click", () => {
      burger.classList.toggle("open");
      mobile.classList.toggle("open");
      document.body.style.overflow = mobile.classList.contains("open") ? "hidden" : "";
    });
    mobile.querySelectorAll("a").forEach(a => a.addEventListener("click", () => {
      burger.classList.remove("open");
      mobile.classList.remove("open");
      document.body.style.overflow = "";
    }));
  }

  /* ---- Reveal on scroll ---- */
  const reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !prefersReduced) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    reveals.forEach(el => {
      const d = el.dataset.delay;
      if (d) el.style.setProperty("--d", d + "ms");
      io.observe(el);
    });
  } else {
    reveals.forEach(el => el.classList.add("in"));
  }

  /* ---- Counters ---- */
  const counters = document.querySelectorAll(".counter");
  const fmt = n => n.toLocaleString("ru-RU");
  if ("IntersectionObserver" in window && !prefersReduced) {
    const cio = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        cio.unobserve(e.target);
        const target = +e.target.dataset.count || 0;
        const dur = 1600, t0 = performance.now();
        const tick = now => {
          const p = Math.min(1, (now - t0) / dur);
          const eased = 1 - Math.pow(1 - p, 3);
          e.target.textContent = fmt(Math.round(target * eased));
          if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      });
    }, { threshold: .6 });
    counters.forEach(c => cio.observe(c));
  } else {
    counters.forEach(c => c.textContent = fmt(+c.dataset.count || 0));
  }

  /* ---- Parallax hero ---- */
  const parallaxEls = document.querySelectorAll("[data-parallax]");
  if (!prefersReduced && parallaxEls.length) {
    let ticking = false;
    addEventListener("scroll", () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        parallaxEls.forEach(el => {
          const speed = parseFloat(el.dataset.parallax) || 0.2;
          el.style.transform = `translateY(${scrollY * speed}px)`;
        });
        ticking = false;
      });
    }, { passive: true });
  }

  /* ---- Tabs (услуги по категориям) ---- */
  const tabs = document.querySelectorAll(".tab");
  tabs.forEach(tab => tab.addEventListener("click", () => {
    tabs.forEach(t => t.classList.remove("active"));
    tab.classList.add("active");
    document.querySelectorAll(".tab-panel").forEach(p => p.classList.add("hidden"));
    const panel = document.getElementById(tab.dataset.tab);
    if (panel) {
      panel.classList.remove("hidden");
      panel.querySelectorAll(".reveal:not(.in)").forEach(el => el.classList.add("in"));
    }
  }));

  /* ---- Отзывы: слайдер ---- */
  const track = document.getElementById("reviewsTrack");
  if (track) {
    let page = 0;
    const perView = () => innerWidth > 1024 ? 3 : innerWidth > 640 ? 2 : 1;
    const pages = () => Math.max(0, track.children.length - perView());
    const go = p => {
      page = Math.max(0, Math.min(pages(), p));
      const card = track.children[0];
      const gap = 22;
      const w = card.getBoundingClientRect().width + gap;
      track.style.transform = `translateX(${-page * w}px)`;
    };
    document.getElementById("revNext")?.addEventListener("click", () => go(page + 1));
    document.getElementById("revPrev")?.addEventListener("click", () => go(page - 1));
    addEventListener("resize", () => go(page));
    // автопрокрутка
    if (!prefersReduced && track.children.length > perView()) {
      setInterval(() => go(page >= pages() ? 0 : page + 1), 5200);
    }
  }

  /* ---- Кнопка «Заказать» → выбрать услугу в форме ---- */
  document.querySelectorAll(".price-order").forEach(btn => btn.addEventListener("click", () => {
    const sel = document.getElementById("serviceSelect");
    if (sel) sel.value = btn.dataset.service || "";
  }));

  /* ---- Маска телефона ---- */
  const phone = document.getElementById("phoneInput");
  if (phone) {
    phone.addEventListener("input", () => {
      let d = phone.value.replace(/\D/g, "");
      if (d.startsWith("8")) d = "7" + d.slice(1);
      if (!d.startsWith("7")) d = "7" + d;
      d = d.slice(0, 11);
      let out = "+7";
      if (d.length > 1) out += " (" + d.slice(1, 4);
      if (d.length >= 5) out += ") " + d.slice(4, 7);
      if (d.length >= 8) out += "-" + d.slice(7, 9);
      if (d.length >= 10) out += "-" + d.slice(9, 11);
      phone.value = out;
    });
  }

  /* ---- Tilt-эффект карточек + подсветка курсором ---- */
  if (!prefersReduced && matchMedia("(pointer:fine)").matches) {
    document.querySelectorAll(".tilt").forEach(card => {
      card.addEventListener("mousemove", e => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
        card.style.setProperty("--mx", x * 100 + "%");
        card.style.setProperty("--my", y * 100 + "%");
        card.style.transform =
          `perspective(900px) rotateY(${(x - .5) * 7}deg) rotateX(${(.5 - y) * 7}deg) translateY(-6px)`;
      });
      card.addEventListener("mouseleave", () => { card.style.transform = ""; });
    });
  }

  /* ---- Magnetic buttons ---- */
  if (!prefersReduced && matchMedia("(pointer:fine)").matches) {
    document.querySelectorAll(".magnetic").forEach(btn => {
      btn.addEventListener("mousemove", e => {
        const r = btn.getBoundingClientRect();
        btn.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .18}px, ${(e.clientY - r.top - r.height / 2) * .3}px)`;
      });
      btn.addEventListener("mouseleave", () => { btn.style.transform = ""; });
    });
  }

  /* ---- Scrollspy для навигации ---- */
  const sections = [...document.querySelectorAll("section[id]")];
  const navLinks = [...document.querySelectorAll(".nav a:not(.nav-cta)")];
  if ("IntersectionObserver" in window && sections.length) {
    const sio = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (en.isIntersecting) {
          navLinks.forEach(l => l.classList.toggle("active", l.getAttribute("href") === "#" + en.target.id));
        }
      });
    }, { rootMargin: "-45% 0px -50% 0px" });
    sections.forEach(s => sio.observe(s));
  }

  /* ---- Игла с ниткой: прошивает «строчку» перед футером при прокрутке ---- */
  const seam = document.querySelector(".seam");
  const seamNeedle = seam && seam.querySelector(".seam-needle");
  const seamStitch = seam && seam.querySelector(".seam-stitch");
  if (seam && seamNeedle && seamStitch && !prefersReduced) {
    const pathLen = seamStitch.getTotalLength();
    seamStitch.style.strokeDasharray = "10 8";
    // маска: строчка проявляется по мере движения иглы
    const maskId = "seamMask";
    const svgNS = "http://www.w3.org/2000/svg";
    const defs = document.createElementNS(svgNS, "defs");
    const mask = document.createElementNS(svgNS, "mask");
    mask.setAttribute("id", maskId);
    const maskRect = document.createElementNS(svgNS, "rect");
    maskRect.setAttribute("x", "0"); maskRect.setAttribute("y", "0");
    maskRect.setAttribute("height", "100%"); maskRect.setAttribute("width", "0");
    maskRect.setAttribute("fill", "#fff");
    mask.appendChild(maskRect);
    defs.appendChild(mask);
    seamStitch.parentElement.insertBefore(defs, seamStitch);
    seamStitch.setAttribute("mask", `url(#${maskId})`);
    let raf = null;
    const updateSeam = () => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = null;
        const r = seam.getBoundingClientRect();
        const vh = innerHeight;
        // p: 0 → секция снизу экрана, 1 → секция ушла за верх
        const p = Math.min(1, Math.max(0, (vh - r.top) / (vh + r.height)));
        const vbW = 1200;
        const x = p * vbW;
        maskRect.setAttribute("width", String(x));
        // позиция иглы — на волнистой линии (path.getPointAtLength)
        const pt = seamStitch.getPointAtLength(p * pathLen);
        const scaleX = r.width / vbW;
        seamNeedle.style.transform =
          `translate(${pt.x * scaleX}px, ${pt.y - 22}px) rotate(${Math.sin(p * Math.PI * 6) * 12 - 30}deg)`;
      });
    };
    addEventListener("scroll", updateSeam, { passive: true });
    addEventListener("resize", updateSeam);
    updateSeam();
  } else if (seam && seamStitch) {
    seamStitch.style.opacity = ".85";
  }

  /* ---- Hero: нить «разматывается» с бобины при прокрутке ---- */
  const thread = document.getElementById("threadPath");
  if (thread) {
    const len = thread.getTotalLength();
    thread.style.strokeDasharray = len;
    const updateThread = () => {
      const max = heroEl ? heroEl.offsetHeight : innerHeight;
      const p = Math.min(1, scrollY / Math.max(max * .7, 1));
      thread.style.strokeDashoffset = String(len * (1 - p));
    };
    addEventListener("scroll", updateThread, { passive: true });
    updateThread();
  }

  /* ---- Нить через всю страницу: полотно от шапки до подвала, игла бежит при прокрутке ---- */
  const pageThreadSvg = document.querySelector(".page-thread");
  const pageThreadPath = document.getElementById("pageThreadPath");
  const pageThreadNeedle = document.getElementById("pageThreadNeedle");
  if (pageThreadSvg && pageThreadPath) {
    const HEADER_H = 96, MARGIN_R = 44, WAVE_AMP = 30, WAVE_STEP = 110;
    let docH = 1, raf = null, x0 = 0, yStart = 0, yEnd = 0;

    function setup() {
      const vh = innerHeight;
      docH = Math.max(document.documentElement.scrollHeight, vh);
      // SVG-полотно на всю высоту документа — нить реально идёт через всю страницу
      pageThreadSvg.style.width = innerWidth + "px";
      pageThreadSvg.style.height = docH + "px";
      pageThreadSvg.setAttribute("viewBox", `0 0 ${innerWidth} ${docH}`);
      x0 = Math.max(innerWidth - MARGIN_R, innerWidth * .5 + 160);
      yStart = HEADER_H + 20;
      yEnd = docH - 40;
    }

    // волнистая линия от yStart до yTo (зигзаг из квадратных кривых)
    function wavePath(yTo) {
      if (yTo <= yStart + 1) return "";
      let d = `M${x0},${yStart}`;
      let i = 0;
      for (let y = yStart; y < yTo; y += WAVE_STEP, i++) {
        const ny = Math.min(y + WAVE_STEP, yTo);
        const dir = i % 2 === 0 ? 1 : -1;
        d += ` Q${(x0 + dir * WAVE_AMP).toFixed(1)},${((y + ny) / 2).toFixed(1)} ${x0},${ny.toFixed(1)}`;
      }
      return d;
    }

    function updatePageThread() {
      raf = null;
      const vh = innerHeight;
      const maxScroll = Math.max(docH - vh, 1);
      const p = Math.min(1, Math.max(0, scrollY / maxScroll));
      // игла всегда в нижней трети видимого окна — создаётся эффект, что она «шьёт» по ходу скролла
      const needleY = Math.min(scrollY + vh * .7, yEnd);
      const d = wavePath(needleY);
      if (d) {
        pageThreadPath.setAttribute("d", d);
        pageThreadPath.style.visibility = "visible";
      } else {
        pageThreadPath.removeAttribute("d");
        pageThreadPath.style.visibility = "hidden";
      }
      if (pageThreadNeedle) {
        pageThreadNeedle.setAttribute("transform", `translate(${x0},${needleY.toFixed(1)})`);
        pageThreadNeedle.style.opacity = p > .002 ? "1" : "0";
      }
    }

    const scheduleUpdate = () => { if (!raf) raf = requestAnimationFrame(updatePageThread); };
    setup();
    updatePageThread();
    addEventListener("scroll", scheduleUpdate, { passive: true });
    addEventListener("resize", () => { setup(); updatePageThread(); });
    // пересчёт после загрузки изображений/шрифтов (высота страницы может измениться)
    addEventListener("load", () => { setup(); updatePageThread(); });
    setTimeout(() => { setup(); updatePageThread(); }, 1200);
  }
})();

/* ---------- Before/After слайдер в галерее ---------- */
(function () {
  document.querySelectorAll(".ba-slider").forEach(function (el) {
    var dragging = false;
    function setPos(clientX) {
      var r = el.getBoundingClientRect();
      var p = Math.min(96, Math.max(4, ((clientX - r.left) / r.width) * 100));
      el.style.setProperty("--ba-pos", p + "%");
      el.setAttribute("aria-valuenow", Math.round(p));
    }
    el.addEventListener("pointerdown", function (e) {
      dragging = true;
      el.setPointerCapture(e.pointerId);
      setPos(e.clientX);
      e.preventDefault();
    });
    el.addEventListener("pointermove", function (e) { if (dragging) setPos(e.clientX); });
    el.addEventListener("pointerup", function () { dragging = false; });
    el.addEventListener("pointercancel", function () { dragging = false; });
    el.addEventListener("keydown", function (e) {
      var cur = parseFloat(el.getAttribute("aria-valuenow")) || 50;
      if (e.key === "ArrowLeft") { cur = Math.max(4, cur - 4); }
      else if (e.key === "ArrowRight") { cur = Math.min(96, cur + 4); }
      else return;
      e.preventDefault();
      el.style.setProperty("--ba-pos", cur + "%");
      el.setAttribute("aria-valuenow", Math.round(cur));
    });
    // лёгкая демо-анимация при первом появлении
    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          io.unobserve(el);
          var start = null;
          function step(ts) {
            if (!start) start = ts;
            var t = ts - start;
            if (t > 900 || dragging) return;
            var p = 50 + Math.sin(t / 900 * Math.PI) * 18;
            el.style.setProperty("--ba-pos", p + "%");
            requestAnimationFrame(step);
          }
          requestAnimationFrame(step);
        });
      }, { threshold: 0.4 });
      io.observe(el);
    }
  });
})();
