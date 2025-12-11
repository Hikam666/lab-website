<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/auth.php";
require "../includes/functions.php";

requireLogin();
$conn = getDBConnection();

$page_title  = "Hapus Fasilitas";
$active_page = "fasilitas";

function getLoggedUserIdFallback() {
    if (function_exists('getCurrentUser')) {
        $u = getCurrentUser();
        if (is_array($u)) {
            if (!empty($u['id']))          return $u['id'];
            if (!empty($u['id_pengguna'])) return $u['id_pengguna'];
        }
    }
    if (isset($_SESSION['user_id']))      return $_SESSION['user_id'];
    if (isset($_SESSION['id_pengguna']))  return $_SESSION['id_pengguna'];
    return null;
}

$user_id = getLoggedUserIdFallback();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    setFlashMessage("ID fasilitas tidak valid.", "danger");
    header("Location: index.php");
    exit;
}

$qSelect = "
    SELECT f.*, m.lokasi_file, m.id_media
    FROM fasilitas f
    LEFT JOIN media m ON f.id_foto = m.id_media
    WHERE f.id_fasilitas = $1
";
$resSelect = pg_query_params($conn, $qSelect, [$id]);
$data      = pg_fetch_assoc($resSelect);

if (!$data) {
    setFlashMessage("Data fasilitas tidak ditemukan.", "danger");
    header("Location: index.php");
    exit;
}

// 1. JIKA OPERATOR: AJUKAN HAPUS
if (!(function_exists('isAdmin') && isAdmin())) {
    $qUpdate = "
        UPDATE fasilitas
        SET status = 'diajukan',
            aksi_request = 'hapus' -- <--- PENAMBAHAN UNTUK KONSISTENSI WORKFLOW
        WHERE id_fasilitas = $1
    ";
    $resUpdate = pg_query_params($conn, $qUpdate, [$id]);

    if ($resUpdate) {
        if (function_exists('log_aktivitas')) {
            $ket = "Mengajukan penghapusan fasilitas: {$data['nama']}";
            log_aktivitas($conn, 'REQUEST_DELETE', 'fasilitas', $id, $ket);
        }
        setFlashMessage("Pengajuan hapus fasilitas telah dikirim dan menunggu persetujuan admin.", "warning");
    } else {
        setFlashMessage("Gagal mengajukan penghapusan fasilitas.", "danger");
    }

    header("Location: index.php");
    exit;
}

// 2. JIKA ADMIN: EKSEKUSI HAPUS PERMANEN
pg_query($conn, "BEGIN");

$id_foto    = $data['id_media'] ?? null;
$fileToRemove = null;

if (!empty($id_foto)) {
    $fileToRemove = $data['lokasi_file']; 
}

$qDeleteFasilitas = "DELETE FROM fasilitas WHERE id_fasilitas = $1";
$resDeleteFasilitas = pg_query_params($conn, $qDeleteFasilitas, [$id]);

if ($resDeleteFasilitas === false || pg_affected_rows($resDeleteFasilitas) == 0) {
    pg_query($conn, "ROLLBACK");
    setFlashMessage("Gagal menghapus fasilitas dari database.", "danger");
    header("Location: index.php");
    exit;
}

if (!empty($id_foto)) {
    $qDeleteMedia = "DELETE FROM media WHERE id_media = $1";
    $resDeleteMedia = pg_query_params($conn, $qDeleteMedia, [$id_foto]);
    if ($resDeleteMedia === false) {
        $err = pg_last_error($conn);
        pg_query($conn, "ROLLBACK");
        setFlashMessage("Gagal menghapus media: $err", "danger");
        header("Location: index.php");
        exit;
    }
}

pg_query($conn, "COMMIT");

if (!empty($fileToRemove)) {
    $uploadDir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
    $filePath  = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileToRemove;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

if (function_exists('log_aktivitas')) {
    $ket = "Menghapus fasilitas: {$data['nama']}";
    log_aktivitas($conn, 'delete', 'fasilitas', $id, $ket);
}

setFlashMessage("Fasilitas berhasil dihapus.", "success");
header("Location: index.php");
exit;