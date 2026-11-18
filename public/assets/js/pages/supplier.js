import { crudPage } from '../core/crud.js';

export const init = (root) => crudPage(root, {
  resource: 'suppliers',
  noun: 'Supplier',
  fields: [{ key: 'nama' }, { key: 'nomor_hp' }],
  deleteMessage: 'Nonaktifkan supplier ini? Barang yang sudah ada tetap tersimpan.',
});
