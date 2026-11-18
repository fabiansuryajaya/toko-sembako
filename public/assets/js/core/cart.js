// Keranjang barang untuk penjualan, hutang, dan restock.
// State disimpan di array (bukan dibaca ulang dari teks "Rp…" di tabel seperti versi lama).

import { html, setHtml, on } from './dom.js';
import { rupiah } from './format.js';

/**
 * @param {HTMLTableSectionElement} tbody
 * @param {{priceKey: 'harga_jual'|'harga_beli', canEditPrice: boolean, onChange?: (total:number)=>void}} opt
 */
export function createCart(tbody, { priceKey, canEditPrice, onChange = () => {} }) {
  let rows = [];
  let editingPrice = false;

  const total = () => rows.reduce((sum, r) => sum + r.qty * r.harga, 0);

  function render() {
    if (rows.length === 0) {
      setHtml(tbody, html`<tr class="empty"><td colspan="6">Belum ada barang. Pilih barang lalu tekan Tambah.</td></tr>`);
    } else {
      setHtml(tbody, html`${rows.map((r, i) => html`
        <tr data-index="${i}">
          <td><button type="button" class="btn-icon btn-remove" title="Hapus baris" aria-label="Hapus ${r.nama}">✕</button></td>
          <td>${r.nama}</td>
          <td>${r.satuan}</td>
          <td><input type="number" class="input-price" min="0" step="1" value="${r.harga}" ${editingPrice ? '' : html`disabled`}></td>
          <td><input type="number" class="input-qty" min="1" step="1" value="${r.qty}"></td>
          <td class="num">${rupiah(r.qty * r.harga)}</td>
        </tr>`)}`);
    }
    onChange(total());
  }

  function updateRowTotal(tr) {
    const r = rows[Number(tr.dataset.index)];
    tr.lastElementChild.textContent = rupiah(r.qty * r.harga);
    onChange(total());
  }

  on(tbody, 'input', '.input-qty', (e, input) => {
    const tr = input.closest('tr');
    rows[Number(tr.dataset.index)].qty = Math.max(0, parseInt(input.value, 10) || 0);
    updateRowTotal(tr);
  });
  on(tbody, 'input', '.input-price', (e, input) => {
    const tr = input.closest('tr');
    rows[Number(tr.dataset.index)].harga = Math.max(0, parseInt(input.value, 10) || 0);
    updateRowTotal(tr);
  });
  on(tbody, 'click', '.btn-remove', (e, btn) => {
    rows.splice(Number(btn.closest('tr').dataset.index), 1);
    render();
  });

  return {
    /** Tambah barang; kalau sudah ada, jumlahnya +1. */
    add(product) {
      const existing = rows.find((r) => r.product_id === Number(product.id));
      if (existing) existing.qty += 1;
      else rows.unshift({ product_id: Number(product.id), nama: product.nama, satuan: product.satuan, harga: Number(product[priceKey]) || 0, qty: 1 });
      render();
    },
    /** Isi dari item transaksi tersimpan (saat edit). */
    load(items) {
      rows = items.map((i) => ({ product_id: Number(i.product_id), nama: i.nama, satuan: i.satuan, harga: Number(i[priceKey]) || 0, qty: Number(i.qty) }));
      render();
    },
    clear() {
      rows = [];
      editingPrice = false;
      render();
    },
    togglePriceEditing() {
      if (!canEditPrice) return false;
      editingPrice = !editingPrice;
      render();
      return editingPrice;
    },
    total,
    isEmpty: () => rows.every((r) => r.qty <= 0),
    /** Data untuk API. Baris dengan jumlah 0 dilewati. */
    items: () => rows.filter((r) => r.qty > 0).map((r) => ({ product_id: r.product_id, qty: r.qty, [priceKey]: r.harga })),
  };
}
