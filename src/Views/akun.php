<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php ?>
<section class="page">
    <header class="page-head"><h1>Akun Saya</h1></header>
    <form class="form card form-narrow" novalidate>
        <h2>Ganti Password</h2>
        <label>Password lama <input name="password_lama" type="password" required autocomplete="current-password"></label>
        <label>Password baru <input name="password_baru" type="password" required minlength="6" autocomplete="new-password"></label>
        <label>Ulangi password baru <input name="password_ulang" type="password" required minlength="6" autocomplete="new-password"></label>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Simpan Password</button></div>
    </form>
</section>
