// Struk 80 mm untuk penjualan tunai dan nota hutang. Identitas toko diambil dari Pengaturan.

import { html } from './dom.js';
import { rupiah, dateTime } from './format.js';

/**
 * @param {object} trx      hasil GET sales/debts ?id=
 * @param {object} settings hasil GET settings
 */
export function receiptHtml(trx, settings) {
  const isDebt = trx.jenis === 'hutang';
  const showSignature = isDebt && settings.tampilkan_ttd_nota_hutang;
  const row = (label, value) => html`<tr><td>${label}</td><td class="num">${value}</td></tr>`;

  return html`
    <div class="receipt">
      <div class="r-title">${settings.nama_toko || 'Toko Sembako'}</div>
      ${settings.alamat_toko ? html`<div class="r-center">${settings.alamat_toko}</div>` : ''}
      ${settings.telepon_toko ? html`<div class="r-center">Telp/WA: ${settings.telepon_toko}</div>` : ''}
      <hr>
      <div>No: #${trx.id}<br>Tanggal: ${dateTime(trx.created_at)}<br>Kasir: ${trx.kasir}</div>
      ${isDebt ? html`<div>Member: ${trx.member}</div>` : trx.nama_pembeli ? html`<div>Pembeli: ${trx.nama_pembeli}</div>` : ''}
      <hr>
      <table class="r-items">
        ${trx.items.map((i) => html`
          <tr><td colspan="2"><input type="checkbox" aria-label="cek ${i.nama}"> <strong>${i.nama}</strong></td></tr>
          <tr><td>${i.qty} ${i.satuan} × ${rupiah(i.harga_jual)}</td><td class="num">${rupiah(i.qty * i.harga_jual)}</td></tr>`)}
      </table>
      <hr class="thick">
      <table class="r-totals">
        ${row('Total', rupiah(trx.subtotal))}
        ${trx.ongkir > 0 ? row('Ongkir', rupiah(trx.ongkir)) : ''}
        ${trx.ongkir > 0 ? row('Grand Total', rupiah(trx.tagihan)) : ''}
        ${trx.dibayar > 0 ? row('Pembayaran', rupiah(trx.dibayar)) : ''}
        ${trx.dibayar > 0 ? row('Kembalian', rupiah(trx.kembalian)) : ''}
        ${isDebt ? row('Status', trx.lunas ? 'Lunas' : 'Belum lunas') : ''}
      </table>
      ${settings.catatan_struk ? html`<div class="r-center r-small">${settings.catatan_struk}</div>` : ''}
      <hr class="thick">
      ${showSignature ? (settings.gambar_ttd
        ? html`<div class="r-center"><img class="r-signature" src="${settings.gambar_ttd}" alt="Tanda tangan"></div><hr class="thick">`
        : html`<div class="r-sign-line">Tanda Tangan</div><hr class="thick">`) : ''}
      <div class="r-center"><strong>${isDebt ? settings.penutup_nota_hutang : settings.penutup_struk}</strong></div>
    </div>`;
}

const PRINT_CSS = `
  @page { size: 80mm auto; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { width: 80mm; padding: 3mm 4mm 8mm; font-family: Calibri, Arial, sans-serif; font-size: 13px; }
  .r-title { text-align: center; font-weight: bold; font-size: 15px; letter-spacing: 1px; }
  .r-center { text-align: center; } .r-small { font-size: 12px; margin-top: 1mm; }
  hr { border: 0; border-top: 1px dashed #333; margin: 2mm 0; } hr.thick { border-top-width: 2px; }
  table { width: 100%; border-collapse: collapse; } td { padding: .3mm 0; vertical-align: top; }
  .num { text-align: right; white-space: nowrap; } .r-totals td { font-weight: bold; }
  .r-signature { max-width: 100%; height: 20mm; object-fit: contain; }
  .r-sign-line { width: 45%; margin: 8mm auto 2mm; border-top: 1px solid #333; text-align: center; }`;

/** Buka jendela baru berisi struk lalu panggil dialog cetak. */
export function printReceipt(receipt) {
  const win = window.open('', '_blank', 'width=420,height=700');
  if (!win) {
    alert('Jendela cetak diblokir browser. Izinkan pop-up untuk situs ini.');
    return;
  }
  const base = new URL('.', window.location.href).href;
  win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><base href="${base}"><title>Struk</title><style>${PRINT_CSS}</style></head><body>${receipt.value ?? receipt}</body></html>`);
  win.document.close();
  win.focus();
  let printed = false;
  const go = () => {
    if (printed) return;
    printed = true;
    win.print();
  };
  win.addEventListener('load', go); // tunggu gambar tanda tangan termuat
  setTimeout(go, 800); // cadangan bila event load tidak terpicu
}
