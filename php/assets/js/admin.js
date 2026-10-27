/* ============ Админ-панель: JS ============ */

// Модалки: data-open="modal-id" открывает, .modal-close / клик по фону закрывает
document.addEventListener("click", e => {
  const opener = e.target.closest("[data-open]");
  if (opener) {
    e.preventDefault();
    const m = document.getElementById(opener.dataset.open);
    if (m) m.classList.add("open");
    return;
  }
  const closer = e.target.closest(".modal-close");
  if (closer) {
    closer.closest(".modal-backdrop").classList.remove("open");
    return;
  }
  if (e.target.classList && e.target.classList.contains("modal-backdrop")) {
    e.target.classList.remove("open");
  }
});
document.addEventListener("keydown", e => {
  if (e.key === "Escape") document.querySelectorAll(".modal-backdrop.open")
    .forEach(m => m.classList.remove("open"));
});

// Подстановка данных записи в модалку редактирования: <button data-edit='{"id":1,...}'>
document.addEventListener("click", e => {
  const btn = e.target.closest("[data-edit]");
  if (!btn) return;
  let data;
  try { data = JSON.parse(btn.dataset.edit); } catch (err) { return; }
  const modal = document.getElementById(btn.dataset.open || "editModal");
  if (!modal) return;
  modal.querySelectorAll("[name]").forEach(input => {
    if (!(input.name in data)) return;
    const val = data[input.name];
    if (input.type === "checkbox") input.checked = !!Number(val);
    else if (input.tagName === "SELECT") input.value = val;
    else if (input.type === "file") { /* value нельзя задать для file */ }
    else input.value = val;
  });
  // Показываем, какие фото уже загружены (при редактировании)
  const bHint = modal.querySelector(".hint[data-for=before_image]");
  if (bHint) bHint.textContent = data.before_image_path ? "Фото «До» уже загружено (новое заменит его)" : "";
  const aHint = modal.querySelector(".hint[data-for=after_image]");
  if (aHint) aHint.textContent = data.after_image_path ? "Фото «После» уже загружено (новое заменит его)" : "";
});

// Удаление с подтверждением
document.addEventListener("submit", e => {
  const form = e.target;
  if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
    e.preventDefault();
  }
});

// Тост из query-параметра (?saved=1 и т.п.)
(() => {
  const p = new URLSearchParams(location.search);
  const msgs = { saved: "Сохранено ✔", ok: "Выполнено ✔", err: "Ошибка — проверьте данные" };
  for (const k of Object.keys(msgs)) {
    if (p.has(k)) {
      const t = document.createElement("div");
      t.className = "toast";
      t.textContent = k === "err" ? "Старый пароль неверен или пароль слишком короткий" : msgs[k];
      if (k === "err") t.style.background = "#cf4b4b";
      document.body.appendChild(t);
      setTimeout(() => t.remove(), 3200);
      break;
    }
  }
})();
