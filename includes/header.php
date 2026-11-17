<?php
$pageTitle = $pageTitle ?? "Allana Coffee";
$isLoggedIn = isset($_SESSION["user_id"]);
$role = $_SESSION["role"] ?? "guest";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php">Allana Coffee</a>
    <nav aria-label="Navigasi utama">
        <?php if (!$isLoggedIn || $role === "customer"): ?>
            <a href="menu.php">Menu</a>
            <?php if ($isLoggedIn): ?><a href="riwayat.php">Pesanan saya</a><a href="dashboard.php">Akun</a><?php else: ?><a href="login.php">Masuk</a><a href="register.php">Daftar</a><?php endif; ?>
            <a class="cart-link" href="checkout.php">Keranjang <span id="cart-count">0</span></a>
        <?php elseif ($role === "kasir"): ?>
            <a href="kasir/dashboard.php">Dashboard kasir</a><a href="kasir/index.php">Pesanan kasir</a>
        <?php elseif ($role === "admin"): ?>
            <a href="admin/index.php">Admin</a><a href="kasir/index.php">Kasir</a>
        <?php endif; ?>
        <?php if ($isLoggedIn): ?>
            <form class="inline-form" action="logout.php" method="post"><?= csrf_field() ?><button type="submit">Keluar</button></form>
        <?php endif; ?>
    </nav>
</header>
<main class="page-content">
