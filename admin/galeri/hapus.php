<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Hapus Album Galeri';

if (!isset($_GET['id'])) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$id_album = (int)$_GET['id'];

// Ambil data album
$sql_album = "
    SELECT ga.id_album, ga.judul, ga.status, ga.aksi_request, COUNT(gi.id_item) AS total_foto
    FROM galeri_album ga
    LEFT JOIN galeri_item gi ON gi.id_album = ga.id_album
    WHERE ga.id_album = $1
    GROUP BY ga.id_album
";
$res_album = pg_query_params($conn, $sql_album, [$id_album]);

if (!$res_album || pg_num_rows($res_album) === 0) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$album = pg_fetch_assoc($res_album);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    
    // ==== OPERATOR: AJUKAN HAPUS ====
    if (!isAdmin()) {
        pg_query($conn, "BEGIN");
        try {
            // Update status album menjadi diajukan dengan aksi_request = 'hapus'
            $sql_update = "
                UPDATE galeri_album 
                SET status = 'diajukan',
                    aksi_request = 'hapus',
                    diperbarui_pada = NOW()
                WHERE id_album = $1
            ";
            $res_update = pg_query_params($conn, $sql_update, [$id_album]);
            
            if (!$res_update) {
                throw new Exception('Gagal mengajukan penghapusan album.');
            }

            // Log aktivitas
            $ket = 'Operator mengajukan penghapusan album galeri: "' . $album['judul'] . '" (ID=' . $id_album . ')';
            log_aktivitas($conn, 'REQUEST_DELETE', 'galeri_album', $id_album, $ket);

            pg_query($conn, "COMMIT");
            
            setFlashMessage('Permintaan penghapusan album telah diajukan dan menunggu persetujuan admin.', 'warning');
            header('Location: index.php');
            exit;

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            setFlashMessage('Gagal mengajukan penghapusan: ' . $e->getMessage(), 'danger');
        }
    }
    
    // ==== ADMIN: HAPUS PERMANEN ====
    else {
        pg_query($conn, "BEGIN");
        try {
            $del = pg_query_params($conn, "DELETE FROM galeri_album WHERE id_album = $1", [$id_album]);
            if (!$del) {
                throw new Exception(pg_last_error($conn));
            }

            $ket = 'Admin menghapus album galeri: "' . $album['judul'] . '" (ID=' . $id_album . ')';
            log_aktivitas($conn, 'DELETE', 'galeri_album', $id_album, $ket);

            pg_query($conn, "COMMIT");
            setFlashMessage('Album dan seluruh fotonya berhasil dihapus.', 'success');
            header('Location: index.php');
            exit;

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            setFlashMessage('Gagal memproses album: ' . $e->getMessage(), 'danger');
            header('Location: index.php');
            exit;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="my-4">
        <h1 class="mt-4">Hapus Album Galeri</h1>
        <p class="text-muted">
            <?php echo isAdmin() ? 'Tindakan ini tidak dapat dibatalkan.' : 'Permintaan ini memerlukan persetujuan Admin.'; ?>
        </p>
    </div>

    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            Konfirmasi Penghapusan
        </div>
        <div class="card-body">
            <p>Anda akan <?php echo isAdmin() ? 'menghapus' : 'mengajukan penghapusan'; ?> album berikut:</p>
            <ul>
                <li><strong>ID:</strong> <?php echo (int)$album['id_album']; ?></li>
                <li><strong>Judul:</strong> <?php echo htmlspecialchars($album['judul']); ?></li>
                <li><strong>Jumlah Foto:</strong> <?php echo (int)$album['total_foto']; ?></li>
            </ul>
            
            <?php if (isAdmin()): ?>
                <p class="text-danger fw-semibold">Semua foto di dalam album ini juga akan terhapus.</p>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Sebagai operator, permintaan penghapusan Anda akan diajukan ke admin untuk persetujuan.
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> 
                    <?php echo isAdmin() ? 'Ya, hapus album ini' : 'Ajukan Hapus'; ?>
                </button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>