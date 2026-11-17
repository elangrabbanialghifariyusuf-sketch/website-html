<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/config/koneksi.php";

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $username = trim((string) ($_POST["username"] ?? ""));
        $name = trim((string) ($_POST["nama"] ?? ""));
        $phone = trim((string) ($_POST["nomor_hp"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        $confirmation = (string) ($_POST["confirm_password"] ?? "");
        if (!preg_match("/^[A-Za-z0-9_.-]{3,30}$/", $username) || $name === "" || mb_strlen($name) > 100 || !preg_match("/^[0-9+() -]{8,30}$/", $phone) || strlen($password) < 8) {
            throw new HttpException(422, "Periksa kembali nama, username, nomor HP, dan kata sandi (minimal 8 karakter).");
        }
        if (!hash_equals($password, $confirmation)) {
            throw new HttpException(422, "Konfirmasi kata sandi tidak cocok.");
        }
        if (one("SELECT user_id FROM login WHERE username = ? LIMIT 1", "s", [$username])) {
            throw new HttpException(409, "Username sudah digunakan.");
        }
        stmt("INSERT INTO login (username, password, nama, nomor_hp, role) VALUES (?, ?, ?, ?, 'customer')", "ssss", [$username, password_hash($password, PASSWORD_DEFAULT), $name, $phone]);
        header("Location: login.php?registered=1");
        exit;
    } catch (HttpException $exception) {
        $error = $exception->getMessage();
    } catch (mysqli_sql_exception $exception) {
        error_log("Kesalahan pendaftaran: " . $exception->getMessage());
        http_response_code(500);
        $error = "Pendaftaran gagal karena kesalahan server.";
    }
}
$pageTitle = "Daftar";
require __DIR__ . "/includes/header.php";
?>
<section class="auth-card">
    <p class="eyebrow">Buat akun baru</p>
    <h1>Daftar pelanggan</h1>
    <?php if (($_GET["registered"] ?? "") === "1"): ?><p class="notice">Pendaftaran berhasil. Silakan masuk.</p><?php endif; ?>
    <?php if ($error !== ""): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="nama">Nama</label><input id="nama" name="nama" maxlength="100" autocomplete="name" required>
        <label for="nomor_hp">Nomor HP</label><input id="nomor_hp" name="nomor_hp" maxlength="30" autocomplete="tel" required>
        <label for="username">Username</label><input id="username" name="username" minlength="3" maxlength="30" pattern="[-A-Za-z0-9_.]+" autocomplete="username" required>
        <label for="password">Kata sandi</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        <label for="confirm_password">Ulangi kata sandi</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
        <button type="submit">Buat akun</button>
    </form>
    <p>Sudah punya akun? <a href="login.php">Masuk</a></p>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
