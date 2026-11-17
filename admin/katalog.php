<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_role(["admin"]);
$message = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $action = (string) ($_POST["action"] ?? "");
        if ($action === "category_save") {
            $id = filter_var($_POST["category_id"] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST["name"] ?? ""));
            $description = trim((string) ($_POST["description"] ?? ""));
            if ($name === "" || mb_strlen($name) > 100) throw new HttpException(422, "Nama kategori wajib diisi (maksimal 100 karakter).");
            if ($id) stmt("UPDATE categories SET name = ?, description = ? WHERE category_id = ?", "ssi", [$name, $description, $id]);
            else stmt("INSERT INTO categories (name, description) VALUES (?, ?)", "ss", [$name, $description]);
            $message = "Kategori disimpan.";
        } elseif ($action === "category_toggle") {
            stmt("UPDATE categories SET is_active = 1 - is_active WHERE category_id = ?", "i", [(int) $_POST["category_id"]]);
            $message = "Status kategori diperbarui.";
        } elseif ($action === "product_save") {
            $id = filter_var($_POST["product_id"] ?? null, FILTER_VALIDATE_INT);
            $categoryId = filter_var($_POST["category_id"] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST["name"] ?? ""));
            $description = trim((string) ($_POST["description"] ?? ""));
            $price = filter_var($_POST["price"] ?? null, FILTER_VALIDATE_INT);
            if (!$categoryId || $name === "" || mb_strlen($name) > 120 || $price === false || $price < 0 || !one("SELECT category_id FROM categories WHERE category_id = ? AND is_active = 1", "i", [$categoryId])) {
                throw new HttpException(422, "Data produk atau kategori tidak valid.");
            }
            $photo = "";
            if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK || $_FILES["photo"]["size"] > 2 * 1024 * 1024) throw new HttpException(422, "Foto gagal diunggah atau melebihi 2 MB.");
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES["photo"]["tmp_name"]);
                $extensions = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
                if (!isset($extensions[$mime])) throw new HttpException(422, "Foto harus berupa JPG, PNG, atau WebP.");
                $fileName = bin2hex(random_bytes(16)) . "." . $extensions[$mime];
                $uploadDir = __DIR__ . "/../assets/uploads";
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException("Folder unggahan tidak dapat dibuat.");
                if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $uploadDir . DIRECTORY_SEPARATOR . $fileName)) throw new RuntimeException("Foto tidak dapat disimpan.");
                $photo = "assets/uploads/" . $fileName;
            }
            if ($id) {
                if ($photo !== "") stmt("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, photo = ? WHERE product_id = ?", "issisi", [$categoryId, $name, $description, $price, $photo, $id]);
                else stmt("UPDATE products SET category_id = ?, name = ?, description = ?, price = ? WHERE product_id = ?", "issii", [$categoryId, $name, $description, $price, $id]);
            } else {
                stmt("INSERT INTO products (category_id, name, description, price, photo) VALUES (?, ?, ?, ?, ?)", "issis", [$categoryId, $name, $description, $price, $photo]);
            }
            $message = "Produk disimpan.";
        } elseif ($action === "product_available") {
            stmt("UPDATE products SET is_available = 1 - is_available WHERE product_id = ?", "i", [(int) $_POST["product_id"]]);
            $message = "Ketersediaan produk diperbarui.";
        } elseif ($action === "product_deactivate") {
            stmt("UPDATE products SET is_active = 0, is_available = 0 WHERE product_id = ?", "i", [(int) $_POST["product_id"]]);
            $message = "Produk dinonaktifkan. Data riwayat pesanan tetap aman.";
        } elseif ($action === "group_save") {
            $groupId = filter_var($_POST["group_id"] ?? null, FILTER_VALIDATE_INT);
            $categoryId = filter_var($_POST["category_id"] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST["name"] ?? ""));
            $required = isset($_POST["is_required"]) ? 1 : 0;
            if (!$categoryId || $name === "" || !one("SELECT category_id FROM categories WHERE category_id = ?", "i", [$categoryId])) throw new HttpException(422, "Kategori grup opsi tidak valid.");
            if ($groupId) {
                stmt("UPDATE option_groups SET category_id = ?, name = ?, is_required = ? WHERE group_id = ?", "isii", [$categoryId, $name, $required, $groupId]);
                $message = "Grup opsi diperbarui.";
            } else {
                stmt("INSERT INTO option_groups (category_id, name, is_required) VALUES (?, ?, ?)", "isi", [$categoryId, $name, $required]);
                $message = "Grup opsi ditambahkan.";
            }
        } elseif ($action === "value_save") {
            $valueId = filter_var($_POST["value_id"] ?? null, FILTER_VALIDATE_INT);
            $groupId = filter_var($_POST["group_id"] ?? null, FILTER_VALIDATE_INT);
            $label = trim((string) ($_POST["label"] ?? ""));
            $extra = filter_var($_POST["extra_price"] ?? 0, FILTER_VALIDATE_INT);
            if (!$groupId || $label === "" || $extra === false || $extra < 0 || !one("SELECT group_id FROM option_groups WHERE group_id = ? AND is_active = 1", "i", [$groupId])) throw new HttpException(422, "Nilai opsi tidak valid.");
            if ($valueId) {
                stmt("UPDATE option_values SET group_id = ?, label = ?, extra_price = ? WHERE value_id = ?", "isii", [$groupId, $label, $extra, $valueId]);
                $message = "Nilai opsi diperbarui.";
            } else {
                stmt("INSERT INTO option_values (group_id, label, extra_price) VALUES (?, ?, ?)", "isi", [$groupId, $label, $extra]);
                $message = "Nilai opsi ditambahkan.";
            }
        } elseif ($action === "group_toggle") {
            stmt("UPDATE option_groups SET is_active = 1 - is_active WHERE group_id = ?", "i", [(int) $_POST["group_id"]]);
            $message = "Status grup opsi diperbarui.";
        } elseif ($action === "value_toggle") {
            stmt("UPDATE option_values SET is_active = 1 - is_active WHERE value_id = ?", "i", [(int) $_POST["value_id"]]);
            $message = "Status nilai opsi diperbarui.";
        } else {
            throw new HttpException(400, "Aksi katalog tidak dikenal.");
        }
    } catch (HttpException $exception) {
        $error = $exception->getMessage();
    } catch (mysqli_sql_exception $exception) {
        error_log("Kesalahan katalog admin: " . $exception->getMessage());
        $error = "Data tidak dapat disimpan. Periksa data yang dimasukkan.";
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
$categories = all_rows("SELECT category_id, name, is_active FROM categories ORDER BY sort_order, name");
$products = all_rows("SELECT p.product_id, p.category_id, p.name, p.description, p.price, p.stock_quantity, p.is_available, p.is_active, c.name AS category_name FROM products p JOIN categories c ON c.category_id = p.category_id ORDER BY p.product_id DESC");
$groups = all_rows("SELECT og.group_id, og.name, og.category_id, c.name AS category_name, og.is_required FROM option_groups og JOIN categories c ON c.category_id = og.category_id ORDER BY og.group_id");
$pageTitle = "Admin · Katalog";
require __DIR__ . "/../includes/header.php";
require __DIR__ . "/_nav.php";
?>
<section>
    <h1>Katalog</h1>
    <?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
    <div class="panel">
        <h2>Kategori</h2>
        <form method="post" class="admin-form form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="category_save"><label>ID untuk mengubah kategori (kosongkan untuk baru)<input name="category_id" type="number" min="1"></label><label>Nama<input name="name" required maxlength="100"></label><label>Deskripsi<input name="description" maxlength="255"></label><button>Simpan kategori</button></form>
        <ul><?php foreach ($categories as $category): ?><li><?= e($category["name"]) ?> (<?= $category["is_active"] ? "aktif" : "nonaktif" ?>)
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="category_toggle"><input type="hidden" name="category_id" value="<?= e($category["category_id"]) ?>"><button type="submit">Ubah status</button></form></li><?php endforeach; ?></ul>
    </div>
    <div class="panel">
        <h2>Tambah atau ubah produk</h2>
        <form method="post" enctype="multipart/form-data" class="admin-form form-grid">
            <?= csrf_field() ?><input type="hidden" name="action" value="product_save"><label>ID untuk ubah (kosongkan untuk baru)<input type="number" min="1" name="product_id"></label>
            <label>Kategori<select name="category_id" required><?php foreach ($categories as $category): if (!$category["is_active"]) continue; ?><option value="<?= e($category["category_id"]) ?>"><?= e($category["name"]) ?></option><?php endforeach; ?></select></label>
            <label>Nama<input name="name" required maxlength="120"></label><label>Harga (rupiah)<input name="price" type="number" min="0" required></label>
            <label>Deskripsi<input name="description" maxlength="500"></label><label>Foto (JPG/PNG/WebP, maks. 2 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
            <button>Simpan produk</button>
        </form>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($products as $product): ?><tr><td><?= e($product["name"]) ?> (ID <?= e($product["product_id"]) ?>)</td><td><?= e($product["category_name"]) ?></td><td>Rp <?= e(number_format((int) $product["price"], 0, ",", ".")) ?></td><td><?= e($product["stock_quantity"]) ?></td><td><?= $product["is_active"] ? ($product["is_available"] && $product["stock_quantity"] > 0 ? "tersedia" : "habis") : "nonaktif" ?></td><td>
            <?php if ($product["is_active"]): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e($product["product_id"]) ?>"><input type="hidden" name="action" value="product_available"><button>Ketersediaan</button></form>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e($product["product_id"]) ?>"><input type="hidden" name="action" value="product_deactivate"><button>Nonaktifkan</button></form>
            <?php endif; ?>
        </td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
    <div class="panel">
        <h2>Grup opsi dan nilai</h2>
        <form method="post" class="admin-form form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="group_save"><label>ID grup untuk ubah (kosongkan untuk baru)<input name="group_id" type="number" min="1"></label><label>Kategori<select name="category_id"><?php foreach ($categories as $category): ?><option value="<?= e($category["category_id"]) ?>"><?= e($category["name"]) ?></option><?php endforeach; ?></select></label><label>Nama grup<input name="name" required maxlength="80"></label><label><span><input type="checkbox" name="is_required"> Wajib dipilih</span></label><button>Simpan grup</button></form>
        <form method="post" class="admin-form form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="value_save"><label>ID nilai untuk ubah (kosongkan untuk baru)<input name="value_id" type="number" min="1"></label><label>Grup<select name="group_id"><?php foreach ($groups as $group): ?><option value="<?= e($group["group_id"]) ?>"><?= e($group["category_name"] . " · " . $group["name"]) ?></option><?php endforeach; ?></select></label><label>Label<input name="label" required maxlength="80"></label><label>Harga tambahan<input name="extra_price" type="number" min="0" value="0" required></label><button>Simpan nilai opsi</button></form>
        <ul><?php foreach ($groups as $group): ?><li><?= e($group["category_name"] . " · " . $group["name"]) ?> (ID <?= e($group["group_id"]) ?>) — <?php foreach (all_rows("SELECT value_id, label, extra_price, is_active FROM option_values WHERE group_id = ? ORDER BY value_id", "i", [$group["group_id"]]) as $value): ?><?= e($value["label"]) ?> (+Rp <?= e(number_format((int) $value["extra_price"], 0, ",", ".")) ?>) (ID <?= e($value["value_id"]) ?>, <?= $value["is_active"] ? "aktif" : "nonaktif" ?>) <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="value_toggle"><input type="hidden" name="value_id" value="<?= e($value["value_id"]) ?>"><button>Ubah status</button></form><?php endforeach; ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="group_toggle"><input type="hidden" name="group_id" value="<?= e($group["group_id"]) ?>"><button>Ubah status grup</button></form></li><?php endforeach; ?></ul>
    </div>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
