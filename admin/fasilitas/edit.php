<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/functions.php";
require "../includes/auth.php";

$conn = getDBConnection();
requireLogin();

// Ambil ID fasilitas
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Ambil data fasilitas + media
$query = "
    SELECT f.*, m.lokasi_file AS foto, m.id_media
    FROM fasilitas f
    LEFT JOIN media m ON f.id_foto = m.id_media
    WHERE f.id_fasilitas = $1
";
$result = pg_query_params($conn, $query, [$id]);
$data = pg_fetch_assoc($result);

if (!$data) {
    die("Fasilitas tidak ditemukan!");
}

// Handle form update
if (isset($_POST['submit'])) {

    $nama       = $_POST['nama'];
    $slug       = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama)));
    $kategori   = $_POST['kategori'];
    $deskripsi  = $_POST['deskripsi'];
    $status     = $_POST['status'];
    $id_foto    = $data['id_media'];

    // Upload foto baru jika ada
    if (!empty($_FILES['foto']['name'])) {

        $uploadBase = __DIR__ . "/../../public/uploads/fasilitas/";

        if (!is_dir($uploadBase)) {
            mkdir($uploadBase, 0777, true);
        }

        $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9.\-_]/', '', $_FILES['foto']['name']);
        $targetFile = $uploadBase . $fileName;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {

            // Jika sudah ada foto sebelumnya, update yang ada
            if ($id_foto) {
                $lokasi_file = "fasilitas/" . $fileName;
                pg_query_params($conn, "UPDATE media SET lokasi_file = $1 WHERE id_media = $2", [$lokasi_file, $id_foto]);
            } else {
                // Simpan sebagai media baru
                $lokasi_file = "fasilitas/" . $fileName;
                $resMedia = pg_query_params($conn, "INSERT INTO media (lokasi_file) VALUES ($1) RETURNING id_media", [$lokasi_file]);
                $mediaRow = pg_fetch_assoc($resMedia);
                $id_foto = $mediaRow['id_media'];
            }
        }
    }

    // Update fasilitas
    $qUpdate = "
        UPDATE fasilitas
        SET nama = $1, slug = $2, kategori = $3, deskripsi = $4, id_foto = $5, status = $6
        WHERE id_fasilitas = $7
    ";

    $resUpdate = pg_query_params($conn, $qUpdate, [
        $nama, $slug, $kategori, $deskripsi, $id_foto, $status, $id
    ]);

    if ($resUpdate) {
        header("Location: index.php?updated=1");
        exit;
    } else {
        $error = "Gagal mengupdate data!";
    }
}

include "../includes/header.php";
?>

<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-pencil"></i> Edit Fasilitas</h2>
                <p class="text-muted">Edit data fasilitas laboratorium</p>
            </div>
            <a href="<?php echo getAdminUrl('fasilitas/index.php'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
    </div>



    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">

        <div class="mb-3">
            <label class="form-label">Nama Fasilitas</label>
            <input type="text" name="nama" class="form-control" required
                   value="<?= htmlspecialchars($data['nama']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control"
                   value="<?= htmlspecialchars($data['kategori']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="4"><?= htmlspecialchars($data['deskripsi']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Foto Lama</label><br>
            <?php if (!empty($data['foto'])): ?>
                <img src="<?= SITE_URL . '/uploads/' . $data['foto'] ?>" width="120" class="rounded border mb-2">
            <?php else: ?>
                <p class="text-muted">Tidak ada foto</p>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Ganti Foto Baru</label>
            <input type="file" name="foto" class="form-control" accept="image/*">
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="disetujui" <?= $data['status'] === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $data['status'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>

        <button type="submit" name="submit" class="btn btn-primary">Update</button>
        <a href="<?= getAdminUrl('fasilitas/index.php'); ?>" class="btn btn-secondary">Batal</a>

    </form>

</div>

<?php include "../includes/footer.php"; ?>
