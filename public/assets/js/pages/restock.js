import { api } from '../core/api.js';
import { $, html, setHtml, on, guard, toast } from '../core/dom.js';
import { rupiah, number, dateTime, today } from '../core/format.js';
import { openModal, closeModal } from '../core/modal.js';
import { fillSelect, setSelect, getSelect } from '../core/select.js';
import { createCart } from '../core/cart.js';

export async function init(root) {
  const list = $('.list', root);
  const from = $('.filter-from', root);
  const to = $('.filter-to', root);
  const formModal = $('.modal-form', root);
  const form = $('form', formModal);
  const detailModal = $('.modal-detail', root);
  let products = [];

  from.value = today();
  to.value = today();

  const cart = createCart($('.cart', form), {
    priceKey: 'harga_beli',
    canEditPrice: true,
    onChange: (total) => { $('.out-total', form).value = rupiah(total); },
  });

  async function load() {
    const rows = await api.get('purchases', { from: from.value, to: to.value });
    setHtml(list, rows.length === 0
      ? html`<tr class="empty"><td colspan="6">Tidak ada restock pada rentang tanggal ini</td></tr>`
      : html`${rows.map((r) => html`<tr>
          <td>#${r.id}</td><td>${dateTime(r.created_at)}</td><td class="num">${number(r.total_qty)}</td>
          <td class="num">${rupiah(r.total)}</td><td>${r.user}</td>
          <td class="actions"><button class="btn btn-sm btn-detail" data-id="${r.id}">Detail</button></td>
        </tr>`)}`);
  }

  $('.btn-filter', root).addEventListener('click', (e) => guard(load, e.currentTarget));
  $('.btn-create', root).addEventListener('click', () => {
    cart.clear();
    $('.btn-price', form).classList.remove('active');
    openModal(formModal);
  });
  $('.btn-add', form).addEventListener('click', () => {
    const product = products.find((p) => p.id === Number(getSelect(form.elements.product_id)));
    if (!product) return toast('Pilih barang terlebih dahulu', 'error');
    cart.add(product);
    setSelect(form.elements.product_id, '');
  });
  $('.btn-price', form).addEventListener('click', (e) => e.currentTarget.classList.toggle('active', cart.togglePriceEditing()));

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (cart.isEmpty()) return toast('Belum ada barang di daftar restock', 'error');
    guard(async () => {
      const saved = await api.post('purchases', { items: cart.items() });
      closeModal(formModal);
      toast(`Restock #${saved.id} disimpan, stok bertambah`);
      products = await api.get('products');
      await load();
    }, $('[type=submit]', form));
  });

  on(list, 'click', '.btn-detail', (e, btn) => guard(async () => {
    const p = await api.get('purchases', { id: btn.dataset.id });
    $('.detail-title', detailModal).textContent = `Restock #${p.id}`;
    setHtml($('.detail-body', detailModal), html`
      <p class="muted">${dateTime(p.created_at)} · ${p.user}</p>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Barang</th><th class="num">Jumlah</th><th class="num">Harga Beli</th><th class="num">Total</th></tr></thead>
        <tbody>${p.items.map((i) => html`<tr><td>${i.nama}</td><td class="num">${number(i.qty)} ${i.satuan}</td><td class="num">${rupiah(i.harga_beli)}</td><td class="num">${rupiah(i.total)}</td></tr>`)}</tbody>
        <tfoot><tr><th colspan="3">Total</th><th class="num">${rupiah(p.total)}</th></tr></tfoot>
      </table></div>`);
    openModal(detailModal);
  }, btn));

  await guard(async () => {
    products = await api.get('products');
    fillSelect(form.elements.product_id, products, {
      label: (p) => `${p.nama} — ${p.supplier} — ${p.satuan} (stok ${number(p.stok)})`,
      placeholder: 'Cari barang…',
      dropdownParent: formModal,
    });
    await load();
  });
}
