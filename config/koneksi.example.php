<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = mysqli_connect("localhost", "root", "", "hayatisayang");
mysqli_set_charset($conn, "utf8mb4");
$timezone = mysqli_prepare($conn, "SET time_zone = '+07:00'");
mysqli_stmt_execute($timezone);
