<?php
// admin/pengguna/hapus.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$conn = getDBConnection();

// ----- CHECK ID -----
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die("ID pengguna tidak valid. Pastikan URL berformat: hapus.php?id=123");
}

$id = (int) $_GET['id'];

// ----- HAPUS USER -----
// Gunakan TRUNCATE ... CASCADE jika tabel lain tergantung (opsional), tapi untuk pengguna pakai DELETE
$query = "DELETE FROM pengguna WHERE id_pengguna = $1";
$result = pg_query_params($conn, $query, [$id]);

if ($result) {
    // Redirect setelah sukses
    header("Location: index.php?status=deleted");
    exit;
} else {
    // Error handling
    $dbErr = pg_last_error($conn);
    http_response_code(500);
    die("Gagal menghapus pengguna: " . htmlspecialchars($dbErr));
}
