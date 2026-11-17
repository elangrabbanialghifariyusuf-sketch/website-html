<?php
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/orders.php";
expire_orders($conn);
if (($_SESSION["role"] ?? "") === "admin") {
    header("Location: admin/index.php");
    exit;
}
if (($_SESSION["role"] ?? "") === "kasir") {
    header("Location: kasir/index.php");
    exit;
}
require_role(["customer"]);
$user = one("SELECT nama, username, nomor_hp, role FROM login WHERE user_id = ?", "i", [(int) $_SESSION["user_id"]]);
$pageTitle = "Akun";
require __DIR__ . "/includes/header.php";
?>
<section class="panel">
    <p class="eyebrow">Akun pelanggan</p>
    <h1>Halo, <?= e($user["nama"]) ?></h1>
    <p>Username: <?= e($user["username"]) ?> · Peran: <?= e($user["role"]) ?></p>
    <p><a class="button-link" href="menu.php">Pesan sekarang</a> <a class="button-link secondary" href="riwayat.php">Lihat riwayat</a></p>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
