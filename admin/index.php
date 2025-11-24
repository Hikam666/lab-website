<?php
/**
 * INDEX.PHP
 * =========
 * Dashboard utama admin panel
 */

// 1. Load dependencies
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// 2. Cek login
requireLogin();

// 3. Page Settings
$active_page = 'dashboard';
$page_title = 'Dashboard Overview';

// PENTING: Panggil dashboard.css di sini
$extra_css = ['dashboard.css']; 

// 4. Buka Koneksi Database Utama
$conn = getDBConnection();

// Initialize stats
$stats = [
    'anggota' => 0,
    'fasilitas' => 0,
    'publikasi' => 0,
    'berita' => 0,
    'pesan_baru' => 0,
    'pending_approval' => 0
];

// 5. Query Statistik (Jika koneksi berhasil)
if ($conn) {
    // Helper function untuk count
    function getCount($conn, $table, $condition = "TRUE") {
        $query = "SELECT COUNT(*) as total FROM $table WHERE $condition";
        $result = @pg_query($conn, $query);
        return ($result) ? pg_fetch_assoc($result)['total'] : 0;
    }

    $stats['anggota']   = getCount($conn, "anggota_lab", "aktif = TRUE");
    $stats['fasilitas'] = getCount($conn, "fasilitas", "status = 'disetujui'");
    $stats['publikasi'] = getCount($conn, "publikasi", "status = 'disetujui'");
    $stats['berita']    = getCount($conn, "berita", "status = 'disetujui'");
    $stats['pesan_baru']= getCount($conn, "pesan_kontak", "status = 'baru'");
    
    // Khusus Admin: Hitung Pending Approval
    if (isAdmin()) {
        $sql_pending = "SELECT 
            (SELECT COUNT(*) FROM berita WHERE status = 'diajukan') +
            (SELECT COUNT(*) FROM galeri_album WHERE status = 'diajukan') +
            (SELECT COUNT(*) FROM publikasi WHERE status = 'diajukan') +
            (SELECT COUNT(*) FROM fasilitas WHERE status = 'diajukan') as total";
        $res_pending = @pg_query($conn, $sql_pending);
        if ($res_pending) {
            $stats['pending_approval'] = pg_fetch_assoc($res_pending)['total'];
        }
    }
}

// 6. Include Header (Tampilan dimulai)
include __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <h1>Dashboard</h1>
    <p>Ringkasan aktivitas Laboratorium Teknologi Data</p>
</div>

<div class="row g-4 mb-4 slide-up">
    
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Anggota</div>
                        <h2 class="mb-0 fw-bold"><?php echo $stats['anggota']; ?></h2>
                    </div>
                    <div class="dashboard-stat-icon text-primary bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Fasilitas</div>
                        <h2 class="mb-0 fw-bold"><?php echo $stats['fasilitas']; ?></h2>
                    </div>
                    <div class="dashboard-stat-icon text-success bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-pc-display"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Publikasi</div>
                        <h2 class="mb-0 fw-bold"><?php echo $stats['publikasi']; ?></h2>
                    </div>
                    <div class="dashboard-stat-icon text-info bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-journal-text"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Berita</div>
                        <h2 class="mb-0 fw-bold"><?php echo $stats['berita']; ?></h2>
                    </div>
                    <div class="dashboard-stat-icon text-warning bg-warning bg-opacity-10 p-3 rounded">
                        <i class="bi bi-newspaper"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<div class="row g-4 slide-up" style="animation-delay: 0.1s;">
    
    <div class="col-lg-6">
        <div class="card dashboard-info-card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-envelope me-2 text-primary"></i> Pesan Masuk
                </h5>
                <?php if ($stats['pesan_baru'] > 0): ?>
                <span class="badge bg-danger rounded-pill"><?php echo $stats['pesan_baru']; ?> Baru</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($stats['pesan_baru'] > 0): ?>
                    <div class="alert alert-light border-start border-4 border-danger">
                        <h6 class="alert-heading fw-bold">Perhatian Diperlukan</h6>
                        <p class="mb-0">Anda memiliki <?php echo $stats['pesan_baru']; ?> pesan yang belum dibaca dari pengunjung website.</p>
                    </div>
                    <div class="mt-3">
                        <a href="<?php echo getAdminUrl('pesan/index.php'); ?>" class="btn btn-primary">
                            <i class="bi bi-envelope-open me-2"></i> Buka Kotak Masuk
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check2-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Tidak ada pesan baru. Semua bersih!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (isAdmin()): ?>
    <div class="col-lg-6">
        <div class="card dashboard-info-card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-list-check me-2 text-warning"></i> Menunggu Persetujuan
                </h5>
            </div>
            <div class="card-body">
                <?php if ($stats['pending_approval'] > 0): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="display-4 fw-bold text-warning me-3"><?php echo $stats['pending_approval']; ?></div>
                        <div class="text-muted">Item konten menunggu review dan persetujuan Anda untuk dipublikasikan.</div>
                    </div>
                    <a href="<?php echo getAdminUrl('persetujuan/index.php'); ?>" class="btn btn-warning text-dark w-100">
                        Review Konten
                    </a>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-shield-check text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Semua konten sudah direview.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-lg-6">
        <div class="card dashboard-info-card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-lightning-charge me-2 text-primary"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo getAdminUrl('berita/tambah.php'); ?>" class="btn btn-outline-primary text-start p-3 dashboard-quick-action">
                        <i class="bi bi-plus-lg me-2"></i> Buat Berita Baru
                    </a>
                    <a href="<?php echo getAdminUrl('galeri/tambah.php'); ?>" class="btn btn-outline-primary text-start p-3 dashboard-quick-action">
                        <i class="bi bi-image me-2"></i> Upload Galeri
                    </a>
                    <a href="<?php echo getAdminUrl('publikasi/tambah.php'); ?>" class="btn btn-outline-primary text-start p-3 dashboard-quick-action">
                        <i class="bi bi-journal-plus me-2"></i> Input Publikasi
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// 7. Include Footer
include __DIR__ . '/includes/footer.php';

?>