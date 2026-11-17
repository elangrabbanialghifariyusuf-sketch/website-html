<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_role(["admin"]);
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $keys = ["nama_kafe", "alamat", "jam_buka", "jam_tutup", "biaya_kemasan"];
    $values = [];
    foreach ($keys as $key) {
        $value = trim((string) ($_POST[$key] ?? ""));
        if (($key === "jam_buka" || $key === "jam_tutup") && !preg_match("/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/", $value)) {
            http_response_code(422);
            exit("Format jam harus HH:MM.");
        }
        if ($key === "biaya_kemasan" && (!ctype_digit($value) || (int) $value > 100000)) {
            http_response_code(422);
            exit("Biaya kemasan tidak valid.");
        }
        $values[$key] = $value;
    }
    if ($values["jam_buka"] >= $values["jam_tutup"]) {
        http_response_code(422);
        exit("Jam tutup harus lebih akhir daripada jam buka.");
    }
    foreach ($values as $key => $value) {
        stmt("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", "ss", [$key, $value]);
    }
    $message = "Pengaturan berhasil disimpan.";
}
$settings = settings_map();
$pageTitle = "Admin · Pengaturan";
require __DIR__ . "/../includes/header.php";
require __DIR__ . "/_nav.php";
?>
<section class="panel"><h1>Pengaturan kafe</h1><?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?>
<form method="post" class="admin-form"><?= csrf_field() ?>
<label>Nama kafe<input name="nama_kafe" value="<?= e($settings["nama_kafe"] ?? "Allana Coffee") ?>" required></label>
<label>Alamat<textarea name="alamat"><?= e($settings["alamat"] ?? "") ?></textarea></label>
<div class="form-grid"><label>Jam buka<input name="jam_buka" type="time" value="<?= e($settings["jam_buka"] ?? "08:00") ?>" required></label><label>Jam tutup<input name="jam_tutup" type="time" value="<?= e($settings["jam_tutup"] ?? "22:00") ?>" required></label></div>
<label>Biaya kemasan (rupiah)<input name="biaya_kemasan" type="number" min="0" value="<?= e($settings["biaya_kemasan"] ?? "2000") ?>" required></label><button>Simpan pengaturan</button></form></section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
