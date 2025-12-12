<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$conn = getDBConnection();

// ----- 1. CHECK ID DARI URL -----
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage("ID pengguna tidak valid.", "danger");
    header("Location: index.php");
    exit;
}

$id_target = (int) $_GET['id'];

// ----- 2. AMBIL ID USER YANG SEDANG LOGIN -----
function getLoggedUserIdInternal() {
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

$id_sekarang = getLoggedUserIdInternal();

// ----- 3. PROTEKSI: CEK APAKAH MENGHAPUS DIRI SENDIRI -----
if ($id_target === (int)$id_sekarang) {
    setFlashMessage("Gagal: Anda tidak diperbolehkan menghapus akun Anda sendiri yang sedang aktif digunakan.", "danger");
    header("Location: index.php");
    exit;
}

// ----- 4. EKSEKUSI HAPUS DENGAN PENGALIHAN KEPEMILIKAN DATA -----
pg_query($conn, "BEGIN");

try {
    $id_admin_sekarang = (int)$id_sekarang; 
    $updateBerita = "UPDATE berita SET dibuat_oleh = $1, disetujui_oleh = $1 WHERE dibuat_oleh = $2 OR disetujui_oleh = $2";
    pg_query_params($conn, $updateBerita, [$id_admin_sekarang, $id_target]);

    $updateMedia = "UPDATE media SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    pg_query_params($conn, $updateMedia, [$id_admin_sekarang, $id_target]);
    
    $updateFasilitas = "UPDATE fasilitas SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    pg_query_params($conn, $updateFasilitas, [$id_admin_sekarang, $id_target]);

    $updatePublikasi = "UPDATE publikasi SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    pg_query_params($conn, $updatePublikasi, [$id_admin_sekarang, $id_target]);

    $updateGaleriAlbum = "UPDATE galeri_album SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    pg_query_params($conn, $updateGaleriAlbum, [$id_admin_sekarang, $id_target]);
    
    $updateGaleriItem = "UPDATE galeri_item SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    pg_query_params($conn, $updateGaleriItem, [$id_admin_sekarang, $id_target]);

    $updateAnggotaLab = "UPDATE anggota_lab SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    pg_query_params($conn, $updateAnggotaLab, [$id_admin_sekarang, $id_target]);

    $updatePesanKontak = "UPDATE pesan_kontak SET ditangani_oleh = $1 WHERE ditangani_oleh = $2";
    pg_query_params($conn, $updatePesanKontak, [$id_admin_sekarang, $id_target]);

    $queryHapus = "DELETE FROM pengguna WHERE id_pengguna = $1";
    $result = pg_query_params($conn, $queryHapus, [$id_target]);

    if (!$result) throw new Exception(pg_last_error($conn));
    pg_query($conn, "COMMIT");
    if (function_exists('log_aktivitas')) {
        log_aktivitas($conn, 'DELETE', 'pengguna', $id_target, "Menghapus akun pengguna ID: $id_target. Semua konten dialihkan ke ID: $id_admin_sekarang.");
    }

    setFlashMessage("Pengguna berhasil dihapus. Semua konten terkait telah dialihkan kepada Anda.", "success");
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    
    setFlashMessage("Gagal menghapus pengguna: " . $e->getMessage(), "danger");
    header("Location: index.php");
    exit;
}