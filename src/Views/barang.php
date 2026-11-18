<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php
 /** @var bool $isAdmin */ ?>
<section class="page">
    <header class="page-head">
        <h1>Daftar Barang</h1>
        <?php if ($isAdmin): ?><button class="btn btn-primary btn-create">Tambah Barang</button><?php endif ?>
    </header>

    <div class="toolbar">
        <input type="search" class="input-search" placeholder="Cari nama barang…" aria-label="Cari barang">
        <label class="check"><input type="checkbox" class="filter-active" checked> Hanya yang aktif</label>
        <span class="spacer"></span>
        <button class="btn btn-export">Export Excel</button>
        <?php if ($isAdmin): ?><button class="btn btn-primary btn-save-prices" disabled>Simpan 0 perubahan harga</button><?php endif ?>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Barang</th>
                    <?php if ($isAdmin): ?><th>Supplier</th><?php endif ?>
                    <th>Satuan</th>
                    <?php if ($isAdmin): ?><th>Modal</th><?php endif ?>
                    <th>Harga Jual</th>
                    <th class="num">Stok</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <?php if ($isAdmin): ?>
    <div class="modal modal-form" hidden>
        <div class="modal-content" role="dialog" aria-modal="true">
            <h2 class="modal-title">Tambah Barang</h2>
            <form class="form" novalidate>
                <label>Nama Barang <input name="nama" required maxlength="100"></label>
                <div class="grid-2">
                    <label>Supplier <select name="supplier_id" required></select></label>
                    <label>Satuan <select name="unit_id" required></select></label>
                    <label>Modal <input name="harga_beli" type="number" min="0" step="1" required></label>
                    <label>Harga Jual <input name="harga_jual" type="number" min="0" step="1" required></label>
                    <label>Stok <input name="stok" type="number" step="1" value="0"></label>
                    <label class="check check-inline"><input name="aktif" type="checkbox" checked> Aktif</label>
                </div>
                <label>Deskripsi <textarea name="deskripsi" rows="3" maxlength="2000"></textarea></label>
                <div class="form-actions">
                    <button type="button" class="btn" data-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif ?>

    <div class="modal modal-detail" hidden>
        <div class="modal-content modal-sm" role="dialog" aria-modal="true">
            <h2>Detail Barang</h2>
            <dl class="detail-list"></dl>
            <div class="form-actions"><button class="btn" data-close>Tutup</button></div>
        </div>
    </div>
</section>
