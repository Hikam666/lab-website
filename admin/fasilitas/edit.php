<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/functions.php";
require "../includes/auth.php";

requireLogin();
$conn = getDBConnection();

$page_title  = "Edit Fasilitas";
$active_page = "fasilitas";

function getLoggedUserIdFallback() {
    if (function_exists('getCurrentUser')) {
        $u = getCurrentUser();
        if (is_array($u)) {
            if (!empty($u['id']))          return $u['id'];
            if (!empty($u['id_pengguna'])) return $u['id_pengguna'];
        }
    }
    if (isset($_SESSION['user_id']))      return $_SESSION['user_id'];
    if (isset($_SESSION['id_pengguna']))  return $_SESSION['id_pengguna'];
    return null;
}

function generateSlugDasar($text) {
    $text = trim($text);
    if ($text === '') return 'fasilitas-'.time();

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'fasilitas-'.time();
}
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

        if ($res && pg_num_rows($res) === 0) {
            return $slug;
        }

        $slug = $base_slug . '-' . $i;
        $i++;
    }
}

$user_id = getLoggedUserIdFallback();

// Ambil ID fasilitas
$id = $_GET['id'] ?? null;
if (!$id || !ctype_digit($id)) {
    header("Location: index.php");
    exit;
}

// Ambil data fasilitas + media
$query = "
    SELECT f.*, m.lokasi_file AS foto, m.id_media
    FROM fasilitas f
    LEFT JOIN media m ON f.id_foto = m.id_media
    WHERE f.id_fasilitas = $1
";
$result = pg_query_params($conn, $query, [$id]);
$data   = pg_fetch_assoc($result);

if (!$data) {
    die("Fasilitas tidak ditemukan!");
}

$error = null;

if (isset($_POST['submit'])) {

    $nama      = trim($_POST['nama'] ?? '');
    $kategori  = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status_in = $_POST['status'] ?? $data['status'];

    if ($nama === '') {
        $error = "Nama fasilitas wajib diisi.";
    }

    $slug    = generateUniqueSlugFasilitas($conn, $nama, $id);
    $id_foto = $data['id_media'];

    // ===== LOGIKA ADMIN vs OPERATOR =====
    if (function_exists('isAdmin') && isAdmin()) {
        $status_final = $status_in;
    } else {
        $status_final = 'diajukan';
    }

    // Upload foto baru jika ada
    if (empty($error) && !empty($_FILES['foto']['name'])) {

        $uploadBase = __DIR__ . "/../../uploads/fasilitas/";

        if (!is_dir($uploadBase)) {
            mkdir($uploadBase, 0777, true);
        }

        $safeName   = preg_replace('/[^A-Za-z0-9.\-_]/', '', $_FILES['foto']['name']);
        $fileName   = time() . "_" . $safeName;
        $targetFile = $uploadBase . $fileName;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {

            $lokasi_file = "fasilitas/" . $fileName;

            if ($id_foto) {
                pg_query_params(
                    $conn,
                    "UPDATE media 
                     SET lokasi_file = $1, diperbarui_pada = NOW(), dibuat_oleh = $2 
                     WHERE id_media = $3",
                    [$lokasi_file, $user_id, $id_foto]
                );
            } else {
                $resMedia = pg_query_params(
                    $conn,
                    "INSERT INTO media (lokasi_file, dibuat_oleh, dibuat_pada) 
                     VALUES ($1, $2, NOW()) 
                     RETURNING id_media",
                    [$lokasi_file, $user_id]
                );
                $mediaRow = pg_fetch_assoc($resMedia);
                $id_foto  = $mediaRow['id_media'] ?? null;
            }
        }
    }

    if (empty($error)) {
        // Update fasilitas
        $qUpdate = "
            UPDATE fasilitas
            SET nama      = $1,
                slug      = $2,
                kategori  = $3,
                deskripsi = $4,
                id_foto   = $5,
                status    = $6
            WHERE id_fasilitas = $7
        ";

        $resUpdate = pg_query_params($conn, $qUpdate, [
            $nama,
            $slug,
            $kategori !== '' ? $kategori : null,
            $deskripsi !== '' ? $deskripsi : null,
            $id_foto,
            $status_final,
            $id
        ]);

        if ($resUpdate) {

            if (function_exists('log_aktivitas')) {
                $ket = "Mengubah fasilitas: {$nama} (status: {$status_final})";
                log_aktivitas($conn, 'update', 'fasilitas', $id, $ket);
            }

            if (function_exists('isAdmin') && isAdmin()) {
                header("Location: index.php?updated=1");
            } else {
                header("Location: index.php?updated=diajukan");
            }
            exit;
        } else {
            $error = "Gagal mengupdate data!";
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

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">

        <div class="mb-3">
            <label class="form-label">Nama Fasilitas</label>
            <input type="text" name="nama" class="form-control" required
                   value="<?= htmlspecialchars($data['nama']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control"
                   value="<?= htmlspecialchars($data['kategori']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="4"><?= htmlspecialchars($data['deskripsi']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Foto Lama</label><br>
            <?php if (!empty($data['foto'])): ?>
                <img src="<?= SITE_URL . '/uploads/' . $data['foto'] ?>" width="160" class="rounded border mb-2" style="object-fit:cover;">
            <?php else: ?>
                <p class="text-muted">Tidak ada foto</p>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Ganti Foto Baru</label>
            <input type="file" name="foto" class="form-control" accept="image/*">
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
                <option value="draft"     <?= ($data['status'] == 'draft'     ? 'selected' : '') ?>>Draft</option>
                <option value="diajukan"  <?= ($data['status'] == 'diajukan'  ? 'selected' : '') ?>>Diajukan</option>
                <option value="disetujui" <?= ($data['status'] == 'disetujui' ? 'selected' : '') ?>>Disetujui</option>
                <option value="ditolak"   <?= ($data['status'] == 'ditolak'   ? 'selected' : '') ?>>Ditolak</option>
                <option value="arsip"     <?= ($data['status'] == 'arsip'     ? 'selected' : '') ?>>Arsip</option>
            </select>
            <?php if (! (function_exists('isAdmin') && isAdmin())): ?>
                <small class="text-muted">
                    *Sebagai operator, perubahan Anda akan diajukan (status menjadi <strong>diajukan</strong>).
                </small>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <button type="submit" name="submit" class="btn btn-primary">Update</button>
            <a href="<?= getAdminUrl('fasilitas/index.php'); ?>" class="btn btn-secondary">Batal</a>
        </div>
    </form>

</div>

<?php include "../includes/footer.php"; ?>
