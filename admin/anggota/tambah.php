<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$active_page = 'anggota';
$page_title = 'Tambah Anggota';
$extra_css = [];

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $peran_lab = trim($_POST['peran_lab'] ?? '');
    $bio_html = trim($_POST['bio_html'] ?? '');
    $aktif = isset($_POST['aktif']) ? true : false;
    
    // Validation
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
        $check_email = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE email = $1", [$email]);
        if (pg_num_rows($check_email) > 0) {
            $errors[] = "Email sudah digunakan anggota lain";
        }
    }
    
    if (!empty($linkedin) && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
        $errors[] = "Format URL LinkedIn tidak valid";
    }
    
    if (!empty($bio_html) && mb_strlen($bio_html) > 5000) {
        $errors[] = "Bio maksimal 5000 karakter";
    }
    
    // Validate foto upload
    $id_foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto = $_FILES['foto'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $foto['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = "Format foto harus JPG, PNG, atau WebP";
        }
        
        // Validate file size (5MB)
        if ($foto['size'] > 5 * 1024 * 1024) {
            $errors[] = "Ukuran foto maksimal 5MB";
        }
    } elseif (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Foto wajib diupload";
    }

    if (empty($errors)) {
        pg_query($conn, "BEGIN");
        
        try {
            $slug = generateSlug($nama);
            $check_slug = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE slug = $1", [$slug]);
            if (pg_num_rows($check_slug) > 0) {
                $counter = 1;
                $original_slug = $slug;
                while (pg_num_rows($check_slug) > 0) {
                    $slug = $original_slug . '-' . $counter;
                    $check_slug = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE slug = $1", [$slug]);
                    $counter++;
                }
            }

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto = $_FILES['foto'];
                
                $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                $filename = 'anggota-' . time() . '-' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../uploads/anggota/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }

                if (move_uploaded_file($foto['tmp_name'], $upload_path . $filename)) {
                    $lokasi_file = 'anggota/' . $filename;
                    $ukuran_file = $foto['size'];
                    $tipe_file = $mime_type;
                    $keterangan_alt = $nama;
                    
                    $media_sql = "INSERT INTO media (lokasi_file, ukuran_file, tipe_file, keterangan_alt, dibuat_oleh, dibuat_pada) 
                                  VALUES ($1, $2, $3, $4, $5, NOW()) 
                                  RETURNING id_media";
                    $media_result = pg_query_params($conn, $media_sql, [
                        $lokasi_file,
                        $ukuran_file,
                        $tipe_file,
                        $keterangan_alt,
                        $_SESSION['user_id']
                    ]);
                    
                    if ($media_result) {
                        $id_foto = pg_fetch_assoc($media_result)['id_media'];
                    } else {
                        throw new Exception("Gagal menyimpan foto ke media");
                    }
                } else {
                    throw new Exception("Gagal mengupload foto");
                }
            }
            
            $urutan_result = pg_query($conn, "SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM anggota_lab");
            $urutan = pg_fetch_assoc($urutan_result)['next_urutan'];

            $sql = "INSERT INTO anggota_lab (
                        nama, slug, email, linkedin, peran_lab, bio_html, 
                        id_foto, aktif, urutan, dibuat_pada, diperbarui_pada
                    ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW(), NOW())";
            
            $result = pg_query_params($conn, $sql, [
                $nama,
                $slug,
                $email,
                $linkedin ?: null,
                $peran_lab ?: null,
                $bio_html ?: null,
                $id_foto,
                $aktif ? 't' : 'f',
                $urutan
            ]);
            
            if ($result) {
                pg_query($conn, "COMMIT");
                setFlashMessage('Anggota berhasil ditambahkan', 'success');
                header('Location: ' . getAdminUrl('anggota/index.php'));
                exit;
            } else {
                throw new Exception("Gagal menyimpan data anggota");
            }
            
        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
    }
}

$form_data = $_SESSION['form_data'] ?? [];
$form_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-person-plus me-2"></i>Tambah Anggota</h1>
            <p class="text-muted mb-0">Tambah anggota peneliti baru</p>
        </div>
        <div>
            <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Kembali
            </a>
        </div>
    </div>
