<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/functions.php";
require "../includes/auth.php";
requireLogin();
$conn = getDBConnection();

$page_title  = "Edit Fasilitas";
$active_page = "fasilitas";

if (!function_exists('getLoggedUserIdFallback')) {
    function getLoggedUserIdFallback() {
        if (function_exists('getCurrentUser')) {
            $u = getCurrentUser();
            if (is_array($u)) {
                if (!empty($u['id']))          return $u['id'];
                if (!empty($u['id_pengguna'])) return $u['id_pengguna'];
            }
        }
        if (isset($_SESSION['user_id']))       return $_SESSION['user_id'];
        if (isset($_SESSION['id_pengguna']))   return $_SESSION['id_pengguna'];
        return null;
    }
}
if (!function_exists('generateSlugDasar')) {
    function generateSlugDasar($text) {
        $text = trim($text);
        if ($text === '') return 'fasilitas-'.time();
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/i', '', $text); 
        $text = preg_replace('/\s+/', '-', $text); 
        $text = trim($text, '-');
        return $text !== '' ? $text : 'fasilitas-'.time();
    }
}

if (!function_exists('generateUniqueSlugFasilitas')) {
    function generateUniqueSlugFasilitas($conn, $nama, $exclude_id = null) {
        $base_slug = generateSlugDasar($nama);
        $slug      = $base_slug;
        $i         = 2;
        while (true) {
            if ($exclude_id) {
                $sql = "SELECT 1 FROM fasilitas WHERE slug = $1 AND id_fasilitas <> $2 LIMIT 1";
                $res = pg_query_params($conn, $sql, [$slug, $exclude_id]);
            } else {
                $sql = "SELECT 1 FROM fasilitas WHERE slug = $1 LIMIT 1";
                $res = pg_query_params($conn, $sql, [$slug]);
            }
            if (!$res || pg_num_rows($res) === 0) {
                return $slug;
            }
            $slug = $base_slug . '-' . $i;
            $i++;
        }
    }
}

$user_id = getLoggedUserIdFallback();
$id = $_GET['id'] ?? null;

if (!$id || !ctype_digit($id)) {
    if (function_exists('setFlashMessage')) {
        setFlashMessage("ID Fasilitas tidak valid.", "danger");
    }
    header("Location: index.php");
    exit;
}
$id = (int)$id;

// 2. Ambil data fasilitas + media awal
$query = "
    SELECT f.*, m.lokasi_file AS foto, m.id_media
    FROM fasilitas f
    LEFT JOIN media m ON f.id_foto = m.id_media
    WHERE f.id_fasilitas = $1
";
$result = pg_query_params($conn, $query, [$id]);
$data   = pg_fetch_assoc($result);
if (!$data) {
    if (function_exists('setFlashMessage')) {
        setFlashMessage("Fasilitas dengan ID {$id} tidak ditemukan.", "danger");
    }
    header("Location: index.php");
    exit;
}
$error = null;

