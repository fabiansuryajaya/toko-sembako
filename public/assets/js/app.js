// Shell aplikasi: navigasi lewat hash (#barang, #penjualan, …).
// Tiap halaman = potongan HTML dari page.php?p=<slug> + modul JS pages/<slug>.js yang mengekspor init(root).

import { api } from './core/api.js';
import { $, $$, toast } from './core/dom.js';
import { wireModals } from './core/modal.js';

const pageEl = $('#page');
const links = $$('.menu a[data-page]');
const pages = links.map((a) => a.dataset.page);
let loadToken = 0;

async function show(page) {
  if (!pages.includes(page)) page = pages[0];
  const token = ++loadToken;
  links.forEach((a) => a.classList.toggle('active', a.dataset.page === page));
  pageEl.innerHTML = '<p class="muted">Memuat…</p>';
  try {
    const res = await fetch(`page.php?p=${encodeURIComponent(page)}`, { credentials: 'same-origin' });
    if (res.status === 401) return void (window.location.href = 'index.php');
    const markup = await res.text();
    if (token !== loadToken) return; // pengguna sudah pindah halaman
    // Wadah baru per kunjungan: kalau pengguna pindah halaman saat init() masih menunggu data,
    // kode halaman lama hanya menyentuh wadahnya sendiri yang sudah dilepas, bukan halaman baru.
    const container = document.createElement('div');
    container.innerHTML = markup;
    pageEl.replaceChildren(container);
    if (!res.ok) return;
    const mod = await import(`./pages/${page}.js`);
    if (token === loadToken) await mod.init(container);
    document.title = `${links.find((a) => a.dataset.page === page)?.textContent ?? ''} — Toko Sembako`;
  } catch (err) {
    console.error(err);
    if (token === loadToken) pageEl.innerHTML = '<p>Gagal memuat halaman.</p>';
  }
}

window.addEventListener('hashchange', () => show(location.hash.slice(1)));

$('#logout-link').addEventListener('click', async (e) => {
  e.preventDefault();
  try {
    await api.post('auth/logout');
  } finally {
    window.location.href = 'index.php';
  }
});

wireModals();
show(location.hash.slice(1));

api.get('auth/me')
  .then((me) => { $('#default-password-warning').hidden = !me.default_password; })
  .catch((err) => toast(err.message, 'error'));
