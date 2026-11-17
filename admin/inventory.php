<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_role(["admin"]);

$message = "";
$error = "";
$transactionStarted = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $productId = filter_var($_POST["product_id"] ?? null, FILTER_VALIDATE_INT);
        $delta = filter_var($_POST["quantity_delta"] ?? null, FILTER_VALIDATE_INT);
        $note = trim((string) ($_POST["note"] ?? ""));
        if (!$productId || $delta === false || $delta === 0 || abs($delta) > 10000 || mb_strlen($note) > 200) {
            throw new HttpException(422, "Produk, jumlah perubahan stok, atau catatan tidak valid.");
        }

        mysqli_begin_transaction($conn);
        $transactionStarted = true;
        $product = one("SELECT product_id, name, stock_quantity FROM products WHERE product_id = ? AND is_active = 1 FOR UPDATE", "i", [$productId]);
        if (!$product) {
            mysqli_rollback($conn);
            $transactionStarted = false;
            throw new HttpException(404, "Produk tidak ditemukan atau sudah nonaktif.");
        }
        $newQuantity = (int) $product["stock_quantity"] + $delta;
        if ($newQuantity < 0) {
            mysqli_rollback($conn);
            $transactionStarted = false;
            throw new HttpException(422, "Stok tidak dapat menjadi kurang dari nol.");
        }
        stmt("UPDATE products SET stock_quantity = ?, is_available = IF(? > 0, 1, 0) WHERE product_id = ?", "iii", [$newQuantity, $newQuantity, $productId]);
        $actor = (int) $_SESSION["user_id"];
        $reason = $note === "" ? "Penyesuaian stok oleh admin." : $note;
        stmt("INSERT INTO inventory_movements (product_id, changed_by, movement_type, quantity_delta, note) VALUES (?, ?, 'adjustment', ?, ?)", "iiis", [$productId, $actor, $delta, $reason]);
        mysqli_commit($conn);
        $transactionStarted = false;
        $message = "Stok " . $product["name"] . " diperbarui menjadi " . $newQuantity . ".";
    } catch (HttpException $exception) {
        if ($transactionStarted) mysqli_rollback($conn);
        $error = $exception->getMessage();
    } catch (mysqli_sql_exception $exception) {
        if ($transactionStarted) mysqli_rollback($conn);
        error_log("Kesalahan perubahan stok: " . $exception->getMessage());
        $error = "Stok tidak dapat diperbarui karena kesalahan basis data.";
    }
}

$products = all_rows("SELECT product_id, name, stock_quantity, is_available FROM products WHERE is_active = 1 ORDER BY name");
$movements = all_rows("SELECT im.created_at, p.name AS product_name, im.movement_type, im.quantity_delta, im.note, l.username, im.order_id FROM inventory_movements im JOIN products p ON p.product_id = im.product_id LEFT JOIN login l ON l.user_id = im.changed_by ORDER BY im.created_at DESC, im.movement_id DESC LIMIT 50");
$pageTitle = "Admin · Stok";
require __DIR__ . "/../includes/header.php";
require __DIR__ . "/_nav.php";
?>
<section>
    <h1>Persediaan produk</h1>
    <p>Stok berkurang saat pembayaran diterima kasir. Jika pesanan lunas dibatalkan, jumlahnya dikembalikan otomatis.</p>
    <?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="panel">
        <h2>Tambah atau koreksi stok</h2>
        <p>Masukkan angka positif untuk menambah stok dan angka negatif untuk mengurangi stok.</p>
        <form method="post" class="admin-form form-grid">
            <?= csrf_field() ?>
            <label for="product-id">Produk<select id="product-id" name="product_id" required>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e($product["product_id"]) ?>"><?= e($product["name"]) ?> — stok <?= e($product["stock_quantity"]) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label for="quantity-delta">Perubahan jumlah<input id="quantity-delta" name="quantity_delta" type="number" min="-10000" max="10000" required></label>
            <label for="note">Alasan (opsional)<input id="note" name="note" maxlength="200"></label>
            <button type="submit">Simpan penyesuaian</button>
        </form>
    </div>
    <div class="panel table-wrap">
        <h2>Stok saat ini</h2>
        <table><thead><tr><th>Produk</th><th>Stok tersedia</th><th>Status menu</th></tr></thead><tbody>
        <?php foreach ($products as $product): ?>
            <tr><td><?= e($product["name"]) ?></td><td><?= e($product["stock_quantity"]) ?></td><td><?= $product["stock_quantity"] > 0 && $product["is_available"] ? "Tersedia" : "Habis/tidak aktif" ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="panel table-wrap">
        <h2>Riwayat pergerakan stok terbaru</h2>
        <table><thead><tr><th>Waktu</th><th>Produk</th><th>Perubahan</th><th>Catatan</th><th>Petugas/pesanan</th></tr></thead><tbody>
        <?php foreach ($movements as $movement): ?>
            <tr><td><?= e($movement["created_at"]) ?></td><td><?= e($movement["product_name"]) ?></td><td><?= $movement["quantity_delta"] > 0 ? "+" : "" ?><?= e($movement["quantity_delta"]) ?></td><td><?= e($movement["note"]) ?></td><td><?= e($movement["username"] ?? "Sistem") ?><?= $movement["order_id"] ? " · #" . e($movement["order_id"]) : "" ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
