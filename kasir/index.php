<?php
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/orders.php";
expire_orders($conn);
require_role(["kasir", "admin"]);
$pageTitle = "Panel kasir";
require __DIR__ . "/../includes/header.php";
?>
<section>
    <p class="eyebrow">Dashboard operasional</p>
    <h1>Dashboard kasir</h1>
    <section class="panel">
        <h2>Sesi kasir</h2>
        <p id="shift-status" role="status">Memeriksa status shift...</p>
        <form id="shift-start-form" class="form-grid">
            <label for="opening-cash">Modal awal kasir (rupiah)<input id="opening-cash" name="opening_cash" type="number" min="0" value="0" required></label>
            <button type="submit">Mulai shift</button>
        </form>
        <form id="shift-end-form" class="form-grid hidden">
            <label for="closing-cash">Uang kas saat tutup shift (rupiah)<input id="closing-cash" name="closing_cash" type="number" min="0" required></label>
            <button type="submit">Tutup shift</button>
        </form>
    </section>
    <p class="eyebrow">Antrean kasir</p>
    <div class="form-grid">
        <label for="status-filter">Status<select id="status-filter"><option value="">Semua status</option><option value="menunggu_bayar">Menunggu bayar</option><option value="diterima">Diterima</option><option value="diproses">Diproses</option><option value="disajikan">Disajikan</option><option value="siap">Siap</option></select></label>
        <label for="type-filter">Tipe<select id="type-filter"><option value="">Semua tipe</option><option value="dine_in">Makan di tempat</option><option value="takeaway">Takeaway</option></select></label>
    </div>
    <p id="cashier-message" role="status"></p>
    <div class="cashier-queues">
        <section class="panel"><h2>Menunggu pembayaran <span id="unpaid-count">0</span></h2><div id="unpaid-orders" class="order-list"></div></section>
        <section class="panel"><h2>Lunas · Dalam proses</h2><div id="paid-orders" class="order-list"></div></section>
    </div>
    <section class="panel">
        <h2>Verifikasi pengambilan takeaway</h2>
        <form id="verify-form" class="form-grid">
            <label for="verify-code">Kode pesanan lengkap<input id="verify-code" placeholder="T-261116-0001" required></label>
            <button type="submit">Tandai selesai</button>
        </form>
    </section>
