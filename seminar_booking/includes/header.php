<?php
/**
 * header.php – shared page header
 * Usage: include with $pageTitle, $activeSection, $userType set
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'SeminarBook') ?> | <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="layout-<?= $userType ?? 'public' ?>">

<?php $flash = getFlash(); if ($flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" id="flashMsg">
    <span><?= e($flash['msg']) ?></span>
    <button onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>
