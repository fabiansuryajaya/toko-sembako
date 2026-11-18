import { api } from '../core/api.js';
import { $, html, setHtml, guard, toast } from '../core/dom.js';
import { rupiah, number, today } from '../core/format.js';
import { fillSelect, getSelect } from '../core/select.js';
import { exportXlsx } from '../core/excel.js';

export async function init(root) {
  const from = $('.filter-from', root);
  const to = $('.filter-to', root);
  const productsEl = $('.filter-products', root);
  let report = { rows: [], totals: {} };

  from.value = today();
  to.value = today();

  async function load() {
    report = await api.get('report', { from: from.value, to: to.value, product_ids: getSelect(productsEl) ?? [] });
    const { rows, totals } = report;
    setHtml($('tbody', root), rows.length === 0
      ? html`<tr class="empty"><td colspan="9">Tidak ada penjualan lunas pada periode ini</td></tr>`
      : html`${rows.map((r) => html`<tr>
          <td>${r.nama}</td><td>${r.satuan}</td><td>${r.supplier}</td><td class="num">${number(r.qty)}</td>
          <td class="num">${rupiah(r.harga_beli)}</td><td class="num">${rupiah(r.total_modal)}</td>
          <td class="num">${rupiah(r.harga_jual)}</td><td class="num">${rupiah(r.total_penjualan)}</td>
          <td class="num ${r.untung < 0 ? 'text-danger' : ''}">${rupiah(r.untung)}</td>
        </tr>`)}`);
    setHtml($('tfoot', root), html`<tr>
      <th colspan="3">Total</th><th class="num">${number(totals.qty)}</th><th></th>
      <th class="num">${rupiah(totals.total_modal)}</th><th></th>
      <th class="num">${rupiah(totals.total_penjualan)}</th><th class="num">${rupiah(totals.untung)}</th>
    </tr>`);
  }

  $('.btn-filter', root).addEventListener('click', (e) => guard(load, e.currentTarget));

  $('.btn-export', root).addEventListener('click', (e) => guard(async () => {
    const head = ['Barang', 'Satuan', 'Supplier', 'Jumlah', 'Modal', 'Total Modal', 'Harga Jual', 'Total Penjualan', 'Untung'];
    const rows = report.rows.map((r) => [r.nama, r.satuan, r.supplier, r.qty, r.harga_beli, r.total_modal, r.harga_jual, r.total_penjualan, r.untung]);
    const t = report.totals;
    rows.push(['Total', '', '', t.qty, '', t.total_modal, '', t.total_penjualan, t.untung]);
    await exportXlsx(`laporan-${from.value}_${to.value}.xlsx`, 'Laporan', [head, ...rows]);
  }, e.currentTarget));

  $('.btn-purge', root).addEventListener('click', (e) => {
    const answer = prompt(
      `Hapus SEMUA transaksi lunas tanggal ${from.value} s/d ${to.value}?\n`
      + 'Hutang yang belum lunas tidak ikut terhapus. Tindakan ini tidak bisa dibatalkan — buat backup dulu.\n\n'
      + 'Ketik HAPUS untuk melanjutkan:',
    );
    if (answer === null) return;
    guard(async () => {
      const r = await api.del('report', { from: from.value, to: to.value }, { konfirmasi: answer.trim() });
      toast(`${r.deleted} transaksi dihapus`);
      await load();
    }, e.currentTarget);
  });

  await guard(async () => {
    const products = await api.get('products', { status: 'semua' });
    fillSelect(productsEl, products, { label: (p) => p.nama, placeholder: 'Semua barang' });
    await load();
  });
}
