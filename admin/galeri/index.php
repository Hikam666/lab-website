<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) {
    requireLogin();
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($conn)) {
    $conn = getDBConnection();
}

$active_page = 'galeri';
$page_title  = 'Galeri Album';


// --- QUERY DATA ALBUM + JUMLAH FOTO + COVER ---
$query = "
    SELECT g.*,
           (SELECT COUNT(*) FROM galeri_item WHERE id_album = g.id_album) AS jumlah_foto,
           m.lokasi_file AS cover_path
    FROM galeri_album g
    LEFT JOIN media m ON g.id_cover = m.id_media
    ORDER BY g.dibuat_pada DESC
";

$result = pg_query($conn, $query);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Galeri</h1>
            <p class="text-muted">Kelola Album Foto</p>
        </div>
        <a href="tambah.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Tambah Album
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?php
                echo $_SESSION['message'];
                unset($_SESSION['message'], $_SESSION['msg_type']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if ($result && pg_num_rows($result) > 0): ?>
            <?php while ($row = pg_fetch_assoc($result)): ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0 galeri-admin-album-card">
                        <div class="galeri-admin-album-cover position-relative">
                            <?php
                                $cover = !empty($row['cover_path'])
                                    ? '../../uploads/' . $row['cover_path']
                                    : '';
                            ?>
                            <?php if ($cover && file_exists(__DIR__ . '/../../uploads/' . $row['cover_path'])): ?>
                                <img src="<?php echo $cover; ?>"
                                     alt="<?php echo htmlspecialchars($row['judul']); ?>"
                                     class="galeri-admin-album-img">
                            <?php else: ?>
                                <div class="galeri-admin-album-placeholder">
                                    <i class="fas fa-images fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>

                            <span class="badge bg-dark galeri-admin-album-count">
                                <?php echo (int)$row['jumlah_foto']; ?> Foto
                            </span>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title fw-bold galeri-admin-album-title">
                                <?php echo htmlspecialchars($row['judul']); ?>
                            </h5>
                            <p class="card-text text-muted small galeri-admin-album-desc">
                                <?php echo htmlspecialchars(substr($row['deskripsi'] ?? '', 0, 100)); ?>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d M Y', strtotime($row['dibuat_pada'])); ?>
                            </small>
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-between">
                            <a href="foto.php?id=<?php echo $row['id_album']; ?>"
                               class="btn btn-sm btn-outline-primary flex-grow-1 me-1">
                                <i class="fas fa-folder-open"></i> Buka Album
                            </a>
                            <a href="edit.php?id=<?php echo $row['id_album']; ?>"
                               class="btn btn-sm btn-outline-secondary me-1">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="hapus.php?id=<?php echo $row['id_album']; ?>" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Hapus
                            </a>

                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-light border">Belum ada album.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
