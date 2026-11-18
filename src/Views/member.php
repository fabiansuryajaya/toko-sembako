<?php defined('APP_ROOT') || exit; // hanya lewat public/page.php

$title = 'Member';
$noun = 'Member';
$fields = [
    ['key' => 'nama', 'label' => 'Nama Member'],
    ['key' => 'nomor_hp', 'label' => 'Nomor HP', 'type' => 'tel', 'max' => 20],
];
require __DIR__ . '/_crud.php';
