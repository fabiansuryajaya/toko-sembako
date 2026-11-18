import { api } from '../core/api.js';
import { $, html, setHtml, on, guard, toast, isAdmin } from '../core/dom.js';
import { rupiah, number } from '../core/format.js';
import { openModal, closeModal } from '../core/modal.js';
import { fillSelect, setSelect, getSelect } from '../core/select.js';
import { exportXlsx } from '../core/excel.js';

export async function init(root) {
  const admin = isAdmin();
  const tbody = $('tbody', root);
  const search = $('.input-search', root);
  const activeOnly = $('.filter-active', root);
  const saveBtn = $('.btn-save-prices', root);
  const formModal = $('.modal-form', root);
  const form = formModal ? $('form', formModal) : null;
  const detailModal = $('.modal-detail', root);

  let products = [];
  let editingId = null;
  const priceChanges = new Map(); // id -> {harga_beli?, harga_jual?}

  try {
    activeOnly.checked = localStorage.getItem('barang.aktif') !== '0';
  } catch { /* localStorage bisa diblokir */ }

  async function load() {
    products = await api.get('products', { status: activeOnly.checked ? 'aktif' : 'semua' });
    priceChanges.clear();
    updateSaveButton();
    render();
  }

  function render() {
    const q = search.value.trim().toLowerCase();
    const rows = q ? products.filter((p) => p.nama.toLowerCase().includes(q)) : products;
    const priceCell = (p, key) => (admin
      ? html`<input type="number" class="input-inline" min="0" step="1" data-id="${p.id}" data-key="${key}" value="${p[key]}" aria-label="${key} ${p.nama}">`
      : rupiah(p[key]));
    setHtml(tbody, rows.length === 0
      ? html`<tr class="empty"><td colspan="9">Tidak ada barang</td></tr>`
      : html`${rows.map((p) => html`
        <tr class="${p.aktif ? '' : 'row-muted'}">
          <td>${p.id}</td>
          <td>${p.nama}</td>
          ${admin ? html`<td>${p.supplier}</td>` : ''}
          <td>${p.satuan}</td>
          ${admin ? html`<td>${priceCell(p, 'harga_beli')}</td>` : ''}
          <td>${priceCell(p, 'harga_jual')}</td>
          <td class="num ${p.stok < 0 ? 'text-danger' : ''}">${number(p.stok)}</td>
          <td><span class="badge ${p.aktif ? 'badge-ok' : 'badge-off'}">${p.aktif ? 'Aktif' : 'Nonaktif'}</span></td>
          <td class="actions">
            <button class="btn btn-sm btn-detail" data-id="${p.id}">Detail</button>
            ${admin ? html`<button class="btn btn-sm btn-edit" data-id="${p.id}">Edit</button>
            ${p.aktif ? html`<button class="btn btn-sm btn-danger btn-delete" data-id="${p.id}">Nonaktifkan</button>` : ''}` : ''}
          </td>
        </tr>`)}`);
  }

  function updateSaveButton() {
    if (!saveBtn) return;
    saveBtn.textContent = `Simpan ${priceChanges.size} perubahan harga`;
    saveBtn.disabled = priceChanges.size === 0;
  }

  // ---------- ubah harga langsung di tabel (admin) ----------
  on(tbody, 'input', '.input-inline', (e, input) => {
    const id = Number(input.dataset.id);
    const key = input.dataset.key;
    const original = products.find((p) => p.id === id)[key];
    const change = priceChanges.get(id) ?? {};
    const value = parseInt(input.value, 10);
    if (Number.isNaN(value) || value === original) delete change[key];
    else change[key] = value;
    if (Object.keys(change).length) priceChanges.set(id, change);
    else priceChanges.delete(id);
    input.classList.toggle('changed', key in change);
    updateSaveButton();
  });

  saveBtn?.addEventListener('click', () => guard(async () => {
    const items = [...priceChanges].map(([id, change]) => ({ id, ...change }));
    await api.put('products/prices', { items });
    toast(`${items.length} harga barang disimpan`);
    await load();
  }, saveBtn));

  // ---------- filter & ekspor ----------
  search.addEventListener('input', render);
  activeOnly.addEventListener('change', () => {
    try { localStorage.setItem('barang.aktif', activeOnly.checked ? '1' : '0'); } catch { /* abaikan */ }
    if (priceChanges.size && !confirm('Perubahan harga yang belum disimpan akan hilang. Lanjutkan?')) {
      activeOnly.checked = !activeOnly.checked;
      return;
    }
    guard(load);
  });

  $('.btn-export', root).addEventListener('click', (e) => guard(async () => {
    const head = admin ? ['ID', 'Nama Barang', 'Supplier', 'Satuan', 'Modal', 'Harga Jual', 'Stok'] : ['ID', 'Nama Barang', 'Satuan', 'Harga Jual', 'Stok'];
    const rows = products.map((p) => (admin
      ? [p.id, p.nama, p.supplier, p.satuan, p.harga_beli, p.harga_jual, p.stok]
      : [p.id, p.nama, p.satuan, p.harga_jual, p.stok]));
    await exportXlsx('daftar-barang.xlsx', 'Barang', [head, ...rows]);
  }, e.currentTarget));

  // ---------- detail ----------
  on(tbody, 'click', '.btn-detail', (e, btn) => guard(async () => {
    const p = await api.get('products', { id: btn.dataset.id });
    const item = (label, value) => html`<dt>${label}</dt><dd>${value}</dd>`;
    setHtml($('.detail-list', detailModal), html`
      ${item('ID', p.id)}
      ${item('Nama', p.nama)}
      ${admin ? item('Supplier', p.supplier) : ''}
      ${item('Satuan', p.satuan)}
      ${admin ? item('Modal', rupiah(p.harga_beli)) : ''}
      ${item('Harga Jual', rupiah(p.harga_jual))}
      ${item('Stok', number(p.stok))}
      ${item('Status', p.aktif ? 'Aktif' : 'Nonaktif')}
      <dt>Deskripsi</dt><dd class="pre">${p.deskripsi || '—'}</dd>`);
    openModal(detailModal);
  }, btn));

  // ---------- tambah / edit / nonaktifkan (admin) ----------
  if (admin) {
    const [suppliers, units] = await Promise.all([api.get('suppliers'), api.get('units')]);
    fillSelect(form.elements.supplier_id, suppliers, { label: (s) => s.nama, placeholder: 'Pilih supplier', dropdownParent: formModal });
    fillSelect(form.elements.unit_id, units, { label: (u) => u.nama, placeholder: 'Pilih satuan', dropdownParent: formModal });

    const openForm = (p = null) => {
      editingId = p?.id ?? null;
      $('.modal-title', formModal).textContent = p ? `Edit Barang #${p.id}` : 'Tambah Barang';
      form.reset();
      for (const key of ['nama', 'harga_beli', 'harga_jual', 'stok', 'deskripsi']) form.elements[key].value = p?.[key] ?? (key === 'stok' ? 0 : '');
      form.elements.aktif.checked = p ? p.aktif : true;
      setSelect(form.elements.supplier_id, p?.supplier_id ?? '');
      setSelect(form.elements.unit_id, p?.unit_id ?? '');
      openModal(formModal);
    };

    $('.btn-create', root).addEventListener('click', () => openForm());
    on(tbody, 'click', '.btn-edit', (e, btn) => guard(async () => openForm(await api.get('products', { id: btn.dataset.id })), btn));
    on(tbody, 'click', '.btn-delete', (e, btn) => {
      if (!confirm('Nonaktifkan barang ini? Barang tidak muncul lagi di kasir, tapi riwayatnya tetap ada.')) return;
      guard(async () => {
        await api.del('products', { id: btn.dataset.id });
        toast('Barang dinonaktifkan');
        await load();
      }, btn);
    });

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const f = form.elements;
      const body = {
        nama: f.nama.value,
        supplier_id: getSelect(f.supplier_id),
        unit_id: getSelect(f.unit_id),
        harga_beli: f.harga_beli.value,
        harga_jual: f.harga_jual.value,
        stok: f.stok.value || 0,
        deskripsi: f.deskripsi.value,
        aktif: f.aktif.checked,
      };
      guard(async () => {
        if (editingId) await api.put('products', body, { id: editingId });
        else await api.post('products', body);
        closeModal(formModal);
        toast('Barang disimpan');
        await load();
      }, $('[type=submit]', form));
    });
  }

  await guard(load);
}
