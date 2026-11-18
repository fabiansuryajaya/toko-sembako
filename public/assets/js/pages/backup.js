import { api } from '../core/api.js';
import { $, guard, toast } from '../core/dom.js';

export function init(root) {
  const form = $('.form-import', root);
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const file = form.elements.file.files[0];
    if (!file) return toast('Pilih file backup (.sql) terlebih dahulu', 'error');
    if (!confirm(`Ganti SELURUH isi database dengan isi file "${file.name}"?`)) return;
    const data = new FormData();
    data.append('file', file);
    guard(async () => {
      const r = await api.upload('backup/import', data);
      toast(`Database dipulihkan (${r.statements} perintah). Salinan sebelumnya: ${r.safety_backup}`);
      // data user bisa berubah: login ulang supaya sesi sesuai isi database baru
      setTimeout(() => { window.location.href = 'index.php'; }, 2500);
      await api.post('auth/logout');
    }, $('[type=submit]', form));
  });
}
