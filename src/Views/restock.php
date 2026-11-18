<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php ?>
<section class="page">
    <header class="page-head">
        <h1>Restock Barang</h1>
        <button class="btn btn-primary btn-create">+ Restock Baru</button>
    </header>

    <div class="toolbar">
        <label>Dari <input type="date" class="filter-from"></label>
        <label>Sampai <input type="date" class="filter-to"></label>
        <button class="btn btn-filter">Tampilkan</button>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>No</th><th>Tanggal</th><th class="num">Jumlah Barang</th><th class="num">Total Modal</th><th>User</th><th>Aksi</th></tr></thead>
            <tbody class="list"></tbody>
        </table>
    </div>

    <div class="modal modal-form" hidden>
        <div class="modal-content modal-lg" role="dialog" aria-modal="true">
            <h2>Restock Baru</h2>
            <p class="muted">Stok bertambah sesuai jumlah, dan modal barang diperbarui ke harga beli terakhir.</p>
            <form class="form" novalidate>
                <div class="add-row">
                    <label class="grow">Barang <select name="product_id"></select></label>
                    <button type="button" class="btn btn-add">Tambah</button>
                    <button type="button" class="btn btn-price">Ganti Harga Beli</button>
                </div>
                <div class="table-wrap">
                    <table class="table table-cart">
                        <thead><tr><th></th><th>Barang</th><th>Satuan</th><th>Harga Beli</th><th>Jumlah</th><th class="num">Total</th></tr></thead>
                        <tbody class="cart"></tbody>
                    </table>
                </div>
                <div class="totals"><label>Total Modal <output class="out-total">Rp 0</output></label></div>
                <div class="form-actions">
                    <button type="button" class="btn" data-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal modal-detail" hidden>
        <div class="modal-content" role="dialog" aria-modal="true">
            <h2 class="detail-title">Detail Restock</h2>
            <div class="detail-body"></div>
            <div class="form-actions"><button class="btn" data-close>Tutup</button></div>
        </div>
    </div>
</section>
