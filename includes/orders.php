<?php
require_once __DIR__ . "/functions.php";

function free_order_table(mysqli $conn, int $orderId): void
{
    $statement = mysqli_prepare($conn, "UPDATE tables_cafe SET is_available = 1 WHERE table_id = (SELECT table_id FROM orders WHERE order_id = ? AND table_id IS NOT NULL)");
    mysqli_stmt_bind_param($statement, "i", $orderId);
    mysqli_stmt_execute($statement);
}

function expire_orders(mysqli $conn): void
{
    mysqli_begin_transaction($conn);
    try {
        $expired = all_rows(
            "SELECT order_id, status FROM orders WHERE (status = 'menunggu_bayar' AND payment_expires_at <= NOW()) OR (order_type = 'takeaway' AND status = 'siap' AND ready_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)) FOR UPDATE"
        );
        foreach ($expired as $order) {
            $newStatus = $order["status"] === "menunggu_bayar" ? "batal" : "kedaluwarsa";
            $statement = mysqli_prepare($conn, "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
            mysqli_stmt_bind_param($statement, "si", $newStatus, $order["order_id"]);
            mysqli_stmt_execute($statement);
            $statement = mysqli_prepare($conn, "INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, 'Status berubah otomatis oleh sistem.', NULL)");
            mysqli_stmt_bind_param($statement, "is", $order["order_id"], $newStatus);
            mysqli_stmt_execute($statement);
            free_order_table($conn, (int) $order["order_id"]);
        }
        mysqli_commit($conn);
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($conn);
        throw $exception;
    }
}

function create_order_code(mysqli $conn, string $type): string
{
    $prefix = $type === "dine_in" ? "D-" : "T-";
    $day = date("ymd");
    $lockName = "order-code-" . $prefix . $day;
    $lock = mysqli_prepare($conn, "SELECT GET_LOCK(?, 5)");
    mysqli_stmt_bind_param($lock, "s", $lockName);
    mysqli_stmt_execute($lock);
    $lockResult = mysqli_stmt_get_result($lock);
    if ((int) mysqli_fetch_row($lockResult)[0] !== 1) {
        throw new HttpException(503, "Nomor pesanan sedang dibuat. Silakan coba kembali.");
    }
    $pattern = $prefix . $day . "-%";
    $last = one("SELECT MAX(CAST(SUBSTRING_INDEX(order_code, '-', -1) AS UNSIGNED)) AS last_number FROM orders WHERE order_code LIKE ?", "s", [$pattern]);
    $next = (int) ($last["last_number"] ?? 0) + 1;
    return $prefix . $day . "-" . str_pad((string) $next, 4, "0", STR_PAD_LEFT);
}

function release_order_code_lock(mysqli $conn, string $orderCode): void
{
    $lockName = "order-code-" . substr($orderCode, 0, 2) . substr($orderCode, 2, 6);
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $lockName);
    mysqli_stmt_execute($release);
}

function order_status_transitions(string $type): array
{
    return $type === "dine_in"
        ? ["diterima" => ["diproses", "batal"], "diproses" => ["disajikan", "batal"], "disajikan" => ["selesai"]]
        : ["diterima" => ["diproses", "batal"], "diproses" => ["siap", "batal"], "siap" => ["selesai"]];
}

function change_order_stock(mysqli $conn, int $orderId, int $userId, string $movementType): void
{
    $items = all_rows(
        "SELECT product_id, SUM(qty) AS quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL GROUP BY product_id ORDER BY product_id",
        "i",
        [$orderId]
    );
    foreach ($items as $item) {
        $productId = (int) $item["product_id"];
        $quantity = (int) $item["quantity"];
        $product = one("SELECT stock_quantity FROM products WHERE product_id = ? FOR UPDATE", "i", [$productId]);
        if (!$product) {
            throw new HttpException(422, "Stok salah satu produk tidak dapat diperiksa.");
        }
        if ($movementType === "sale") {
            if ((int) $product["stock_quantity"] < $quantity) {
                throw new HttpException(422, "Stok produk tidak cukup untuk menerima pembayaran pesanan ini.");
            }
            stmt("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?", "iii", [$quantity, $productId, $quantity]);
            $delta = -$quantity;
            $note = "Stok dikurangi saat pembayaran diterima.";
        } elseif ($movementType === "return") {
            stmt("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?", "ii", [$quantity, $productId]);
            $delta = $quantity;
            $note = "Stok dikembalikan karena pesanan lunas dibatalkan.";
        } else {
            throw new LogicException("Jenis perubahan stok pesanan tidak valid.");
        }
        stmt(
            "INSERT INTO inventory_movements (product_id, order_id, changed_by, movement_type, quantity_delta, note) VALUES (?, ?, ?, ?, ?, ?)",
            "iiisis",
            [$productId, $orderId, $userId, $movementType, $delta, $note]
        );
    }
}

