<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/functions.php";
require "../includes/auth.php";

$conn = getDBConnection();
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle submit
if (isset($_POST['submit'])) {

    $nama       = trim($_POST['nama']);
    $slug       = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama)));
    $kategori   = trim($_POST['kategori']);
    $deskripsi  = trim($_POST['deskripsi']);
    $status     = $_POST['status'] ?? 'disetujui';
    $id_foto    = null;

    // ------- UPLOAD FOTO --------
    if (!empty($_FILES['foto']['name'])) {

        $uploadDir = __DIR__ . "/../../public/uploads/fasilitas/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9.\-_]/', '', $_FILES['foto']['name']);
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
            
            $lokasi_file = "fasilitas/" . $fileName;

            $sqlMedia = "INSERT INTO media(lokasi_file) VALUES ($1) RETURNING id_media";
            $resMedia = pg_query_params($conn, $sqlMedia, [$lokasi_file]);

            if ($resMedia) {
                $media = pg_fetch_assoc($resMedia);
                $id_foto = $media['id_media'];
            }
        }
    }

    // ------ INSERT TABLE FASILITAS ------
    $sql = "
        INSERT INTO fasilitas (nama, slug, kategori, deskripsi, id_foto, status, dibuat_oleh, diperbarui_pada)
        VALUES ($1, $2, $3, $4, $5, $6, $7, NULL)
    ";

    $result = pg_query_params($conn, $sql, [
        $nama, $slug, $kategori, $deskripsi, $id_foto, $status, $user_id
    ]);

    if ($result) {
        header("Location: index.php?success=1");
        exit;
    } else {
        $error = "Gagal menyimpan data fasilitas!";
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
        <a href="<?php echo getAdminUrl('fasilitas/index.php'); ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="card card-body">

        <div class="mb-3">
            <label class="form-label">Nama Fasilitas</label>
            <input type="text" name="nama" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control"
                placeholder="contoh: Laboratorium, Ruang Kelas" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="4"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Upload Foto</label>
            <input type="file" name="foto" class="form-control" accept="image/*">
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="disetujui">Disetujui</option>
                <option value="ditolak">Ditolak</option>
            </select>
        </div>

                <button type="submit" name="submit" class="btn btn-primary">Update</button>
        <a href="<?= getAdminUrl('fasilitas/index.php'); ?>" class="btn btn-secondary">Batal</a>
    </form>
</div>

<?php include "../includes/footer.php"; ?>
