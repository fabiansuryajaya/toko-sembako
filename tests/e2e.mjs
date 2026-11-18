// Uji alur lengkap di browser (Playwright). Jalankan server dulu, lalu:
//   BASE_URL=http://127.0.0.1:8765/public node tests/e2e.mjs
// Database harus berisi seed awal (database/schema.sql + seed.sql). Tes ini MENGUBAH data.
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8765/public';
const SHOTS = process.env.SHOTS_DIR ?? join(tmpdir(), 'toko-sembako-e2e');
mkdirSync(SHOTS, { recursive: true });

const errors = [];
let expectHttpError = false; // true saat sengaja menguji penolakan server
let passed = 0;
const check = (cond, msg) => {
  if (!cond) throw new Error(`GAGAL: ${msg}`);
  passed++;
  console.log(`  ✔ ${msg}`);
};

const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});

async function session(username, password) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 }, acceptDownloads: true });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(`[${username}] pageerror: ${e.message}`));
  page.on('console', (m) => {
    if (m.type() !== 'error') return;
    if (expectHttpError && m.text().includes('status of 4')) return;
    errors.push(`[${username}] console: ${m.text()}`);
  });
  page.on('dialog', (d) => (d.type() === 'prompt' ? d.accept('HAPUS') : d.accept()));
  await page.goto(`${BASE}/index.php`);
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('button[type=submit]');
  await page.waitForURL(/app\.php/);
  return { ctx, page };
}

const go = async (page, slug) => {
  await page.goto(`${BASE}/app.php#${slug}`);
  await page.waitForSelector(`.menu a[data-page="${slug}"].active`);
  await page.waitForLoadState('networkidle');
};
const toastText = async (page) => (await page.waitForSelector('.toast')).textContent();
const clearToasts = (page) => page.evaluate(() => document.querySelectorAll('.toast').forEach((t) => t.remove()));
const pick = (page, selector, value) => page.evaluate(([s, v]) => window.jQuery(s).val(v).trigger('change'), [selector, String(value)]);
const stock = async (page, id) => page.evaluate(async (pid) => {
  const r = await fetch(`api.php?r=products&id=${pid}`);
  return (await r.json()).data.stok;
}, id);

