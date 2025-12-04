<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); }
if (!isset($conn)) $conn = getDBConnection();

// Helper slug
function makeSlug($string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
}

$error = '';

// ambil user login
$currentUser = getCurrentUser();
$user_id     = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($judul === '') {
        $error = 'Judul tidak boleh kosong.';
    } else {
        // status tergantung role
        $status = isAdmin() ? 'disetujui' : 'diajukan';

        $slug = makeSlug($judul) . '-' . time(); // unik
        pg_query($conn, "BEGIN");

        try {
            // 1. Insert album dulu (tanpa cover)
            $sql = "INSERT INTO galeri_album (judul, slug, deskripsi, dibuat_oleh, status)
                    VALUES ($1, $2, $3, $4, $5)
                    RETURNING id_album";
            $res = pg_query_params($conn, $sql, [$judul, $slug, $deskripsi, $user_id, $status]);

            if (!$res) {
                throw new Exception(pg_last_error($conn));
            }

            $row          = pg_fetch_assoc($res);
            $new_album_id = (int)$row['id_album'];

            // 2. Jika ada upload cover
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === 0) {
                $ext       = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                $filename  = 'cover_' . time() . '.' . $ext;
                $target    = __DIR__ . '/../../uploads/' . $filename;
                $filesize  = (int)$_FILES['cover']['size'];

                if (!move_uploaded_file($_FILES['cover']['tmp_name'], $target)) {
                    throw new Exception('Gagal memindahkan file cover.');
                }

                // Insert ke tabel media
                $media_sql = "
                    INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh, ukuran_file)
                    VALUES ($1, $2, $3, $4, $5)
                    RETURNING id_media
                ";
                $media_res = pg_query_params(
                    $conn,
                    $media_sql,
                    [$filename, $ext, $judul, $user_id, $filesize]
                );

                if (!$media_res) {
                    throw new Exception(pg_last_error($conn));
                }

                $media_row = pg_fetch_assoc($media_res);
                $id_cover  = (int)$media_row['id_media'];

                // Update album dengan id_cover
                pg_query_params(
                    $conn,
                    "UPDATE galeri_album SET id_cover = $1 WHERE id_album = $2",
                    [$id_cover, $new_album_id]
                );
            }

            // LOG AKTIVITAS
            $keterangan = 'Membuat album galeri: "' . $judul . '" dengan status ' . $status;
            log_aktivitas(
                $conn,
                'CREATE',
                'galeri_album',
                $new_album_id,
                $keterangan
            );

            pg_query($conn, "COMMIT");

            if (isAdmin()) {
                $_SESSION['message']  = "Album berhasil dibuat dan langsung dipublikasikan.";
            } else {
                $_SESSION['message']  = "Album berhasil diajukan dan menunggu persetujuan admin.";
            }
            $_SESSION['msg_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $error = "Gagal membuat album: " . $e->getMessage();
        }
    }
}

$page_title  = "Tambah Album";
$active_page = "galeri";

include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow-sm col-md-8 mx-auto">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Buat Album Baru</h5>
            <a href="index.php" class="btn btn-sm btn-light">Kembali</a>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Judul Album <span class="text-danger">*</span></label>
                    <input type="text" name="judul" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cover Album (Opsional)</label>
                    <input type="file" name="cover" class="form-control" accept="image/*">
                    <small class="text-muted">Cover akan disimpan di tabel media.</small>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Album</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
