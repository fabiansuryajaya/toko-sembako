<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php

$title = 'Satuan';
$noun = 'Satuan';
$fields = [['key' => 'nama', 'label' => 'Nama Satuan', 'max' => 50]];
require __DIR__ . '/_crud.php';
