<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); }
if (!isset($conn)) $conn = getDBConnection();

// cek izin edit (admin & operator boleh)
if (!hasPermission('edit', 'galeri')) {
    setFlashMessage('Anda tidak memiliki izin untuk mengedit album.', 'error');
    header('Location: ' . getAdminUrl('galeri/index.php'));
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_album = (int)$_GET['id'];

// helper slug
function makeSlugEdit($string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
}

// ambil data album
$q = pg_query_params($conn, "SELECT * FROM galeri_album WHERE id_album = $1", [$id_album]);
if (!$q || pg_num_rows($q) === 0) {
    $_SESSION['message']  = "Album tidak ditemukan!";
    $_SESSION['msg_type'] = "danger";
    header("Location: index.php");
    exit();
}

$album = pg_fetch_assoc($q);
$page_title  = "Edit Album";
$active_page = "galeri";
$error       = "";

// hitung jumlah foto
$foto_count_q = pg_query_params(
    $conn,
    "SELECT COUNT(*) AS total FROM galeri_item WHERE id_album = $1",
    [$id_album]
);
$foto_count = (int)pg_fetch_result($foto_count_q, 0, 'total');

// user login
$currentUser = getCurrentUser();
$user_id     = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status    = $_POST['status'] ?? $album['status'];

    // validasi status enum
    $allowed_status = ['draft','diajukan','disetujui','ditolak','arsip'];
    if (!in_array($status, $allowed_status, true)) {
        $status = $album['status'];
    }

    // Operator: paksa status jadi 'diajukan'
    if (isOperator()) {
        $status = 'diajukan';
    }

    if ($judul === '') {
        $error = "Judul album tidak boleh kosong.";
    } else {
        pg_query($conn, "BEGIN");

        try {
            $slug = makeSlugEdit($judul) . '-' . $id_album;

            $id_cover = $album['id_cover'];

            // jika upload cover baru
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === 0) {
                $ext      = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                $filename = 'cover_' . time() . '_' . $id_album . '.' . $ext;
                $target   = __DIR__ . '/../../uploads/' . $filename;
                $size     = (int)$_FILES['cover']['size'];

                if (!move_uploaded_file($_FILES['cover']['tmp_name'], $target)) {
                    throw new Exception("Gagal upload cover baru.");
                }

                // insert media baru
                $sql_media = "
                    INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh, ukuran_file)
                    VALUES ($1, $2, $3, $4, $5)
                    RETURNING id_media
                ";
                $res_media = pg_query_params(
                    $conn,
                    $sql_media,
                    [$filename, $ext, $judul, $user_id, $size]
                );

                if (!$res_media) {
                    throw new Exception(pg_last_error($conn));
                }

                $media_row = pg_fetch_assoc($res_media);
                $id_cover  = (int)$media_row['id_media'];
            }

            // update album
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
            $res_update = pg_query_params(
                $conn,
                $sql_update,
                [$judul, $slug, $deskripsi, $id_cover, $status, $id_album]
            );

            if (!$res_update) {
                throw new Exception(pg_last_error($conn));
            }

            // LOG AKTIVITAS
            $aksi = 'UPDATE';
            $ket  = 'Mengubah album galeri: "' . $judul . '" (status sekarang: ' . $status . ')';
            if (isOperator()) {
                $ket .= ' oleh operator (menunggu persetujuan admin).';
            }

            log_aktivitas(
                $conn,
                $aksi,
                'galeri_album',
                $id_album,
                $ket
            );

            pg_query($conn, "COMMIT");

            if (isAdmin()) {
                $_SESSION['message']  = "Album berhasil diperbarui.";
            } else {
                $_SESSION['message']  = "Perubahan album diajukan dan menunggu persetujuan admin.";
            }
            $_SESSION['msg_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $error = "Gagal mengupdate album: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Album</h5>
                    <a href="index.php" class="btn btn-sm btn-light">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Judul Album <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" required
                                   value="<?php echo htmlspecialchars($album['judul']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Cover Baru (opsional)</label>
                            <input type="file" name="cover" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="4"><?php
                                echo htmlspecialchars($album['deskripsi'] ?? '');
                            ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <?php
                                $statuses = ['draft','diajukan','disetujui','ditolak','arsip'];
                                foreach ($statuses as $s):
                                ?>
                                    <option value="<?php echo $s; ?>"
                                        <?php echo $album['status'] === $s ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i>
                            Album ini memiliki <strong><?php echo $foto_count; ?> foto</strong>.
                            <a href="foto.php?id=<?php echo $id_album; ?>" class="alert-link">Kelola Foto →</a>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-warning flex-grow-1 text-dark fw-bold">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="index.php" class="btn btn-secondary flex-grow-1">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>

                        <hr class="my-4">
                        <div class="alert alert-danger p-3">
                            <h6 class="alert-heading mb-2">
                                <i class="fas fa-exclamation-triangle"></i> Zona Berbahaya
                            </h6>
                            <p class="mb-2 small">
                                Menghapus album akan menghapus semua foto di dalamnya secara permanen.
                            </p>
                            <a href="index.php?delete=<?php echo $id_album; ?>"
                               class="btn btn-sm btn-danger w-100"
                               onclick="return confirm('Yakin ingin menghapus album ini beserta semua fotonya?')">
                                <i class="fas fa-trash"></i> Hapus Album
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
