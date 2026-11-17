# Allana Coffee

Website pemesanan untuk satu outlet, memakai PHP native, MySQL/mysqli, HTML, CSS, dan JavaScript tanpa framework. Pembayaran dilakukan di kasir; integrasi payment gateway tidak disertakan.

## Persiapan

1. Gunakan PHP 8.1+ dengan ekstensi `mysqli`, `mbstring`, dan `fileinfo`, serta MySQL/MariaDB di XAMPP atau Laragon.
2. Buat basis data baru dengan mengimpor `database/schema.sql` melalui phpMyAdmin. Untuk pemasangan lama yang sudah mempunyai tabel `login`, baca bagian migrasi.
3. Salin `config/koneksi.example.php` menjadi `config/koneksi.php`, lalu sesuaikan host, pengguna, kata sandi, dan nama basis data. File konfigurasi lokal tersebut diabaikan Git.
4. Arahkan document root Apache ke folder proyek. `index.php` menjadi halaman awal. Menu pelanggan tersedia di `menu.php`; produk contoh pada instalasi baru diberi stok awal 20 unit per produk, dan admin dapat menyesuaikannya dengan stok fisik.
5. Daftarkan akun melalui `register.php`, lalu promosikan akun pertama menjadi admin melalui phpMyAdmin:

   ```sql
   UPDATE login SET role = 'admin' WHERE username = 'username-admin';
   ```

6. Masuk sebagai admin, lalu atur jam buka, biaya kemasan, katalog, opsi produk, lantai, dan meja. Skema awal telah menambahkan dua lantai dengan sepuluh meja per lantai.

## Migrasi basis data yang sudah terpasang

Cadangkan basis data lebih dahulu. Migrasi ini ditujukan untuk tabel `login` lama yang memiliki `id_login`, `nama`, `no_hp`, dan `role`. Dari direktori root proyek, jalankan berurutan `SOURCE database/02_pemesanan.sql;`, `SOURCE database/03_inventory.sql;`, `SOURCE database/04_cashier_shifts.sql;`, lalu `SOURCE database/05_payment_timestamps.sql;` di klien MySQL. Migrasi mempertahankan akun dan pesanan, menambah stok, shift kasir, serta waktu transaksi pembayaran/refund. Jangan jalankan migrasi pada database baru yang sudah dibuat dari `schema.sql`.

## Alur utama

- `index.php`, `menu.php`: halaman beranda dan menu publik.
- `checkout.php`: checkout setelah login; keranjang tamu tetap tersimpan pada `localStorage`.
- `riwayat.php`, `pesanan.php`: riwayat, detail, pembatalan sesuai status, dan polling status.
- `kasir/dashboard.php`, `kasir/index.php`: dashboard shift, daftar pesanan aktif, pembayaran tunai, transisi status, serta verifikasi kode takeaway.
- `admin/index.php`: ringkasan. Navigasi admin mencakup katalog, meja, pengaturan, pengguna, dan laporan.
- Stok produk dikelola di `admin/inventory.php`; stok dikurangi saat kasir menerima pembayaran dan dikembalikan bila pesanan lunas dibatalkan.
- Logout selalu memakai POST dan token CSRF.

## Endpoint JSON

- `GET api/menu.php`, `GET api/me.php`
- `GET api/floors.php`, `GET api/tables.php?floor_id=...`
- `GET api/orders.php`, `GET api/orders.php?id=...`, `POST api/orders.php`
- `GET api/kasir/orders.php`, `POST api/kasir/orders.php`

Mutasi JSON harus mengirim token pada header `X-CSRF-Token`; halaman menyediakan token di elemen `meta[name="csrf"]`. Endpoint kasir memerlukan peran kasir/admin. Harga pesanan dihitung ulang di server.

## Pemeriksaan

Jalankan lint PHP untuk seluruh file dari PowerShell:

```powershell
Get-ChildItem -Filter *.php -Recurse | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
```

Lakukan uji manual dengan dua akun customer untuk memastikan meja terkunci dan data pesanan privat, serta satu akun kasir dan satu admin untuk memeriksa pembatasan peran. Pemeriksaan otomatis tidak dapat menguji alur MySQL sampai konfigurasi lokal disiapkan.