</section>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf"]').content;
    const roots = {
        unpaid: document.querySelector('#unpaid-orders'),
        paid: document.querySelector('#paid-orders')
    };
    const message = document.querySelector('#cashier-message');
    const shiftStatus = document.querySelector('#shift-status');
    const startForm = document.querySelector('#shift-start-form');
    const endForm = document.querySelector('#shift-end-form');
    const statusFilter = document.querySelector('#status-filter');
    const typeFilter = document.querySelector('#type-filter');
    const transitions = {
        diterima: [['diproses', 'Mulai diproses']],
        diproses: [['disajikan', 'Tandai disajikan'], ['siap', 'Tandai siap']]
    };

    async function mutate(payload) {
        const response = await fetch('../api/kasir/orders.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payload) });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Perubahan gagal.');
        message.textContent = data.message;
        await load();
    }

    async function loadBucket(bucket) {
        const query = new URLSearchParams();
        query.set('bucket', bucket);
        if (statusFilter.value) query.set('status', statusFilter.value);
        if (typeFilter.value) query.set('type', typeFilter.value);
        try {
            const response = await fetch('../api/kasir/orders.php?' + query.toString());
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Pesanan gagal dimuat.');
            roots[bucket].replaceChildren();
            if (bucket === 'unpaid') {
                document.querySelector('#unpaid-count').textContent = String(data.unpaid_count);
                if (data.shift) {
                    shiftStatus.textContent = 'Shift aktif sejak ' + data.shift.opened_at + ' · Modal awal Rp ' + Number(data.shift.opening_cash).toLocaleString('id-ID');
                    startForm.classList.add('hidden');
                    endForm.classList.remove('hidden');
                } else {
                    shiftStatus.textContent = 'Belum ada shift aktif. Mulai shift sebelum memproses pembayaran atau status pesanan.';
                    startForm.classList.remove('hidden');
                    endForm.classList.add('hidden');
                }
            }
            data.orders.forEach((order) => {
                const card = document.createElement('article');
                card.className = 'order-card';
                const title = document.createElement('h2');
                title.textContent = order.order_code.slice(0, 2) + order.order_code.slice(-4) + ' · ' + order.created_at;
                const details = document.createElement('p');
                details.textContent = 'Kode lengkap: ' + order.order_code + ' · ' + (order.buyer_name || (order.floor_name ? order.floor_name + ' / ' + order.table_number : ''));
                const state = document.createElement('p');
                state.textContent = order.status + ' · ' + order.payment_status + ' · Rp ' + Number(order.total).toLocaleString('id-ID');
                const items = document.createElement('p');
                items.textContent = order.items.map((item) => item.product_name + ' × ' + item.qty + (item.note ? ' (' + item.note + ')' : '')).join(', ');
                card.append(title, details, state, items);
                if (order.pickup_at) {
                    const pickup = document.createElement('p');
                    pickup.textContent = 'Jadwal ambil: ' + order.pickup_at;
                    card.append(pickup);
                }
                if (order.notes) {
                    const notes = document.createElement('p');
                    notes.textContent = order.notes;
                    card.append(notes);
                }
                if (order.status === 'menunggu_bayar') {
                    const paid = document.createElement('button');
                    paid.textContent = 'Tandai lunas';
                    paid.addEventListener('click', () => mutate({ action: 'paid', order_id: order.order_id }).catch((cause) => { message.textContent = cause.message; }));
                    card.append(paid);
                }
                (transitions[order.status] || []).filter(([next]) => next !== 'siap' || order.order_type === 'takeaway').filter(([next]) => next !== 'disajikan' || order.order_type === 'dine_in').forEach(([next, label]) => {
                    const button = document.createElement('button');
                    button.textContent = label;
                    button.addEventListener('click', () => mutate({ action: 'status', status: next, order_id: order.order_id }).catch((cause) => { message.textContent = cause.message; }));
                    card.append(button);
                });
                if (order.status === 'disajikan' || order.status === 'siap') {
                    const done = document.createElement('button');
                    done.textContent = 'Tandai selesai';
                    done.addEventListener('click', () => mutate({ action: 'status', status: 'selesai', order_id: order.order_id }).catch((cause) => { message.textContent = cause.message; }));
                    card.append(done);
                }
                roots[bucket].append(card);
            });
            if (!roots[bucket].childElementCount) {
                const empty = document.createElement('p');
                empty.textContent = bucket === 'unpaid' ? 'Tidak ada pembayaran yang menunggu.' : 'Belum ada pesanan lunas yang sedang diproses.';
                roots[bucket].append(empty);
            }
        } catch (cause) { message.textContent = cause.message; }
    }

    async function load() {
        await Promise.all([loadBucket('unpaid'), loadBucket('paid')]);
    }

    async function shiftAction(action, field) {
        const amount = document.querySelector(field).value;
        try {
            await mutate({ action, [action === 'shift_start' ? 'opening_cash' : 'closing_cash']: amount });
        } catch (cause) {
            message.textContent = cause.message;
        }
    }
    startForm.addEventListener('submit', (event) => {
        event.preventDefault();
        shiftAction('shift_start', '#opening-cash');
    });
    endForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (window.confirm('Tutup shift kasir sekarang?')) shiftAction('shift_end', '#closing-cash');
    });
    statusFilter.addEventListener('change', load);
    typeFilter.addEventListener('change', load);
    document.querySelector('#verify-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const response = await fetch('../api/kasir/orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ action: 'lookup', code: document.querySelector('#verify-code').value })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Pesanan tidak ditemukan.');
            const confirmed = window.confirm('Pesanan ' + data.order.order_code + ' untuk ' + data.order.buyer_name + ' ditemukan. Selesaikan pesanan?');
            if (confirmed) await mutate({ action: 'verify', order_id: data.order.order_id, code: data.order.order_code });
        } catch (cause) { message.textContent = cause.message; }
    });
    load();
    window.setInterval(load, 10000);
})();
</script>
<?php require __DIR__ . "/../includes/footer.php"; ?>
