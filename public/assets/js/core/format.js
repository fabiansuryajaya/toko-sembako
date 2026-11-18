const idr = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 });
const num = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

export const rupiah = (v) => idr.format(Number(v) || 0);
export const number = (v) => num.format(Number(v) || 0);

/** "2026-11-18 14:05:00" → "18/11/2026 14.05" */
export function dateTime(s) {
  if (!s) return '';
  const d = new Date(String(s).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return s;
  return `${d.toLocaleDateString('id-ID')} ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
}

/** Tanggal hari ini (zona waktu lokal) dalam format YYYY-MM-DD. */
export function today() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
