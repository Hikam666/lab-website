<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Tambah Album Galeri';

$currentUser = getCurrentUser();
$id_pengguna = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

$judul      = '';
$deskripsi  = '';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($judul === '') {
        $errors[] = 'Judul album wajib diisi.';
    }

    // Generate slug otomatis dari judul
    $slug = '';
    if ($judul !== '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $judul));
        $slug = trim($slug, '-');
    }

    if ($slug === '') {
        $errors[] = 'Slug album tidak valid (gagal dibuat dari judul).';
    }

    // Cek slug unik
    if ($slug !== '') {
        $check = pg_query_params(
            $conn,
            "SELECT id_album FROM galeri_album WHERE slug = $1",
            [$slug]
        );
        if ($check && pg_num_rows($check) > 0) {
            $errors[] = 'Slug otomatis dari judul sudah digunakan oleh album lain. Coba ubah judul sedikit.';
        }
    }

    // Siapkan variabel untuk id_cover (media)
    $id_cover = null;

    // Jika tidak ada error sejauh ini → boleh proses cover
    if (empty($errors) && isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Terjadi kesalahan saat upload cover album.';
        } else {
            // Upload cover sebagai media
            $upload_dir_fs = __DIR__ . '/../../uploads';
            $allowed_types = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size      = 5 * 1024 * 1024;

            $uploadResult = uploadFile($_FILES['cover'], $upload_dir_fs, $allowed_types, $max_size);
            if (!$uploadResult['success']) {
                $errors[] = 'Upload cover gagal: ' . $uploadResult['message'];
            } else {
                $filename = $uploadResult['filename'];
                $filepath = $upload_dir_fs . '/' . $filename;

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $filepath);
                finfo_close($finfo);

                $size = file_exists($filepath) ? filesize($filepath) : 0;

                // Insert ke media
                $sql_media = "INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh, ukuran_file)
                              VALUES ($1, $2, $3, $4, $5)
                              RETURNING id_media";
                $res_media = pg_query_params($conn, $sql_media, [
                    $filename,
                    $mime,
                    $judul,
                    $id_pengguna,
                    $size
                ]);

                if ($res_media && ($m = pg_fetch_assoc($res_media))) {
                    $id_cover = (int)$m['id_media'];
                } else {
                    $errors[] = 'Gagal menyimpan data cover album ke tabel media.';
                }
            }
        }
    }

    if (empty($errors)) {
        $status = isAdmin() ? 'disetujui' : 'diajukan';

        $sql = "INSERT INTO galeri_album (judul, slug, deskripsi, id_cover, status, dibuat_oleh)
                VALUES ($1, $2, $3, $4, $5, $6)
                RETURNING id_album";
        $res = pg_query_params($conn, $sql, [
            $judul,
            $slug,
            $deskripsi !== '' ? $deskripsi : null,
            $id_cover,
            $status,
            $id_pengguna
        ]);

        if ($res && ($row = pg_fetch_assoc($res))) {
            $id_album = (int)$row['id_album'];

            if (isAdmin()) {
                $ket = 'Admin membuat album galeri baru: "' . $judul . '" (ID=' . $id_album . ')';
                log_aktivitas($conn, 'CREATE', 'galeri_album', $id_album, $ket);
                setFlashMessage('Album berhasil dibuat.', 'success');
            } else {
                $ket = 'Operator mengajukan pembuatan album galeri baru: "' . $judul . '" (ID=' . $id_album . ')';
                log_aktivitas($conn, 'REQUEST_CREATE', 'galeri_album', $id_album, $ket);
                setFlashMessage('Album berhasil diajukan dan menunggu persetujuan admin.', 'warning');
            }

            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Gagal menyimpan data album ke database.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Tambah Album Galeri</h1>
            <p class="text-muted mb-0">Buat album baru untuk menampung foto-foto kegiatan.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul Album</label>
                    <input type="text" name="judul" id="judul" class="form-control"
                           value="<?php echo htmlspecialchars($judul); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="cover" class="form-label">
                        Cover Album (opsional)
                    </label>
                    <input type="file" name="cover" id="cover" class="form-control" accept="image/*">
                    <div class="form-text">
                        Gambar cover yang akan ditampilkan sebagai thumbnail album. Format: JPG, PNG, WebP, GIF. Maks 5MB.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="deskripsi" rows="4" class="form-control"
                              placeholder="Deskripsi singkat album"><?php echo htmlspecialchars($deskripsi); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Album
                </button>
                <a href="index.php" class="btn btn-link">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
