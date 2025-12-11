<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Edit Album Galeri';

if (!isset($_GET['id'])) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$id_album = (int)$_GET['id'];

$sql_album = "
    SELECT 
        ga.id_album,
        ga.judul,
        ga.slug,
        ga.deskripsi,
        ga.status,
        ga.id_cover,
        m.lokasi_file AS cover_image
    FROM galeri_album ga
    LEFT JOIN media m ON ga.id_cover = m.id_media
    WHERE ga.id_album = $1
";
$res_album = pg_query_params($conn, $sql_album, [$id_album]);
if (!$res_album || pg_num_rows($res_album) === 0) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$album       = pg_fetch_assoc($res_album);
$judul       = $album['judul'];
$deskripsi   = $album['deskripsi'];
$cover_image = $album['cover_image'] ?? null;
$id_cover    = $album['id_cover'] ?? null;

$errors      = [];

$currentUser = getCurrentUser();
$id_pengguna = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

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

    // cek slug unik (tidak boleh bentrok dengan album lain)
    if ($slug !== '') {
        $check = pg_query_params(
            $conn,
            "SELECT id_album FROM galeri_album WHERE slug = $1 AND id_album <> $2",
            [$slug, $id_album]
        );
        if ($check && pg_num_rows($check) > 0) {
            $errors[] = 'Slug otomatis dari judul sudah digunakan oleh album lain. Coba ubah judul.';
        }
    }

    // Handle upload cover baru (opsional)
    $new_cover_uploaded = false;
    $new_id_cover       = $id_cover; // default: tetap pakai yang lama

    if (empty($errors) && isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Terjadi kesalahan saat upload cover album.';
        } else {
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
                    $new_id_cover       = (int)$m['id_media'];
                    $new_cover_uploaded = true;
                } else {
                    $errors[] = 'Gagal menyimpan data cover album ke tabel media.';
                }
            }
        }
    }

    if (empty($errors)) {
        $status_baru = isAdmin() ? 'disetujui' : 'diajukan';

        $sql_update = "
            UPDATE galeri_album
            SET judul = $1,
                slug = $2,
                deskripsi = $3,
                id_cover = $4,
                status = $5,
                diperbarui_pada = NOW()
            WHERE id_album = $6
        ";

        $ok = pg_query_params($conn, $sql_update, [
            $judul,
            $slug,
            $deskripsi !== '' ? $deskripsi : null,
            $new_id_cover,
            $status_baru,
            $id_album
        ]);

        if ($ok) {
            if (isAdmin()) {
                $ket = 'Admin mengubah album galeri: "' . $judul . '" (ID=' . $id_album . ')';
                log_aktivitas($conn, 'UPDATE', 'galeri_album', $id_album, $ket);
                setFlashMessage('Album berhasil diperbarui.', 'success');
            } else {
                $ket = 'Operator mengajukan perubahan album galeri: "' . $judul . '" (ID=' . $id_album . ')';
                log_aktivitas($conn, 'REQUEST_UPDATE', 'galeri_album', $id_album, $ket);
                setFlashMessage('Perubahan album diajukan dan menunggu persetujuan admin.', 'warning');
            }

            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Gagal memperbarui album.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Edit Album Galeri</h1>
            <p class="text-muted mb-0">Perbarui informasi album galeri.</p>
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

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul Album</label>
                    <input type="text" name="judul" id="judul" class="form-control"
                           value="<?php echo htmlspecialchars($judul); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cover Album</label><br>
                    <?php 
                    $cover_url = (!empty($cover_image) && defined('SITE_URL')) ? SITE_URL . '/uploads/' . $cover_image : null;
                    $default_img = (defined('SITE_URL')) ? SITE_URL . '/assets/img/default-cover.jpg' : 'default-cover.jpg';
                    $image_id = 'galeri_cover_preview';
                    ?>
                    <div id="image_preview_container" class="mb-2" style="width: 220px; height: 140px;">
                        <img id="<?= $image_id ?>" 
                              src="<?= $cover_url ?: $default_img ?>" 
                              width="220" height="140" 
                              class="rounded border" 
                              style="object-fit:cover;">
                    </div>
                    <?php if (!$cover_url): ?>
                        <p class="text-muted small" id="no_cover_text" style="display: <?= $cover_url ? 'none' : 'block' ?>;">Belum ada cover album.</p>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ganti Cover Baru</label>
                    <input type="file" name="cover" id="cover_input" class="form-control" accept="image/*">
                    <div class="form-text">
                        Pilih file untuk mengganti cover album (opsional). Format: JPG, PNG, WebP, GIF. Maks 5MB.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="deskripsi" rows="4" class="form-control"><?php 
                        echo htmlspecialchars($deskripsi); 
                    ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                </button>
                <a href="index.php" class="btn btn-link">Batal</a>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('cover_input').addEventListener('change', function(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('galeri_cover_preview');
    const noCoverText = document.getElementById('no_cover_text');
    
    const coverLamaUrl = '<?= $cover_url ?>'; 
    const defaultImg = '<?= $default_img ?>'; 

    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            if (noCoverText) {
                noCoverText.style.display = 'none';
            }
        };

        reader.readAsDataURL(file);
    } else {
        preview.src = coverLamaUrl || defaultImg;
        
        if (!coverLamaUrl && noCoverText) {
             noCoverText.style.display = 'block';
        }
    }
});
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>