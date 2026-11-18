import { api } from '../core/api.js';
import { $, guard, toast } from '../core/dom.js';

const TEXT_FIELDS = ['nama_toko', 'alamat_toko', 'telepon_toko', 'catatan_struk', 'penutup_struk', 'penutup_nota_hutang'];

export async function init(root) {
  const form = $('.form-settings', root);
  const sigForm = $('.form-signature', root);
  const preview = $('.signature-preview', root);
  const empty = $('.signature-empty', root);

  function show(settings) {
    for (const key of TEXT_FIELDS) form.elements[key].value = settings[key] ?? '';
    form.elements.tampilkan_ttd_nota_hutang.checked = settings.tampilkan_ttd_nota_hutang;
    preview.hidden = !settings.gambar_ttd;
    empty.hidden = Boolean(settings.gambar_ttd);
    if (settings.gambar_ttd) preview.src = `${settings.gambar_ttd}?t=${Date.now()}`;
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const values = Object.fromEntries(TEXT_FIELDS.map((k) => [k, form.elements[k].value]));
    values.tampilkan_ttd_nota_hutang = form.elements.tampilkan_ttd_nota_hutang.checked;
    guard(async () => {
      show(await api.put('settings', { values }));
      toast('Pengaturan disimpan');
    }, $('[type=submit]', form));
  });

  sigForm.elements.signature.addEventListener('change', () => {
    const file = sigForm.elements.signature.files[0];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    empty.hidden = true;
  });

  sigForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const file = sigForm.elements.signature.files[0];
    if (!file) return toast('Pilih gambar terlebih dahulu', 'error');
    const data = new FormData();
    data.append('signature', file);
    guard(async () => {
      show(await api.upload('settings/signature', data));
      sigForm.reset();
      toast('Gambar tanda tangan disimpan');
    }, $('[type=submit]', sigForm));
  });

  $('.btn-delete-signature', root).addEventListener('click', (e) => {
    if (!confirm('Hapus gambar tanda tangan?')) return;
    guard(async () => {
      show(await api.del('settings/signature'));
      toast('Gambar tanda tangan dihapus');
    }, e.currentTarget);
  });

  await guard(async () => show(await api.get('settings')));
}
