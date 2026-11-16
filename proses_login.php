<?php
session_start();
require __DIR__ . "/Koneksi/koneksi.php";

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    header("Location: login.html?error=1");
    exit;
}

$statement = mysqli_prepare($conn, "SELECT password FROM login WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($statement, "s", $username);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);

if (mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($statement);
    header("Location: login.html?error=1");
    exit;
}

$account = mysqli_fetch_assoc($result);
$storedPassword = $account["password"];
$passwordIsValid = password_verify($password, $storedPassword);
$isLegacyPassword = !$passwordIsValid && hash_equals($storedPassword, $password);

if (!$passwordIsValid && !$isLegacyPassword) {
    mysqli_stmt_close($statement);
    header("Location: login.html?error=1");
    exit;
}

if ($isLegacyPassword) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $update = mysqli_prepare($conn, "UPDATE login SET password = ? WHERE username = ?");
    mysqli_stmt_bind_param($update, "ss", $hashedPassword, $username);
    mysqli_stmt_execute($update);
    mysqli_stmt_close($update);
}

session_regenerate_id(true);
$_SESSION["username"] = $username;
mysqli_stmt_close($statement);

header("Location: Beranda/dashboard.html");
exit;