</div>

<?php if (!empty($form_errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5 class="alert-heading">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Terjadi Kesalahan
    </h5>
    <ul class="mb-0">
        <?php foreach ($form_errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" id="formAnggota">
    
    <div class="row">
        
        <div class="col-lg-8">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Data Utama</h5>
                </div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">
                            Nama Lengkap <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="nama" 
                               name="nama" 
                               maxlength="255"
                               value="<?php echo htmlspecialchars($form_data['nama'] ?? ''); ?>" 
                               required>
                        <div class="form-text">Maksimal 255 karakter.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                               required>
                        <div class="form-text">Email harus unique (tidak boleh sama dengan anggota lain)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="linkedin" class="form-label">
                            LinkedIn URL
                        </label>
                        <input type="url" 
                               class="form-control" 
                               id="linkedin" 
                               name="linkedin" 
                               value="<?php echo htmlspecialchars($form_data['linkedin'] ?? ''); ?>">
                        <div class="form-text">Contoh: https://linkedin.com/in/username (opsional)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="peran_lab" class="form-label">
                            Peran Lab
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="peran_lab" 
                               name="peran_lab" 
                               value="<?php echo htmlspecialchars($form_data['peran_lab'] ?? ''); ?>">
                        <div class="form-text">Jabatan/posisi di laboratorium (opsional)</div>
                    </div>
                    
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Biografi</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="bio_html" class="form-label">Bio/Deskripsi</label>
                        <textarea class="form-control" 
                                  id="bio_html" 
                                  name="bio_html" 
                                  rows="10"><?php echo htmlspecialchars($form_data['bio_html'] ?? ''); ?></textarea>
                        <div class="form-text">Maksimal 5000 karakter. Gunakan spasi dan baris baru untuk memisahkan paragraf.</div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="col-lg-4">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-image me-2"></i>Foto Profil</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="foto" class="form-label">
                            Upload Foto <span class="text-danger">*</span>
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="foto" 
                               name="foto" 
                               accept="image/jpeg,image/jpg,image/png,image/webp"
                               required>
                        <div class="form-text">
                            Format: JPG, PNG, WebP<br>
                            Maksimal: 5MB
                        </div>
                    </div>
                    
                    <div id="fotoPreview" class="anggota-foto-preview-container" style="display: none;">
                        <img src="" alt="Preview" class="img-fluid rounded anggota-new-photo-preview">
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-toggle-on me-2"></i>Status</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="aktif" 
                               name="aktif" 
                               <?php echo (!isset($form_data['aktif']) || $form_data['aktif']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aktif">
                            Aktif (tampil di website)
                        </label>
                    </div>
                    <div class="form-text mt-2">
                        Jika aktif, anggota akan tampil di halaman profil laboratorium
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Simpan Anggota
                        </button>
                        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>
                            Batal
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
        
    </div>
    
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Preview foto
    const fotoInput = document.getElementById('foto');
    const fotoPreview = document.getElementById('fotoPreview');
    
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file maksimal 5MB');
                    this.value = '';
                    fotoPreview.style.display = 'none';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file harus JPG, PNG, atau WebP');
                    this.value = '';
                    fotoPreview.style.display = 'none';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    fotoPreview.querySelector('img').src = e.target.result;
                    fotoPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                fotoPreview.style.display = 'none';
            }
        });
    }
    
    // Form validation (tanpa tinymce.triggerSave)
    const form = document.getElementById('formAnggota');
    if (form) {
        form.addEventListener('submit', function(e) {
            
            // Basic validation
            const nama = document.getElementById('nama').value.trim();
            const email = document.getElementById('email').value.trim();
            const foto = document.getElementById('foto').files[0];
            
            if (!nama) {
                alert('Nama wajib diisi');
                e.preventDefault();
                return false;
            }
            
            if (!email) {
                alert('Email wajib diisi');
                e.preventDefault();
                return false;
            }
            
            if (!foto) {
                // Di halaman tambah, foto wajib
                alert('Foto wajib diupload');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
    
});
</script>

<?php

include __DIR__ . '/../includes/footer.php';
?>