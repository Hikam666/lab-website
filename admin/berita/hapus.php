<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$conn = getDBConnection();
$id_anggota = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_anggota <= 0) {
    setFlashMessage('ID anggota tidak valid', 'error');
    header('Location: ' . getAdminUrl('berita/index.php'));
    exit;
}

$sql = "SELECT a.*, m.lokasi_file as foto FROM berita a LEFT JOIN media m ON a.id_cover = m.id_media WHERE a.id_berita = $1";
$result = pg_query_params($conn, $sql, [$id_anggota]);

if (!$result || pg_num_rows($result) === 0) {
    setFlashMessage('Anggota tidak ditemukan', 'error');
    header('Location: ' . getAdminUrl('anggota/index.php'));
    exit;
}

$anggota = pg_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    pg_query($conn, "BEGIN");
    try {
        $delete_sql = "DELETE FROM berita WHERE id_berita = $1";
        $delete_result = pg_query_params($conn, $delete_sql, [$id_anggota]);
        
        if ($delete_result) {
            if ($anggota['foto']) {
                $foto_path = __DIR__ . '/../../uploads/' . $anggota['foto'];
                if (file_exists($foto_path)) @unlink($foto_path);
            }
            pg_query($conn, "COMMIT");
            setFlashMessage('Anggota berhasil dihapus', 'success');
            header('Location: ' . getAdminUrl('berita/index.php'));
            exit;
        }
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage('Gagal menghapus: ' . $e->getMessage(), 'error');
    }
}

$active_page = 'anggota';
$page_title = 'Hapus Anggota';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-trash me-2"></i>Hapus Anggota</h1>
        </div>
        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
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
                    Anda akan menghapus anggota berikut. Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                </div>
                
                <?php if ($anggota['foto']): ?>
                <div class="text-center mb-4">
                    <img src="<?php echo SITE_URL.'/uploads/'.$anggota['foto']; ?>" 
                         class="img-fluid rounded anggota-delete-foto-preview">
                </div>
                <?php endif; ?>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_delete" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Yakin ingin menghapus?');">
                            <i class="bi bi-trash me-2"></i>Ya, Hapus Anggota
                        </button>
                        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary btn-lg"><i class="bi bi-x-circle me-2"></i>Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>