try {
  // ================= ADMIN =================
  console.log('admin');
  const { page: a } = await session('admin', 'admin');
  check(await a.isVisible('#default-password-warning'), 'peringatan password awal tampil');

  // --- barang ---
  await go(a, 'barang');
  check((await a.$$('tbody tr')).length === 5, 'daftar barang berisi 5 barang seed');
  await a.fill('.input-search', 'gula');
  check((await a.$$('tbody tr')).length === 1, 'pencarian barang bekerja');
  await a.fill('.input-search', '');
  await a.fill('input[data-id="1"][data-key="harga_jual"]', '14000');
  await a.dispatchEvent('input[data-id="1"][data-key="harga_jual"]', 'input');
  check((await a.textContent('.btn-save-prices')).includes('1'), 'tombol simpan menghitung 1 perubahan harga');
  await a.click('.btn-save-prices');
  check((await toastText(a)).includes('harga'), 'perubahan harga disimpan');
  await clearToasts(a);

  await a.click('.btn-create');
  await a.fill('form [name=nama]', 'Kopi <b>Bubuk</b>');
  await pick(a, 'form [name=supplier_id]', 3);
  await pick(a, 'form [name=unit_id]', 3);
  await a.fill('form [name=harga_beli]', '5000');
  await a.fill('form [name=harga_jual]', '6500');
  await a.fill('form [name=stok]', '20');
  await a.click('.modal-form [type=submit]');
  await toastText(a);
  await clearToasts(a);
  check(await a.isVisible('text=Kopi <b>Bubuk</b>'), 'nama barang berisi HTML tampil sebagai teks (aman dari XSS)');
  await a.screenshot({ path: `${SHOTS}/1-barang-admin.png` });

  // --- member ---
  await go(a, 'member');
  await a.click('.btn-create');
  await a.fill('.modal [name=nama]', 'Bu Sari');
  await a.fill('.modal [name=nomor_hp]', '0812-3456');
  await a.click('.modal [type=submit]');
  await toastText(a);
  await clearToasts(a);
  check(await a.isVisible('td:text("Bu Sari")'), 'member baru tersimpan');

  // --- penjualan: pilih barang lewat kotak pencarian Select2 ---
  await go(a, 'penjualan');
  await a.click('.btn-create');
  await a.click('.modal-form [name=product_id] + .select2 .select2-selection');
  await a.keyboard.type('Beras Med');
  await a.keyboard.press('Enter');
  await a.click('.btn-add');
  await a.fill('.cart .input-qty', '3');
  await a.dispatchEvent('.cart .input-qty', 'input');
  await a.fill('[name=dibayar]', '50000');
  await a.dispatchEvent('[name=dibayar]', 'input');
  check((await a.textContent('.out-total')).replace(/\s/g, '') === 'Rp42.000', 'grand total 3 × Rp14.000');
  check((await a.textContent('.out-change')).replace(/\s/g, '') === 'Rp8.000', 'kembalian dihitung');
  await a.fill('[name=nama_pembeli]', 'Pak Budi');
  await a.click('.modal-form [type=submit]');
  await a.waitForSelector('.modal-receipt:not([hidden])');
  check((await a.textContent('.receipt-preview')).includes('Toko Sembako'), 'struk langsung tampil setelah transaksi');
  check(await stock(a, 1) === 97, 'stok beras berkurang 3');
  await a.screenshot({ path: `${SHOTS}/2-struk.png` });
  await a.click('.modal-receipt [data-close]');
  await clearToasts(a);

  // edit penjualan: 3 → 5, lalu simpan ulang tanpa perubahan (bug lama: stok berkurang lagi)
  await a.click('.list .btn-edit');
  await a.waitForSelector('.modal-form:not([hidden]) .cart .input-qty');
  await a.fill('.cart .input-qty', '5');
  await a.dispatchEvent('.cart .input-qty', 'input');
  await a.click('.modal-form [type=submit]');
  await toastText(a);
  check(await stock(a, 1) === 95, 'edit penjualan: stok = 100 - 5');
  await clearToasts(a);
  await a.click('.list .btn-edit');
  await a.waitForSelector('.modal-form:not([hidden]) .cart .input-qty');
  await a.click('.modal-form [type=submit]');
  await toastText(a);
  check(await stock(a, 1) === 95, 'simpan ulang edit tidak mengurangi stok lagi');
  await clearToasts(a);
  await a.screenshot({ path: `${SHOTS}/3-penjualan.png` });

  // --- hutang ---
  await go(a, 'hutang');
  await a.click('.btn-create');
  await pick(a, '.modal-form [name=member_id]', 1);
  await pick(a, '.modal-form [name=product_id]', 3);
  await a.click('.btn-add');
  await pick(a, '.modal-form [name=product_id]', 3); // barang sama dipilih lagi → jumlah 2
  await a.click('.btn-add');
  check(await a.inputValue('.cart .input-qty') === '2', 'menambah barang yang sama menaikkan jumlah');
  await a.fill('[name=ongkir]', '5000');
  await a.dispatchEvent('[name=ongkir]', 'input');
  await a.click('.modal-form [type=submit]');
  await a.waitForSelector('.modal-receipt:not([hidden])');
  check((await a.textContent('.receipt-preview')).includes('Belum lunas'), 'nota hutang menampilkan status belum lunas');
  await a.click('.modal-receipt [data-close]');
  check(await a.isVisible('.list .badge-warn'), 'hutang tampil belum lunas');
  await a.click('.list .btn-pay');
  await a.waitForSelector('.list .badge-ok');
  check(!(await a.isVisible('.list .btn-delete')), 'hutang lunas tidak bisa dihapus dari daftar');
  await clearToasts(a);
  await a.click('.list .btn-detail');
  await a.click('.btn-unpay');
  await a.waitForSelector('.list .badge-warn');
  check(true, 'batalkan lunas bekerja');
  await a.screenshot({ path: `${SHOTS}/4-hutang.png` });

  // --- restock ---
  await go(a, 'restock');
  await a.click('.btn-create');
  await pick(a, '.modal-form [name=product_id]', 2);
  await a.click('.btn-add');
  await a.click('.btn-price');
  await a.fill('.cart .input-price', '300000');
  await a.dispatchEvent('.cart .input-price', 'input');
  await a.fill('.cart .input-qty', '4');
  await a.dispatchEvent('.cart .input-qty', 'input');
  await a.click('.modal-form [type=submit]');
  await toastText(a);
  check(await stock(a, 2) === 14, 'restock menambah stok 10 + 4');
  await clearToasts(a);

  // --- laporan ---
  await go(a, 'laporan');
  await a.waitForSelector('tfoot th');
  check((await a.textContent('tbody')).includes('Beras Medium'), 'laporan berisi penjualan lunas');
  check(!(await a.textContent('tbody')).includes('Minyak'), 'laporan tidak berisi hutang belum lunas');
  await a.screenshot({ path: `${SHOTS}/5-laporan.png` });

  // --- pengaturan & tanda tangan ---
  await go(a, 'pengaturan');
  await a.fill('[name=nama_toko]', 'TK. Contoh Jaya');
  await a.click('.form-settings [type=submit]');
  await toastText(a);
  await clearToasts(a);
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
  await a.setInputFiles('[name=signature]', { name: 'ttd.png', mimeType: 'image/png', buffer: png });
  await a.click('.form-signature [type=submit]');
  check((await toastText(a)).includes('disimpan'), 'gambar tanda tangan PNG diterima');
  await clearToasts(a);
  expectHttpError = true;
  await a.setInputFiles('[name=signature]', { name: 'shell.php', mimeType: 'image/png', buffer: Buffer.from('<?php system($_GET["c"]);') });
  await a.click('.form-signature [type=submit]');
  check((await toastText(a)).includes('harus berupa gambar'), 'file .php berkedok gambar ditolak');
  expectHttpError = false;
  await clearToasts(a);

  // --- backup ---
  await go(a, 'backup');
  const [download] = await Promise.all([a.waitForEvent('download'), a.click('a[href*="backup/export"]')]);
  const path = await download.path();
  const { readFileSync } = await import('node:fs');
  check(readFileSync(path, 'utf8').startsWith('-- toko-sembako backup'), 'backup database terunduh');

  // --- user & akun ---
  await go(a, 'user');
  check((await a.$$('tbody tr')).length === 2, 'daftar user berisi admin & pegawai');
  await go(a, 'akun');
  await a.fill('[name=password_lama]', 'admin');
  await a.fill('[name=password_baru]', 'admin-baru-1');
  await a.fill('[name=password_ulang]', 'admin-baru-1');
  await a.click('[type=submit]');
  check((await toastText(a)).includes('berhasil'), 'ganti password sendiri');
  check(!(await a.isVisible('#default-password-warning')), 'peringatan password awal hilang');

  // ================= PEGAWAI =================
  console.log('pegawai');
  const { page: p } = await session('pegawai', 'pegawai');
  const menu = await p.$$eval('.menu a[data-page]', (els) => els.map((e) => e.dataset.page));
  check(JSON.stringify(menu) === JSON.stringify(['barang', 'penjualan', 'hutang', 'akun']), `menu pegawai hanya barang, penjualan, hutang, akun (${menu})`);
  await go(p, 'barang');
  check(!(await p.isVisible('text=Modal')), 'pegawai tidak melihat kolom modal');
  check(!(await p.isVisible('.btn-create')), 'pegawai tidak bisa menambah barang');
  await go(p, 'penjualan');
  await p.click('.btn-create');
  check(!(await p.isVisible('.btn-price')), 'pegawai tidak punya tombol ganti harga');
  check(!(await p.isVisible('.modal-form [name=user_id]')), 'pegawai tidak memilih kasir');
  await pick(p, '.modal-form [name=product_id]', 4);
  await p.click('.btn-add');
  await p.click('.modal-form [type=submit]');
  await p.waitForSelector('.modal-receipt:not([hidden])');
  check((await p.textContent('.receipt-preview')).includes('TK. Contoh Jaya'), 'struk memakai nama toko dari pengaturan');
  check((await p.textContent('.receipt-preview')).includes('Kasir: pegawai'), 'kasir tercatat pegawai');
  check(!(await p.isVisible('.list .btn-delete')), 'pegawai tidak bisa menghapus penjualan');
  await p.click('.modal-receipt [data-close]');
  await p.screenshot({ path: `${SHOTS}/6-penjualan-pegawai.png` });
  await p.goto(`${BASE}/app.php#laporan`);
  await p.waitForLoadState('networkidle');
  check(await p.locator('.menu a.active').getAttribute('data-page') === 'barang', 'hash halaman admin diarahkan ke halaman pertama');

  // tampilan HP
  await p.setViewportSize({ width: 390, height: 844 });
  await go(p, 'penjualan');
  await p.screenshot({ path: `${SHOTS}/7-hp.png`, fullPage: true });
  const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  check(overflow <= 0, `tidak ada scroll horizontal di layar HP (selisih ${overflow}px)`);

  // logout
  await p.click('#logout-link');
  await p.waitForURL(/index\.php/);
  expectHttpError = true;
  const after = await p.evaluate(async () => (await fetch('api.php?r=products')).status);
  check(after === 401, 'setelah logout API menolak (401)');
} catch (e) {
  console.error(e.message);
  errors.push(e.message);
} finally {
  await browser.close();
}

console.log(`\n${passed} cek lulus`);
if (errors.length) {
  console.log(`${errors.length} masalah:\n - ${errors.join('\n - ')}`);
  process.exit(1);
}
console.log(`screenshot: ${SHOTS}`);
writeFileSync(join(SHOTS, 'ok'), '');
