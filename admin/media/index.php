<?php
session_start();
// Pastikan path ini benar
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); } 
elseif (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

if (!isset($conn)) $conn = getDBConnection();

$active_page = 'media';
$page_title = 'Media Library';


// --- HAPUS ALBUM ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Karena ON DELETE CASCADE sudah ada di database, kita cukup hapus albumnya
    // Item galeri akan terhapus otomatis oleh PostgreSQL
    $res = pg_query_params($conn, "DELETE FROM galeri_album WHERE id_album = $1", [$id]);
    
    if ($res) {
        $_SESSION['message'] = "Album berhasil dihapus!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menghapus: " . pg_last_error($conn);
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: index.php");
    exit();
}

// --- QUERY DATA ---
// Mengambil album + menghitung item + mengambil URL foto cover dari tabel media
$query = "SELECT g.*, 
          (SELECT COUNT(*) FROM galeri_item WHERE id_album = g.id_album) as jumlah_foto,
          m.lokasi_file as cover_path
          FROM galeri_album g
          LEFT JOIN media m ON g.id_cover = m.id_media
          ORDER BY g.dibuat_pada DESC";

$result = pg_query($conn, $query);

include __DIR__ . '/../includes/header.php'; 
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Media</h1>
            <p class="text-muted">Kelola file gambar dan dokumen</p>
        </div>
        <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Album</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['msg_type']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if ($result && pg_num_rows($result) > 0): ?>
            <?php while ($row = pg_fetch_assoc($result)): ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="position-relative" style="height: 200px; overflow: hidden; bg-light">
                            <?php 
                                // Sesuaikan path uploads dengan struktur folder Anda
                                $cover = !empty($row['cover_path']) ? '../../uploads/' . $row['cover_path'] : ''; 
                            ?>
                            <?php if ($cover && file_exists(__DIR__ . '/../../uploads/' . $row['cover_path'])): ?>
                                <img src="<?php echo $cover; ?>" class="w-100 h-100" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 bg-secondary text-white">
                                    <i class="fas fa-images fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <span class="badge bg-dark position-absolute bottom-0 end-0 m-2">
                                <?php echo $row['jumlah_foto']; ?> Foto
                            </span>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($row['judul']); ?></h5>
                            <p class="card-text text-muted small">
                                <?php echo htmlspecialchars(substr($row['deskripsi'], 0, 100)); ?>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($row['dibuat_pada'])); ?>
                            </small>
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-between">
                            <a href="foto.php?id=<?php echo $row['id_album']; ?>" class="btn btn-sm btn-outline-primary flex-grow-1 me-1">
                                <i class="fas fa-folder-open"></i> Buka Album
                            </a>
                            <a href="index.php?delete=<?php echo $row['id_album']; ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Yakin hapus album ini?');">
                                <i class="fas fa-trash"></i>
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