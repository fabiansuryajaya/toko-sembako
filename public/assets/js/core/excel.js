// Ekspor tabel ke .xlsx memakai SheetJS (dimuat saat dibutuhkan saja, ±900 KB).

let loading = null;
function loadXlsx() {
  if (window.XLSX) return Promise.resolve(window.XLSX);
  loading ??= new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = 'assets/vendor/xlsx.full.min.js';
    s.onload = () => resolve(window.XLSX);
    s.onerror = () => reject(new Error('Gagal memuat modul Excel'));
    document.head.appendChild(s);
  });
  return loading;
}

/**
 * @param {string} filename  mis. 'barang.xlsx'
 * @param {string} sheet
 * @param {Array<Array<string|number>>} rows baris pertama = judul kolom
 */
export async function exportXlsx(filename, sheet, rows) {
  const XLSX = await loadXlsx();
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(rows), sheet);
  XLSX.writeFile(wb, filename);
}