function calculate_order_items(array $requestedItems): array
{
    if (count($requestedItems) < 1 || count($requestedItems) > 30) {
        throw new HttpException(422, "Keranjang tidak valid.");
    }
    $calculated = [];
    $subtotal = 0;
    foreach ($requestedItems as $requested) {
        if (!is_array($requested)) {
            throw new HttpException(422, "Data item keranjang tidak valid.");
        }
        $productId = filter_var($requested["product_id"] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($requested["qty"] ?? null, FILTER_VALIDATE_INT);
        if (!$productId || !$qty || $qty < 1 || $qty > 99) {
            throw new HttpException(422, "Produk atau jumlah pesanan tidak valid.");
        }
        $product = one(
            "SELECT p.product_id, p.name, p.price, p.category_id, p.stock_quantity FROM products p JOIN categories c ON c.category_id = p.category_id AND c.is_active = 1 WHERE p.product_id = ? AND p.is_available = 1 AND p.is_active = 1 AND p.stock_quantity > 0",
            "i",
            [$productId]
        );
        if (!$product) {
            throw new HttpException(422, "Salah satu produk sudah tidak tersedia.");
        }
        if ($qty > (int) $product["stock_quantity"]) {
            throw new HttpException(422, "Stok " . $product["name"] . " tidak cukup. Stok tersedia: " . $product["stock_quantity"] . ".");
        }
        $groups = all_rows(
            "SELECT group_id, name, is_required FROM option_groups WHERE category_id = ? AND is_active = 1",
            "i",
            [$product["category_id"]]
        );
        $postedOptions = $requested["options"] ?? [];
        if (!is_array($postedOptions)) {
            throw new HttpException(422, "Pilihan produk tidak valid.");
        }
        $selectedByGroup = [];
        foreach ($postedOptions as $option) {
            if (!is_array($option)) {
                throw new HttpException(422, "Pilihan produk tidak valid.");
            }
            $groupId = filter_var($option["group_id"] ?? null, FILTER_VALIDATE_INT);
            $valueId = filter_var($option["value_id"] ?? null, FILTER_VALIDATE_INT);
            if (!$groupId || !$valueId || isset($selectedByGroup[$groupId])) {
                throw new HttpException(422, "Pilihan produk tidak valid atau terduplikasi.");
            }
            $valid = one(
                "SELECT og.name AS group_name, ov.value_id, ov.label, ov.extra_price FROM option_groups og JOIN option_values ov ON ov.group_id = og.group_id WHERE og.group_id = ? AND og.category_id = ? AND og.is_active = 1 AND ov.value_id = ? AND ov.is_active = 1",
                "iii",
                [$groupId, $product["category_id"], $valueId]
            );
            if (!$valid) {
                throw new HttpException(422, "Pilihan tidak berlaku untuk produk ini.");
            }
            $selectedByGroup[$groupId] = $valid;
        }
        foreach ($groups as $group) {
            if ((int) $group["is_required"] === 1 && !isset($selectedByGroup[$group["group_id"]])) {
                throw new HttpException(422, "Lengkapi semua pilihan wajib produk.");
            }
        }
        $options = [];
        $unitPrice = (int) $product["price"];
        foreach ($selectedByGroup as $groupId => $value) {
            $unitPrice += (int) $value["extra_price"];
            $options[] = [
                "group_id" => (int) $groupId,
                "group" => $value["group_name"],
                "value_id" => (int) $value["value_id"],
                "label" => $value["label"],
                "extra" => (int) $value["extra_price"],
            ];
        }
        if (isset($requested["note"]) && !is_string($requested["note"])) {
            throw new HttpException(422, "Catatan produk tidak valid.");
        }
        $note = trim((string) ($requested["note"] ?? ""));
        if (mb_strlen($note) > 150) {
            throw new HttpException(422, "Catatan produk maksimal 150 karakter.");
        }
        $lineTotal = $unitPrice * $qty;
        $subtotal += $lineTotal;
        if ($subtotal > 4000000000) {
            throw new HttpException(422, "Total pesanan melebihi batas yang diizinkan.");
        }
        $calculated[] = [
            "product_id" => (int) $product["product_id"],
            "name" => $product["name"],
            "price" => $unitPrice,
            "qty" => $qty,
            "note" => $note,
            "options" => $options,
        ];
    }
    return ["items" => $calculated, "subtotal" => $subtotal];
}
