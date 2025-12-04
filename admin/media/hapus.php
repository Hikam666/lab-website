<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$currentUser = getCurrentUser();
$id_pengguna = $currentUser['id'] ?? null;

// pastikan ada id media
if (!isset($_GET['id'])) {
    setFlashMessage('Media tidak ditemukan.', 'error');
    header('Location: ' . getAdminUrl('media/index.php'));
    exit;
}

$id_media = (int)$_GET['id'];

// ambil data media
$sql  = "SELECT id_media, lokasi_file, keterangan_alt FROM media WHERE id_media = $1";
$res  = pg_query_params($conn, $sql, [$id_media]);
$media = $res && pg_num_rows($res) ? pg_fetch_assoc($res) : null;

if (!$media) {
    setFlashMessage('Media tidak ditemukan.', 'error');
    header('Location: ' . getAdminUrl('media/index.php'));
    exit;
}

// kalau operator → blok / request delete
if (isOperator()) {
    $ket = 'Operator mencoba menghapus media: "' . $media['keterangan_alt'] . '" (ID=' . $id_media . ')';
    // kalau mau, bisa log REQUEST_DELETE:
    log_aktivitas($conn, 'REQUEST_DELETE', 'media', $id_media, $ket);

    setFlashMessage('Anda tidak memiliki izin untuk menghapus media. Permintaan telah dicatat.', 'warning');
    header('Location: ' . getAdminUrl('media/index.php'));
    exit;
}

// kalau admin: butuh konfirmasi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    pg_query($conn, "BEGIN");
    try {
        // hapus file fisik
        $path = __DIR__ . '/../../uploads/' . $media['lokasi_file'];
        if (file_exists($path)) {
            @unlink($path);
        }

        // hapus dari db
        $del = pg_query_params($conn, "DELETE FROM media WHERE id_media = $1", [$id_media]);
        if (!$del) {
            throw new Exception(pg_last_error($conn));
        }

        // LOG DELETE
        $ket = 'Menghapus media: "' . $media['keterangan_alt'] . '" (file=' . $media['lokasi_file'] . ')';
        log_aktivitas($conn, 'DELETE', 'media', $id_media, $ket);

        pg_query($conn, "COMMIT");
        setFlashMessage('Media berhasil dihapus.', 'success');
        header('Location: ' . getAdminUrl('media/index.php'));
        exit;

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage('Gagal menghapus media: ' . $e->getMessage(), 'error');
    }
}

$active_page = 'media';
$page_title  = 'Hapus Media';

include __DIR__ . '/../includes/header.php';
?>

<!-- Tampilan konfirmasi -->
<div class="page-header">
  <h1>Hapus Media</h1>
  <p class="text-muted">Media yang dihapus tidak dapat dikembalikan.</p>
</div>

<div class="card border-danger">
  <div class="card-header bg-danger text-white">
    Konfirmasi Penghapusan
  </div>
  <div class="card-body">
    <p>Anda akan menghapus media berikut:</p>
    <ul>
      <li><strong>ID:</strong> <?php echo $media['id_media']; ?></li>
      <li><strong>Nama / Alt:</strong> <?php echo htmlspecialchars($media['keterangan_alt']); ?></li>
      <li><strong>File:</strong> <?php echo htmlspecialchars($media['lokasi_file']); ?></li>
    </ul>

    <form method="POST">
      <input type="hidden" name="confirm_delete" value="1">
      <button type="submit" class="btn btn-danger">
        <i class="bi bi-trash"></i> Ya, hapus media ini
      </button>
      <a href="<?php echo getAdminUrl('media/index.php'); ?>" class="btn btn-secondary">Batal</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
