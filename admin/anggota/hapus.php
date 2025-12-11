<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$conn       = getDBConnection();
$id_anggota = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Cek role
$is_admin = function_exists('isAdmin') ? isAdmin() : false;

if ($id_anggota <= 0) {
    setFlashMessage('ID anggota tidak valid', 'error');
    header('Location: ' . getAdminUrl('anggota/index.php'));
    exit;
}

$sql = "SELECT a.*, m.lokasi_file as foto 
        FROM anggota_lab a 
        LEFT JOIN media m ON a.id_foto = m.id_media 
        WHERE a.id_anggota = $1";
$result = pg_query_params($conn, $sql, [$id_anggota]);

if (!$result || pg_num_rows($result) === 0) {
    setFlashMessage('Anggota tidak ditemukan', 'error');
    header('Location: ' . getAdminUrl('anggota/index.php'));
    exit;
}

$anggota = pg_fetch_assoc($result);

// === Handle submit form ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {

    // ==== OPERATOR: ajukan penghapusan, bukan delete langsung ====
    if (!$is_admin) {
        pg_query($conn, "BEGIN");
        try {
            $update_sql = "UPDATE anggota_lab 
                           SET status = 'diajukan',
                               aktif = FALSE,
                               diperbarui_pada = NOW()
                           WHERE id_anggota = $1";
            $update_res = pg_query_params($conn, $update_sql, [$id_anggota]);

            if (!$update_res) {
                throw new Exception('Gagal mengajukan penghapusan anggota.');
            }

            pg_query($conn, "COMMIT");

            // Log aktivitas: request_delete
            $keterangan_log = 'Operator mengajukan penghapusan anggota: ' . $anggota['nama'] . ' (ID ' . $id_anggota . ')';
            log_aktivitas($conn, 'request_delete', 'anggota_lab', $id_anggota, $keterangan_log);

            setFlashMessage(
                'Permintaan penghapusan anggota telah diajukan dan menunggu persetujuan admin.',
                'info'
            );
            header('Location: ' . getAdminUrl('anggota/index.php'));
            exit;

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            setFlashMessage('Gagal mengajukan penghapusan: ' . $e->getMessage(), 'error');
        }

    // ==== ADMIN: hapus beneran ====
    } else {
        pg_query($conn, "BEGIN");
        try {
            $delete_sql    = "DELETE FROM anggota_lab WHERE id_anggota = $1";
            $delete_result = pg_query_params($conn, $delete_sql, [$id_anggota]);

            if (!$delete_result) {
                throw new Exception('Query delete gagal dieksekusi.');
            }

            // Hapus file foto di filesystem (kalau ada)
            if (!empty($anggota['foto'])) {
                $foto_path = __DIR__ . '/../../uploads/' . $anggota['foto'];
                if (file_exists($foto_path)) {
                    @unlink($foto_path);
                }
            }

            pg_query($conn, "COMMIT");

            // Catat ke log_aktivitas
            $keterangan_log = 'Menghapus anggota: ' . $anggota['nama'] . ' (ID ' . $id_anggota . ')';
            log_aktivitas($conn, 'delete', 'anggota_lab', $id_anggota, $keterangan_log);

            setFlashMessage('Anggota berhasil dihapus', 'success');
            header('Location: ' . getAdminUrl('anggota/index.php'));
            exit;

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            setFlashMessage('Gagal menghapus: ' . $e->getMessage(), 'error');
        }
    }
}

$active_page = 'anggota';
$page_title  = 'Hapus Anggota';


include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-trash me-2"></i>Hapus Anggota</h1>
        </div>
        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
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
                    Anda akan menghapus anggota berikut. 
                    <?php if ($is_admin): ?>
                        Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                    <?php else: ?>
                        Sebagai operator, tindakan ini akan diajukan ke admin untuk persetujuan.
                    <?php endif; ?>
                </div>

                <?php if ($anggota['foto']): ?>
                    <div class="text-center mb-4">
                        <img src="<?php echo SITE_URL . '/uploads/' . $anggota['foto']; ?>"
                             class="img-fluid rounded anggota-delete-foto-preview">
                    </div>
                <?php endif; ?>

                <table class="table">
                    <tr>
                        <th width="150">Nama:</th>
                        <td><strong><?php echo htmlspecialchars($anggota['nama']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($anggota['email']); ?></td>
                    </tr>
                    <?php if ($anggota['peran_lab']): ?>
                        <tr>
                            <th>Peran Lab:</th>
                            <td><?php echo htmlspecialchars($anggota['peran_lab']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?php echo $anggota['aktif'] ? 'success' : 'secondary'; ?>">
                                <?php echo $anggota['aktif'] ? 'Aktif' : 'Non-aktif'; ?>
                            </span>
                        </td>
                    </tr>
                </table>

                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_delete" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg"
                                onclick="return confirm('Yakin ingin melanjutkan proses ini?');">
                            <i class="bi bi-trash me-2"></i>
                            <?php echo $is_admin ? 'Ya, Hapus Anggota' : 'Ajukan Penghapusan'; ?>
                        </button>
                        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary btn-lg">
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
