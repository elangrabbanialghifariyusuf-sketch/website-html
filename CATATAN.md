# Catatan implementasi

- Workspace awal hanya berisi halaman HTML, dua skrip autentikasi sederhana, dan koneksi MySQL lokal; direktori `api/`, `includes/`, `auth/`, dan `database/` belum tersedia. Fondasi pemesanan dibuat baru di atasnya. Database lokal yang diperiksa memiliki kolom `id_login` dan `no_hp`; migrasi `02_pemesanan.sql` menggantinya dengan nama yang dipakai aplikasi, `user_id` dan `nomor_hp`.
- Jam server dan basis data diselaraskan ke WIB (UTC+07:00). Jadwal takeaway berlaku di hari yang sama, maksimal sampai jam tutup hari itu.
- Kafe hanya satu outlet. Dua lantai dan sepuluh meja per lantai dimasukkan sebagai data contoh; admin dapat menambah lantai/meja.
- Pembayaran adalah tunai di kasir. Pengembalian setelah pembatalan pesanan lunas dicatat `refunded`, tetapi pengembalian uang dilakukan manual.
- Stok produk dikurangi saat pembayaran diterima kasir, bukan saat checkout. Pembatalan pesanan lunas mengembalikan stok; perubahan stok admin dicatat sebagai pergerakan persediaan. Produk lama setelah migrasi stok dimulai dari 0 sehingga admin perlu mencatat stok fisik aktual sebelum produk dijual.
- Menu publik menampilkan semua produk aktif dan menandai stok habis; tombol pesan aktif hanya untuk produk tersedia dengan stok cukup. Data contoh instalasi baru diberi stok awal 20 per produk contoh, sedangkan database terpasang perlu mengisi stok nyata melalui panel admin.
- Shift kasir dicatat sejak mulai hingga tutup shift; pembayaran yang diterima kasir dikaitkan ke shift aktif untuk rekap admin.
- Laporan pemasukan memakai waktu pembayaran (`paid_at`) dan pengembalian (`refunded_at`), bukan tanggal order dibuat. Pesanan lama tanpa waktu tersebut diinisialisasi ke `updated_at` oleh migrasi.
- Akun pertama dibuat melalui pendaftaran customer, lalu role dinaikkan menjadi `admin` secara manual melalui database. Tidak ada kata sandi admin bawaan.
- Konfigurasi lokal memakai `config/koneksi.php` dan tidak boleh masuk Git; salin dari `config/koneksi.example.php`.
- Berkas HTML lama untuk beranda, autentikasi, dan dashboard dipertahankan sebagai pengarah ke halaman PHP baru. Endpoint autentikasi lama tidak lagi memproses kredensial.
