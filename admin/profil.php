<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$active_page = 'profil';
$page_title  = 'Profil Saya';
$extra_css   = ['dashboard.css'];

$conn = getDBConnection();

// ======================================================================
// Ambil user yang sedang login dari session (auth.php)
// ======================================================================
$current_user = getCurrentUser();
$user_detail  = null;

if ($conn && $current_user && !empty($current_user['id'])) {
    $sql = "SELECT 
                id_pengguna,
                nama_lengkap,
                email,
                peran,
                aktif,
                dibuat_pada,
                diperbarui_pada
            FROM pengguna
            WHERE id_pengguna = $1
            LIMIT 1";

    $res = pg_query_params($conn, $sql, [$current_user['id']]);

    if ($res && pg_num_rows($res) > 0) {
        $user_detail = pg_fetch_assoc($res);
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <h1>Profil Saya</h1>
    <p>Informasi akun dan pengaturan keamanan untuk pengguna yang sedang login</p>
</div>

<div class="row justify-content-center slide-up">
    <div class="col-lg-6 col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-person-circle me-2 text-primary"></i> Informasi Akun
                </h5>

                <?php if ($user_detail): ?>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Nama Lengkap</div>
                        <div class="fw-semibold">
                            <?php echo htmlspecialchars($user_detail['nama_lengkap']); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small mb-1">Email</div>
                        <div>
                            <a href="mailto:<?php echo htmlspecialchars($user_detail['email']); ?>">
                                <?php echo htmlspecialchars($user_detail['email']); ?>
                            </a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small mb-1">Peran</div>
                        <span class="badge bg-primary">
                            <?php echo strtoupper(htmlspecialchars($user_detail['peran'])); ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small mb-1">Status Akun</div>
                        <?php
                        $is_active = (
                            $user_detail['aktif'] === 't' ||
                            $user_detail['aktif'] === true ||
                            $user_detail['aktif'] == 1
                        );
                        ?>
                        <?php if ($is_active): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non-aktif</span>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <div class="mb-2 small text-muted">
                        Dibuat:
                        <?php echo !empty($user_detail['dibuat_pada'])
                            ? formatDateTime($user_detail['dibuat_pada'])
                            : '-'; ?>
                    </div>
                    <div class="small text-muted mb-3">
                        Terakhir diperbarui:
                        <?php echo !empty($user_detail['diperbarui_pada'])
                            ? formatDateTime($user_detail['diperbarui_pada'])
                            : '-'; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Data profil tidak dapat dimuat dari database.<br>
                        <small class="text-muted">
                            Pastikan user sudah login dan tabel <code>pengguna</code> berisi data dengan
                            <code>id_pengguna = <?php echo htmlspecialchars($current_user['id'] ?? ''); ?></code>.
                        </small>
                    </div>
                <?php endif; ?>

                <hr>

                <h6 class="fw-bold mb-2">
                    <i class="bi bi-shield-lock me-2 text-primary"></i> Keamanan
                </h6>
                <p class="text-muted small mb-3">
                    Demi keamanan akun, disarankan untuk mengganti password secara berkala dengan
                    kombinasi huruf, angka, dan simbol.
                </p>

                <a href="<?php echo getAdminUrl('resetpassword.php'); ?>" class="btn btn-primary w-100">
                    <i class="bi bi-key me-2"></i> Ganti Password
                </a>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
