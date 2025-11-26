<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$active_page = 'anggota';
$page_title = 'Edit Anggota';
$conn = getDBConnection();

$id_anggota = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_anggota <= 0) {
    setFlashMessage('ID anggota tidak valid', 'error');
    header('Location: ' . getAdminUrl('anggota/index.php'));
    exit;
}

// Ambil data anggota dari database
$sql = "SELECT a.*, m.lokasi_file as foto, m.id_media as id_foto_current
        FROM anggota_lab a
        LEFT JOIN media m ON a.id_foto = m.id_media
        WHERE a.id_anggota = $1";
$result = pg_query_params($conn, $sql, [$id_anggota]);

if (!$result || pg_num_rows($result) === 0) {
    setFlashMessage('Anggota tidak ditemukan', 'error');
    header('Location: ' . getAdminUrl('anggota/index.php'));
    exit;
}

$anggota = pg_fetch_assoc($result);

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Ambil data form
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $peran_lab = trim($_POST['peran_lab'] ?? '');
    $bio_html = trim($_POST['bio_html'] ?? '');
    $aktif = isset($_POST['aktif']) ? true : false;
    
    // Validation (Server-side)
    if (empty($nama)) {
        $errors[] = "Nama wajib diisi";
    } elseif (mb_strlen($nama) > 255) { 
        $errors[] = "Nama maksimal 255 karakter";
    }
    
    if (empty($email)) {
        $errors[] = "Email wajib diisi";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    } else {
        $check_email = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE email = $1 AND id_anggota != $2", [$email, $id_anggota]);
        if (pg_num_rows($check_email) > 0) {
            $errors[] = "Email sudah digunakan anggota lain";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
    } else {
        pg_query($conn, "BEGIN");
        try {
            $slug = $anggota['slug'];
            if ($nama !== $anggota['nama']) {
                $slug = generateSlug($nama);
            }
            
            $id_foto_final = $anggota['id_foto'];

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto = $_FILES['foto'];
                
                $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                $filename = 'anggota-' . time() . '-' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../uploads/anggota/';
                
                if (!file_exists($upload_path)) mkdir($upload_path, 0755, true);
                
                if (move_uploaded_file($foto['tmp_name'], $upload_path . $filename)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $upload_path . $filename);
                    finfo_close($finfo);
                    
                    $media_sql = "INSERT INTO media (lokasi_file, ukuran_file, tipe_file, keterangan_alt, dibuat_oleh, dibuat_pada) 
                                  VALUES ($1, $2, $3, $4, $5, NOW()) RETURNING id_media";
                    $media_result = pg_query_params($conn, $media_sql, [
                        'anggota/' . $filename, $foto['size'], $mime_type, $nama, $_SESSION['user_id']
                    ]);
                    
                    if ($media_result) $id_foto_final = pg_fetch_assoc($media_result)['id_media'];
                }
            }
            
            // Update anggota
            $sql = "UPDATE anggota_lab SET nama=$1, slug=$2, email=$3, linkedin=$4, peran_lab=$5, bio_html=$6, id_foto=$7, aktif=$8, diperbarui_pada=NOW() WHERE id_anggota=$9";
            $result = pg_query_params($conn, $sql, [$nama, $slug, $email, $linkedin?:null, $peran_lab?:null, $bio_html?:null, $id_foto_final, $aktif?'t':'f', $id_anggota]);
            
            if ($result) {
                pg_query($conn, "COMMIT");
                setFlashMessage('Anggota berhasil diperbarui', 'success');
                header('Location: ' . getAdminUrl('anggota/index.php'));
                exit;
            } else {
                 throw new Exception("Gagal memperbarui data anggota");
            }
        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $errors[] = $e->getMessage();
            $_SESSION['form_errors'] = $errors;
        }
    }
}

$form_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// --- Tampilan HTML ---
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-pencil me-2"></i>Edit Anggota</h1>
            <p class="text-muted mb-0">Edit: <?php echo htmlspecialchars($anggota['nama']); ?></p>
        </div>
        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
    </div>
</div>

<?php if (!empty($form_errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <h5><i class="bi bi-exclamation-triangle me-2"></i>Terjadi Kesalahan</h5>
    <ul class="mb-0"><?php foreach ($form_errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="formAnggota">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-person me-2"></i>Data Utama</h5></div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="nama" 
                               name="nama" 
                               maxlength="255"
                               value="<?php echo htmlspecialchars($anggota['nama']); ?>"> 
                               </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($anggota['email']); ?>"> 
                               </div>
                    
                    <div class="mb-3">
                        <label for="linkedin" class="form-label">LinkedIn URL</label>
                        <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($anggota['linkedin']??''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="peran_lab" class="form-label">Peran Lab</label>
                        <input type="text" class="form-control" id="peran_lab" name="peran_lab" value="<?php echo htmlspecialchars($anggota['peran_lab']??''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Biografi</h5></div>
                <div class="card-body">
                    <textarea class="form-control" id="bio_html" name="bio_html" rows="10"><?php echo htmlspecialchars($anggota['bio_html']??''); ?></textarea>
                    <div class="form-text">Maksimal 5000 karakter. Gunakan spasi dan baris baru untuk memisahkan paragraf.</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-image me-2"></i>Foto Profil</h5></div>
                <div class="card-body">
                    <?php if ($anggota['foto']): ?>
                    <div class="mb-3 anggota-current-photo-container" id="currentPhotoContainer">
                        <img src="<?php echo SITE_URL.'/uploads/'.$anggota['foto']; ?>" 
                             class="img-fluid rounded anggota-current-photo" 
                             id="currentPhoto">
                    </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                    <div class="form-text">Upload foto baru (opsional)</div>
                    <div id="fotoPreview" class="anggota-foto-preview-container mt-3" style="display:none">
                        <img src="" class="img-fluid rounded anggota-new-photo-preview">
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-toggle-on me-2"></i>Status</h5></div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?php echo $anggota['aktif']?'checked':''; ?>>
                        <label class="form-check-label" for="aktif">Aktif (tampil di website)</label>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan Perubahan</button>
                        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const fotoInput = document.getElementById('foto');
    const fotoPreview = document.getElementById('fotoPreview');
    const currentPhotoContainer = document.getElementById('currentPhotoContainer');
    
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5*1024*1024) { alert('Max 5MB'); this.value=''; return; }
                const reader = new FileReader();
                reader.onload = function(e) {
                    fotoPreview.querySelector('img').src = e.target.result;
                    fotoPreview.style.display = 'block';
                    if (currentPhotoContainer) currentPhotoContainer.style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                fotoPreview.style.display = 'none';
                if (currentPhotoContainer) currentPhotoContainer.style.display = 'block';
            }
        });
    }
});
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>