<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php ?>
<section class="page">
    <header class="page-head">
        <h1>User</h1>
        <button class="btn btn-primary btn-create">Tambah User</button>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="modal" hidden>
        <div class="modal-content modal-sm" role="dialog" aria-modal="true">
            <h2 class="modal-title">Tambah User</h2>
            <form class="form" novalidate>
                <label>Username <input name="username" required maxlength="50" autocomplete="off"></label>
                <label>Role
                    <select name="role">
                        <option value="pegawai">Pegawai (kasir)</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <label>Password <input name="password" type="password" minlength="6" autocomplete="new-password"></label>
                <p class="muted hint-password">Minimal 6 karakter.</p>
                <div class="form-actions">
                    <button type="button" class="btn" data-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</section>
