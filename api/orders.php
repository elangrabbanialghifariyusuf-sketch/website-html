<?php
require_once __DIR__ . "/_bootstrap.php";

api_run(function () use ($conn): void {
    require_login(true);
    if (!in_array($_SESSION["role"] ?? "", ["customer", "admin"], true)) {
        throw new HttpException(403, "Kasir hanya dapat mengelola pesanan melalui panel kasir.");
    }
    $method = api_method(["GET", "POST"]);
    if ($method === "GET") {
        $userId = (int) $_SESSION["user_id"];
        $orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if ($orderId) {
            $order = one(
                "SELECT o.*, f.name AS floor_name, t.table_number FROM orders o LEFT JOIN floors f ON f.floor_id = o.floor_id LEFT JOIN tables_cafe t ON t.table_id = o.table_id WHERE o.order_id = ? AND o.user_id = ?",
                "ii",
                [$orderId, $userId]
            );
            if (!$order) {
                throw new HttpException(404, "Pesanan tidak ditemukan.");
            }
            $order["items"] = all_rows("SELECT order_item_id, product_name, price, qty, note FROM order_items WHERE order_id = ? ORDER BY order_item_id", "i", [$orderId]);
            foreach ($order["items"] as &$item) {
                $item["options"] = all_rows("SELECT group_name, value_name, extra_price FROM order_item_options WHERE order_item_id = ? ORDER BY order_item_option_id", "i", [$item["order_item_id"]]);
            }
            unset($item);
            $order["timeline"] = all_rows("SELECT status, note, created_at FROM order_status_log WHERE order_id = ? ORDER BY created_at, log_id", "i", [$orderId]);
            $order["short_code"] = substr($order["order_code"], 0, 2) . substr($order["order_code"], -4);
            json_response(["order" => $order]);
        }
        $orders = all_rows(
            "SELECT order_id, order_code, order_type, status, payment_status, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 100",
            "i",
            [$userId]
        );
        foreach ($orders as &$order) {
            $order["short_code"] = substr($order["order_code"], 0, 2) . substr($order["order_code"], -4);
        }
        unset($order);
        json_response(["orders" => $orders]);
    }

    $payload = request_json();
    if (($payload["action"] ?? "create") === "cancel") {
        $orderId = filter_var($payload["order_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$orderId) {
            throw new HttpException(400, "Pesanan tidak valid.");
        }
        mysqli_begin_transaction($conn);
        $order = one("SELECT order_id, status, payment_status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE", "ii", [$orderId, (int) $_SESSION["user_id"]]);
        if (!$order) {
            mysqli_rollback($conn);
            throw new HttpException(404, "Pesanan tidak ditemukan.");
        }
        if (!in_array($order["status"], ["menunggu_bayar", "diterima"], true)) {
            mysqli_rollback($conn);
            throw new HttpException(422, "Pesanan tidak dapat dibatalkan pada tahap ini.");
        }
        $paymentStatus = $order["payment_status"] === "paid" ? "refunded" : $order["payment_status"];
        $userId = (int) $_SESSION["user_id"];
        if ($paymentStatus === "refunded") {
            change_order_stock($conn, (int) $orderId, $userId, "return");
        }
        $update = mysqli_prepare($conn, "UPDATE orders SET status = 'batal', payment_status = ?, refunded_at = IF(? = 'refunded', NOW(), refunded_at), updated_at = NOW() WHERE order_id = ?");
        mysqli_stmt_bind_param($update, "ssi", $paymentStatus, $paymentStatus, $orderId);
        mysqli_stmt_execute($update);
        $statusNote = $paymentStatus === "refunded" ? "Dibatalkan pelanggan; pengembalian dana dilakukan manual oleh kasir." : "Dibatalkan oleh pelanggan.";
        $log = mysqli_prepare($conn, "INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'batal', ?, ?)");
        mysqli_stmt_bind_param($log, "isi", $orderId, $statusNote, $userId);
        mysqli_stmt_execute($log);
        free_order_table($conn, (int) $orderId);
        mysqli_commit($conn);
        json_response(["message" => "Pesanan dibatalkan.", "manual_refund" => $paymentStatus === "refunded"]);
    }

    $items = $payload["items"] ?? null;
    if (!is_array($items)) {
        throw new HttpException(422, "Isi keranjang tidak valid.");
    }
    $calculated = calculate_order_items($items);
    $type = $payload["order_type"] ?? "";
    if (!is_string($type) || !in_array($type, ["dine_in", "takeaway"], true)) {
        throw new HttpException(422, "Pilih tipe pesanan.");
    }
    $settings = settings_map();
    $floorId = null;
    $tableId = null;
    $buyerName = null;
    $buyerPhone = null;
    $pickupMode = null;
    $pickupAt = null;
    $notes = "";
    $packagingFee = 0;
    if ($type === "dine_in") {
        $floorId = filter_var($payload["floor_id"] ?? null, FILTER_VALIDATE_INT);
        $tableId = filter_var($payload["table_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$floorId || !$tableId) {
            throw new HttpException(422, "Pilih lantai dan meja.");
        }
    } else {
        if (!is_string($payload["buyer_name"] ?? null) || !is_string($payload["buyer_phone"] ?? null)) {
            throw new HttpException(422, "Nama dan nomor HP pengambil wajib diisi.");
        }
        $buyerName = trim($payload["buyer_name"]);
        $buyerPhone = trim($payload["buyer_phone"]);
        if ($buyerName === "" || mb_strlen($buyerName) > 100 || !preg_match("/^[0-9+() -]{8,30}$/", $buyerPhone)) {
            throw new HttpException(422, "Nama dan nomor HP pengambil wajib diisi dengan benar.");
        }
        $pickupMode = $payload["pickup_mode"] ?? "";
        if (!is_string($pickupMode) || !in_array($pickupMode, ["asap", "scheduled"], true)) {
            throw new HttpException(422, "Pilih waktu pengambilan.");
        }
        if ($pickupMode === "scheduled") {
            $pickupInput = $payload["pickup_at"] ?? "";
            if (!is_string($pickupInput)) {
                throw new HttpException(422, "Jadwal pengambilan tidak valid.");
            }
            $parsed = DateTimeImmutable::createFromFormat("Y-m-d\\TH:i", $pickupInput);
            $errors = DateTimeImmutable::getLastErrors();
            $now = new DateTimeImmutable("now");
            if (!$parsed || ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0)) || $parsed < $now->modify("+15 minutes") || $parsed->format("Y-m-d") !== $now->format("Y-m-d")) {
                throw new HttpException(422, "Waktu terjadwal harus hari ini dan minimal 15 menit dari sekarang.");
            }
            $open = $settings["jam_buka"] ?? "08:00";
            $close = $settings["jam_tutup"] ?? "22:00";
            if ($parsed->format("H:i") < $open || $parsed->format("H:i") > $close) {
                throw new HttpException(422, "Waktu pengambilan harus berada di dalam jam buka kafe.");
            }
            $pickupAt = $parsed->format("Y-m-d H:i:s");
        }
        $packagingFee = max(0, (int) ($settings["biaya_kemasan"] ?? 0));
        $extras = $payload["extras"] ?? [];
        if (!is_array($extras) || count(array_filter($extras, "is_string")) !== count($extras) || array_diff($extras, ["sedotan", "kantong"])) {
            throw new HttpException(422, "Pilihan tambahan takeaway tidak valid.");
        }
        if (isset($payload["notes"]) && !is_string($payload["notes"])) {
            throw new HttpException(422, "Catatan untuk kafe tidak valid.");
        }
        $customerNote = trim((string) ($payload["notes"] ?? ""));
        if (mb_strlen($customerNote) > 150) {
            throw new HttpException(422, "Catatan untuk kafe maksimal 150 karakter.");
        }
        $notes = "Tambahan: " . ($extras ? implode(", ", array_unique($extras)) : "tanpa tambahan") . ". Catatan: " . $customerNote;
    }

    mysqli_begin_transaction($conn);
    $userId = (int) $_SESSION["user_id"];
    $userLock = one("SELECT user_id FROM login WHERE user_id = ? AND is_active = 1 FOR UPDATE", "i", [$userId]);
    if (!$userLock) {
        mysqli_rollback($conn);
        throw new HttpException(401, "Akun tidak aktif.");
    }
    $rate = one("SELECT COUNT(*) AS order_count FROM orders WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)", "i", [$userId]);
    if ((int) $rate["order_count"] >= 5) {
        mysqli_rollback($conn);
        throw new HttpException(429, "Batas 5 pesanan dalam 10 menit tercapai.");
    }
    if ($type === "dine_in") {
        $table = one("SELECT t.table_id FROM tables_cafe t JOIN floors f ON f.floor_id = t.floor_id WHERE t.table_id = ? AND t.floor_id = ? AND t.is_active = 1 AND t.is_available = 1 AND f.is_active = 1 FOR UPDATE", "ii", [$tableId, $floorId]);
        if (!$table) {
            mysqli_rollback($conn);
            throw new HttpException(422, "Meja sudah tidak tersedia. Silakan pilih meja lain.");
        }
    }
    try {
        $orderCode = create_order_code($conn, $type);
    } catch (HttpException $exception) {
        mysqli_rollback($conn);
        throw $exception;
    }
    try {
        $subtotal = (int) $calculated["subtotal"];
        $total = $subtotal + $packagingFee;
        $insert = mysqli_prepare($conn, "INSERT INTO orders (order_code, user_id, order_type, status, payment_status, subtotal, packaging_fee, total, floor_id, table_id, buyer_name, buyer_phone, pickup_mode, pickup_at, notes, payment_expires_at) VALUES (?, ?, ?, 'menunggu_bayar', 'unpaid', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        mysqli_stmt_bind_param($insert, "sisiiiiisssss", $orderCode, $userId, $type, $subtotal, $packagingFee, $total, $floorId, $tableId, $buyerName, $buyerPhone, $pickupMode, $pickupAt, $notes);
        mysqli_stmt_execute($insert);
        $orderId = mysqli_insert_id($conn);
        foreach ($calculated["items"] as $item) {
            $itemInsert = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, product_name, price, qty, note) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($itemInsert, "iisiis", $orderId, $item["product_id"], $item["name"], $item["price"], $item["qty"], $item["note"]);
            mysqli_stmt_execute($itemInsert);
            $itemId = mysqli_insert_id($conn);
            foreach ($item["options"] as $option) {
                $optionInsert = mysqli_prepare($conn, "INSERT INTO order_item_options (order_item_id, group_name, value_name, extra_price) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($optionInsert, "issi", $itemId, $option["group"], $option["label"], $option["extra"]);
                mysqli_stmt_execute($optionInsert);
            }
        }
        $log = mysqli_prepare($conn, "INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'menunggu_bayar', 'Pesanan dibuat; pembayaran di kasir.', ?)");
        mysqli_stmt_bind_param($log, "ii", $orderId, $userId);
        mysqli_stmt_execute($log);
        if ($type === "dine_in") {
            $reserve = mysqli_prepare($conn, "UPDATE tables_cafe SET is_available = 0 WHERE table_id = ? AND is_available = 1");
            mysqli_stmt_bind_param($reserve, "i", $tableId);
            mysqli_stmt_execute($reserve);
            if (mysqli_affected_rows($conn) !== 1) {
                throw new HttpException(422, "Meja baru saja dipesan. Silakan pilih meja lain.");
            }
        }
        mysqli_commit($conn);
        release_order_code_lock($conn, $orderCode);
        json_response(["order_id" => $orderId, "order_code" => $orderCode, "url" => "pesanan.php?id=" . $orderId], 201);
    } catch (HttpException $exception) {
        mysqli_rollback($conn);
        release_order_code_lock($conn, $orderCode);
        throw $exception;
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($conn);
        release_order_code_lock($conn, $orderCode);
        throw $exception;
    }
});
