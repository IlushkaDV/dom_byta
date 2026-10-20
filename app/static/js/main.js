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
  const onScroll = () => header && header.classList.toggle("scrolled", scrollY > 40);
  addEventListener("scroll", onScroll, { passive: true });
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
})();
