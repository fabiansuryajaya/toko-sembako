<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php ?>
<section class="page">
    <header class="page-head"><h1>Backup Database</h1></header>

    <div class="cards">
        <div class="card">
            <h2>Download backup</h2>
            <p class="muted">Semua data (barang, transaksi, hutang, user, pengaturan) dalam satu file .sql. Simpan di flashdisk atau Google Drive secara rutin.</p>
            <a class="btn btn-primary" href="api.php?r=backup/export">Download Backup</a>
        </div>

        <form class="card form-import" novalidate>
            <h2>Pulihkan dari backup</h2>
            <p class="muted">Seluruh isi database sekarang akan <strong>diganti</strong> dengan isi file. Sebelum itu, salinan database saat ini otomatis disimpan di folder <code>storage/backups</code>.</p>
            <input type="file" name="file" accept=".sql" required>
            <div class="form-actions"><button type="submit" class="btn btn-danger">Pulihkan</button></div>
        </form>
    </div>
</section>
