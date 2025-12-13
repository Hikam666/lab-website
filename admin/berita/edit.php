<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$conn = getDBConnection();
$user = getCurrentUser();

$id_berita = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_berita <= 0) {
    setFlashMessage('ID berita tidak valid', 'error');
    header('Location: ' . getAdminUrl('berita/index.php'));
    exit;
}

$sql = "SELECT b.*, m.lokasi_file AS foto, m.id_media AS id_foto
        FROM berita b
        LEFT JOIN media m ON b.id_cover = m.id_media
        WHERE b.id_berita = $1";
$result = pg_query_params($conn, $sql, [$id_berita]);

if (!$result || pg_num_rows($result) === 0) {
    setFlashMessage('Berita tidak ditemukan', 'error');
    header('Location: ' . getAdminUrl('berita/index.php'));
    exit;
}

$berita = pg_fetch_assoc($result);

// ===== PROSES SIMPAN EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul      = trim($_POST['judul']);
    $jenis      = $_POST['jenis'] ?? 'berita';
    $penulis    = trim($_POST['penulis'] ?? ''); 
    $ringkasan  = $_POST['ringkasan'] ?? '';
    $isi        = $_POST['isiberita'] ?? '';

    // ===== VALIDASI DASAR =====
    if ($judul === '') {
        setFlashMessage('Judul wajib diisi.', 'error');
        header('Location: ' . getAdminUrl('berita/edit.php?id='.$id_berita));
        exit;
    }
    if ($penulis === '') {
        setFlashMessage('Penulis wajib diisi.', 'error');
        header('Location: ' . getAdminUrl('berita/edit.php?id='.$id_berita));
        exit;
    }

    if (!in_array($jenis, ['berita','pengumuman'], true)) { 
        setFlashMessage('Jenis berita tidak valid.', 'error');
        header('Location: ' . getAdminUrl('berita/edit.php?id='.$id_berita));
        exit;
    }

    // ===== STATUS BERDASARKAN ROLE (LOGIKA UTAMA YANG SAMA DENGAN TAMBAH) =====
    if (isAdmin()) {
        $status = 'disetujui';
        $disetujui_oleh = $user['id'] ?? null;
        $disetujui_pada = 'NOW()'; 
        $flash_message = 'Berita berhasil diperbarui dan disetujui.';
    } else {
        $status = 'diajukan';
        $disetujui_oleh = null;
        $disetujui_pada = null;
        $flash_message = 'Perubahan tersimpan dan berita diajukan untuk persetujuan admin.';
    }

    $slug = generateSlug($judul);
    $id_foto_final = $berita['id_foto'];

    if (!empty($_FILES['foto']['name'])) {
        $file = $_FILES['foto'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $nama_file = 'berita-' . time() . '-' . uniqid() . '.' . $ext;
            $path = __DIR__ . '/../../uploads/berita/';

            if (!file_exists($path)) mkdir($path, 0755, true);

            if (move_uploaded_file($file['tmp_name'], $path . $nama_file)) {

                $media_sql = "INSERT INTO media (lokasi_file, ukuran_file, tipe_file, keterangan_alt, dibuat_oleh, dibuat_pada)
                              VALUES ($1,$2,$3,$4,$5,NOW()) RETURNING id_media";
                $media_result = pg_query_params($conn, $media_sql, [
                    'berita/' . $nama_file,
                    $file['size'],
                    $file['type'],
                    $judul,
                    $user['id'] ?? null
                ]);
                if ($media_result) {
                    $id_foto_final = pg_fetch_assoc($media_result)['id_media'];
                }
            }
        }
    }

    $update_sql_parts = [];
    $params_final = [];
    $param_index = 1;

    $update_sql_parts[] = "judul = $" . $param_index++; $params_final[] = $judul;
    $update_sql_parts[] = "slug = $" . $param_index++; $params_final[] = $slug;
    $update_sql_parts[] = "jenis = $" . $param_index++; $params_final[] = $jenis;
    $update_sql_parts[] = "penulis = $" . $param_index++; $params_final[] = $penulis;
    $update_sql_parts[] = "ringkasan = $" . $param_index++; $params_final[] = $ringkasan;
    $update_sql_parts[] = "isi_html = $" . $param_index++; $params_final[] = $isi;
    $update_sql_parts[] = "status = $" . $param_index++; $params_final[] = $status;
    $update_sql_parts[] = "id_cover = $" . $param_index++; $params_final[] = $id_foto_final;
    $update_sql_parts[] = "disetujui_oleh = $" . $param_index++; $params_final[] = $disetujui_oleh;
    
    $update_sql_parts[] = "diperbarui_pada = NOW()";
    
    if ($disetujui_pada === 'NOW()') {
        $update_sql_parts[] = "disetujui_pada = NOW()";
    } else {
        $update_sql_parts[] = "disetujui_pada = NULL";
    }
    
    $update_sql = "UPDATE berita SET " . implode(', ', $update_sql_parts) . " WHERE id_berita = $" . $param_index++;
    $params_final[] = $id_berita;
    
    $update = pg_query_params($conn, $update_sql, $params_final);
    
    if ($update) {
        log_aktivitas(
            $conn,
            'update',
            'berita',
            $id_berita,
            'Mengupdate berita: ' . $judul . ' (status: ' . $status . ')'
        );

        setFlashMessage($flash_message, 'success');
        header('Location: ' . getAdminUrl('berita/index.php'));
        exit;
    } else {
        setFlashMessage('Gagal memperbarui berita: ' . pg_last_error($conn), 'error');
    }
}

