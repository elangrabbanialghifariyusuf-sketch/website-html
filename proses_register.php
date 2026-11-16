<?php
require __DIR__ . "/Koneksi/koneksi.php";

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if (!preg_match("/^[A-Za-z0-9_.-]{3,30}$/", $username) || strlen($password) < 8) {
    header("Location: register.html?error=invalid");
    exit;
}

if (!hash_equals($password, $confirmPassword)) {
    header("Location: register.html?error=password_mismatch");
    exit;
}

$check = mysqli_prepare($conn, "SELECT username FROM login WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($check, "s", $username);
mysqli_stmt_execute($check);
$existingUser = mysqli_stmt_get_result($check);

if (mysqli_num_rows($existingUser) > 0) {
    mysqli_stmt_close($check);
    header("Location: register.html?error=username_taken");
    exit;
}
mysqli_stmt_close($check);

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$insert = mysqli_prepare($conn, "INSERT INTO login (username, password) VALUES (?, ?)");
mysqli_stmt_bind_param($insert, "ss", $username, $hashedPassword);

if (!mysqli_stmt_execute($insert)) {
    mysqli_stmt_close($insert);
    header("Location: register.html?error=failed");
    exit;
}

mysqli_stmt_close($insert);
header("Location: register.html?success=1");
exit;