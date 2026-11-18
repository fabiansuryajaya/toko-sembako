import { api } from '../core/api.js';
import { $, html, setHtml, on, guard, toast, currentUserId } from '../core/dom.js';
import { dateTime } from '../core/format.js';
import { openModal, closeModal } from '../core/modal.js';

export async function init(root) {
  const tbody = $('tbody', root);
  const modal = $('.modal', root);
  const form = $('form', modal);
  let editingId = null;

  async function load() {
    const users = await api.get('users');
    setHtml(tbody, html`${users.map((u) => html`<tr>
      <td>${u.id}</td>
      <td>${u.username}${u.id === currentUserId() ? html` <span class="badge">Anda</span>` : ''}</td>
      <td><span class="badge ${u.role === 'admin' ? 'badge-ok' : ''}">${u.role}</span></td>
      <td>${dateTime(u.created_at)}</td>
      <td class="actions">
        <button class="btn btn-sm btn-edit" data-id="${u.id}">Edit</button>
        ${u.id === currentUserId() ? '' : html`<button class="btn btn-sm btn-danger btn-delete" data-id="${u.id}">Hapus</button>`}
      </td>
    </tr>`)}`);
  }

  function open(user = null) {
    editingId = user?.id ?? null;
    $('.modal-title', modal).textContent = user ? `Edit User ${user.username}` : 'Tambah User';
    form.reset();
    form.elements.username.value = user?.username ?? '';
    form.elements.role.value = user?.role ?? 'pegawai';
    $('.hint-password', modal).textContent = user ? 'Kosongkan bila password tidak diganti. Minimal 6 karakter.' : 'Minimal 6 karakter.';
    openModal(modal);
  }

  $('.btn-create', root).addEventListener('click', () => open());
  on(tbody, 'click', '.btn-edit', (e, btn) => guard(async () => open(await api.get('users', { id: btn.dataset.id })), btn));
  on(tbody, 'click', '.btn-delete', (e, btn) => {
    if (!confirm('Hapus user ini? User yang sudah punya transaksi tidak bisa dihapus.')) return;
    guard(async () => {
      await api.del('users', { id: btn.dataset.id });
      toast('User dihapus');
      await load();
    }, btn);
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const body = { username: form.elements.username.value, role: form.elements.role.value, password: form.elements.password.value };
    guard(async () => {
      if (editingId) await api.put('users', body, { id: editingId });
      else await api.post('users', body);
      closeModal(modal);
      toast('User disimpan');
      await load();
    }, $('[type=submit]', form));
  });

  await guard(load);
}
