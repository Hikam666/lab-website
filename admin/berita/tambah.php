<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = getCurrentUser();

$conn = getDBConnection();

$active_page = 'berita';
$page_title  = 'Tambah Berita';

$errors      = [];
$form_data   = $_SESSION['form_data']    ?? [];
$form_errors = $_SESSION['form_errors']  ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);


$judul       = $form_data['judul']        ?? '';
$jenis       = $form_data['jenis']        ?? 'berita';
$penulis     = $form_data['penulis']      ?? ($user['nama_lengkap'] ?? ''); 
$ringkasan   = $form_data['ringkasan']    ?? '';
$isi_html    = $form_data['isiberita']    ?? '';
$catatan_rev = $form_data['catatan_review'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $errors = [];

    $judul       = trim($_POST['judul']    ?? '');
    $jenis       = trim($_POST['jenis']    ?? 'berita');
    $penulis     = trim($_POST['penulis']  ?? ''); 
    $ringkasan   = trim($_POST['ringkasan'] ?? '');
    $isi_html    = trim($_POST['isiberita'] ?? '');
    $catatan_rev = trim($_POST['catatan_review'] ?? '');

    if ($judul === '') {
        $errors[] = "Judul wajib diisi.";
    }
    if (!in_array($jenis, ['berita','pengumuman'], true)) {
        $errors[] = "Jenis berita tidak valid.";
    }
    if ($penulis === '') {
         $errors[] = "Nama penulis wajib diisi.";
    }

    if (isAdmin()) {
        $status = 'disetujui';
    } else {
        $status = 'diajukan';
    }
    $id_foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Terjadi kesalahan saat upload cover.";
        } else {
            $foto = $_FILES['foto'];

            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $foto['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types, true)) {
                $errors[] = "Format cover harus JPG, PNG, atau WebP.";
            }

            if ($foto['size'] > 5 * 1024 * 1024) {
                $errors[] = "Ukuran cover maksimal 5MB.";
            }
        }
    } else {
        $errors[] = "Cover wajib diupload.";
    }

    if (empty($errors)) {
        pg_query($conn, "BEGIN");

        try {
            $slug = generateSlug($judul);
            $check_slug = pg_query_params($conn, "SELECT id_berita FROM berita WHERE slug = $1", [$slug]);
            if (pg_num_rows($check_slug) > 0) {
                $counter = 1;
                $original_slug = $slug;
                while (pg_num_rows($check_slug) > 0) {
                    $slug = $original_slug . '-' . $counter;
                    $check_slug = pg_query_params($conn, "SELECT id_berita FROM berita WHERE slug = $1", [$slug]);
                    $counter++;
                }
            }

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto = $_FILES['foto'];
                $ext  = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                $filename = 'berita-' . time() . '-' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../uploads/berita/';

                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }

                if (!move_uploaded_file($foto['tmp_name'], $upload_path . $filename)) {
                    throw new Exception("Gagal mengupload cover berita.");
                }

                $lokasi_file   = 'berita/' . $filename;
                $ukuran_file   = $foto['size'];
                $tipe_file     = $mime_type;
                $keterangan_alt= $judul;

                $media_sql = "INSERT INTO media (lokasi_file, ukuran_file, tipe_file, keterangan_alt, dibuat_oleh, dibuat_pada) 
                              VALUES ($1, $2, $3, $4, $5, NOW()) 
                              RETURNING id_media";

                $media_result = pg_query_params($conn, $media_sql, [
                    $lokasi_file,
                    $ukuran_file,
                    $tipe_file,
                    $keterangan_alt,
                    $user['id'] ?? null
                ]);

                if (!$media_result) {
                    throw new Exception("Gagal menyimpan data cover ke tabel media.");
                }

                $id_foto = pg_fetch_assoc($media_result)['id_media'];
            }

            $tanggal_mulai = date('Y-m-d H:i:s'); 
            $dibuatOleh    = $user['id'] ?? null;

            $sql = "INSERT INTO berita (
                        judul, slug, jenis, penulis, ringkasan, isi_html, catatan_review, 
                        id_cover, status, tanggal_mulai, dibuat_oleh, dibuat_pada, diperbarui_pada
                    ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,NOW(),NOW())
                    RETURNING id_berita";

            $result = pg_query_params($conn, $sql, [
                $judul,
                $slug,
                $jenis,
                $penulis, 
                $ringkasan ?: null,
                $isi_html ?: null,
                $catatan_rev ?: null,
                $id_foto,
                $status,
                $tanggal_mulai,
                $dibuatOleh
            ]);

            if (!$result) {
                throw new Exception("Gagal menyimpan data berita.");
            }

            $row         = pg_fetch_assoc($result);
            $id_berita   = $row['id_berita'] ?? null;

            // LOG AKTIVITAS
            if ($id_berita) {
                log_aktivitas(
                    $conn,
                    'create',
                    'berita',
                    $id_berita,
                    'Menambahkan berita: ' . $judul . ' (status: ' . $status . ')'
                );
            }

            pg_query($conn, "COMMIT");

            if (isAdmin()) {
                setFlashMessage('Berita berhasil ditambahkan dan langsung dipublikasikan.', 'success');
            } else {
                setFlashMessage('Berita berhasil diajukan dan menunggu persetujuan admin.', 'success');
            }

            header('Location: ' . getAdminUrl('berita/index.php'));
            exit;

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $errors[] = $e->getMessage();
        }
    }

    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = $_POST;

    header('Location: ' . getAdminUrl('berita/tambah.php'));
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-newspaper"></i> Tambah Berita dan Pengumuman</h1>
            <p class="text-muted mb-0">
                <?php if (isAdmin()): ?>
                    Berita yang Anda buat akan langsung berstatus <strong>Disetujui</strong>.
                <?php else: ?>
                    Berita yang Anda buat akan berstatus <strong>Diajukan</strong> dan menunggu persetujuan admin.
                <?php endif; ?>
            </p>
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

