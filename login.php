<?php
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/config/koneksi.php";

$error = "";
$redirect = (string) ($_GET["redirect"] ?? $_POST["redirect"] ?? "dashboard");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $username = trim((string) ($_POST["username"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        if (!preg_match("/^[A-Za-z0-9_.-]{3,30}$/", $username) || $password === "") {
            throw new HttpException(422, "Username atau kata sandi tidak valid.");
        }
        $ip = substr((string) ($_SERVER["REMOTE_ADDR"] ?? "unknown"), 0, 45);
        $recent = one("SELECT COUNT(*) AS attempts FROM login_attempts WHERE username = ? AND ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)", "ss", [$username, $ip]);
        if ((int) $recent["attempts"] >= 5) {
            throw new HttpException(429, "Terlalu banyak percobaan masuk. Coba lagi 15 menit kemudian.");
        }
        $user = one("SELECT user_id, username, password, nama, role, is_active FROM login WHERE username = ? LIMIT 1", "s", [$username]);
        $passwordValid = $user && (password_verify($password, $user["password"]) || hash_equals($user["password"], $password));
        if (!$user || (int) $user["is_active"] !== 1 || !$passwordValid) {
            stmt("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)", "ss", [$username, $ip]);
            throw new HttpException(401, "Username atau kata sandi salah.");
        }
        if (!password_verify($password, $user["password"])) {
            stmt("UPDATE login SET password = ? WHERE user_id = ?", "si", [password_hash($password, PASSWORD_DEFAULT), (int) $user["user_id"]]);
        }
        stmt("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?", "ss", [$username, $ip]);
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int) $user["user_id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["nama"] = $user["nama"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        header("Location: " . safe_redirect_target($redirect));
        exit;
    } catch (HttpException $exception) {
        $error = $exception->getMessage();
    } catch (mysqli_sql_exception $exception) {
        error_log("Kesalahan masuk: " . $exception->getMessage());
        http_response_code(500);
        $error = "Terjadi kesalahan server. Silakan coba kembali.";
    }
}
$pageTitle = "Masuk";
require __DIR__ . "/includes/header.php";
?>
<section class="auth-card">
    <p class="eyebrow">Selamat datang kembali</p>
    <h1>Masuk ke akun</h1>
    <?php if ($error !== ""): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" required>
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Masuk</button>
    </form>
    <p>Belum punya akun? <a href="register.php">Daftar</a></p>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
