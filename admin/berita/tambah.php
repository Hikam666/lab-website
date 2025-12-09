<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (function_exists('isLoggedIn') && !isLoggedIn()) {
    redirectAdmin('login.php');
}

requireLogin();
$user = function_exists('getCurrentUser') ? getCurrentUser() : null;



$active_page = 'anggota';
$page_title = 'Tambah Anggota';
$extra_css = [];

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $nama = trim($_POST['judul'] ?? '');
    $email = trim($_POST['jenis'] ?? '');
    $linkedin = trim($_POST['ringkasan'] ?? '');
    $peran_lab = trim($_POST['isiberita'] ?? '');
    $bio_html = trim($_POST['catatan_review'] ?? '');
    $aktif = trim($_POST['status'] ?? '');
    
    // Validation
   
   
    
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
                $upload_path = __DIR__ . '/../../uploads/berita/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }

                if (move_uploaded_file($foto['tmp_name'], $upload_path . $filename)) {
                    $lokasi_file = 'berita/' . $filename;
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
                        var_dump('data anda');
                        throw new Exception("Gagal menyimpan foto ke media");
                    }
                } else {
                    var_dump('data anda');
                    throw new Exception("Gagal mengupload foto");
                }
            }
            
           
            $tanggal = date('Y-m-d H:i:s');
            $dibuatOleh = $user['id'];

            $sql = "INSERT INTO berita (
                        judul, slug, jenis, ringkasan, isi_html, catatan_review, 
                        id_cover, status, tanggal_mulai, dibuat_oleh, dibuat_pada 
                    ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, NOW())";
            
            $result = pg_query_params($conn, $sql, [
                $nama, //done
                $slug, //done
                $email, //done
                $linkedin ?: null, //done
                $peran_lab ?: null, //done
                $bio_html ?: null, //done
                $id_foto,
                $aktif,
                $tanggal,
                $dibuatOleh
            ]);
            
            if ($result) {
                pg_query($conn, "COMMIT");
                setFlashMessage('Anggota berhasil ditambahkan', 'success');
                header('Location: ' . getAdminUrl('berita/index.php'));
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
            <h1><i class="bi bi-newspaper"></i>Tambah Berita</h1>
            <p class="text-muted mb-0">Tambah Berita Baru</p>
        </div>
        <div>
            <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">
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
                    <h5 class="mb-0"><i class="bi bi-newspaper"></i></i>Berita Utama</h5>
                </div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">
                            Judul <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="nama" 
                               name="judul" 
                               maxlength="255"
                               value="" 
                               required>
                        <div class="form-text">Maksimal 255 karakter.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jenis" class="form-label">
                            Jenis <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" name="jenis" placeholder="jenis berita">
                            <option value="berita">Berita</option>
                            <option value="agenda">Agenda</option>
                            <option value="pengumuman">Pengumuman</option>
                        </select>
                    </div>

                    <div class="mb-3">
                       <label for="status" class="form-label">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" name="status" placeholder="jenis berita">
                            <option value="draft">ditunda</option>
                            <option value="disetujui">disetujui</option>
                            <option value="diajukan">diajukan</option>
                            <option value="ditolak">ditolak</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="linkedin" class="form-label">
                            Ringkasan 
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="linkedin" 
                               name="ringkasan" 
                               >
                        <div class="form-text">Contoh: https://linkedin.com/in/username (opsional)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="peran_lab" class="form-label">
                            Isi Berita
                        </label>
                        <textarea name="isiberita" id="editor"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="bio_html" class="form-label">Catatan Review</label>
                        <textarea class="form-control" 
                                  id="bio_html" 
                                  name="catatan_review" 
                                  rows="10"></textarea>
                        <div class="form-text">Maksimal 5000 karakter. Gunakan spasi dan baris baru untuk memisahkan paragraf.</div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="col-lg-4">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-image me-2"></i>Cover</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="foto" class="form-label">
                            Upload Cover <span class="text-danger">*</span>
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
                
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Simpan Berita
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
<script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>

<script>
    ClassicEditor
    .create( document.querySelector( '#editor' ), {
        toolbar: {
            items: [
                'undo', 'redo', '|',
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                'alignment', '|',
                'bulletedList', 'numberedList', '|',
                'outdent', 'indent', '|',
                'blockQuote', 'insertTable', 'link', 'mediaEmbed', 'uploadImage', '|',
                'horizontalLine', 'codeBlock'
            ]
        },
        image: {
            toolbar: [
                'imageTextAlternative',
                'imageStyle:inline',
                'imageStyle:block',
                'imageStyle:side'
            ]
        },
        table: {
            contentToolbar: [
                'tableColumn',
                'tableRow',
                'mergeTableCells'
            ]
        }
    })
    .then(editor => {
        window.editor = editor;
    })
    .catch(error => {
        console.error(error);
    });
</script>
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
    
    
});
</script>

<?php

include __DIR__ . '/../includes/footer.php';
?>