<form method="POST" action="" enctype="multipart/form-data" id="formBerita">
    
    <div class="row">
        
        <div class="col-lg-8">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-newspaper"></i> Berita Utama</h5>
                </div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Judul <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               name="judul" 
                               maxlength="255"
                               value="<?php echo htmlspecialchars($judul); ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Jenis <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" name="jenis">
                            <option value="berita"    <?php echo $jenis==='berita'?'selected':''; ?>>Berita</option>
                            <option value="pengumuman" <?php echo $jenis==='pengumuman'?'selected':''; ?>>Pengumuman</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Penulis <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               name="penulis" 
                               maxlength="150"
                               value="<?php echo htmlspecialchars($penulis); ?>" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Ringkasan 
                        </label>
                        <input type="text" 
                               class="form-control" 
                               name="ringkasan" 
                               value="<?php echo htmlspecialchars($ringkasan); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Isi Berita
                        </label>
                        <textarea name="isiberita" id="editor"><?php echo htmlspecialchars($isi_html); ?></textarea>
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
                        <label class="form-label">
                            Upload Cover <span class="text-danger">*</span>
                        </label>
                        <input type="file" 
                               class="form-control" 
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
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Simpan Berita
                        </button>
                        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">
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

        <style>
        .ck-editor__editable {
            min-height: 450px;
            max-height: 700px;
            overflow-y: auto;
        }
        </style>

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
        }
    })
    .then(editor => {
        window.editor = editor;
    })
    .catch(error => {
        console.error(error);
    });

    document.addEventListener('DOMContentLoaded', function() {
        const fotoInput = document.querySelector('input[name="foto"]');
        const fotoPreview = document.getElementById('fotoPreview');
        
        if (fotoInput) {
            fotoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file maksimal 5MB');
                        this.value = '';
                        fotoPreview.style.display = 'none';
                        return;
                    }
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                    if (file.type && !allowedTypes.includes(file.type)) {
                    }

                    
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
    });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>