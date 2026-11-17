<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/orders.php";
expire_orders($conn);
require_role(["admin"]);
$summary = one("SELECT (SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS order_count, (SELECT COALESCE(SUM(total), 0) FROM orders WHERE paid_at >= CURDATE() AND paid_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS revenue, (SELECT COALESCE(SUM(total), 0) FROM orders WHERE refunded_at >= CURDATE() AND refunded_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS refunded");
$active = one("SELECT COUNT(*) AS active_count FROM orders WHERE status NOT IN ('selesai', 'batal', 'kedaluwarsa')");
$pipeline = all_rows("SELECT status, COUNT(*) AS order_count FROM orders WHERE status IN ('menunggu_bayar', 'diterima', 'diproses', 'disajikan', 'siap') GROUP BY status ORDER BY FIELD(status, 'menunggu_bayar', 'diterima', 'diproses', 'disajikan', 'siap')");
$lowStock = all_rows("SELECT product_id, name, stock_quantity FROM products WHERE is_active = 1 AND stock_quantity <= 5 ORDER BY stock_quantity, name LIMIT 10");
$sold = all_rows("SELECT product_name, SUM(oi.qty) AS qty_sold FROM order_items oi JOIN orders o ON o.order_id = oi.order_id WHERE o.paid_at >= CURDATE() AND o.paid_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) GROUP BY product_name ORDER BY qty_sold DESC LIMIT 5");
$pageTitle = "Admin";
require __DIR__ . "/../includes/header.php";
require __DIR__ . "/_nav.php";
?>
<section class="panel">
    <h1>Ringkasan admin</h1>
    <p>Pesanan hari ini: <?= e($summary["order_count"]) ?></p>
    <p>Pemasukan dari pembayaran hari ini: Rp <?= e(number_format((int) $summary["revenue"], 0, ",", ".")) ?></p>
    <p>Pengembalian manual yang tercatat hari ini: Rp <?= e(number_format((int) $summary["refunded"], 0, ",", ".")) ?></p>
    <p>Pesanan aktif: <?= e($active["active_count"]) ?></p>
    <h2>Antrean pesanan</h2>
    <?php if (!$pipeline): ?><p>Tidak ada pesanan aktif.</p><?php else: ?>
        <ul><?php foreach ($pipeline as $stage): ?><li><?= e(str_replace("_", " ", $stage["status"])) ?>: <?= e($stage["order_count"]) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <h2>Stok menipis (5 atau kurang)</h2>
    <?php if (!$lowStock): ?><p>Tidak ada produk dengan stok menipis.</p><?php else: ?>
        <ul><?php foreach ($lowStock as $product): ?><li><?= e($product["name"]) ?>: <?= e($product["stock_quantity"]) ?> tersisa</li><?php endforeach; ?></ul>
    <?php endif; ?>
    <h2>Produk terlaris hari ini</h2>
    <?php if (!$sold): ?><p>Belum ada produk terjual hari ini.</p><?php else: ?>
        <ol><?php foreach ($sold as $product): ?><li><?= e($product["product_name"]) ?> — <?= e($product["qty_sold"]) ?> item</li><?php endforeach; ?></ol>
    <?php endif; ?>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
