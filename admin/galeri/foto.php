<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Foto Album Galeri';

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

        $ket = "Operator mengajukan penghapusan foto (id_item=$id_item) di album #$id_album ({$album['judul']})";
        log_aktivitas($conn, 'REQUEST_DELETE', 'galeri_item', $id_item, $ket);

        setFlashMessage('Permintaan penghapusan foto diajukan ke admin.', 'warning');
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
        pg_query_params($conn, "DELETE FROM media       WHERE id_media = $1", [$row['id_media']]);

        $ket = "Admin menghapus foto (id_item=$id_item) dari album #$id_album ({$album['judul']})";
        log_aktivitas($conn, 'DELETE', 'galeri_item', $id_item, $ket);

        setFlashMessage('Foto berhasil dihapus.', 'success');
    } else {
        setFlashMessage('Foto tidak ditemukan.', 'danger');
    }

    header('Location: foto.php?id=' . $id_album);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_form = $_POST['aksi'] ?? '';

    if ($aksi_form === 'tambah_foto') {
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            setFlashMessage('Tidak ada file foto yang diupload.', 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }

        $caption = trim($_POST['caption'] ?? '');

        $status_foto  = isAdmin() ? 'disetujui' : 'diajukan';
        $aksi_request = isAdmin() ? null : 'tambah';

        $upload_dir_fs = __DIR__ . '/../../uploads';
        $allowed_types = ['image/jpeg','image/png','image/webp','image/gif'];
        $max_size      = 5 * 1024 * 1024;

        $uploadResult = uploadFile($_FILES['foto'], $upload_dir_fs, $allowed_types, $max_size);
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
                $status_foto,
                $aksi_request
            ]);

            if (isAdmin()) {
                $ket = "Admin menambahkan foto ke album #$id_album ({$album['judul']})";
                log_aktivitas($conn, 'CREATE', 'galeri_item', $id_media, $ket);
                setFlashMessage('Foto berhasil ditambahkan.', 'success');
            } else {
                $ket = "Operator mengajukan penambahan foto ke album #$id_album ({$album['judul']})";
                log_aktivitas($conn, 'REQUEST_CREATE', 'galeri_item', $id_media, $ket);
                setFlashMessage('Foto diajukan dan menunggu persetujuan admin.', 'warning');
            }

            header('Location: foto.php?id=' . $id_album);
            exit;
        } else {
            setFlashMessage('Gagal menyimpan data media.', 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }
    }

    if ($aksi_form === 'edit_foto') {
        $id_item = (int)($_POST['id_item'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');

        if ($id_item <= 0) {
            setFlashMessage('Data foto tidak valid.', 'danger');
            header('Location: foto.php?id=' . $id_album);
            exit;
        }

        $status_foto  = isAdmin() ? 'disetujui' : 'diajukan';
        $aksi_request = isAdmin() ? null : 'edit';

        if (isAdmin()) {

            $sql = "UPDATE galeri_item
                    SET caption = $1,
                        status = $2,
                        aksi_request = NULL
                    WHERE id_item = $3 AND id_album = $4";
            pg_query_params($conn, $sql, [$caption, $status_foto, $id_item, $id_album]);

            $ket = "Admin mengedit foto (id_item=$id_item) di album #$id_album ({$album['judul']})";
            log_aktivitas($conn, 'UPDATE', 'galeri_item', $id_item, $ket);
            setFlashMessage('Foto berhasil diperbarui.', 'success');
        } else {
            $sql = "UPDATE galeri_item
                    SET caption = $1,
                        status = $2,
                        aksi_request = $3
                    WHERE id_item = $4 AND id_album = $5";
            pg_query_params($conn, $sql, [$caption, $status_foto, $aksi_request, $id_item, $id_album]);

            $ket = "Operator mengajukan pengeditan foto (id_item=$id_item) di album #$id_album ({$album['judul']})";
            log_aktivitas($conn, 'REQUEST_UPDATE', 'galeri_item', $id_item, $ket);
            setFlashMessage('Perubahan foto diajukan dan menunggu persetujuan admin.', 'warning');
        }

        header('Location: foto.php?id=' . $id_album);
        exit;
    }
}

$sql_foto = "
    SELECT 
        gi.id_item,
        gi.caption,
        gi.status,
        gi.aksi_request,
        gi.dibuat_pada,
        m.lokasi_file,
        m.keterangan_alt
    FROM galeri_item gi
    JOIN media m ON gi.id_media = m.id_media
    WHERE gi.id_album = $1
    ORDER BY gi.dibuat_pada ASC
";
$res_foto = pg_query_params($conn, $sql_foto, [$id_album]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Foto Album: <?php echo htmlspecialchars($album['judul']); ?></h1>
            <p class="text-muted mb-0">
                Kelola foto di dalam album ini. Foto yang disetujui akan tampil di website publik.
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
            <strong>Tambah Foto</strong>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="aksi" value="tambah_foto">
                <div class="col-md-4">
                    <label for="foto" class="form-label">File Foto</label>
                    <input type="file" name="foto" id="foto" class="form-control" accept="image/*" required>
                    <div class="form-text">Format: JPG, PNG, WebP, GIF. Maks 5MB.</div>
                </div>
                <div class="col-md-4">
                    <label for="caption" class="form-label">Caption (opsional)</label>
                    <input type="text" name="caption" id="caption" class="form-control" maxlength="200">
                </div>
                <div class="col-md-2 d-flex align-items-center pt-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Foto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Daftar Foto</strong>
        </div>
        <div class="card-body p-0">
            <?php if ($res_foto && pg_num_rows($res_foto) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID</th>
                                <th width="120">Preview</th>
                                <th>Caption</th>
                                <th width="140">Status</th>
                                <th width="160">Dibuat</th>
                                <th width="220" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($f = pg_fetch_assoc($res_foto)): ?>
                            <tr>
                                <td><?php echo (int)$f['id_item']; ?></td>
                                <td>
                                    <img src="../../uploads/<?php echo htmlspecialchars($f['lokasi_file']); ?>"
                                            alt="<?php echo htmlspecialchars($f['keterangan_alt']); ?>"
                                            class="rounded"
                                            style="width: 100px; height: 70px; object-fit: cover;">
                                </td>
                                <td>
                                    <form method="post" class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                                        <input type="hidden" name="aksi" value="edit_foto">
                                        <input type="hidden" name="id_item" value="<?php echo (int)$f['id_item']; ?>">
                                        <input type="text" name="caption" class="form-control form-control-sm"
                                                value="<?php echo htmlspecialchars($f['caption']); ?>"
                                                placeholder="Caption foto">
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
                                        onclick="return confirm('Yakin ingin menghapus foto ini?');">
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
                    Belum ada foto di album ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>