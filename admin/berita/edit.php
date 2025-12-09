<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$conn = getDBConnection();
$id_berita = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_berita <= 0) {
    setFlashMessage('ID berita tidak valid', 'error');
    header('Location: ' . getAdminUrl('berita/index.php'));
    exit;
}

// Ambil data berita
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
    $judul     = trim($_POST['judul']);
    $jenis     = $_POST['jenis'];
    $status    = $_POST['status'];
    $ringkasan = $_POST['ringkasan'];
    $isi       = $_POST['isiberita'];

    $slug = generateSlug($judul);
    $id_foto_final = $berita['id_foto'];

    // Upload cover baru jika ada
    if (!empty($_FILES['foto']['name'])) {
        $file = $_FILES['foto'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nama_file = 'berita-' . time() . '.' . $ext;
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
                $_SESSION['user_id']
            ]);
            $id_foto_final = pg_fetch_assoc($media_result)['id_media'];
        }
    }

    // Update berita
    $update_sql = "UPDATE berita SET
                    judul = $1,
                    slug = $2,
                    jenis = $3,
                    ringkasan = $4,
                    isi_html = $5,
                    status = $6,
                    id_cover = $7,
                    diperbarui_pada = NOW()
                   WHERE id_berita = $8";

    $update = pg_query_params($conn, $update_sql, [
        $judul,
        $slug,
        $jenis,
        $ringkasan,
        $isi,
        $status,
        $id_foto_final,
        $id_berita
    ]);

    if ($update) {
        setFlashMessage('Berita berhasil diperbarui', 'success');
        header('Location: ' . getAdminUrl('berita/index.php'));
        exit;
    } else {
        setFlashMessage('Gagal memperbarui berita', 'error');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h3>Edit Berita</h3>

<form method="POST" enctype="multipart/form-data">

    <label>Judul</label>
    <input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($berita['judul']); ?>">

    <label>Jenis</label>
    <select name="jenis" class="form-control">
        <option value="berita" <?= $berita['jenis']=='berita'?'selected':'' ?>>Berita</option>
        <option value="agenda" <?= $berita['jenis']=='agenda'?'selected':'' ?>>Agenda</option>
        <option value="pengumuman" <?= $berita['jenis']=='pengumuman'?'selected':'' ?>>Pengumuman</option>
    </select>

    <label>Status</label>
    <select name="status" class="form-control">
        <option value="draft" <?= $berita['status']=='draft'?'selected':'' ?>>Draft</option>
        <option value="disetujui" <?= $berita['status']=='disetujui'?'selected':'' ?>>Disetujui</option>
        <option value="ditolak" <?= $berita['status']=='ditolak'?'selected':'' ?>>Ditolak</option>
    </select>

    <label>Ringkasan</label>
    <input type="text" name="ringkasan" class="form-control" value="<?php echo htmlspecialchars($berita['ringkasan']); ?>">

    <label>Isi Berita</label>
    <textarea name="isiberita" class="form-control" rows="6"><?php echo htmlspecialchars($berita['isi_html']); ?></textarea>

    <label>Cover Baru (Opsional)</label>
    <input type="file" name="foto" class="form-control">

    <br>
    <button class="btn btn-primary">Simpan Perubahan</button>
    <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">Batal</a>

</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
