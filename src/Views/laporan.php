<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php ?>
<section class="page">
    <header class="page-head"><h1>Laporan Penjualan</h1></header>
    <p class="muted">Berisi penjualan tunai dan hutang yang sudah lunas. Untung dihitung dari modal yang tercatat saat transaksi.</p>

    <div class="toolbar">
        <label>Dari <input type="date" class="filter-from"></label>
        <label>Sampai <input type="date" class="filter-to"></label>
        <label class="grow">Barang <select class="filter-products" multiple></select></label>
        <button class="btn btn-filter">Tampilkan</button>
        <span class="spacer"></span>
        <button class="btn btn-export">Export Excel</button>
        <button class="btn btn-danger btn-purge">Hapus Data Periode Ini</button>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Barang</th><th>Satuan</th><th>Supplier</th><th class="num">Jumlah</th>
                    <th class="num">Modal</th><th class="num">Total Modal</th>
                    <th class="num">Harga Jual</th><th class="num">Total Penjualan</th><th class="num">Untung</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot></tfoot>
        </table>
    </div>
</section>
