<?php
/**
 * RESETPASSWORD.PHP
 * =================
 * Halaman untuk mengganti password pengguna yang sedang login.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$active_page = 'profil';
$page_title  = 'Ganti Password';
$extra_css   = ['dashboard.css'];

$conn         = getDBConnection();
$current_user = getCurrentUser();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===============================
    // 1. Cek CSRF token
    // ===============================
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf_token)) {
        $errors[] = 'Token formulir tidak valid. Silakan coba lagi.';
    } else {

        // ===============================
        // 2. Ambil input
        // ===============================
        $password_lama      = $_POST['password_lama'] ?? '';
        $password_baru      = $_POST['password_baru'] ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

        // ===============================
        // 3. Validasi dasar
        // ===============================
        if (empty($password_lama) || empty($password_baru) || empty($password_konfirmasi)) {
            $errors[] = 'Semua kolom wajib diisi.';
        }

        if (strlen($password_baru) < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        }

        if ($password_baru !== $password_konfirmasi) {
            $errors[] = 'Konfirmasi password baru tidak sama.';
        }

        if (empty($errors) && $conn && $current_user && !empty($current_user['id'])) {
            // ===============================
            // 4. Ambil password saat ini dari DB
            // ===============================
            $sql = "SELECT id_pengguna, password_hash 
                    FROM pengguna 
                    WHERE id_pengguna = $1 AND aktif = TRUE
                    LIMIT 1";

            $res = pg_query_params($conn, $sql, [$current_user['id']]);

            if (!$res || pg_num_rows($res) === 0) {
                $errors[] = 'Data pengguna tidak ditemukan atau akun tidak aktif.';
            } else {
                $row = pg_fetch_assoc($res);

                // ===============================
                // 5. Verifikasi password lama
                // ===============================
                if (!password_verify($password_lama, $row['password_hash'])) {
                    $errors[] = 'Password lama yang Anda masukkan tidak sesuai.';
                } else {
                    // ===============================
                    // 6. Update password baru
                    // ===============================
                    $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);

                    $sql_update = "UPDATE pengguna
                                   SET password_hash = $1,
                                       diperbarui_pada = NOW()
                                   WHERE id_pengguna = $2";

                    $update_res = pg_query_params($conn, $sql_update, [
                        $hash_baru,
                        $current_user['id']
                    ]);

                    if ($update_res) {
                        // Catat di log_aktivitas (kalau ingin)
                        if (function_exists('log_aktivitas')) {
                            log_aktivitas(
                                $conn,
                                'update',
                                'pengguna',
                                $current_user['id'],
                                'User mengganti password akunnya sendiri'
                            );
                        }

                        $success = 'Password berhasil diperbarui.';
                        setFlashMessage($success, 'success');
                        // Redirect ke profil atau tetap di halaman ini
                        redirectAdmin('profil.php');
                        exit;
                    } else {
                        $errors[] = 'Terjadi kesalahan saat menyimpan password baru.';
                    }
                }
            }
        } elseif (!$conn) {
            $errors[] = 'Koneksi database gagal.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <h1>Ganti Password</h1>
    <p>Ubah password akun Anda untuk menjaga keamanan akses ke CMS.</p>
</div>

<div class="row justify-content-center slide-up">
    <div class="col-lg-5 col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">

                <h5 class="fw-bold mb-3">
                    <i class="bi bi-key me-2 text-primary"></i> Form Ganti Password
                </h5>

                <!-- Tampilkan error -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Tampilkan success lokal (jarang muncul karena langsung redirect) -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <?php echo csrfTokenInput(); ?>

                    <div class="mb-3">
                        <label for="password_lama" class="form-label">Password Lama</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_lama" 
                            name="password_lama" 
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password_baru" class="form-label">Password Baru</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_baru" 
                            name="password_baru" 
                            required
                            minlength="8"
                        >
                        <div class="form-text">
                            Minimal 8 karakter, disarankan kombinasi huruf besar, kecil, angka, dan simbol.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password_konfirmasi" class="form-label">Konfirmasi Password Baru</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_konfirmasi" 
                            name="password_konfirmasi" 
                            required
                            minlength="8"
                        >
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i> Simpan Password Baru
                        </button>
                        <a href="<?php echo getAdminUrl('profil.php'); ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i> Kembali ke Profil
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
