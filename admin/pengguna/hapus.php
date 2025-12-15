<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$conn = getDBConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage("ID pengguna tidak valid.", "danger");
    header("Location: index.php");
    exit;
}

$id_target = (int) $_GET['id'];

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

// -----  CEK APAKAH MENGHAPUS DIRI SENDIRI -----
if ($id_target === (int)$id_sekarang) {
    setFlashMessage("Gagal: Anda tidak diperbolehkan menghapus akun Anda sendiri yang sedang aktif digunakan.", "danger");
    header("Location: index.php");
    exit;
}

// ----- EKSEKUSI HAPUS DENGAN PENGALIHAN KEPEMILIKAN DATA -----
pg_query($conn, "BEGIN");

try {
    $id_admin_sekarang = (int)$id_sekarang; 

    // PENGALIHAN 1: Berita
    $updateBerita = "UPDATE berita SET dibuat_oleh = $1, disetujui_oleh = $1 WHERE dibuat_oleh = $2 OR disetujui_oleh = $2";
    $resBerita = pg_query_params($conn, $updateBerita, [$id_admin_sekarang, $id_target]);
    if (!$resBerita) throw new Exception("Gagal update berita: " . pg_last_error($conn)); 

    // PENGALIHAN 2: Media
    $updateMedia = "UPDATE media SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    $resMedia = pg_query_params($conn, $updateMedia, [$id_admin_sekarang, $id_target]);
    if (!$resMedia) throw new Exception("Gagal update media: " . pg_last_error($conn)); 
    
    // PENGALIHAN 3: Fasilitas
    $updateFasilitas = "UPDATE fasilitas SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    $resFasilitas = pg_query_params($conn, $updateFasilitas, [$id_admin_sekarang, $id_target]);
    if (!$resFasilitas) throw new Exception("Gagal update fasilitas: " . pg_last_error($conn)); 

    // PENGALIHAN 4: Publikasi
    $updatePublikasi = "UPDATE publikasi SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    $resPublikasi = pg_query_params($conn, $updatePublikasi, [$id_admin_sekarang, $id_target]);
    if (!$resPublikasi) throw new Exception("Gagal update publikasi: " . pg_last_error($conn)); 

    // PENGALIHAN 5: Galeri Album
    $updateGaleriAlbum = "UPDATE galeri_album SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    $resAlbum = pg_query_params($conn, $updateGaleriAlbum, [$id_admin_sekarang, $id_target]);
    if (!$resAlbum) throw new Exception("Gagal update galeri album: " . pg_last_error($conn)); 
    
    // PENGALIHAN 6: Galeri Item
    $updateGaleriItem = "UPDATE galeri_item SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    $resItem = pg_query_params($conn, $updateGaleriItem, [$id_admin_sekarang, $id_target]);
    if (!$resItem) throw new Exception("Gagal update galeri item: " . pg_last_error($conn)); 

    // PENGALIHAN 7: Anggota Lab
    $updateAnggotaLab = "UPDATE anggota_lab SET dibuat_oleh = $1 WHERE dibuat_oleh = $2";
    $resAnggota = pg_query_params($conn, $updateAnggotaLab, [$id_admin_sekarang, $id_target]);
    if (!$resAnggota) throw new Exception("Gagal update anggota lab: " . pg_last_error($conn));

    // PENGALIHAN 8: Pesan Kontak
    $updatePesanKontak = "UPDATE pesan_kontak SET ditangani_oleh = $1 WHERE ditangani_oleh = $2";
    $resPesan = pg_query_params($conn, $updatePesanKontak, [$id_admin_sekarang, $id_target]);
    if (!$resPesan) throw new Exception("Gagal update pesan kontak: " . pg_last_error($conn));

    // DELETE pengguna
    $queryHapus = "DELETE FROM pengguna WHERE id_pengguna = $1";
    $result = pg_query_params($conn, $queryHapus, [$id_target]);

    if (!$result) throw new Exception("Gagal hapus pengguna: " . pg_last_error($conn));
    
    // COMMIT jika semua berhasil
    pg_query($conn, "COMMIT"); 
    
    // Logging dan Redirect sukses
    if (function_exists('log_aktivitas')) {
        log_aktivitas($conn, 'DELETE', 'pengguna', $id_target, "Menghapus akun pengguna ID: $id_target. Semua konten dialihkan ke ID: $id_admin_sekarang.");
    }

    setFlashMessage("Pengguna berhasil dihapus. Semua konten terkait telah dialihkan kepada Anda.", "success");
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    // ROLLBACK jika ada kegagalan
    pg_query($conn, "ROLLBACK");
    
    // Tampilkan pesan error spesifik dari Exception
    setFlashMessage("Gagal menghapus pengguna: " . $e->getMessage(), "danger");
    header("Location: index.php");
    exit;
}