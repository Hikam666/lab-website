<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'media';
$page_title  = 'Hapus Media';

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
$sql   = "SELECT id_media, lokasi_file, keterangan_alt FROM media WHERE id_media = $1";
$res   = pg_query_params($conn, $sql, [$id_media]);
$media = $res && pg_num_rows($res) ? pg_fetch_assoc($res) : null;

if (!$media) {
    setFlashMessage('Media tidak ditemukan.', 'error');
    header('Location: ' . getAdminUrl('media/index.php'));
    exit;
}

// --- BARU: PENGECEKAN FOREIGN KEY (FK) PENGGUNAAN ---
$fasilitas_using_media = [];

// Pengecekan FK: Fasilitas
$sql_check_fasilitas = "
    SELECT id_fasilitas, nama 
    FROM fasilitas 
    WHERE id_foto = $1
";
$res_check_fasilitas = pg_query_params($conn, $sql_check_fasilitas, [$id_media]);
if ($res_check_fasilitas) {
    $fasilitas_using_media = pg_fetch_all($res_check_fasilitas);
}
// Tambahkan pengecekan FK tabel lain di sini (misal: galeri_album, berita, publikasi, anggota_lab)

$is_in_use = !empty($fasilitas_using_media); // Perlu diperluas jika ada tabel lain

// --- AKHIR PENGECEKAN FK ---

// kalau operator → blok / request delete
if (isOperator()) {
    $ket = 'Operator mencoba menghapus media: "' . $media['keterangan_alt'] . '" (ID=' . $id_media . ')';
    log_aktivitas($conn, 'REQUEST_DELETE', 'media', $id_media, $ket);

    setFlashMessage('Anda tidak memiliki izin untuk menghapus media. Permintaan telah dicatat.', 'warning');
    header('Location: ' . getAdminUrl('media/index.php'));
    exit;
}

// kalau admin: butuh konfirmasi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    
    // Cegah penghapusan jika masih digunakan (meskipun tombol sudah di-disable)
    if ($is_in_use) {
        setFlashMessage('Gagal menghapus: Media masih digunakan oleh entitas terkait.', 'error');
        header('Location: ' . getAdminUrl('media/hapus.php?id=' . $id_media));
        exit;
    }
    
    pg_query($conn, "BEGIN");
    try {
        // hapus file fisik
        $path = _DIR_ . '/../../uploads/' . $media['lokasi_file'];
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

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Hapus Media</h1>
    <p class="text-muted">Media yang dihapus tidak dapat dikembalikan.</p>
</div>

<div class="card border-danger">
    <div class="card-header bg-danger text-white">
        Konfirmasi Penghapusan
    </div>
    <div class="card-body">
        
        <?php if ($is_in_use): ?>
            <div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i> 
                Media ini *sedang digunakan* oleh entitas berikut. Anda tidak dapat menghapusnya sebelum memutuskan hubungan ini:
                <ul class="mt-2 mb-0">
                    <?php foreach ($fasilitas_using_media as $f): ?>
                        <li>Fasilitas: *<?php echo htmlspecialchars($f['nama']); ?>* (ID: <?php echo $f['id_fasilitas']; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p>Anda akan menghapus media berikut:</p>
        <ul>
            <li><strong>ID:</strong> <?php echo $media['id_media']; ?></li>
            <li><strong>Nama / Alt:</strong> <?php echo htmlspecialchars($media['keterangan_alt']); ?></li>
            <li><strong>File:</strong> <?php echo htmlspecialchars($media['lokasi_file']); ?></li>
        </ul>

        <form method="POST">
            <input type="hidden" name="confirm_delete" value="1">
            <button type="submit" class="btn btn-danger" <?php echo $is_in_use ? 'disabled' : ''; ?>>
                <i class="bi bi-trash"></i> Ya, hapus media ini
            </button>
            <a href="<?php echo getAdminUrl('media/index.php'); ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>