<?php
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/orders.php";
expire_orders($conn);
require_role(["customer", "admin"]);
$user = one("SELECT nama, nomor_hp FROM login WHERE user_id = ?", "i", [(int) $_SESSION["user_id"]]);
$settings = settings_map();
$pageTitle = "Checkout";
require __DIR__ . "/includes/header.php";
?>
<section>
    <p class="eyebrow">Langkah terakhir</p>
    <h1>Checkout</h1>
    <div id="checkout-error" class="notice error" role="alert" hidden></div>
    <div id="cart-summary" class="panel"></div>
    <form id="checkout-form" class="panel">
        <fieldset>
            <legend>Tipe pesanan</legend>
            <label><span><input type="radio" name="order_type" value="dine_in" checked> Makan di tempat</span></label>
            <label><span><input type="radio" name="order_type" value="takeaway"> Takeaway</span></label>
        </fieldset>
        <div id="dine-in-fields" class="form-grid">
            <label for="floor">Lantai<select id="floor" name="floor_id" required><option value="">Pilih lantai</option></select></label>
            <label for="table">Meja<select id="table" name="table_id" required disabled><option value="">Pilih lantai terlebih dahulu</option></select></label>
        </div>
        <div id="takeaway-fields" class="hidden">
            <div class="form-grid">
                <label for="buyer-name">Nama pengambil<input id="buyer-name" maxlength="100" value="<?= e($user["nama"] ?? "") ?>"></label>
                <label for="buyer-phone">Nomor HP pengambil<input id="buyer-phone" maxlength="30" value="<?= e($user["nomor_hp"] ?? "") ?>"></label>
                <label for="pickup-mode">Waktu pengambilan<select id="pickup-mode"><option value="asap">Secepatnya (estimasi 10-15 menit)</option><option value="scheduled">Dijadwalkan</option></select></label>
                <label id="pickup-time-label" class="hidden" for="pickup-at">Jam pengambilan<input id="pickup-at" type="datetime-local"></label>
            </div>
            <p>Biaya kemasan: Rp <?= e(number_format((int) ($settings["biaya_kemasan"] ?? 0), 0, ",", ".")) ?>.</p>
            <fieldset>
                <legend>Pilihan tambahan</legend>
                <label><span><input type="checkbox" name="extras" value="sedotan"> Sedotan</span></label>
                <label><span><input type="checkbox" name="extras" value="kantong"> Kantong</span></label>
            </fieldset>
            <label for="order-notes">Catatan untuk kafe<textarea id="order-notes" maxlength="150"></textarea></label>
        </div>
        <button id="submit-order" type="submit">Buat pesanan</button>
    </form>
</section>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf"]').content;
    const cartSummary = document.querySelector('#cart-summary');
    const error = document.querySelector('#checkout-error');
    const form = document.querySelector('#checkout-form');
    const floor = document.querySelector('#floor');
    const table = document.querySelector('#table');
    const dineFields = document.querySelector('#dine-in-fields');
    const takeawayFields = document.querySelector('#takeaway-fields');
    const pickupMode = document.querySelector('#pickup-mode');
    const pickupLabel = document.querySelector('#pickup-time-label');
    const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
    let cart;
    try { cart = JSON.parse(localStorage.getItem('kafe_cart_v1') || '[]'); } catch { cart = []; }

    function showError(message) { error.textContent = message; error.hidden = false; }
    function loadTables() {
        table.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = floor.value ? 'Pilih meja' : 'Pilih lantai terlebih dahulu';
        table.append(placeholder);
        table.disabled = !floor.value;
        if (!floor.value) return;
        fetch('api/tables.php?floor_id=' + encodeURIComponent(floor.value))
            .then((response) => response.json().then((data) => {
                if (!response.ok) throw new Error(data.error || 'Daftar meja gagal dimuat.');
                return data;
            }))
            .then((data) => {
                data.tables.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.table_id;
                    option.textContent = item.table_number;
                    table.append(option);
                });
                if (!data.tables.length) {
                    const empty = document.createElement('option');
                    empty.value = '';
                    empty.textContent = 'Tidak ada meja tersedia';
                    table.append(empty);
                }
            })
            .catch((cause) => showError(cause.message));
    }

    if (!Array.isArray(cart) || cart.length === 0) {
        showError('Keranjang kosong. Silakan pilih menu terlebih dahulu.');
        document.querySelector('#submit-order').disabled = true;
    } else {
        const list = document.createElement('div');
        let total = 0;
        cart.forEach((item) => {
            const row = document.createElement('p');
            const qty = Math.max(1, Number(item.qty) || 1);
            row.textContent = (item.name || 'Produk') + ' × ' + qty;
            list.append(row);
            total += (Number(item.unit_price) || 0) * qty;
        });
        const totalRow = document.createElement('strong');
        totalRow.textContent = 'Perkiraan subtotal: ' + money(total) + ' (dihitung ulang saat pesanan dibuat)';
        cartSummary.append(list, totalRow);
    }

    fetch('api/floors.php').then((response) => response.json()).then((data) => {
        data.floors.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.floor_id;
            option.textContent = item.name;
            floor.append(option);
        });
    }).catch((cause) => showError(cause.message));

    floor.addEventListener('change', loadTables);
    document.querySelectorAll('input[name="order_type"]').forEach((radio) => radio.addEventListener('change', () => {
        const takeaway = document.querySelector('input[name="order_type"]:checked').value === 'takeaway';
        dineFields.classList.toggle('hidden', takeaway);
        takeawayFields.classList.toggle('hidden', !takeaway);
        floor.required = !takeaway;
        table.required = !takeaway;
        document.querySelector('#buyer-name').required = takeaway;
        document.querySelector('#buyer-phone').required = takeaway;
    }));
    pickupMode.addEventListener('change', () => {
        const scheduled = pickupMode.value === 'scheduled';
        pickupLabel.classList.toggle('hidden', !scheduled);
        document.querySelector('#pickup-at').required = scheduled;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        const orderType = document.querySelector('input[name="order_type"]:checked').value;
        const payload = {
            action: 'create',
            order_type: orderType,
            items: cart.map((item) => ({
                product_id: item.product_id,
                qty: item.qty,
                options: Array.isArray(item.options) ? item.options.map((option) => ({ group_id: option.group_id, value_id: option.value_id })) : [],
                note: item.note || ''
            }))
        };
        if (orderType === 'dine_in') {
            payload.floor_id = floor.value;
            payload.table_id = table.value;
        } else {
            payload.buyer_name = document.querySelector('#buyer-name').value;
            payload.buyer_phone = document.querySelector('#buyer-phone').value;
            payload.pickup_mode = pickupMode.value;
            payload.pickup_at = document.querySelector('#pickup-at').value;
            payload.extras = Array.from(form.querySelectorAll('input[name="extras"]:checked')).map((input) => input.value);
            payload.notes = document.querySelector('#order-notes').value;
        }
        try {
            const response = await fetch('api/orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Pesanan gagal dibuat.');
            localStorage.removeItem('kafe_cart_v1');
            window.location.href = data.url;
        } catch (cause) {
            showError(cause.message);
        }
    });
})();
</script>
<?php require __DIR__ . "/includes/footer.php"; ?>
