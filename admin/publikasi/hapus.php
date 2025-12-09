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

// Ambil detail publikasi
$q    = pg_query_params($conn, "SELECT * FROM publikasi WHERE id_publikasi = $1", [$id]);
$data = pg_fetch_assoc($q);

if (!$data) {
    setFlashMessage("Data publikasi tidak ditemukan.", "danger");
    redirectAdmin("publikasi/index.php");
    exit;
}

// ============================
// PROSES HAPUS
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $delete = pg_query_params($conn, "DELETE FROM publikasi WHERE id_publikasi = $1", [$id]);

    if ($delete) {
        if (function_exists('log_aktivitas')) {
            log_aktivitas($conn, 'delete', 'publikasi', $id, "Menghapus publikasi: " . $data['judul']);
        }
        setFlashMessage("Publikasi berhasil dihapus.", "success");
    } else {
        $err = pg_last_error($conn);
        setFlashMessage("Gagal menghapus: $err", "danger");
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
        Tindakan ini tidak dapat dibatalkan.
    </p>

    <form method="POST">
        <button type="submit" class="delete-btn-danger">Ya, Hapus</button>
        <a href="index.php" class="delete-btn-cancel">Batal</a>
    </form>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
