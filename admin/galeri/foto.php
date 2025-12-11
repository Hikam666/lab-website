<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Foto & Video Album Galeri';

if (!isset($_GET['id'])) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$id_album = (int)$_GET['id'];

// Ambil data album
$sql_album = "SELECT id_album, judul, slug, deskripsi, status FROM galeri_album WHERE id_album = $1";
$res_album = pg_query_params($conn, $sql_album, [$id_album]);
if (!$res_album || pg_num_rows($res_album) === 0) {
    setFlashMessage('Album tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$album       = pg_fetch_assoc($res_album);
$currentUser = getCurrentUser();
$id_pengguna = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

if (isset($_GET['hapus_item'])) {
    $id_item = (int)$_GET['hapus_item'];

    if (isOperator()) {
        $sql = "UPDATE galeri_item
                SET status = 'diajukan', aksi_request = 'hapus'
                WHERE id_item = $1 AND id_album = $2";
        pg_query_params($conn, $sql, [$id_item, $id_album]);

        $ket = "Operator mengajukan penghapusan item (id_item=$id_item) di album #$id_album ({$album['judul']})";
        log_aktivitas($conn, 'REQUEST_DELETE', 'galeri_item', $id_item, $ket);

        setFlashMessage('Permintaan penghapusan item diajukan ke admin.', 'warning');
        header('Location: foto.php?id=' . $id_album);
        exit;
    }

    // ADMIN → hapus betulan
    $q_cek = pg_query_params(
        $conn,
        "SELECT gi.id_item, gi.id_media, m.lokasi_file
         FROM galeri_item gi
         JOIN media m ON gi.id_media = m.id_media
         WHERE gi.id_item = $1 AND gi.id_album = $2",
        [$id_item, $id_album]
    );

    if ($row = pg_fetch_assoc($q_cek)) {
        $path = __DIR__ . '/../../uploads/' . $row['lokasi_file'];
        if (file_exists($path)) {
            @unlink($path);
        }

        pg_query_params($conn, "DELETE FROM galeri_item WHERE id_item = $1", [$id_item]);
        pg_query_params($conn, "DELETE FROM media       WHERE id_media = $1", [$row['id_media']]);

        $ket = "Admin menghapus item (id_item=$id_item) dari album #$id_album ({$album['judul']})";
        log_aktivitas($conn, 'DELETE', 'galeri_item', $id_item, $ket);

        setFlashMessage('Item berhasil dihapus.', 'success');
    } else {
        setFlashMessage('Item tidak ditemukan.', 'danger');
    }

    header('Location: foto.php?id=' . $id_album);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_form = $_POST['aksi'] ?? '';

    if ($aksi_form === 'tambah_item') {
        // Cek apakah ada file yang diupload
        $file_uploaded = false;
        $file_input_name = '';
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_uploaded = true;
            $file_input_name = 'foto';
        } elseif (isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_uploaded = true;
            $file_input_name = 'video';
        }
        
        if (!$file_uploaded) {
            setFlashMessage('Tidak ada file yang diupload.', 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }

        $caption = trim($_POST['caption'] ?? '');

        $status_item  = isAdmin() ? 'disetujui' : 'diajukan';
        $aksi_request = isAdmin() ? null : 'tambah';

        $upload_dir_fs = __DIR__ . '/../../uploads';
        
        // Tentukan allowed types berdasarkan jenis file
        if ($file_input_name === 'video') {
            $allowed_types = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
            $max_size = 50 * 1024 * 1024; // 50MB untuk video
        } else {
            $allowed_types = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB untuk foto
        }

        $uploadResult = uploadFile($_FILES[$file_input_name], $upload_dir_fs, $allowed_types, $max_size);
        if (!$uploadResult['success']) {
            setFlashMessage('Upload gagal: ' . $uploadResult['message'], 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }

        $filename = $uploadResult['filename'];
        $path     = $upload_dir_fs . '/' . $filename;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $path);
        finfo_close($finfo);

        $size = file_exists($path) ? filesize($path) : 0;

        $sql_media = "INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh, ukuran_file)
                      VALUES ($1, $2, $3, $4, $5)
                      RETURNING id_media";
        $res_media = pg_query_params($conn, $sql_media, [
            $filename,
            $mime,
            $caption !== '' ? $caption : $album['judul'],
            $id_pengguna,
            $size
        ]);

        if ($res_media && ($m = pg_fetch_assoc($res_media))) {
            $id_media = (int)$m['id_media'];

            $sql_item = "INSERT INTO galeri_item (id_album, id_media, caption, dibuat_oleh, status, aksi_request)
                         VALUES ($1, $2, $3, $4, $5, $6)";
            pg_query_params($conn, $sql_item, [
                $id_album,
                $id_media,
                $caption,
                $id_pengguna,
                $status_item,
                $aksi_request
            ]);

            $item_type = ($file_input_name === 'video') ? 'video' : 'foto';
            
            if (isAdmin()) {
                $ket = "Admin menambahkan {$item_type} ke album #$id_album ({$album['judul']})";
                log_aktivitas($conn, 'CREATE', 'galeri_item', $id_media, $ket);
                setFlashMessage(ucfirst($item_type) . ' berhasil ditambahkan.', 'success');
            } else {
                $ket = "Operator mengajukan penambahan {$item_type} ke album #$id_album ({$album['judul']})";
                log_aktivitas($conn, 'REQUEST_CREATE', 'galeri_item', $id_media, $ket);
                setFlashMessage(ucfirst($item_type) . ' diajukan dan menunggu persetujuan admin.', 'warning');
            }

            header('Location: foto.php?id=' . $id_album);
            exit;
        } else {
            setFlashMessage('Gagal menyimpan data media.', 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }
    }

    if ($aksi_form === 'edit_item') {
        $id_item = (int)($_POST['id_item'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');

        if ($id_item <= 0) {
            setFlashMessage('Data item tidak valid.', 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }

        $status_item  = isAdmin() ? 'disetujui' : 'diajukan';
        $aksi_request = isAdmin() ? null : 'edit';

        if (isAdmin()) {

            $sql = "UPDATE galeri_item
                    SET caption = $1,
                        status = $2,
                        aksi_request = NULL
                    WHERE id_item = $3 AND id_album = $4";
            pg_query_params($conn, $sql, [$caption, $status_item, $id_item, $id_album]);

            $ket = "Admin mengedit item (id_item=$id_item) di album #$id_album ({$album['judul']})";
            log_aktivitas($conn, 'UPDATE', 'galeri_item', $id_item, $ket);
            setFlashMessage('Item berhasil diperbarui.', 'success');
        } else {
            $sql = "UPDATE galeri_item
                    SET caption = $1,
                        status = $2,
                        aksi_request = $3
                    WHERE id_item = $4 AND id_album = $5";
            pg_query_params($conn, $sql, [$caption, $status_item, $aksi_request, $id_item, $id_album]);

            $ket = "Operator mengajukan pengeditan item (id_item=$id_item) di album #$id_album ({$album['judul']})";
            log_aktivitas($conn, 'REQUEST_UPDATE', 'galeri_item', $id_item, $ket);
            setFlashMessage('Perubahan item diajukan dan menunggu persetujuan admin.', 'warning');
        }

        header('Location: foto.php?id=' . $id_album);
        exit;
    }
}

$sql_items = "
    SELECT 
        gi.id_item,
        gi.caption,
        gi.status,
        gi.aksi_request,
        gi.dibuat_pada,
        m.lokasi_file,
        m.tipe_file,
        m.keterangan_alt,
        m.ukuran_file
    FROM galeri_item gi
    JOIN media m ON gi.id_media = m.id_media
    WHERE gi.id_album = $1
    ORDER BY gi.dibuat_pada DESC
";
$res_items = pg_query_params($conn, $sql_items, [$id_album]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Foto & Video Album: <?php echo htmlspecialchars($album['judul']); ?></h1>
            <p class="text-muted mb-0">
                Kelola foto dan video di dalam album ini. Item yang disetujui akan tampil di website publik.
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Album
        </a>
    </div>

    <?php if (hasFlashMessage()): ?>
        <?php $flash = getFlashMessage(); ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <strong>Tambah Foto atau Video</strong>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" id="uploadTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="foto-tab" data-bs-toggle="tab" data-bs-target="#foto-panel" type="button">
                        <i class="bi bi-image me-1"></i> Upload Foto
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="video-tab" data-bs-toggle="tab" data-bs-target="#video-panel" type="button">
                        <i class="bi bi-camera-video me-1"></i> Upload Video
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="uploadTabsContent">
                <!-- Tab Foto -->
                <div class="tab-pane fade show active" id="foto-panel" role="tabpanel">
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="aksi" value="tambah_item">
                        <div class="col-md-4">
                            <label for="foto" class="form-label">File Foto</label>
                            <input type="file" name="foto" id="foto" class="form-control" accept="image/*" required>
                            <div class="form-text">Format: JPG, PNG, WebP, GIF. Maks 5MB.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="caption_foto" class="form-label">Caption (opsional)</label>
                            <input type="text" name="caption" id="caption_foto" class="form-control" maxlength="200">
                        </div>
                        <div class="col-md-2 d-flex align-items-center pt-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Foto
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Tab Video -->
                <div class="tab-pane fade" id="video-panel" role="tabpanel">
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="aksi" value="tambah_item">
                        <div class="col-md-4">
                            <label for="video" class="form-label">File Video</label>
                            <input type="file" name="video" id="video" class="form-control" accept="video/*" required>
                            <div class="form-text">Format: MP4, WebM, OGG, MOV. Maks 50MB.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="caption_video" class="form-label">Caption (opsional)</label>
                            <input type="text" name="caption" id="caption_video" class="form-control" maxlength="200">
                        </div>
                        <div class="col-md-2 d-flex align-items-center pt-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Video
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Daftar Foto & Video</strong>
        </div>
        <div class="card-body p-0">
            <?php if ($res_items && pg_num_rows($res_items) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="120">Preview</th>
                                <th width="80">Tipe</th>
                                <th>Caption</th>
                                <th width="140">Status</th>
                                <th width="160">Dibuat</th>
                                <th width="220" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($f = pg_fetch_assoc($res_items)): 
                            $is_video = strpos($f['tipe_file'], 'video/') === 0;
                            $file_size_mb = number_format($f['ukuran_file'] / (1024 * 1024), 2);
                        ?>
                            <tr>
                                <td>
                                    <?php if ($is_video): ?>
                                        <video width="100" height="70" class="rounded" style="object-fit: cover;">
                                            <source src="../../uploads/<?php echo htmlspecialchars($f['lokasi_file']); ?>" 
                                                    type="<?php echo htmlspecialchars($f['tipe_file']); ?>">
                                        </video>
                                    <?php else: ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($f['lokasi_file']); ?>"
                                             alt="<?php echo htmlspecialchars($f['keterangan_alt']); ?>"
                                             class="rounded"
                                             style="width: 100px; height: 70px; object-fit: cover;">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_video): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-camera-video"></i> Video
                                        </span>
                                        <div><small class="text-muted"><?php echo $file_size_mb; ?> MB</small></div>
                                    <?php else: ?>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-image"></i> Foto
                                        </span>
                                        <div><small class="text-muted"><?php echo $file_size_mb; ?> MB</small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                                        <input type="hidden" name="aksi" value="edit_item">
                                        <input type="hidden" name="id_item" value="<?php echo (int)$f['id_item']; ?>">
                                        <input type="text" name="caption" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars($f['caption']); ?>"
                                               placeholder="Caption">
                                </td>
                                <td>
                                    <?php echo getStatusBadge($f['status']); ?>
                                    <?php if (!empty($f['aksi_request'])): ?>
                                        <div>
                                            <small class="badge bg-warning text-dark">
                                                request: <?php echo htmlspecialchars($f['aksi_request']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatTanggalWaktu($f['dibuat_pada']); ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                        <button type="submit" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="bi bi-save"></i> Simpan
                                        </button>
                                    </form>
                                    <a href="foto.php?id=<?php echo $id_album; ?>&hapus_item=<?php echo (int)$f['id_item']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin ingin menghapus item ini?');">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    Belum ada foto atau video di album ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>