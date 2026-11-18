// Halaman transaksi bersama untuk penjualan tunai (resource "sales") dan hutang (resource "debts").

import { api } from './api.js';
import { $, html, setHtml, on, guard, toast, isAdmin } from './dom.js';
import { rupiah, number, dateTime, today } from './format.js';
import { openModal, closeModal } from './modal.js';
import { fillSelect, setSelect, getSelect } from './select.js';
import { createCart } from './cart.js';
import { receiptHtml, printReceipt } from './receipt.js';

/**
 * @param {HTMLElement} root
 * @param {{resource: 'sales'|'debts', jenis: 'tunai'|'hutang', noun: string}} cfg
 */
export async function transactionPage(root, { resource, jenis, noun }) {
  const admin = isAdmin();
  const isDebt = jenis === 'hutang';
  const list = $('.list', root);
  const from = $('.filter-from', root);
  const to = $('.filter-to', root);
  const formModal = $('.modal-form', root);
  const form = $('form', formModal);
  const f = form.elements;
  const detailModal = $('.modal-detail', root);
  const receiptModal = $('.modal-receipt', root);
  let editingId = null;
  let products = [];
  let settings = {};
  let lastReceipt = null;

  from.value = today();
  to.value = today();

  // ---------- keranjang ----------
  const refreshTotals = () => {
    const ongkir = isDebt ? Number(f.ongkir.value) || 0 : 0;
    const total = cart.total() + ongkir;
    $('.out-total', form).value = rupiah(total);
    $('.out-change', form).value = rupiah((Number(f.dibayar.value) || 0) - total);
  };
  const cart = createCart($('.cart', form), { priceKey: 'harga_jual', canEditPrice: admin, onChange: refreshTotals });
  f.dibayar.addEventListener('input', refreshTotals);
  f.ongkir?.addEventListener('input', refreshTotals);

  $('.btn-add', form).addEventListener('click', () => {
    const id = Number(getSelect(f.product_id));
    const product = products.find((p) => p.id === id);
    if (!product) return toast('Pilih barang terlebih dahulu', 'error');
    cart.add(product);
    setSelect(f.product_id, '');
  });
  $('.btn-price', form)?.addEventListener('click', (e) => {
    e.currentTarget.classList.toggle('active', cart.togglePriceEditing());
  });

  // ---------- daftar ----------
  async function load() {
    const rows = await api.get(resource, { from: from.value, to: to.value });
    const sum = rows.reduce((a, r) => a + r.tagihan, 0);
    const unpaid = rows.filter((r) => !r.lunas).reduce((a, r) => a + r.tagihan - r.dibayar, 0);
    setHtml($('.summary', root), html`<span>${rows.length} transaksi</span><span>Total ${rupiah(sum)}</span>
      ${isDebt ? html`<span class="text-danger">Belum dibayar ${rupiah(unpaid)}</span>` : ''}`);

    setHtml(list, rows.length === 0
      ? html`<tr class="empty"><td colspan="8">Tidak ada transaksi pada rentang tanggal ini</td></tr>`
      : html`${rows.map((r) => html`
        <tr>
          <td>#${r.id}</td>
          <td>${dateTime(r.created_at)}</td>
          <td>${isDebt ? r.member : r.nama_pembeli || '—'}</td>
          <td class="num">${number(r.total_qty)}</td>
          <td class="num">${rupiah(r.tagihan)}</td>
          ${isDebt ? html`<td><span class="badge ${r.lunas ? 'badge-ok' : 'badge-warn'}">${r.lunas ? 'Lunas' : 'Belum lunas'}</span></td>` : ''}
          <td>${r.kasir}</td>
          <td class="actions">
            <button class="btn btn-sm btn-detail" data-id="${r.id}">Detail</button>
            <button class="btn btn-sm btn-receipt" data-id="${r.id}">Struk</button>
            <button class="btn btn-sm btn-edit" data-id="${r.id}">Edit</button>
            ${isDebt && admin && !r.lunas ? html`<button class="btn btn-sm btn-success btn-pay" data-id="${r.id}">Lunas</button>` : ''}
            ${admin && !(isDebt && r.lunas) ? html`<button class="btn btn-sm btn-danger btn-delete" data-id="${r.id}">Hapus</button>` : ''}
          </td>
        </tr>`)}`);
  }
  $('.btn-filter', root).addEventListener('click', (e) => guard(load, e.currentTarget));

  // ---------- form ----------
  function openForm(trx = null) {
    editingId = trx?.id ?? null;
    $('.modal-title', formModal).textContent = trx ? `Edit ${noun} #${trx.id}` : `${noun} Baru`;
    form.reset();
    $('.btn-price', form)?.classList.remove('active');
    cart.clear();
    if (trx) cart.load(trx.items);
    if (f.user_id) setSelect(f.user_id, trx?.user_id ?? document.body.dataset.userId);
    if (isDebt) {
      setSelect(f.member_id, trx?.member_id ?? '');
      f.ongkir.value = trx?.ongkir ?? 0;
    } else {
      f.nama_pembeli.value = trx?.nama_pembeli ?? '';
    }
    f.dibayar.value = trx?.dibayar ?? 0;
    setSelect(f.product_id, '');
    refreshTotals();
    openModal(formModal);
  }

  $('.btn-create', root).addEventListener('click', () => openForm());
  on(list, 'click', '.btn-edit', (e, btn) => guard(async () => openForm(await api.get(resource, { id: btn.dataset.id })), btn));

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (cart.isEmpty()) return toast('Belum ada barang di keranjang', 'error');
    const body = { items: cart.items(), dibayar: Number(f.dibayar.value) || 0 };
    if (f.user_id) body.user_id = getSelect(f.user_id);
    if (isDebt) {
      body.member_id = getSelect(f.member_id);
      if (!body.member_id) return toast('Pilih member terlebih dahulu', 'error');
      body.ongkir = Number(f.ongkir.value) || 0;
    } else {
      body.nama_pembeli = f.nama_pembeli.value;
    }
    guard(async () => {
      const saved = editingId ? await api.put(resource, body, { id: editingId }) : await api.post(resource, body);
      closeModal(formModal);
      toast(`${noun} #${saved.id} disimpan`);
      await load();
      if (!editingId) showReceipt(saved); // langsung tampilkan struk untuk transaksi baru
    }, $('[type=submit]', form));
  });

  // ---------- detail ----------
  on(list, 'click', '.btn-detail', (e, btn) => guard(async () => {
    const t = await api.get(resource, { id: btn.dataset.id });
    $('.detail-title', detailModal).textContent = `${noun} #${t.id}`;
    setHtml($('.detail-body', detailModal), html`
      <p class="muted">${dateTime(t.created_at)} · Kasir ${t.kasir}${isDebt ? html` · Member ${t.member} · ${t.lunas ? `Lunas ${dateTime(t.lunas_at)}` : 'Belum lunas'}` : t.nama_pembeli ? ` · Pembeli ${t.nama_pembeli}` : ''}</p>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Barang</th><th>Supplier</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Total</th></tr></thead>
        <tbody>${t.items.map((i) => html`<tr><td>${i.nama}</td><td>${i.supplier}</td><td class="num">${number(i.qty)} ${i.satuan}</td><td class="num">${rupiah(i.harga_jual)}</td><td class="num">${rupiah(i.total)}</td></tr>`)}</tbody>
        <tfoot>
          ${t.ongkir > 0 ? html`<tr><td colspan="4">Ongkir</td><td class="num">${rupiah(t.ongkir)}</td></tr>` : ''}
          <tr><th colspan="4">Total</th><th class="num">${rupiah(t.tagihan)}</th></tr>
          <tr><td colspan="4">Dibayar</td><td class="num">${rupiah(t.dibayar)}</td></tr>
        </tfoot>
      </table></div>`);
    const unpay = $('.btn-unpay', detailModal);
    if (unpay) {
      unpay.hidden = !t.lunas;
      unpay.dataset.id = t.id;
    }
    openModal(detailModal);
  }, btn));

  // ---------- struk ----------
  function showReceipt(trx) {
    lastReceipt = receiptHtml(trx, settings);
    setHtml($('.receipt-preview', receiptModal), lastReceipt);
    openModal(receiptModal);
  }
  on(list, 'click', '.btn-receipt', (e, btn) => guard(async () => showReceipt(await api.get(resource, { id: btn.dataset.id })), btn));
  $('.btn-print', receiptModal).addEventListener('click', () => lastReceipt && printReceipt(lastReceipt));

  // ---------- hapus & lunas (admin) ----------
  on(list, 'click', '.btn-delete', (e, btn) => {
    if (!confirm(`Hapus ${noun.toLowerCase()} #${btn.dataset.id}? Stok barangnya akan dikembalikan.`)) return;
    guard(async () => {
      await api.del(resource, { id: btn.dataset.id });
      toast(`${noun} #${btn.dataset.id} dihapus, stok dikembalikan`);
      await load();
    }, btn);
  });

  const setPaid = (id, lunas, btn) => guard(async () => {
    await api.put('debts/paid', { lunas }, { id });
    toast(lunas ? `Hutang #${id} ditandai lunas` : `Status lunas hutang #${id} dibatalkan`);
    closeModal(detailModal);
    await load();
  }, btn);
  on(list, 'click', '.btn-pay', (e, btn) => confirm(`Tandai hutang #${btn.dataset.id} sebagai lunas?`) && setPaid(btn.dataset.id, true, btn));
  $('.btn-unpay', detailModal)?.addEventListener('click', (e) => {
    const btn = e.currentTarget;
    if (confirm(`Batalkan status lunas hutang #${btn.dataset.id}?`)) setPaid(btn.dataset.id, false, btn);
  });

  const toggleSig = $('.toggle-signature', root);
  toggleSig?.addEventListener('change', () => guard(async () => {
    settings = await api.put('settings', { values: { tampilkan_ttd_nota_hutang: toggleSig.checked } });
    toast(toggleSig.checked ? 'Tanda tangan akan tampil di nota hutang' : 'Tanda tangan disembunyikan dari nota hutang');
  }));

  // ---------- data awal ----------
  await guard(async () => {
    const [p, s, members, users] = await Promise.all([
      api.get('products'),
      api.get('settings'),
      isDebt ? api.get('members') : null,
      admin ? api.get('users') : null,
    ]);
    products = p;
    settings = s;
    if (toggleSig) toggleSig.checked = settings.tampilkan_ttd_nota_hutang;
    fillSelect(f.product_id, products, { label: (x) => `${x.nama} — ${x.satuan} — ${rupiah(x.harga_jual)} (stok ${number(x.stok)})`, placeholder: 'Cari barang…', dropdownParent: formModal });
    if (members) fillSelect(f.member_id, members, { label: (m) => `${m.nama}${m.nomor_hp ? ` — ${m.nomor_hp}` : ''}`, placeholder: 'Pilih member', dropdownParent: formModal });
    if (users && f.user_id) fillSelect(f.user_id, users, { label: (u) => `${u.username} (${u.role})`, placeholder: 'Pilih kasir', dropdownParent: formModal });
    await load();
  });
}
