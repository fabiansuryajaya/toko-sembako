import { crudPage } from '../core/crud.js';

export const init = (root) => crudPage(root, {
  resource: 'units',
  noun: 'Satuan',
  fields: [{ key: 'nama' }],
  deleteMessage: 'Nonaktifkan satuan ini? Barang yang sudah ada tetap tersimpan.',
});
