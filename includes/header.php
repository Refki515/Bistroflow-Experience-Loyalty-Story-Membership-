<?php
/**
 * BistroFlow ERP - Header shared
 * Memuat <head>, font, dan stylesheet. Membuka <body> dan .app-shell
 * Variabel opsional: $pageTitle
 */
if (!isset($pageTitle)) {
    $pageTitle = 'BistroFlow ERP';
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - BistroFlow ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/bistroflow/assets/css/style.css">
</head>
<body>
