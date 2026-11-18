<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php

$title = 'Supplier';
$noun = 'Supplier';
$fields = [
    ['key' => 'nama', 'label' => 'Nama Supplier'],
    ['key' => 'nomor_hp', 'label' => 'Nomor HP', 'type' => 'tel', 'required' => false, 'max' => 20],
];
require __DIR__ . '/_crud.php';
