<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/functions.php";
require "../includes/auth.php";

$conn = getDBConnection();
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle submit
if (isset($_POST['submit'])) {

    $nama       = trim($_POST['nama']);
    $slug       = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama)));
    $kategori   = trim($_POST['kategori']);
    $deskripsi  = trim($_POST['deskripsi']);
    $status     = $_POST['status'] ?? 'disetujui';
    $id_foto    = null;

    // ------- UPLOAD FOTO --------
    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {

        $uploadDir = __DIR__ . "/../../public/uploads/fasilitas/";

        // Buat direktori jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Validasi tipe file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['foto']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Tipe file tidak diizinkan. Hanya JPG, PNG, dan GIF.";
        } else {
            // Generate nama file unik
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $fileName = time() . "_" . uniqid() . "." . $ext;
            $uploadPath = $uploadDir . $fileName;

            // Upload file
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
                
                // Path relatif untuk disimpan di database
                $lokasi_file = "fasilitas/" . $fileName;

                // Insert ke tabel media dan ambil id_media
                $sqlMedia = "INSERT INTO media(lokasi_file, tipe_file, ukuran_file) 
                            VALUES ($1, $2, $3) RETURNING id_media";
                
                $file_size = filesize($uploadPath);
                
                $resMedia = pg_query_params($conn, $sqlMedia, [
                    $lokasi_file,
                    $file_type,
                    $file_size
                ]);

                if ($resMedia) {
                    $media = pg_fetch_assoc($resMedia);
                    $id_foto = $media['id_media'];
                    
                    // Debug: uncomment untuk melihat ID yang di-generate
                    // echo "Debug: ID Media = " . $id_foto . "<br>";
                } else {
                    $error = "Gagal menyimpan data media: " . pg_last_error($conn);
                }
            } else {
                $error = "Gagal mengupload file.";
            }
        }
    }

    // ------ INSERT TABLE FASILITAS ------
    if (empty($error)) {
        $sql = "
            INSERT INTO fasilitas (nama, slug, kategori, deskripsi, id_foto, status, dibuat_oleh, dibuat_pada)
            VALUES ($1, $2, $3, $4, $5, $6, $7, NOW())
        ";

        $result = pg_query_params($conn, $sql, [
            $nama, 
            $slug, 
            $kategori, 
            $deskripsi, 
            $id_foto,  // Pastikan ini tidak NULL jika upload berhasil
            $status, 
            $user_id
        ]);

        if ($result) {
            $_SESSION['success_message'] = "Fasilitas berhasil ditambahkan!";
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Gagal menyimpan data fasilitas: " . pg_last_error($conn);
        }
    }
}

include "../includes/header.php";
?>

<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="bi bi-building-add"></i> Tambah Fasilitas</h1>
            <small class="text-muted">Manajemen Fasilitas Laboratorium</small>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">Nama Fasilitas <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" required 
                           value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>">
                    <small class="text-muted">Contoh: Laboratorium Komputer, Ruang Server</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="kategori" class="form-control" required
                           value="<?= isset($_POST['kategori']) ? htmlspecialchars($_POST['kategori']) : '' ?>"
                           placeholder="Contoh: Laboratorium, Ruang Kelas, Perpustakaan">
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="4" 
                              placeholder="Jelaskan tentang fasilitas ini..."><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload Foto</label>
                    <input type="file" name="foto" class="form-control" accept="image/*" id="fotoInput">
                    <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 5MB.</small>
                    
                    <!-- Preview Image -->
                    <div id="imagePreview" class="mt-3" style="display:none;">
                        <img id="preview" src="" alt="Preview" style="max-width: 300px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                </div>

                <hr>

                <div class="d-flex gap-2">
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Fasilitas
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script untuk preview image -->
<script>
document.getElementById('fotoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php include "../includes/footer.php"; ?>