<?php
$extra_css = ['pesan.css'];
require_once "../includes/functions.php";
require_once "../includes/auth.php";

requireLogin();

$conn = getDBConnection();
$active_page = 'pesan';
$page_title  = 'Hapus Pesan';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_pesan = intval($_GET['id']);
$currentUser = getCurrentUser();

$sql_get = "SELECT subjek, nama_pengirim AS pengirim FROM pesan_kontak WHERE id_pesan = $1"; 
$res_get = pg_query_params($conn, $sql_get, [$id_pesan]);

if (!$res_get || pg_num_rows($res_get) === 0) {

    setFlashMessage('Pesan tidak ditemukan.', 'error');
    header("Location: index.php");
    exit;
}
$pesan = pg_fetch_assoc($res_get);
$subjek_pesan = htmlspecialchars($pesan['subjek'] ?: '(Tanpa Subjek)');
$pengirim_pesan = htmlspecialchars($pesan['pengirim'] ?: 'Anonim');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    pg_query($conn, "BEGIN");
    try {
        if (isAdmin()) {
            pg_query_params($conn, "DELETE FROM pesan_kontak WHERE id_pesan = $1", [$id_pesan]);
            
            log_aktivitas($conn, 'DELETE', 'pesan_kontak', $id_pesan, 'Menghapus pesan (langsung): ' . $subjek_pesan);
            
            pg_query($conn, "COMMIT");
            setFlashMessage('Pesan kontak berhasil dihapus secara permanen.', 'success');
            header("Location: index.php?deleted=1");
            exit;

        } else {
            $update_sql = "
                UPDATE pesan_kontak 
                SET status_request = 'diajukan',
                    aksi_request = 'hapus'
                WHERE id_pesan = $1
            ";
            $update_result = pg_query_params($conn, $update_sql, [$id_pesan]);
            
            if (!$update_result) {
                throw new Exception('Gagal mengajukan penghapusan pesan.');
            }

            log_aktivitas($conn, 'REQUEST_DELETE', 'pesan_kontak', $id_pesan, 'Operator mengajukan penghapusan pesan: ' . $subjek_pesan);
            
            pg_query($conn, "COMMIT");
            setFlashMessage('Permintaan penghapusan pesan telah diajukan ke Admin.', 'warning');
            header('Location: index.php');
            exit;
        }

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage('Gagal memproses permintaan: ' . $e->getMessage(), 'error');
        header('Location: index.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php'; 
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-trash me-2"></i>Hapus Pesan Kontak</h1>
        </div>
        <a href="index.php" class="btn btn-secondary">
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
                    Anda akan menghapus pesan berikut: **<?php echo $subjek_pesan; ?>** (dari **<?php echo $pengirim_pesan; ?>**).
                    <?php if (isAdmin()): ?>
                        <strong>Tindakan ini akan menghapus permanen.</strong>
                    <?php else: ?>
                        <strong>Tindakan ini akan mengajukan permintaan penghapusan kepada Admin.</strong>
                    <?php endif; ?>
                </div>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_delete" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Yakin ingin menghapus pesan ini?');">
                            <i class="bi bi-trash me-2"></i>Ya, Hapus Pesan
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
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