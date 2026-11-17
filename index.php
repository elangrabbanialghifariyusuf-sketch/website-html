<?php
require_once __DIR__ . "/includes/functions.php";
if (($_SESSION["role"] ?? "") === "kasir") {
    header("Location: kasir/dashboard.php");
    exit;
}
if (($_SESSION["role"] ?? "") === "admin") {
    header("Location: admin/index.php");
    exit;
}
$pageTitle = "Beranda";
require __DIR__ . "/includes/header.php";
?>
<section class="hero">
    <p class="eyebrow">Allana Coffee</p>
    <h1>Tempat singgah, rasa yang tinggal.</h1>
    <p>Kopi, minuman segar, dan suasana nyaman untuk menemani harimu.</p>
    <a class="button-link" href="menu.php">Lihat menu</a>
</section>
<section class="panel">
    <h2>Pesan dengan mudah</h2>
    <p>Pilih makan di tempat atau takeaway. Pembayaran dilakukan di kasir.</p>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
