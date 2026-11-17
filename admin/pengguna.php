<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_role(["admin"]);
$message = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        if (($_POST["action"] ?? "") === "create") {
            $username = trim((string) ($_POST["username"] ?? ""));
            $name = trim((string) ($_POST["nama"] ?? ""));
            $password = (string) ($_POST["password"] ?? "");
            $role = (string) ($_POST["role"] ?? "kasir");
            if (!preg_match("/^[A-Za-z0-9_.-]{3,30}$/", $username) || $name === "" || strlen($password) < 8 || !in_array($role, ["customer", "kasir", "admin"], true)) throw new HttpException(422, "Data pengguna tidak valid. Kata sandi minimal 8 karakter.");
            stmt("INSERT INTO login (username, password, nama, role) VALUES (?, ?, ?, ?)", "ssss", [$username, password_hash($password, PASSWORD_DEFAULT), $name, $role]);
            $message = "Pengguna ditambahkan.";
        } else {
            $userId = filter_var($_POST["user_id"] ?? null, FILTER_VALIDATE_INT);
            $role = (string) ($_POST["role"] ?? "");
            if (!$userId || !in_array($role, ["customer", "kasir", "admin"], true)) throw new HttpException(422, "Peran pengguna tidak valid.");
            if ($userId === (int) $_SESSION["user_id"] && $role !== "admin") throw new HttpException(422, "Peran akun admin yang sedang digunakan tidak dapat diturunkan.");
            stmt("UPDATE login SET role = ? WHERE user_id = ?", "si", [$role, $userId]);
            $message = "Peran pengguna diperbarui.";
        }
    } catch (HttpException $exception) { $error = $exception->getMessage(); }
    catch (mysqli_sql_exception $exception) { $error = "Pengguna tidak dapat disimpan. Pastikan username belum dipakai."; }
}
$users = all_rows("SELECT user_id, username, nama, role, is_active FROM login ORDER BY user_id DESC LIMIT 200");
$pageTitle = "Admin · Pengguna";
require __DIR__ . "/../includes/header.php";
require __DIR__ . "/_nav.php";
?>
<section><h1>Pengguna</h1><?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?><?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<div class="panel"><h2>Buat akun</h2><form method="post" class="admin-form form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create"><label>Nama<input name="nama" required></label><label>Username<input name="username" minlength="3" maxlength="30" required></label><label>Kata sandi sementara<input name="password" type="password" minlength="8" required></label><label>Peran<select name="role"><option value="kasir">Kasir</option><option value="admin">Admin</option><option value="customer">Customer</option></select></label><button>Buat pengguna</button></form></div>
<div class="panel"><h2>Ubah peran</h2><div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>Peran</th><th>Status</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?= e($user["nama"] . " (" . $user["username"] . ")") ?></td><td><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($user["user_id"]) ?>"><select name="role"><?php foreach (["customer", "kasir", "admin"] as $role): ?><option value="<?= e($role) ?>" <?= $user["role"] === $role ? "selected" : "" ?>><?= e($role) ?></option><?php endforeach; ?></select><button>Simpan peran</button></form></td><td><?= $user["is_active"] ? "aktif" : "nonaktif" ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
