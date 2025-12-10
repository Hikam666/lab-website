<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/auth.php";
require "../includes/functions.php";

requireLogin();
$conn = getDBConnection();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$id = (int) $_GET['id'];

$fileToRemove = null;

pg_query($conn, "BEGIN");

// Ambil id_foto dan nama file (jika ada)
$qSelect = "SELECT id_foto FROM fasilitas WHERE id_fasilitas = $1";
$resSelect = pg_query_params($conn, $qSelect, [$id]);
if ($resSelect === false) {
    $err = pg_last_error($conn);
    pg_query($conn, "ROLLBACK");
    // Untuk debugging sementara: sertakan error (jangan tunjukkan di production)
    header("Location: index.php?error=select_failed&msg=" . urlencode($err));
    exit;
}

$data = pg_fetch_assoc($resSelect);
if (!$data) {
    pg_query($conn, "ROLLBACK");
    header("Location: index.php?error=not_found");
    exit;
}

// Jika ada foto, ambil nama file dari tabel media (tapi jangan unlink sekarang)
$id_foto = $data['id_foto'];
if (!empty($id_foto)) {
    $qMedia = "SELECT lokasi_file FROM media WHERE id_media = $1";
    $resMedia = pg_query_params($conn, $qMedia, [$id_foto]);
    if ($resMedia === false) {
        $err = pg_last_error($conn);
        pg_query($conn, "ROLLBACK");
        header("Location: index.php?error=media_select_failed&msg=" . urlencode($err));
        exit;
    }
    $media = pg_fetch_assoc($resMedia);
    if ($media) {
        $fileToRemove = $media['lokasi_file'];
    }
}

// Hapus fasilitas terlebih dahulu
$qDeleteFasilitas = "DELETE FROM fasilitas WHERE id_fasilitas = $1";
$resDeleteFasilitas = pg_query_params($conn, $qDeleteFasilitas, [$id]);

if ($resDeleteFasilitas === false) {
    $err = pg_last_error($conn);
    pg_query($conn, "ROLLBACK");
    header("Location: index.php?error=failed_delete_fasilitas&msg=" . urlencode($err));
    exit;
}

// Pastikan ada baris yang terhapus
if (pg_affected_rows($resDeleteFasilitas) == 0) {
    pg_query($conn, "ROLLBACK");
    header("Location: index.php?error=no_rows_deleted");
    exit;
}

// Jika ada id_foto, hapus record media
if (!empty($id_foto)) {
    $qDeleteMedia = "DELETE FROM media WHERE id_media = $1";
    $resDeleteMedia = pg_query_params($conn, $qDeleteMedia, [$id_foto]);
    if ($resDeleteMedia === false) {
        $err = pg_last_error($conn);
        pg_query($conn, "ROLLBACK");
        header("Location: index.php?error=failed_delete_media&msg=" . urlencode($err));
        exit;
    }

    // Pastikan media dihapus (atau setujui logika Anda jika boleh ada media tanpa referensi)
    if (pg_affected_rows($resDeleteMedia) == 0) {
        // rollback dan abort
        pg_query($conn, "ROLLBACK");
        header("Location: index.php?error=no_media_deleted");
        exit;
    }
}

// Semua operasi DB sukses -> commit
pg_query($conn, "COMMIT");

// Sekarang hapus file fisik setelah commit (aman jika terjadi rollback DB)
if (!empty($fileToRemove)) {
    // sesuaikan path sesuai struktur proyekkamu
    $uploadDir = realpath(__DIR__ . '/../../public/uploads') ?: __DIR__ . '/../../public/uploads';
    $filePath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileToRemove;
    if (file_exists($filePath)) {
        @unlink($filePath); // diamkan error unlink, tapi bisa ditangani jika perlu
    }
}

header("Location: index.php?success=deleted");
exit;
