<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Hapus Album Galeri';

if (!isAdmin()) {
    setFlashMessage('Hanya admin yang dapat menghapus album.', 'danger');
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id'])) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$id_album = (int)$_GET['id'];

// Ambil data album
$sql_album = "
    SELECT ga.id_album, ga.judul, COUNT(gi.id_item) AS total_foto
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
    pg_query($conn, "BEGIN");
    try {
        // Hapus album (galeri_item akan terhapus via ON DELETE CASCADE)
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
        setFlashMessage('Gagal menghapus album: ' . $e->getMessage(), 'danger');
        header('Location: index.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="my-4">
        <h1 class="mt-4">Hapus Album Galeri</h1>
        <p class="text-muted">Tindakan ini tidak dapat dibatalkan.</p>
    </div>

    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            Konfirmasi Penghapusan
        </div>
        <div class="card-body">
            <p>Anda akan menghapus album berikut:</p>
            <ul>
                <li><strong>ID:</strong> <?php echo (int)$album['id_album']; ?></li>
                <li><strong>Judul:</strong> <?php echo htmlspecialchars($album['judul']); ?></li>
                <li><strong>Jumlah Foto:</strong> <?php echo (int)$album['total_foto']; ?></li>
            </ul>
            <p class="text-danger fw-semibold">Semua foto di dalam album ini juga akan terhapus.</p>

            <form method="post">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> Ya, hapus album ini
                </button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
