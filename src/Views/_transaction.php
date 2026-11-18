<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php

/**
 * Template halaman transaksi (penjualan tunai & hutang).
 * @var bool $isAdmin
 * @var bool $isDebt
 */
$noun = $isDebt ? 'Hutang' : 'Penjualan';
?>
<section class="page" data-jenis="<?= $isDebt ? 'hutang' : 'tunai' ?>">
    <header class="page-head">
        <h1><?= $isDebt ? 'Hutang Barang' : 'Penjualan Barang' ?></h1>
        <button class="btn btn-primary btn-create">+ <?= $noun ?> Baru</button>
    </header>

    <div class="toolbar">
        <label>Dari <input type="date" class="filter-from"></label>
        <label>Sampai <input type="date" class="filter-to"></label>
        <button class="btn btn-filter">Tampilkan</button>
        <span class="spacer"></span>
        <?php if ($isDebt && $isAdmin): ?>
            <label class="check"><input type="checkbox" class="toggle-signature"> Tanda tangan di nota hutang</label>
        <?php endif ?>
    </div>

    <div class="summary"></div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th><?= $isDebt ? 'Member' : 'Pembeli' ?></th>
                    <th class="num">Jumlah Barang</th>
                    <th class="num"><?= $isDebt ? 'Tagihan' : 'Total' ?></th>
                    <?php if ($isDebt): ?><th>Status</th><?php endif ?>
                    <th>Kasir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody class="list"></tbody>
        </table>
    </div>

    <!-- Form transaksi -->
    <div class="modal modal-form" hidden>
        <div class="modal-content modal-lg" role="dialog" aria-modal="true">
            <h2 class="modal-title"><?= $noun ?> Baru</h2>
            <form class="form" novalidate>
                <div class="grid-2">
                    <?php if ($isAdmin): ?>
                        <label>Kasir <select name="user_id"></select></label>
                    <?php endif ?>
                    <?php if ($isDebt): ?>
                        <label>Member <select name="member_id" required></select></label>
                    <?php else: ?>
                        <label>Nama Pembeli <input name="nama_pembeli" maxlength="100" placeholder="(opsional)"></label>
                    <?php endif ?>
                </div>

                <div class="add-row">
                    <label class="grow">Barang <select name="product_id"></select></label>
                    <button type="button" class="btn btn-add">Tambah</button>
                    <?php if ($isAdmin): ?><button type="button" class="btn btn-price">Ganti Harga</button><?php endif ?>
                </div>

                <div class="table-wrap">
                    <table class="table table-cart">
                        <thead><tr><th></th><th>Barang</th><th>Satuan</th><th>Harga</th><th>Jumlah</th><th class="num">Total</th></tr></thead>
                        <tbody class="cart"></tbody>
                    </table>
                </div>

                <div class="totals">
                    <?php if ($isDebt): ?>
                        <label>Ongkir <input name="ongkir" type="number" min="0" step="1" value="0"></label>
                    <?php endif ?>
                    <label>Grand Total <output class="out-total">Rp 0</output></label>
                    <label>Total Bayar <input name="dibayar" type="number" min="0" step="1" value="0"></label>
                    <label>Kembalian <output class="out-change">Rp 0</output></label>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" data-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Detail -->
    <div class="modal modal-detail" hidden>
        <div class="modal-content" role="dialog" aria-modal="true">
            <h2 class="detail-title">Detail</h2>
            <div class="detail-body"></div>
            <div class="form-actions">
                <?php if ($isDebt && $isAdmin): ?><button class="btn btn-warning btn-unpay" hidden>Batalkan Lunas</button><?php endif ?>
                <button class="btn" data-close>Tutup</button>
            </div>
        </div>
    </div>

    <!-- Struk -->
    <div class="modal modal-receipt" hidden>
        <div class="modal-content modal-receipt-content" role="dialog" aria-modal="true">
            <div class="receipt-preview"></div>
            <div class="form-actions">
                <button class="btn" data-close>Tutup</button>
                <button class="btn btn-primary btn-print">Cetak</button>
            </div>
        </div>
    </div>
</section>
