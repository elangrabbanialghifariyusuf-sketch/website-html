<?php
require_once __DIR__ . "/../_bootstrap.php";
api_run(function () use ($conn): void {
    require_login(true);
    $method = api_method(["GET", "POST"]);
    require_role(["kasir", "admin"]);
    if ($method === "GET") {
        $cashierId = (int) $_SESSION["user_id"];
        $shift = one("SELECT shift_id, opened_at, opening_cash FROM cashier_shifts WHERE cashier_id = ? AND closed_at IS NULL ORDER BY shift_id DESC LIMIT 1", "i", [$cashierId]);
        $status = (string) ($_GET["status"] ?? "");
        $type = (string) ($_GET["type"] ?? "");
        $bucket = (string) ($_GET["bucket"] ?? "unpaid");
        if (!in_array($bucket, ["unpaid", "paid"], true)) {
            throw new HttpException(400, "Filter daftar pesanan tidak valid.");
        }
        $sql = "SELECT o.order_id, o.order_code, o.order_type, o.status, o.payment_status, o.total, o.created_at, o.buyer_name, o.buyer_phone, o.pickup_at, o.notes, f.name AS floor_name, t.table_number FROM orders o LEFT JOIN floors f ON f.floor_id = o.floor_id LEFT JOIN tables_cafe t ON t.table_id = o.table_id WHERE o.status NOT IN ('selesai', 'batal', 'kedaluwarsa')";
        $types = "s";
        $values = [$bucket === "unpaid" ? "unpaid" : "paid"];
        $sql .= " AND o.payment_status = ?";
        if ($bucket === "unpaid") {
            $sql .= " AND o.status = 'menunggu_bayar'";
        }
        $statuses = ["menunggu_bayar", "diterima", "diproses", "disajikan", "siap"];
        if (in_array($status, $statuses, true)) { $sql .= " AND o.status = ?"; $types .= "s"; $values[] = $status; }
        if (in_array($type, ["dine_in", "takeaway"], true)) { $sql .= " AND o.order_type = ?"; $types .= "s"; $values[] = $type; }
        $sql .= " ORDER BY o.created_at ASC LIMIT 200";
        $orders = all_rows($sql, $types, $values);
        foreach ($orders as &$order) {
            $order["items"] = all_rows("SELECT product_name, price, qty, note FROM order_items WHERE order_id = ? ORDER BY order_item_id", "i", [$order["order_id"]]);
        }
        unset($order);
        $pendingCount = one("SELECT COUNT(*) AS order_count FROM orders WHERE payment_status = 'unpaid' AND status = 'menunggu_bayar'");
        json_response(["orders" => $orders, "shift" => $shift, "unpaid_count" => (int) $pendingCount["order_count"]]);
    }

    $payload = request_json();
    $action = $payload["action"] ?? "";
    $cashierId = (int) $_SESSION["user_id"];
    if ($action === "shift_start") {
        $openingCash = filter_var($payload["opening_cash"] ?? 0, FILTER_VALIDATE_INT);
        if ($openingCash === false || $openingCash < 0 || $openingCash > 1000000000) {
            throw new HttpException(422, "Modal awal shift tidak valid.");
        }
        mysqli_begin_transaction($conn);
        one("SELECT user_id FROM login WHERE user_id = ? FOR UPDATE", "i", [$cashierId]);
        $current = one("SELECT shift_id FROM cashier_shifts WHERE cashier_id = ? AND closed_at IS NULL FOR UPDATE", "i", [$cashierId]);
        if ($current) {
            mysqli_rollback($conn);
            throw new HttpException(422, "Anda masih memiliki shift yang belum ditutup.");
        }
        stmt("INSERT INTO cashier_shifts (cashier_id, opening_cash) VALUES (?, ?)", "ii", [$cashierId, $openingCash]);
        mysqli_commit($conn);
        json_response(["message" => "Shift berhasil dibuka."]);
    }
    if ($action === "shift_end") {
        $closingCash = filter_var($payload["closing_cash"] ?? null, FILTER_VALIDATE_INT);
        if ($closingCash === false || $closingCash < 0 || $closingCash > 1000000000) {
            throw new HttpException(422, "Uang kas akhir shift tidak valid.");
        }
        mysqli_begin_transaction($conn);
        $current = one("SELECT shift_id FROM cashier_shifts WHERE cashier_id = ? AND closed_at IS NULL FOR UPDATE", "i", [$cashierId]);
        if (!$current) {
            mysqli_rollback($conn);
            throw new HttpException(422, "Tidak ada shift aktif untuk ditutup.");
        }
        stmt("UPDATE cashier_shifts SET closed_at = NOW(), closing_cash = ? WHERE shift_id = ?", "ii", [$closingCash, $current["shift_id"]]);
        mysqli_commit($conn);
        json_response(["message" => "Shift berhasil ditutup."]);
    }
    if ($action === "lookup") {
        $code = strtoupper(trim((string) ($payload["code"] ?? "")));
        $order = one("SELECT order_id, order_code, order_type, status, payment_status, buyer_name, buyer_phone FROM orders WHERE order_code = ? AND order_type = 'takeaway' AND status = 'siap'", "s", [$code]);
        if (!$order) {
            throw new HttpException(404, "Pesanan takeaway siap tidak ditemukan dengan kode tersebut.");
        }
        json_response(["order" => $order]);
    }
    $orderId = filter_var($payload["order_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$orderId) {
        throw new HttpException(422, "Pesanan tidak valid.");
    }
    mysqli_begin_transaction($conn);
    $order = one("SELECT order_id, order_code, order_type, status, payment_status FROM orders WHERE order_id = ? FOR UPDATE", "i", [$orderId]);
    if (!$order) {
        mysqli_rollback($conn);
        throw new HttpException(404, "Pesanan tidak ditemukan.");
    }
    $actor = (int) $_SESSION["user_id"];
    $shift = one("SELECT shift_id FROM cashier_shifts WHERE cashier_id = ? AND closed_at IS NULL ORDER BY shift_id DESC LIMIT 1", "i", [$actor]);
    if (!$shift) {
        mysqli_rollback($conn);
        throw new HttpException(422, "Buka shift kasir sebelum memproses pembayaran atau status pesanan.");
    }
    if ($action === "paid") {
        if ($order["status"] !== "menunggu_bayar" || $order["payment_status"] !== "unpaid") {
            mysqli_rollback($conn);
            throw new HttpException(422, "Pesanan tidak menunggu pembayaran.");
        }
        change_order_stock($conn, (int) $orderId, $actor, "sale");
        stmt("UPDATE orders SET payment_status = 'paid', status = 'diterima', paid_at = NOW(), cashier_shift_id = ?, updated_at = NOW() WHERE order_id = ?", "ii", [$shift["shift_id"], $orderId]);
        stmt("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'diterima', 'Pembayaran tunai diterima kasir.', ?)", "ii", [$orderId, $actor]);
    } elseif ($action === "status") {
        $next = (string) ($payload["status"] ?? "");
        $allowed = order_status_transitions($order["order_type"]);
        if (!in_array($next, $allowed[$order["status"]] ?? [], true)) {
            mysqli_rollback($conn);
            throw new HttpException(422, "Perubahan status pesanan tidak diizinkan.");
        }
        if ($order["payment_status"] !== "paid") {
            mysqli_rollback($conn);
            throw new HttpException(422, "Pesanan harus dibayar sebelum diproses.");
        }
        if ($next === "batal" && $order["payment_status"] === "paid") {
            change_order_stock($conn, (int) $orderId, $actor, "return");
            stmt("UPDATE orders SET status = ?, payment_status = 'refunded', refunded_at = NOW(), updated_at = NOW() WHERE order_id = ?", "si", [$next, $orderId]);
        } else {
            stmt("UPDATE orders SET status = ?, ready_at = IF(? = 'siap', NOW(), ready_at), updated_at = NOW() WHERE order_id = ?", "ssi", [$next, $next, $orderId]);
        }
        stmt("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, 'Status diperbarui kasir.', ?)", "isi", [$orderId, $next, $actor]);
        if (in_array($next, ["batal", "selesai"], true)) {
            free_order_table($conn, (int) $orderId);
        }
    } elseif ($action === "verify") {
        $code = strtoupper(trim((string) ($payload["code"] ?? "")));
        if ($order["order_type"] !== "takeaway" || $order["status"] !== "siap" || !hash_equals($order["order_code"], $code)) {
            mysqli_rollback($conn);
            throw new HttpException(422, "Kode tidak cocok atau pesanan belum siap diambil.");
        }
        stmt("UPDATE orders SET status = 'selesai', updated_at = NOW() WHERE order_id = ?", "i", [$orderId]);
        stmt("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'selesai', 'Kode pengambilan diverifikasi kasir.', ?)", "ii", [$orderId, $actor]);
    } else {
        mysqli_rollback($conn);
        throw new HttpException(400, "Aksi kasir tidak dikenal.");
    }
    mysqli_commit($conn);
    json_response(["message" => "Perubahan pesanan berhasil disimpan."]);
});
