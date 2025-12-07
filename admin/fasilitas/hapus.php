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

pg_query($conn, "BEGIN"); // mulai transaksi

// Ambil data fasilitas + foto
$qSelect = "
    SELECT id_foto 
    FROM fasilitas 
    WHERE id_fasilitas = $1
";
$resSelect = pg_query_params($conn, $qSelect, [$id]);
$data = pg_fetch_assoc($resSelect);

if (!$data) {
    pg_query($conn, "ROLLBACK");
    header("Location: index.php?error=not_found");
    exit;
}

// Jika ada foto, hapus file & data media
if (!empty($data['id_foto'])) {

    // Ambil lokasi file
    $qMedia = "SELECT lokasi_file FROM media WHERE id_media = $1";
    $resMedia = pg_query_params($conn, $qMedia, [$data['id_foto']]);
    $media = pg_fetch_assoc($resMedia);

    if ($media) {
        $filePath = __DIR__ . "/../../public/uploads/" . $media['lokasi_file'];
        if (file_exists($filePath)) {
            unlink($filePath); // hapus file fisik
        }

        // Hapus record media
        $qDeleteMedia = "DELETE FROM media WHERE id_media = $1";
        pg_query_params($conn, $qDeleteMedia, [$data['id_foto']]);
    }
}

// Hapus fasilitas
$qDeleteFasilitas = "DELETE FROM fasilitas WHERE id_fasilitas = $1";
$resDeleteFasilitas = pg_query_params($conn, $qDeleteFasilitas, [$id]);

if ($resDeleteFasilitas) {
    pg_query($conn, "COMMIT");
    header("Location: index.php?success=deleted");
} else {
    pg_query($conn, "ROLLBACK");
    header("Location: index.php?error=failed_delete");
}
exit;
