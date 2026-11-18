import { transactionPage } from '../core/transaction.js';

export const init = (root) => transactionPage(root, { resource: 'sales', jenis: 'tunai', noun: 'Penjualan' });
