<?php
require_once __DIR__ . "/includes/functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Metode tidak diizinkan.");
}
require_csrf();
$_SESSION = [];
session_destroy();
header("Location: index.php");
exit;
