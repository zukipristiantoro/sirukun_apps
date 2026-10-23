# SIRUKUN — Sistem Informasi RT/RW Terpadu

Aplikasi web administrasi lingkungan berbasis **Laravel 12, PHP 8.2+, dan MySQL/MariaDB**, dengan tampilan responsif dan empat peran: Warga, RT, RW, serta Administrator (super user).

## Fitur

- Data wilayah, hunian, kartu keluarga, dan warga.
- Mutasi pindah masuk, pindah keluar, kelahiran, dan kematian.
- Pelaporan tamu menginap serta penghuni kontrakan/indekos dan pengingat masa tinggal.
- Lima jenis surat dengan persetujuan Ketua RT → Ketua RW, PDF bernomor urut, QR verifikasi, dan arsip digital.
- Dashboard, laporan PDF/Excel, pengelolaan pengguna/menu, log aktivitas, dan backup SQL.
- Pembatasan akses sesuai peran/wilayah dan penyimpanan dokumen privat.

## Instalasi dan pendaftaran Administrator

Repositori berisi source code dan `composer.lock`, tetapi tidak menyertakan `vendor` atau kredensial. Node.js/npm tidak diperlukan.

1. Gunakan PHP **8.2 atau lebih baru** dan MySQL/MariaDB. Aktifkan ekstensi PHP `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `tokenizer`, `dom`, `xml`, `xmlwriter`, `gd`, dan `zip`.
2. Pasang dependensi dari folder proyek:

   ```bat
   composer install
   ```

   Composer harus menggunakan PHP yang sama dengan runtime aplikasi.
3. Buat database kosong bernama `sirukun_apps` melalui phpMyAdmin atau MySQL. Buat/siapkan akun MySQL yang dapat membuat tabel dan indeks di database tersebut.
4. Salin `.env.example` menjadi `.env`, lalu atur `DB_HOST`, `DB_PORT`, `DB_DATABASE=sirukun_apps`, `DB_USERNAME`, dan `DB_PASSWORD`. Atur juga `APP_URL` sesuai alamat aplikasi.
5. Dari terminal pada folder proyek, siapkan kunci dan tabel/data dasar:

   ```bat
   php artisan optimize:clear
   php artisan key:generate
   php artisan migrate --seed
   ```

6. Jalankan aplikasi dari folder mana pun, tanpa perlu meletakkannya di `htdocs`:

   ```bat
   php artisan serve
   ```

7. Buka **http://127.0.0.1:8000/install.php** dari komputer yang menjalankan server. Formulir membuat akun Administrator serta identitas RW/RT awal; halaman hanya dapat diakses lokal dan terkunci setelah pendaftaran. Semua kata sandi minimal 6 karakter; huruf, angka, simbol, dan spasi diperbolehkan, serta huruf besar/kecil dibedakan.
8. Masuk ke **http://127.0.0.1:8000/**. Tambahkan RW/RT lain bila diperlukan, kemudian lengkapi hunian dan data warga.

Tidak tersedia akun Administrator dengan kata sandi bawaan. Ekstensi GD tidak lagi menghalangi pendaftaran awal, tetapi tetap diperlukan oleh Composer dan fitur tertentu seperti ekspor spreadsheet/gambar. Jika memakai XAMPP, jalankan perintah `php artisan` menggunakan PHP XAMPP, misalnya `C:\xampp\php\php.exe artisan migrate --seed`.

Direktori `storage`, `storage/app`, `storage/framework/sessions`, `bootstrap/cache`, dan root proyek harus dapat ditulis. Server bawaan Laravel ditujukan untuk pengembangan/pemakaian lokal; untuk deployment, gunakan web server dengan document root diarahkan ke folder `public`.

## Pengembangan dan pengujian

Pasang seluruh dependensi termasuk PHPUnit:

```sh
composer install
php vendor/bin/phpunit
```

Pengujian menggunakan SQLite dalam memori; aktifkan `pdo_sqlite` dan ekstensi PHP yang dibutuhkan aplikasi. Data pada pengujian adalah data contoh. Hasil pengujian paket sebelumnya dan batas verifikasinya tercatat di [HASIL_PENGUJIAN.txt](HASIL_PENGUJIAN.txt).

## Struktur proyek

| Folder/file | Fungsi |
|---|---|
| `app/` | Controller, model, middleware, dan layanan aplikasi |
| `database/` | Migrasi dan pengisian data dasar |
| `resources/views/` | Tampilan Blade |
| `public/` | Entry point, installer, CSS, dan JavaScript |
| `routes/` | Rute aplikasi dan penjadwalan |
| `config/` | Konfigurasi Laravel |
| `storage/` | Data privat, surat, cache, sesi, dan log saat aplikasi dijalankan |
| `tests/` | Pengujian aplikasi |
| `scheduler.bat` | Pemanggilan penjadwal melalui Windows Task Scheduler |
| `PANDUAN_INSTALL.txt` | Panduan lengkap paket XAMPP dan operasional |

Panduan paket menjelaskan pemasangan ZIP/RAR yang sudah menyertakan `vendor`. Untuk unduhan source GitHub, ikuti langkah Composer di atas terlebih dahulu.

## Data dan operasional

Konfigurasi `.env`, kata sandi, dokumen warga, database, sesi, log, cache, dan hasil pengujian tidak dimasukkan ke repositori. Backup disimpan terpisah dari source code. Atur penjadwalan sesuai [PANDUAN_INSTALL.txt](PANDUAN_INSTALL.txt) untuk pengingat dan pencadangan berkala.

## Lisensi

[MIT](LICENSE).
