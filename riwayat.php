<?php
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/orders.php";
expire_orders($conn);
require_role(["customer", "admin"]);
$pageTitle = "Riwayat pesanan";
require __DIR__ . "/includes/header.php";
?>
<section>
    <h1>Riwayat pesanan</h1>
    <div id="history-list" class="order-list" aria-live="polite">Memuat riwayat...</div>
    <p id="history-error" class="notice error" hidden></p>
</section>
<script>
(() => {
    const root = document.querySelector('#history-list');
    const error = document.querySelector('#history-error');
    const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
    fetch('api/orders.php').then((response) => response.json().then((data) => {
        if (!response.ok) throw new Error(data.error || 'Riwayat gagal dimuat.');
        return data.orders;
    })).then((orders) => {
        root.replaceChildren();
        if (!orders.length) { root.textContent = 'Belum ada pesanan.'; return; }
        orders.forEach((order) => {
            const card = document.createElement('article');
            card.className = 'order-card';
            const title = document.createElement('h2');
            title.textContent = order.short_code + ' · ' + (order.order_type === 'dine_in' ? 'Makan di tempat' : 'Takeaway');
            const status = document.createElement('p');
            status.className = 'status';
            status.textContent = order.status.replaceAll('_', ' ') + ' · ' + order.payment_status;
            const total = document.createElement('p');
            total.textContent = money(order.total);
            const date = document.createElement('p');
            date.textContent = order.created_at;
            const link = document.createElement('a');
            link.className = 'button-link';
            link.href = 'pesanan.php?id=' + encodeURIComponent(order.order_id);
            link.textContent = 'Detail pesanan';
            card.append(title, status, total, date, link);
            root.append(card);
        });
    }).catch((cause) => { error.textContent = cause.message; error.hidden = false; root.textContent = ''; });
})();
</script>
<?php require __DIR__ . "/includes/footer.php"; ?>
