<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_role(["admin"]);
$message = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        if (($_POST["action"] ?? "") === "floor_save") {
            $name = trim((string) ($_POST["name"] ?? ""));
            if ($name === "") throw new HttpException(422, "Nama lantai wajib diisi.");
            $floorId = filter_var($_POST["floor_id"] ?? null, FILTER_VALIDATE_INT);
            if ($floorId) {
                stmt("UPDATE floors SET name = ? WHERE floor_id = ?", "si", [$name, $floorId]);
                $message = "Lantai diperbarui.";
            } else {
                stmt("INSERT INTO floors (name) VALUES (?)", "s", [$name]);
                $message = "Lantai ditambahkan.";
            }
        } elseif (($_POST["action"] ?? "") === "table_save") {
            $floorId = filter_var($_POST["floor_id"] ?? null, FILTER_VALIDATE_INT);
            $number = trim((string) ($_POST["table_number"] ?? ""));
            if (!$floorId || !preg_match("/^[A-Za-z0-9-]{1,20}$/", $number)) throw new HttpException(422, "Nomor meja atau lantai tidak valid.");
            $tableId = filter_var($_POST["table_id"] ?? null, FILTER_VALIDATE_INT);
            if ($tableId) {
                stmt("UPDATE tables_cafe SET floor_id = ?, table_number = ? WHERE table_id = ? AND is_available = 1", "isi", [$floorId, $number, $tableId]);
                $message = "Meja diperbarui.";
            } else {
                stmt("INSERT INTO tables_cafe (floor_id, table_number) VALUES (?, ?)", "is", [$floorId, $number]);
                $message = "Meja ditambahkan.";
            }
        } elseif (($_POST["action"] ?? "") === "toggle") {
            stmt("UPDATE tables_cafe SET is_active = 1 - is_active WHERE table_id = ?", "i", [(int) $_POST["table_id"]]);
            $message = "Status meja diperbarui.";
        } elseif (($_POST["action"] ?? "") === "floor_toggle") {
            stmt("UPDATE floors SET is_active = 1 - is_active WHERE floor_id = ?", "i", [(int) $_POST["floor_id"]]);
            $message = "Status lantai diperbarui.";
        }
    } catch (HttpException $exception) { $error = $exception->getMessage(); }
    catch (mysqli_sql_exception $exception) { $error = "Data tidak dapat disimpan (mungkin nomor meja sudah digunakan)."; }
}
$floors = all_rows("SELECT floor_id, name, is_active FROM floors ORDER BY floor_id");
$tables = all_rows("SELECT t.table_id, t.table_number, t.is_active, t.is_available, f.name AS floor_name, t.floor_id FROM tables_cafe t JOIN floors f ON f.floor_id = t.floor_id ORDER BY f.floor_id, t.table_number");
$pageTitle = "Admin · Lantai & meja";
require __DIR__ . "/../includes/header.php";
require __DIR__ . "/_nav.php";
?>
<section><h1>Lantai dan meja</h1><?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?><?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<div class="panel"><h2>Tambah atau ubah lantai</h2><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="floor_save"><label>ID lantai untuk ubah (kosongkan untuk baru)<input name="floor_id" type="number" min="1"></label><label>Nama lantai<input name="name" required></label><button>Simpan lantai</button></form></div>
<div class="panel"><h2>Tambah atau ubah meja</h2><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="table_save"><label>ID meja untuk ubah (kosongkan untuk baru)<input name="table_id" type="number" min="1"></label><label>Lantai<select name="floor_id"><?php foreach ($floors as $floor): ?><option value="<?= e($floor["floor_id"]) ?>"><?= e($floor["name"]) ?></option><?php endforeach; ?></select></label><label>Nomor meja<input name="table_number" required maxlength="20"></label><button>Simpan meja</button></form></div>
<div class="panel"><h2>Daftar</h2><div class="table-wrap"><table><thead><tr><th>Lantai/meja</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach ($tables as $table): ?><tr><td><?= e($table["floor_name"] . " · " . $table["table_number"]) ?></td><td><?= !$table["is_active"] ? "nonaktif" : ($table["is_available"] ? "tersedia" : "dipesan") ?></td><td><?php if ($table["is_available"]): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="table_id" value="<?= e($table["table_id"]) ?>"><button><?= $table["is_active"] ? "Nonaktifkan meja" : "Aktifkan meja" ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<h3>Lantai</h3><?php foreach ($floors as $floor): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="floor_toggle"><input type="hidden" name="floor_id" value="<?= e($floor["floor_id"]) ?>"><button><?= e($floor["name"]) ?> — <?= $floor["is_active"] ? "nonaktifkan" : "aktifkan" ?></button></form><?php endforeach; ?></div>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
