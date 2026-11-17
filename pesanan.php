<?php
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/orders.php";
expire_orders($conn);
require_role(["customer", "admin"]);
$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(400);
    exit("Nomor pesanan tidak valid.");
}
$pageTitle = "Detail pesanan";
require __DIR__ . "/includes/header.php";
?>
<section>
    <div id="order-detail" class="panel" aria-live="polite">Memuat pesanan...</div>
    <p id="order-error" class="notice error" hidden></p>
</section>
<script>
(() => {
    const root = document.querySelector('#order-detail');
    const error = document.querySelector('#order-error');
    const orderId = <?= json_encode($orderId) ?>;
    const csrf = document.querySelector('meta[name="csrf"]').content;
    const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
    let timer;

    function addText(parent, tag, text, className) {
        const element = document.createElement(tag);
        element.textContent = text;
        if (className) element.className = className;
        parent.append(element);
        return element;
    }

    function render(order) {
        root.replaceChildren();
        addText(root, 'p', order.short_code + ' · ' + order.created_at, 'eyebrow');
        addText(root, 'h1', 'Pesanan ' + order.order_code);
        addText(root, 'p', 'Status: ' + order.status.replaceAll('_', ' '), 'status');
        addText(root, 'p', 'Pembayaran: ' + order.payment_status + ' · Total ' + money(order.total));
        addText(root, 'p', order.order_type === 'dine_in'
            ? 'Makan di tempat · ' + (order.floor_name || '') + ' · Meja ' + (order.table_number || '')
            : 'Takeaway · ' + (order.buyer_name || '') + ' · ' + (order.buyer_phone || '') + (order.pickup_at ? ' · Jadwal ' + order.pickup_at : ' · Secepatnya'));
        if (order.payment_expires_at && order.payment_status === 'unpaid') addText(root, 'p', 'Batas pembayaran: ' + order.payment_expires_at);
        if (order.ready_at && order.order_type === 'takeaway') addText(root, 'p', 'Batas pengambilan: 30 menit setelah siap (' + order.ready_at + ')');
        const itemList = document.createElement('ul');
        order.items.forEach((item) => {
            const row = document.createElement('li');
            const details = item.options.map((option) => option.group_name + ': ' + option.value_name).join(', ');
            row.textContent = item.product_name + ' × ' + item.qty + ' · ' + money(item.price * item.qty) + (details ? ' · ' + details : '') + (item.note ? ' · Catatan: ' + item.note : '');
            itemList.append(row);
        });
        root.append(itemList);
        if (order.notes) addText(root, 'p', order.notes);
        addText(root, 'h2', 'Linimasa');
        const timeline = document.createElement('ol');
        order.timeline.forEach((entry) => {
            const row = document.createElement('li');
            row.textContent = entry.created_at + ' · ' + entry.status.replaceAll('_', ' ') + (entry.note ? ' · ' + entry.note : '');
            timeline.append(row);
        });
        root.append(timeline);
        if (order.status === 'batal' && order.payment_status === 'refunded') addText(root, 'p', 'Pengembalian dana dilakukan manual oleh kasir.', 'notice');
        if (['menunggu_bayar', 'diterima'].includes(order.status)) {
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.textContent = 'Batalkan pesanan';
            cancel.addEventListener('click', async () => {
                if (!window.confirm('Batalkan pesanan ini?')) return;
                const response = await fetch('api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ action: 'cancel', order_id: order.order_id })
                });
                const data = await response.json();
                if (!response.ok) { error.textContent = data.error || 'Pembatalan gagal.'; error.hidden = false; return; }
                await load();
            });
            root.append(cancel);
        }
    }

    async function load() {
        try {
            const response = await fetch('api/orders.php?id=' + encodeURIComponent(orderId));
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Pesanan tidak dapat dimuat.');
            render(data.order);
            const finalStatus = ['selesai', 'batal', 'kedaluwarsa'].includes(data.order.status);
            if (timer) window.clearTimeout(timer);
            if (!finalStatus) timer = window.setTimeout(load, 10000);
        } catch (cause) {
            error.textContent = cause.message;
            error.hidden = false;
        }
    }
    load();
})();
</script>
<?php require __DIR__ . "/includes/footer.php"; ?>
