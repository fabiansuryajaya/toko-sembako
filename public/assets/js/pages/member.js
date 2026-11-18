import { crudPage } from '../core/crud.js';

export const init = (root) => crudPage(root, {
  resource: 'members',
  noun: 'Member',
  fields: [{ key: 'nama' }, { key: 'nomor_hp' }],
  deleteMessage: 'Hapus member ini? Member yang punya riwayat hutang/transaksi tidak bisa dihapus.',
});
