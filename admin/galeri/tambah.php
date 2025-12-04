<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); }
if (!isset($conn)) $conn = getDBConnection();

// Fungsi helper membuat slug sederhana
function makeSlug($string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = pg_escape_string($conn, $_POST['judul']);
    $deskripsi = pg_escape_string($conn, $_POST['deskripsi']);
    $slug = makeSlug($judul) . '-' . time(); // Tambah time agar unik
    $user_id = $_SESSION['user_id']; // Asumsi session user_id ada
    
    // 1. Insert Album Dulu (Tanpa cover)
    $sql = "INSERT INTO galeri_album (judul, slug, deskripsi, dibuat_oleh, status) 
            VALUES ($1, $2, $3, $4, 'disetujui') RETURNING id_album";
    $res = pg_query_params($conn, $sql, [$judul, $slug, $deskripsi, $user_id]);
    
    if ($res) {
        $row = pg_fetch_assoc($res);
        $new_album_id = $row['id_album'];
        
        // 2. Jika ada upload cover
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
            // Proses Upload ke folder
            $ext = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
            $filename = 'cover_' . time() . '.' . $ext;
            $target = __DIR__ . '/../../uploads/' . $filename;
            
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $target)) {
                // Insert ke tabel media
                $media_sql = "INSERT INTO media (nama_file, lokasi_file, tipe_file, diunggah_oleh) 
                              VALUES ($1, $2, $3, $4) RETURNING id_media";
                $media_res = pg_query_params($conn, $media_sql, 
                    [$_FILES['cover']['name'], $filename, $ext, $user_id]);
                
                if ($media_res) {
                    $media_row = pg_fetch_assoc($media_res);
                    $id_cover = $media_row['id_media'];
                    
                    // Update Album dengan ID Cover
                    pg_query_params($conn, "UPDATE galeri_album SET id_cover = $1 WHERE id_album = $2", 
                        [$id_cover, $new_album_id]);
                }
            }
        }
        
        $_SESSION['message'] = "Album berhasil dibuat!";
        $_SESSION['msg_type'] = "success";
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal membuat album: " . pg_last_error($conn);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow-sm col-md-8 mx-auto">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Buat Album Baru</h5>
        </div>
        <div class="card-body">
            <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Judul Album <span class="text-danger">*</span></label>
                    <input type="text" name="judul" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cover Album (Opsional)</label>
                    <input type="file" name="cover" class="form-control" accept="image/*">
                    <small class="text-muted">Akan disimpan di tabel media.</small>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Album</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>