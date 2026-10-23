<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lock = $root.'/storage/app/installed.lock';

if (is_file($lock)) {
    http_response_code(403);
    exit('SIRUKUN sudah terpasang. Buka halaman masuk.');
}
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Pendaftaran Administrator awal hanya dapat dilakukan dari komputer server.');
}

ini_set('session.use_strict_mode', '1');
session_name('sirukun_install');
session_save_path($root.'/storage/framework/sessions');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'dom', 'xml', 'xmlwriter', 'zip'];
$missingExtensions = array_values(array_filter($requiredExtensions, fn ($extension) => !extension_loaded($extension)));
$gdAvailable = extension_loaded('gd');
$folders = [
    'storage' => is_writable($root.'/storage'),
    'storage/app' => is_writable($root.'/storage/app'),
    'storage/framework/sessions' => is_writable($root.'/storage/framework/sessions'),
    'bootstrap/cache' => is_writable($root.'/bootstrap/cache'),
    'root' => is_writable($root),
];
$foldersReady = !in_array(false, $folders, true);
$phpReady = PHP_VERSION_ID >= 80200 && $missingExtensions === [];
$vendorReady = is_file($root.'/vendor/autoload.php');
$envReady = is_file($root.'/.env');
$databaseReady = false;
$databaseStatus = 'Konfigurasi database belum dapat diperiksa.';
$userCount = 0;
$adminRoleId = null;

if ($phpReady && $vendorReady && $envReady && $foldersReady) {
    try {
        require_once $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        if (config('app.key') === null || config('app.key') === '') {
            throw new RuntimeException('APP_KEY belum diatur. Jalankan php artisan key:generate.');
        }
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sirukun_apps') {
            throw new RuntimeException('Atur DB_CONNECTION=mysql dan DB_DATABASE=sirukun_apps di .env.');
        }

        $requiredTables = ['rw', 'rt', 'peran', 'menu', 'pengguna', 'jenis_surat', 'hak_akses_menu'];
        $missingTables = array_values(array_filter(
            $requiredTables,
            fn ($table) => !Illuminate\Support\Facades\Schema::hasTable($table)
        ));
        if ($missingTables !== []) {
            throw new RuntimeException('Tabel aplikasi belum siap. Jalankan php artisan migrate --seed. Tabel yang belum tersedia: '.implode(', ', $missingTables).'.');
        }

        $adminRoleId = Illuminate\Support\Facades\DB::table('peran')->where('kode', 'ADMINISTRATOR')->value('id');
        if (!$adminRoleId) {
            throw new RuntimeException('Data peran belum tersedia. Jalankan php artisan db:seed.');
        }

        $userCount = Illuminate\Support\Facades\DB::table('pengguna')->whereNull('deleted_at')->count();
        $databaseReady = true;
        $databaseStatus = $userCount === 0
            ? 'Terhubung ke sirukun_apps; tabel dan data dasar siap.'
            : 'Database sudah berisi akun. Pendaftaran Administrator awal ditutup untuk mencegah pengambilalihan akun.';

        $existingAdmin = Illuminate\Support\Facades\DB::table('pengguna')
            ->where('peran_id', $adminRoleId)
            ->where('aktif', true)
            ->whereNull('deleted_at')
            ->exists();
        if ($existingAdmin) {
            $handle = @fopen($lock, 'x');
            if ($handle !== false) {
                fwrite($handle, 'Terpasang '.date(DATE_ATOM));
                fclose($handle);
            }
            if (is_file($lock)) {
                header('Location: index.php');
                exit;
            }
            throw new RuntimeException('Administrator sudah ada, tetapi file pengunci tidak dapat dibuat. Periksa hak tulis storage/app.');
        }
    } catch (Throwable $exception) {
        $databaseStatus = 'Belum siap: '.$exception->getMessage();
    }
}

