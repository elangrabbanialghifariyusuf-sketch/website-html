<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_role(["kasir", "admin"]);

$cashierId = (int) $_SESSION["user_id"];
$shift = one("SELECT shift_id, opened_at, opening_cash FROM cashier_shifts WHERE cashier_id = ? AND closed_at IS NULL ORDER BY shift_id DESC LIMIT 1", "i", [$cashierId]);
$waiting = one("SELECT COUNT(*) AS order_count FROM orders WHERE status = 'menunggu_bayar' AND payment_status = 'unpaid'");
$processing = one("SELECT COUNT(*) AS order_count FROM orders WHERE payment_status = 'paid' AND status IN ('diterima', 'diproses', 'disajikan', 'siap')");
$pageTitle = "Dashboard kasir";
require __DIR__ . "/../includes/header.php";
?>
<section class="panel">
    <p class="eyebrow">Ringkasan shift</p>
    <h1>Dashboard kasir</h1>
    <?php if ($shift): ?>
        <p>Shift aktif sejak <?= e($shift["opened_at"]) ?>.</p>
        <p>Modal awal: Rp <?= e(number_format((int) $shift["opening_cash"], 0, ",", ".")) ?>.</p>
    <?php else: ?>
        <p>Belum ada shift aktif. Buka shift di panel pesanan sebelum memproses pembayaran.</p>
    <?php endif; ?>
    <div class="summary-grid">
        <article class="summary-card"><h2>Menunggu pembayaran</h2><p><?= e($waiting["order_count"]) ?> pesanan</p></article>
        <article class="summary-card"><h2>Lunas · Dalam proses</h2><p><?= e($processing["order_count"]) ?> pesanan</p></article>
    </div>
    <a class="button-link" href="index.php">Buka panel pesanan</a>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
