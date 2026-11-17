<?php
require_once __DIR__ . "/../includes/functions.php";
try {
    require_once __DIR__ . "/../config/koneksi.php";
    require_once __DIR__ . "/../includes/orders.php";
    expire_orders($conn);
} catch (mysqli_sql_exception $exception) {
    error_log("Kesalahan pemeriksaan pesanan kedaluwarsa: " . $exception->getMessage());
    json_response(["error" => "Terjadi kesalahan pada server."], 500);
}
