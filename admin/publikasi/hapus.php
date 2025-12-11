<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$conn        = getDBConnection();
$page_title  = "Hapus Publikasi";
$active_page = "publikasi";
$extra_css   = ['publikasi.css'];

// ============================
// CEK ID
// ============================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    setFlashMessage("ID publikasi tidak valid.", "danger");
    redirectAdmin("publikasi/index.php");
    exit;
}
$q = pg_query_params(
    $conn,
    "SELECT p.*, m.lokasi_file AS cover_file 
     FROM publikasi p
     LEFT JOIN media m ON p.id_cover = m.id_media
     WHERE p.id_publikasi = $1",
    [$id]
);
$data = pg_fetch_assoc($q);

if (!$data) {
    setFlashMessage("Data publikasi tidak ditemukan.", "danger");
    redirectAdmin("publikasi/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $judul_publikasi = $data['judul'];
    $is_admin = isAdmin();
    if (!$is_admin) {
        pg_query($conn, "BEGIN");
        try {
            $update_sql = "UPDATE publikasi SET status = 'diajukan', aksi_request = 'hapus' WHERE id_publikasi = $1";
            $update_result = pg_query_params($conn, $update_sql, [$id]);

            if (!$update_result) {
                throw new Exception(pg_last_error($conn));
            }

            if (function_exists('log_aktivitas')) {
                log_aktivitas(
                    $conn,
                    'REQUEST_DELETE',
                    'publikasi',
                    $id,
                    "Operator mengajukan penghapusan publikasi: " . $judul_publikasi
                );
            }
            
            pg_query($conn, "COMMIT");
            setFlashMessage("Permintaan penghapusan publikasi telah diajukan dan menunggu persetujuan Admin.", "warning");

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            setFlashMessage("Gagal mengajukan penghapusan: " . $e->getMessage(), "danger");
        }

        redirectAdmin("publikasi/index.php");
        exit;
    }

    pg_query($conn, "BEGIN");

    try {
        $delete = pg_query_params($conn, "DELETE FROM publikasi WHERE id_publikasi = $1", [$id]);

        if (!$delete) {
            throw new Exception(pg_last_error($conn));
        }
        if (!empty($data['cover_file'])) {
            $file_path = __DIR__ . '/../../uploads/' . $data['cover_file'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        // Log aktivitas
        if (function_exists('log_aktivitas')) {
            log_aktivitas(
                $conn,
                'DELETE',
                'publikasi',
                $id,
                "Menghapus publikasi (permanen oleh Admin): " . $judul_publikasi
            );
        }

        pg_query($conn, "COMMIT");
        setFlashMessage("Publikasi berhasil dihapus secara permanen.", "success");

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage("Gagal menghapus permanen: " . $e->getMessage(), "danger");
    }

    redirectAdmin("publikasi/index.php");
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="delete-container">

    <h2 class="delete-title">Hapus Publikasi?</h2>

    <p class="delete-desc">
        Yakin ingin menghapus publikasi:<br>
        <strong>“<?= htmlspecialchars($data['judul']) ?>”</strong><br><br>
        Tindakan ini <?php echo isAdmin() ? 'akan menghapus permanen' : 'akan diajukan untuk persetujuan Admin'; ?> dan tidak dapat dibatalkan.
    </p>

    <form method="POST">
        <button type="submit" class="delete-btn-danger">
            Ya, <?php echo isAdmin() ? 'Hapus Permanen' : 'Ajukan Hapus'; ?>
        </button>
        <a href="index.php" class="delete-btn-cancel">Batal</a>
    </form>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>