// 3. Logika Pemrosesan POST
if (isset($_POST['submit'])) {
    $nama      = trim($_POST['nama'] ?? '');
    $kategori  = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    $data['nama']      = $nama; 
    $data['kategori']  = $kategori;
    $data['deskripsi'] = $deskripsi;

    // A. Validasi
    if ($nama === '') {
        $error = "Nama fasilitas wajib diisi.";
    } elseif ($kategori === '') {
        $error = "Kategori fasilitas wajib diisi.";
    }

    $slug      = generateUniqueSlugFasilitas($conn, $nama, $id);
    $id_foto   = $data['id_media']; 
    $foto_lama = $data['foto'];     

    // LOGIKA PENENTUAN STATUS OTOMATIS

    if (function_exists('isAdmin') && isAdmin()) {
        $status_final = $data['status'];
        // Jika admin submit, dan statusnya draft atau diajukan, set ke disetujui.
        if ($status_final === 'draft' || $status_final === 'diajukan') {
            $status_final = 'disetujui';
        }
    } else {
        $status_final = 'diajukan';
    }

    if (empty($error) && !empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadBase = __DIR__ . "/../../uploads/fasilitas/";
        if (!is_dir($uploadBase)) {
            if (!mkdir($uploadBase, 0777, true)) {
                $error = "Gagal membuat direktori upload: " . $uploadBase;
            }
        }
        if (empty($error)) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $file_type     = $_FILES['foto']['type'];
            $file_size     = $_FILES['foto']['size'];
            $max_size      = 5 * 1024 * 1024; 
            if (!in_array($file_type, $allowed_types, true)) {
                $error = "Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, WebP.";
            } elseif ($file_size > $max_size) {
                $error = "Ukuran file melebihi batas maksimal 5MB.";
            } else {
                $fileInfo   = pathinfo($_FILES['foto']['name']);
                $safeName   = preg_replace('/[^A-Za-z0-9\-\_]/', '', $fileInfo['filename']);
                $extension  = strtolower($fileInfo['extension']);
                $fileName   = time() . "_" . uniqid() . "." . $extension; 
                $targetFile = $uploadBase . $fileName; 
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
                    $lokasi_file_baru = "fasilitas/" . $fileName; 
                    if ($id_foto && $foto_lama) {
                        $oldFilePath = __DIR__ . "/../../uploads/" . $foto_lama; 
                        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                            @unlink($oldFilePath); 
                        }
                    }
                    if ($id_foto) {
                        $qMedia = "
                            UPDATE media 
                            SET lokasi_file = $1, 
                                tipe_file = $4, 
                                ukuran_file = $5,
                                dibuat_oleh = $2 
                            WHERE id_media = $3
                        ";
                        $resMediaUpdate = pg_query_params($conn, $qMedia, [$lokasi_file_baru, $user_id, $id_foto, $file_type, $file_size]);
                        if (!$resMediaUpdate) {
                             $error = "Gagal update record media: " . pg_last_error($conn);
                        }
                    } else {
                        $resMedia = pg_query_params(
                            $conn,
                            "INSERT INTO media (lokasi_file, tipe_file, ukuran_file, dibuat_oleh, dibuat_pada) 
                             VALUES ($1, $2, $3, $4, NOW()) 
                             RETURNING id_media",
                            [$lokasi_file_baru, $file_type, $file_size, $user_id]
                        );
                        if ($resMedia) {
                            $mediaRow = pg_fetch_assoc($resMedia);
                            $id_foto  = $mediaRow['id_media'] ?? null;
                        } else {
                            $error = "Gagal insert record media: " . pg_last_error($conn);
                        }
                    }
                    if (!$id_foto && empty($error)) {
                        $error = "Gagal mendapatkan ID media setelah upload/insert.";
                    }
                    $data['id_media'] = $id_foto;
                    $data['foto']     = $lokasi_file_baru;
                } else {
                    $error = "Gagal memindahkan file foto! Periksa izin file atau folder.";
                }
            }
        }
    } elseif (isset($_FILES['foto']['error']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "Terjadi kesalahan saat upload file. Error code: " . $_FILES['foto']['error'];
    }
    if (empty($error)) {
        $qUpdate = "
            UPDATE fasilitas
            SET nama      = $1,
                slug      = $2,
                kategori  = $3,
                deskripsi = $4,
                id_foto   = $5,
                status    = $6,
                diperbarui_pada = NOW()
            WHERE id_fasilitas = $7
        ";
        $final_id_foto = $id_foto ?? null; 
        $resUpdate = pg_query_params($conn, $qUpdate, [
            $nama,
            $slug,
            $kategori !== '' ? $kategori : null,
            $deskripsi !== '' ? $deskripsi : null,
            $final_id_foto,
            $status_final, 
            $id
        ]);
        if ($resUpdate) {
            if (function_exists('log_aktivitas')) {
                $activity_type = isAdmin() ? 'UPDATE' : 'REQUEST_UPDATE';
                $ket = ($activity_type === 'UPDATE' ? 'Mengubah' : 'Mengajukan perubahan') . " fasilitas: {$nama} (ID: {$id}, status: {$status_final})";
                log_aktivitas($conn, $activity_type, 'fasilitas', $id, $ket);
            }
            if (function_exists('setFlashMessage')) {
                $msg = isAdmin() 
                       ? "Fasilitas '{$nama}' berhasil diperbarui." 
                       : "Perubahan fasilitas '{$nama}' berhasil diajukan dan menunggu persetujuan admin.";
                $msg_type = isAdmin() ? 'success' : 'warning';
                setFlashMessage($msg, $msg_type);
            }
            header("Location: index.php");
            exit;
        } else {
            $error = "Gagal mengupdate data Fasilitas! Error DB: " . pg_last_error($conn);
        }
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
    <?php 
    if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
             <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
             <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Nama Fasilitas</label>
            <input type="text" name="nama" class="form-control" required
                   value="<?= htmlspecialchars($data['nama'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control" required
                   value="<?= htmlspecialchars($data['kategori'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="4"><?= htmlspecialchars($data['deskripsi'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Foto Fasilitas</label><br>
            <?php 
            $foto_url = (!empty($data['foto']) && defined('SITE_URL')) ? SITE_URL . '/uploads/' . $data['foto'] : null;
            $default_img = (defined('SITE_URL')) ? SITE_URL . '/assets/img/default-cover.jpg' : 'default-cover.jpg';
            $image_id = 'fasilitas_foto_preview';
            ?>
            <div id="image_preview_container" class="mb-2" style="width: 160px; height: 160px;">
                <img id="<?= $image_id ?>" 
                      src="<?= $foto_url ?: $default_img ?>" 
                      width="160" height="160" 
                      class="rounded border" 
                      style="object-fit:cover;">
            </div>
            <?php if (!$foto_url): ?>
                <p class="text-muted small" id="no_foto_text" style="display: <?= $foto_url ? 'none' : 'block' ?>;">Tidak ada foto</p>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Ganti Foto Baru</label>
            <input type="file" name="foto" id="foto_input" class="form-control" accept="image/*">
            <div class="form-text">
                Pilih file untuk mengganti foto fasilitas (opsional). Format: JPG, PNG, GIF, WebP. Maksimal 5MB.
            </div>
        </div>
                <div class="mb-3">
            <label class="form-label">Status Saat Ini</label>
            <p class="form-control-static">
                <?php $current_status = $data['status'] ?? 'draft'; ?>
                <strong><?= htmlspecialchars(ucfirst($current_status)) ?></strong>
                <span class="badge bg-<?= 
                    ($current_status === 'disetujui') ? 'success' : 
                    (($current_status === 'diajukan') ? 'warning' : 
                    (($current_status === 'ditolak') ? 'danger' : 'secondary')) 
                ?>"><?= htmlspecialchars(ucfirst($current_status)) ?></span>
            </p>
            <?php if (! (function_exists('isAdmin') && isAdmin())): ?>
                <small class="text-muted">
                    *Karena Anda operator, setiap perubahan akan diatur ulang ke status <strong>diajukan</strong> setelah disimpan.
                </small>
            <?php else: ?>
                <small class="text-muted">
                    *Sebagai Admin, status akan otomatis disetujui.
                </small>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <button type="submit" name="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i>Update
            </button>
            <a href="<?= getAdminUrl('fasilitas/index.php'); ?>" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
<script>
document.getElementById('foto_input').addEventListener('change', function(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('fasilitas_foto_preview');
    const noFotoText = document.getElementById('no_foto_text');
    const fotoLamaUrl = '<?= $foto_url ?>'; 
    const defaultImg = '<?= $default_img ?>'; 

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            if (noFotoText) {
                noFotoText.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    } else {
        preview.src = fotoLamaUrl || defaultImg;
        if (!fotoLamaUrl && noFotoText) {
             noFotoText.style.display = 'block';
        }
    }
});
</script>
<?php include "../includes/footer.php"; ?>