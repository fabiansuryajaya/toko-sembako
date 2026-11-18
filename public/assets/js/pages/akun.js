import { api } from '../core/api.js';
import { $, guard, toast } from '../core/dom.js';

export function init(root) {
  const form = $('form', root);
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const f = form.elements;
    if (f.password_baru.value !== f.password_ulang.value) return toast('Ulangi password baru dengan benar', 'error');
    guard(async () => {
      await api.put('auth/password', { password_lama: f.password_lama.value, password_baru: f.password_baru.value });
      form.reset();
      document.getElementById('default-password-warning').hidden = true;
      toast('Password berhasil diganti');
    }, $('[type=submit]', form));
  });
}