$errors = [];
$success = false;
$ready = $phpReady && $vendorReady && $envReady && $foldersReady && $databaseReady && $userCount === 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$ready) {
        $errors[] = 'Persyaratan belum terpenuhi. Ikuti petunjuk persiapan database di bawah.';
    }
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman.';
    }

    $name = trim((string) ($_POST['admin_name'] ?? ''));
    $email = trim((string) ($_POST['admin_email'] ?? ''));
    $password = (string) ($_POST['admin_password'] ?? '');
    $rwCode = trim((string) ($_POST['rw_code'] ?? ''));
    $rwName = trim((string) ($_POST['rw_name'] ?? ''));
    $kelurahan = trim((string) ($_POST['kelurahan'] ?? ''));
    $rtNumber = trim((string) ($_POST['rt_number'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
        $errors[] = 'Nama dan email Administrator harus valid.';
    }
    if (mb_strlen($password) < 6 || mb_strlen($password) > 100 || $password !== (string) ($_POST['admin_confirm'] ?? '')) {
        $errors[] = 'Kata sandi minimal 6 karakter dan konfirmasi harus sama.';
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{1,10}$/', $rwCode) || $rwName === '' || mb_strlen($rwName) > 100 || $kelurahan === '' || mb_strlen($kelurahan) > 100 || !preg_match('/^[a-zA-Z0-9_-]{1,10}$/', $rtNumber)) {
        $errors[] = 'Lengkapi kode dan nama RW, kelurahan, serta nomor RT awal dengan format yang valid.';
    }

    if ($errors === []) {
        $guard = @fopen($root.'/storage/app/install.guard', 'c');
        if ($guard === false || !flock($guard, LOCK_EX | LOCK_NB)) {
            $errors[] = 'Pendaftaran lain sedang berjalan. Muat ulang halaman beberapa saat lagi.';
            if (is_resource($guard)) {
                fclose($guard);
            }
        } else {
            $lockCreated = false;
            try {
                Illuminate\Support\Facades\DB::transaction(function () use ($name, $email, $password, $rwCode, $rwName, $kelurahan, $rtNumber, $adminRoleId, $lock, &$lockCreated): void {
                    if (Illuminate\Support\Facades\DB::table('pengguna')->whereNull('deleted_at')->exists()) {
                        throw new RuntimeException('Akun sudah dibuat. Pendaftaran Administrator awal hanya dapat digunakan satu kali.');
                    }

                    $now = now();
                    $rwId = Illuminate\Support\Facades\DB::table('rw')->insertGetId([
                        'kode' => $rwCode,
                        'nama' => $rwName,
                        'kelurahan' => $kelurahan,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    Illuminate\Support\Facades\DB::table('rt')->insert([
                        'rw_id' => $rwId,
                        'nomor' => $rtNumber,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    Illuminate\Support\Facades\DB::table('pengguna')->insert([
                        'peran_id' => $adminRoleId,
                        'nama' => $name,
                        'email' => $email,
                        'password_hash' => Illuminate\Support\Facades\Hash::make($password),
                        'aktif' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $handle = @fopen($lock, 'x');
                    if ($handle === false) {
                        throw new RuntimeException('File pengunci installer tidak dapat dibuat.');
                    }
                    $lockCreated = true;
                    try {
                        if (fwrite($handle, 'Terpasang '.date(DATE_ATOM)) === false) {
                            throw new RuntimeException('File pengunci installer tidak dapat ditulis.');
                        }
                    } finally {
                        fclose($handle);
                    }
                });
                $success = true;
                session_regenerate_id(true);
                $_SESSION = [];
            } catch (Throwable $exception) {
                if ($lockCreated && is_file($lock)) {
                    unlink($lock);
                }
                $errors[] = 'Pendaftaran belum selesai: '.$exception->getMessage();
            } finally {
                flock($guard, LOCK_UN);
                fclose($guard);
            }
        }
    }
}

$appUrl = 'http://127.0.0.1:8000';
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pendaftaran Administrator SIRUKUN</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body style="background:#f3f5ee">
<main style="max-width:860px;margin:35px auto;padding:0 18px">
    <section class="card">
        <div class="eyebrow">SISTEM INFORMASI RT/RW TERPADU</div>
        <h1 style="font-size:30px">Pendaftaran Administrator</h1>
        <p class="muted">Database dibuat dan disiapkan terpisah. Formulir ini membuat akun Administrator dan identitas RW/RT awal.</p>

        <div class="infobox">
            <div>
                <strong>Pemeriksaan server</strong><br>
                PHP <?= $escape(PHP_VERSION) ?> <?= PHP_VERSION_ID >= 80200 ? '✓' : '— minimal 8.2' ?><br>
                Ekstensi wajib: <?= $missingExtensions === [] ? 'lengkap ✓' : 'belum aktif: '.$escape(implode(', ', $missingExtensions)) ?><br>
                Ekstensi GD: <?= $gdAvailable ? 'aktif ✓' : 'belum aktif — fitur ekspor spreadsheet/gambar dapat memerlukan GD' ?><br>
                Dependensi vendor: <?= $vendorReady ? 'tersedia ✓' : 'tidak ditemukan' ?><br>
                File .env: <?= $envReady ? 'tersedia ✓' : 'belum dibuat' ?><br>
                Hak tulis folder: <?php foreach ($folders as $folder => $writable): ?><?= $escape($folder) ?> <?= $writable ? '✓' : 'tidak bisa ditulis' ?><?= $folder !== array_key_last($folders) ? ' · ' : '' ?><?php endforeach ?><br>
                Database: <?= $escape($databaseStatus) ?>
            </div>
        </div>

        <?php if (!$databaseReady): ?>
            <div class="notice">
                Buat database kosong <code>sirukun_apps</code> di MySQL, salin <code>.env.example</code> menjadi <code>.env</code>, lalu atur DB_USERNAME dan DB_PASSWORD. Dari terminal pada folder proyek, jalankan <code>php artisan optimize:clear</code>, <code>php artisan key:generate</code>, lalu <code>php artisan migrate --seed</code>. Setelah selesai, muat ulang halaman ini.
            </div>
        <?php endif ?>

        <?php if ($success): ?>
            <div class="successbox"><strong>Pendaftaran berhasil.</strong> Akun Administrator pertama sudah dibuat dan pendaftaran awal dikunci.</div>
            <a class="btn" href="index.php">Masuk ke SIRUKUN →</a>
            <p class="notice">Setelah masuk, buat data RW dan RT melalui menu Wilayah RT / RW sebelum memasukkan data warga.</p>
        <?php else: ?>
            <?php foreach ($errors as $error): ?>
                <div class="errorbox" role="alert"><?= $escape($error) ?></div>
            <?php endforeach ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf']) ?>">
                <h3>Identitas lingkungan</h3>
                <div class="input-group">
                    <div class="field">
                        <label for="rw_code">Kode RW *</label>
                        <input id="rw_code" name="rw_code" value="<?= $escape($_POST['rw_code'] ?? '03') ?>" required maxlength="10">
                    </div>
                    <div class="field">
                        <label for="rw_name">Nama lingkungan / RW *</label>
                        <input id="rw_name" name="rw_name" value="<?= $escape($_POST['rw_name'] ?? '') ?>" required maxlength="100">
                    </div>
                    <div class="field">
                        <label for="kelurahan">Kelurahan *</label>
                        <input id="kelurahan" name="kelurahan" value="<?= $escape($_POST['kelurahan'] ?? '') ?>" required maxlength="100">
                    </div>
                    <div class="field">
                        <label for="rt_number">Nomor RT awal *</label>
                        <input id="rt_number" name="rt_number" value="<?= $escape($_POST['rt_number'] ?? '003') ?>" required maxlength="10">
                    </div>
                </div>
                <h3>Akun Administrator pertama</h3>
                <div class="input-group">
                    <div class="field">
                        <label for="admin_name">Nama lengkap *</label>
                        <input id="admin_name" name="admin_name" value="<?= $escape($_POST['admin_name'] ?? '') ?>" required maxlength="100">
                    </div>
                    <div class="field">
                        <label for="admin_email">Email Administrator *</label>
                        <input type="email" id="admin_email" name="admin_email" value="<?= $escape($_POST['admin_email'] ?? '') ?>" required maxlength="191">
                    </div>
                    <div class="field">
                        <label for="admin_password">Kata sandi (minimal 6 karakter) *</label>
                        <input id="admin_password" name="admin_password" type="password" minlength="6" maxlength="100" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="admin_confirm">Ulangi kata sandi *</label>
                        <input id="admin_confirm" name="admin_confirm" type="password" minlength="6" maxlength="100" required autocomplete="new-password">
                    </div>
                </div>
                <p class="hint">Boleh menggunakan huruf besar/kecil, angka, simbol, maupun spasi. Tidak wajib menggabungkan jenis karakter tertentu; kata sandi membedakan huruf besar dan kecil.</p>
                <button class="btn" <?= !$ready ? 'disabled' : '' ?>>Buat akun Administrator →</button>
                <p class="hint">Pendaftaran awal hanya terbuka dari komputer server dan otomatis dikunci setelah akun Administrator serta RW/RT awal berhasil dibuat.</p>
            </form>
        <?php endif ?>
    </section>
    <p class="loginfoot">SIRUKUN · Laravel 12 · MySQL</p>
</main>
</body>
</html>
