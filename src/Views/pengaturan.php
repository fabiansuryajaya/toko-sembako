<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php ?>
<section class="page">
    <header class="page-head"><h1>Pengaturan</h1></header>

    <div class="cards">
        <form class="card form-settings" novalidate>
            <h2>Identitas toko di struk</h2>
            <label>Nama toko <input name="nama_toko" maxlength="100"></label>
            <label>Alamat <input name="alamat_toko" maxlength="200"></label>
            <label>Telepon / WA <input name="telepon_toko" maxlength="50"></label>
            <label>Catatan di struk <input name="catatan_struk" maxlength="300"></label>
            <label>Penutup struk penjualan <input name="penutup_struk" maxlength="200"></label>
            <label>Penutup nota hutang <input name="penutup_nota_hutang" maxlength="200"></label>
            <label class="check"><input type="checkbox" name="tampilkan_ttd_nota_hutang"> Tampilkan tanda tangan pada nota hutang</label>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Simpan Pengaturan</button></div>
        </form>

        <form class="card form-signature" novalidate>
            <h2>Gambar tanda tangan</h2>
            <p class="muted">Tampil di nota hutang bila diaktifkan. PNG, JPG, atau WEBP, maksimal 1 MB. Tanpa gambar, nota memakai garis tanda tangan kosong.</p>
            <div class="signature-box"><img class="signature-preview" alt="Tanda tangan" hidden><span class="signature-empty muted">Belum ada</span></div>
            <input type="file" name="signature" accept="image/png,image/jpeg,image/webp">
            <div class="form-actions">
                <button type="button" class="btn btn-danger btn-delete-signature">Hapus</button>
                <button type="submit" class="btn btn-primary">Unggah</button>
            </div>
        </form>
    </div>
</section>
