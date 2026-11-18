<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php

/**
 * Template halaman CRUD sederhana.
 * @var string $title judul halaman
 * @var string $noun  nama data (mis. "Supplier")
 * @var array<int, array{key:string, label:string, type?:string, required?:bool, max?:int}> $fields
 */
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
?>
<section class="page">
    <header class="page-head">
        <h1><?= $e($title) ?></h1>
        <button class="btn btn-primary btn-create">Tambah <?= $e($noun) ?></button>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php foreach ($fields as $f): ?><th><?= $e($f['label']) ?></th><?php endforeach ?>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="modal" hidden>
        <div class="modal-content modal-sm" role="dialog" aria-modal="true">
            <h2 class="modal-title"></h2>
            <form class="form" novalidate>
                <?php foreach ($fields as $f): ?>
                    <label><?= $e($f['label']) ?>
                        <input name="<?= $e($f['key']) ?>" type="<?= $e($f['type'] ?? 'text') ?>"
                               <?= ($f['required'] ?? true) ? 'required' : '' ?> maxlength="<?= (int) ($f['max'] ?? 100) ?>">
                    </label>
                <?php endforeach ?>
                <div class="form-actions">
                    <button type="button" class="btn" data-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</section>
