// Halaman CRUD sederhana (supplier, satuan, member): tabel + modal form.
// Markup yang dipakai: lihat src/Views/_crud.php.

import { api } from './api.js';
import { $, html, setHtml, on, guard, toast } from './dom.js';
import { openModal, closeModal } from './modal.js';

/**
 * @param {HTMLElement} root
 * @param {{resource: string, noun: string, fields: Array<{key:string, label:string, type?:string, required?:boolean}>, deleteMessage?: string}} cfg
 */
export function crudPage(root, { resource, noun, fields, deleteMessage }) {
  const tbody = $('tbody', root);
  const modal = $('.modal', root);
  const form = $('form', modal);
  const title = $('.modal-title', modal);
  let editingId = null;

  async function load() {
    const rows = await api.get(resource);
    setHtml(tbody, rows.length === 0
      ? html`<tr class="empty"><td colspan="${fields.length + 2}">Belum ada data</td></tr>`
      : html`${rows.map((r) => html`
          <tr>
            <td>${r.id}</td>
            ${fields.map((f) => html`<td>${r[f.key]}</td>`)}
            <td class="actions">
              <button class="btn btn-sm btn-edit" data-id="${r.id}">Edit</button>
              <button class="btn btn-sm btn-danger btn-delete" data-id="${r.id}">Hapus</button>
            </td>
          </tr>`)}`);
  }

  function open(row = null) {
    editingId = row?.id ?? null;
    title.textContent = `${row ? 'Edit' : 'Tambah'} ${noun}`;
    form.reset();
    for (const f of fields) form.elements[f.key].value = row?.[f.key] ?? '';
    openModal(modal);
  }

  $('.btn-create', root).addEventListener('click', () => open());

  on(tbody, 'click', '.btn-edit', (e, btn) => guard(async () => open(await api.get(resource, { id: btn.dataset.id })), btn));

  on(tbody, 'click', '.btn-delete', (e, btn) => {
    if (!confirm(deleteMessage ?? `Hapus ${noun.toLowerCase()} ini?`)) return;
    guard(async () => {
      await api.del(resource, { id: btn.dataset.id });
      toast(`${noun} dihapus`);
      await load();
    }, btn);
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const body = Object.fromEntries(fields.map((f) => [f.key, form.elements[f.key].value]));
    guard(async () => {
      if (editingId) await api.put(resource, body, { id: editingId });
      else await api.post(resource, body);
      closeModal(modal);
      toast(`${noun} disimpan`);
      await load();
    }, $('[type=submit]', form));
  });

  return guard(load);
}
