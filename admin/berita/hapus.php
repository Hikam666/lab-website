<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$conn = getDBConnection();
$id_berita = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_berita <= 0) {
    setFlashMessage('ID berita tidak valid', 'error');
    header('Location: ' . getAdminUrl('berita/index.php'));
    exit;
}

// Ambil data berita
$sql = "SELECT b.*, m.lokasi_file as foto 
        FROM berita b 
        LEFT JOIN media m ON b.id_cover = m.id_media 
        WHERE b.id_berita = $1";
$result = pg_query_params($conn, $sql, [$id_berita]);

if (!$result || pg_num_rows($result) === 0) {
    setFlashMessage('Berita tidak ditemukan', 'error');
    header('Location: ' . getAdminUrl('berita/index.php'));
    exit;
}

$berita = pg_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    pg_query($conn, "BEGIN");
    try {
        $delete_sql = "DELETE FROM berita WHERE id_berita = $1";
        $delete_result = pg_query_params($conn, $delete_sql, [$id_berita]);
        
        if ($delete_result) {

            if ($berita['foto']) {
                $foto_path = __DIR__ . '/../../uploads/' . $berita['foto'];
                if (file_exists($foto_path)) {
                    @unlink($foto_path);
                }
            }

            // log aktivitas
            log_aktivitas(
                $conn,
                'delete',
                'berita',
                $id_berita,
                'Menghapus berita: ' . ($berita['judul'] ?? 'tanpa judul')
            );

            pg_query($conn, "COMMIT");
            setFlashMessage('Berita berhasil dihapus', 'success');
            header('Location: ' . getAdminUrl('berita/index.php'));
            exit;
        } else {
            throw new Exception('Gagal menghapus dari database.');
        }
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage('Gagal menghapus: ' . $e->getMessage(), 'error');
        header('Location: ' . getAdminUrl('berita/index.php'));
        exit;
    }
}

$active_page = 'berita';
$page_title  = 'Hapus Berita';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-trash me-2"></i>Hapus Berita</h1>
        </div>
        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Peringatan!</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    Anda akan menghapus berita berikut.<strong>Tindakan ini tidak dapat dibatalkan</strong>.
                </div>
                
                <h5 class="mb-3"><?php echo htmlspecialchars($berita['judul']); ?></h5>

                <?php if ($berita['foto']): ?>
                <div class="text-center mb-4">
                    <img src="<?php echo SITE_URL.'/uploads/'.$berita['foto']; ?>" 
                         class="img-fluid rounded anggota-delete-foto-preview">
                </div>
                <?php endif; ?>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_delete" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Yakin ingin menghapus berita ini?');">
                            <i class="bi bi-trash me-2"></i>Ya, Hapus Berita
                        </button>
                        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle me-2"></i>Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
