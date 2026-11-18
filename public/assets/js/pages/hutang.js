import { transactionPage } from '../core/transaction.js';

export const init = (root) => transactionPage(root, { resource: 'debts', jenis: 'hutang', noun: 'Hutang' });