$active_page = 'berita';
$page_title  = 'Edit Berita';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-pencil-square me-2"></i>Edit Berita</h1>
            <p class="text-muted mb-0">
                <?php if (isAdmin()): ?>
                    Berita ini akan otomatis berstatus <strong>Disetujui</strong> saat disimpan.
                <?php else: ?>
                    Perubahan akan diajukan dan statusnya menjadi <strong>Diajukan</strong>.
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">

    <div class="row">
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-newspaper me-2"></i>Berita Utama</h5>
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input type="text" name="judul" class="form-control"
                               value="<?php echo htmlspecialchars($berita['judul']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis</label>
                        <select name="jenis" class="form-control">
                            <option value="berita"      <?= $berita['jenis']=='berita'?'selected':'' ?>>Berita</option>
                            <option value="pengumuman"  <?= $berita['jenis']=='pengumuman'?'selected':'' ?>>Pengumuman</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Penulis</label>
                        <input type="text" name="penulis" class="form-control"
                               value="<?php echo htmlspecialchars($berita['penulis'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status Saat Ini</label>
                        <?php 
                        $status_display = ucfirst($berita['status']);
                        if (!isAdmin()) {
                            $status_display .= " (Perubahan akan menjadi Diajukan)";
                        }
                        ?>
                        <input type="text" class="form-control"
                               value="<?php echo htmlspecialchars($status_display); ?>" disabled>
                        <div class="form-text">
                            Status publikasi diatur secara otomatis berdasarkan peran Anda saat menyimpan.
                        </div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label">Ringkasan</label>
                        <input type="text" name="ringkasan" class="form-control"
                               value="<?php echo htmlspecialchars($berita['ringkasan']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Isi Berita</label>
                        <textarea name="isiberita" id="editor" rows="6"
                                  class="form-control"><?php echo htmlspecialchars($berita['isi_html']); ?></textarea>
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
                    <?php if ($berita['foto']): ?>
                        <div class="mb-3 text-center">
                            <img src="<?php echo SITE_URL . '/uploads/' . $berita['foto']; ?>"
                                 class="img-fluid rounded mb-2" style="max-height: 180px;">
                            <div class="text-muted small">Cover saat ini</div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Cover Baru (Opsional)</label>
                        <input type="file" name="foto" class="form-control"
                               accept="image/jpeg,image/jpg,image/png,image/webp">
                        <div class="form-text">
                            Kosongkan jika tidak ingin mengganti cover.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> Simpan Perubahan
                        </button>
                        <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i> Batal
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
    .create( document.querySelector( '#editor' ) )
    .catch( error => { console.error( error ); } );
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>