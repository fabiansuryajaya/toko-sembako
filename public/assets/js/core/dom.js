// Bantuan DOM kecil. Semua nilai yang dimasukkan ke HTML lewat html`` otomatis di-escape (mencegah XSS).

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

/** Penanda HTML yang sudah aman (tidak di-escape lagi). */
export class Safe {
  constructor(value) {
    this.value = value;
  }
  toString() {
    return this.value;
  }
}
export const raw = (s) => new Safe(s);

/** Template literal aman: html`<td>${nama}</td>`. Array digabung; Safe tidak di-escape. */
export function html(strings, ...values) {
  const out = strings.reduce((acc, s, i) => acc + s + (i < values.length ? render(values[i]) : ''), '');
  return new Safe(out);
}
const render = (v) => (v instanceof Safe ? v.value : Array.isArray(v) ? v.map(render).join('') : esc(v));

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

/** Event delegation: on(root, 'click', '.btn-edit', (e, el) => ...) */
export function on(root, event, selector, handler) {
  root.addEventListener(event, (e) => {
    const el = e.target.closest(selector);
    if (el && root.contains(el)) handler(e, el);
  });
}

export function setHtml(el, content) {
  el.innerHTML = content instanceof Safe ? content.value : esc(content);
}

export function toast(message, type = 'success') {
  const box = document.getElementById('toasts');
  if (!box) return alert(message);
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = message;
  box.appendChild(t);
  setTimeout(() => t.remove(), type === 'error' ? 6000 : 3000);
}

/** Jalankan aksi async; error ditampilkan sebagai toast. Tombol dinonaktifkan selama berjalan. */
export async function guard(fn, button) {
  if (button) button.disabled = true;
  try {
    return await fn();
  } catch (err) {
    toast(err.message || String(err), 'error');
    return undefined;
  } finally {
    if (button) button.disabled = false;
  }
}

export const isAdmin = () => document.body.dataset.role === 'admin';
export const currentUserId = () => Number(document.body.dataset.userId);
