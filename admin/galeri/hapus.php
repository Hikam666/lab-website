<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
if (!isset($conn)) $conn = getDBConnection();

$currentUser = getCurrentUser();
$user_id     = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

// ambil id album
if (!isset($_GET['id'])) {
    header('Location: ' . getAdminUrl('galeri/index.php'));
    exit;
}

$id_album = (int)$_GET['id'];

// ambil data album (sebelum dihapus)
$sql_album = "SELECT id_album, judul FROM galeri_album WHERE id_album = $1";
$res_album = pg_query_params($conn, $sql_album, [$id_album]);
if (!$res_album || pg_num_rows($res_album) === 0) {
    setFlashMessage('Album tidak ditemukan.', 'error');
    header('Location: ' . getAdminUrl('galeri/index.php'));
    exit;
}
$album = pg_fetch_assoc($res_album);

// jika operator: tidak boleh hapus, hanya ajukan
if (isOperator()) {
    $ket = 'Operator mengajukan penghapusan album galeri: "' . $album['judul'] . '" (ID=' . $id_album . ')';
    log_aktivitas($conn, 'REQUEST_DELETE', 'galeri_album', $id_album, $ket);

    setFlashMessage('Penghapusan album telah diajukan ke admin.', 'warning');
    header('Location: ' . getAdminUrl('galeri/index.php'));
    exit;
}

// jika admin dan submit konfirmasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    pg_query($conn, "BEGIN");
    try {
        // hapus album (galeri_item akan terhapus via ON DELETE CASCADE)
        $del = pg_query_params($conn, "DELETE FROM galeri_album WHERE id_album = $1", [$id_album]);
        if (!$del) {
            throw new Exception(pg_last_error($conn));
        }

        // LOG
        $ket = 'Menghapus album galeri: "' . $album['judul'] . '"';
        log_aktivitas($conn, 'DELETE', 'galeri_album', $id_album, $ket);

        pg_query($conn, "COMMIT");
        setFlashMessage('Album berhasil dihapus.', 'success');
        header('Location: ' . getAdminUrl('galeri/index.php'));
        exit;

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage('Gagal menghapus album: ' . $e->getMessage(), 'error');
    }
}

$active_page = 'galeri';
$page_title  = 'Hapus Album';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-trash me-2"></i>Hapus Album</h1>
            <p class="text-muted mb-0">Konfirmasi penghapusan album galeri</p>
        </div>
        <a href="<?php echo getAdminUrl('galeri/index.php'); ?>" class="btn btn-secondary">
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
                <p>Anda akan menghapus album berikut. Tindakan ini <strong>tidak dapat dibatalkan</strong>.</p>
                <table class="table">
                    <tr><th width="150">Judul Album:</th><td><strong><?php echo htmlspecialchars($album['judul']); ?></strong></td></tr>
                    <tr><th>ID Album:</th><td><?php echo $album['id_album']; ?></td></tr>
                </table>

                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_delete" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Yakin ingin menghapus album ini?');">
                            <i class="bi bi-trash me-2"></i>Ya, Hapus Album
                        </button>
                        <a href="<?php echo getAdminUrl('galeri/index.php'); ?>" class="btn btn-secondary btn-lg">
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

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-trash me-2"></i>Hapus Album Galeri</h1>
        </div>
        <a href="<?php echo getAdminUrl('galeri/index.php'); ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>Peringatan!
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    Anda akan menghapus album berikut. Tindakan ini 
                    <strong>tidak dapat dibatalkan</strong>.
                    Semua foto di album ini juga akan dihapus.
                </div>
                
                <?php if (!empty($album['cover_path'])): ?>
                    <div class="text-center mb-4">
                        <img src="<?php echo SITE_URL . '/uploads/' . htmlspecialchars($album['cover_path']); ?>" 
                             class="img-fluid rounded anggota-delete-foto-preview">
                    </div>
                <?php endif; ?>
                
                <table class="table">
                    <tr>
                        <th width="150">Judul Album:</th>
                        <td><strong><?php echo htmlspecialchars($album['judul']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $album['status'] === 'disetujui' ? 'success' : 
                                     ($album['status'] === 'draft' ? 'secondary' : 'warning');
                            ?>">
                                <?php echo htmlspecialchars($album['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Jumlah Foto:</th>
                        <td><?php echo (int)$album['jumlah_foto']; ?></td>
                    </tr>
                    <tr>
                        <th>Dibuat Pada:</th>
                        <td><?php echo date('d M Y H:i', strtotime($album['dibuat_pada'])); ?></td>
                    </tr>
                </table>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_delete" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg"
                                onclick="return confirm('Yakin ingin menghapus album beserta semua foto di dalamnya?');">
                            <i class="bi bi-trash me-2"></i>Ya, Hapus Album
                        </button>
                        <a href="<?php echo getAdminUrl('galeri/index.php'); ?>" 
                           class="btn btn-secondary btn-lg">
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
