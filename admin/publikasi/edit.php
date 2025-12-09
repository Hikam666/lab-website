<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$page_title  = "Edit Publikasi";
$active_page = "publikasi";
$extra_css   = ['publikasi.css'];

$id = $_GET['id'] ?? null;

if (!$id || !ctype_digit($id)) {
    die("ID publikasi tidak valid.");
}

// =======================
// Ambil data publikasi
// =======================
$sql = "
    SELECT p.*, m.lokasi_file AS cover_file, m.tipe_file
    FROM publikasi p
    LEFT JOIN media m ON p.id_cover = m.id_media
    WHERE p.id_publikasi = $1
";

$res = pg_query_params($conn, $sql, [$id]);
$data = pg_fetch_assoc($res);

if (!$data) {
    die("Data publikasi tidak ditemukan.");
}

// Form awal
$form = [
    'judul'   => $data['judul'],
    'abstrak' => $data['abstrak'],
    'jenis'   => $data['jenis'],
    'tempat'  => $data['tempat'],
    'tahun'   => $data['tahun'],
    'doi'     => $data['doi'],
    'url_sinta' => $data['url_sinta'] ?? '',
];

$errors = [];


// ========================================
// PROSES EDIT
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // input text
    $form['judul']   = trim($_POST['judul']);
    $form['abstrak'] = trim($_POST['abstrak']);
    $form['jenis']   = trim($_POST['jenis']);
    $form['tempat']  = trim($_POST['tempat']);
    $form['tahun']   = trim($_POST['tahun']);
    $form['doi']     = trim($_POST['doi']);
    $form['url_sinta'] = trim($_POST['url_sinta']);

    if ($form['judul'] === '') $errors[] = "Judul wajib diisi.";
    if ($form['tahun'] !== '' && !ctype_digit($form['tahun'])) {
        $errors[] = "Tahun harus berupa angka.";
    }

    $id_cover = $data['id_cover'];


    // ============================
    // Upload Cover Baru
    // ============================
    if (!empty($_FILES['cover']['name'])) {

        $file = $_FILES['cover'];
        $allowed_ext = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext)) {
            $errors[] = "Format cover harus JPG/PNG/WEBP.";
        } else {
            $upload_folder = __DIR__ . '/../../uploads/cover/';
            if (!is_dir($upload_folder)) mkdir($upload_folder, 0777, true);

            $new_name = 'cover_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $path = $upload_folder . $new_name;

            if (!move_uploaded_file($file['tmp_name'], $path)) {
                $errors[] = "Gagal upload cover.";
            } else {

                // Insert media baru
                $sqlMedia = "
                    INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh)
                    VALUES ($1, $2, $3, $4)
                    RETURNING id_media
                ";

                $mediaParams = [
                    'uploads/cover/' . $new_name,
                    $file['type'],
                    'Cover publikasi',
                    $_SESSION['user']['id_pengguna'] ?? null
                ];

                $rsMedia = pg_query_params($conn, $sqlMedia, $mediaParams);
                $dataMedia = pg_fetch_assoc($rsMedia);

                if ($dataMedia) {
                    $id_cover = $dataMedia['id_media'];
                }
            }
        }
    }


    // ========================================
    // SIMPAN PERUBAHAN
    // ========================================
    if (empty($errors)) {

        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $form['judul'])));

        $sqlUpdate = "
            UPDATE publikasi
            SET judul = $1,
                slug = $2,
                abstrak = $3,
                jenis = $4,
                tempat = $5,
                tahun = $6,
                doi = $7,
                url_sinta = $8,
                id_cover = $9,
                diperbarui_pada = NOW()
            WHERE id_publikasi = $10
        ";

        $params = [
            $form['judul'],
            $slug,
            $form['abstrak'],
            $form['jenis'],
            $form['tempat'],
            $form['tahun'] !== '' ? (int)$form['tahun'] : null,
            $form['doi'],
            $form['url_sinta'],
            $id_cover,
            $id
        ];

        $result = pg_query_params($conn, $sqlUpdate, $params);

        if ($result) {
            setFlashMessage("Publikasi berhasil diperbarui.", "success");
            redirectAdmin("publikasi/index.php");
        } else {
            $errors[] = "Gagal memperbarui data: " . pg_last_error($conn);
        }
    }
}


include __DIR__ . '/../includes/header.php';
?>

<div class="pub-container">
    <div class="pub-card">
        <h2 class="pub-title">Edit Publikasi</h2>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="pub-form">

            <!-- JUDUL -->
            <div class="pub-group">
                <label>Judul</label>
                <input type="text" name="judul" required 
                       value="<?= htmlspecialchars($form['judul']) ?>">
            </div>

            <!-- ABSTRAK -->
            <div class="pub-group">
                <label>Abstrak</label>
                <textarea name="abstrak"><?= htmlspecialchars($form['abstrak']) ?></textarea>
            </div>

            <!-- JENIS -->
            <div class="pub-group">
                <label>Jenis</label>
                <select name="jenis">
                    <option value="Jurnal"    <?= $form['jenis']=="Jurnal" ? "selected":"" ?>>Jurnal</option>
                    <option value="Prosiding" <?= $form['jenis']=="Prosiding" ? "selected":"" ?>>Prosiding</option>
                    <option value="Buku"      <?= $form['jenis']=="Buku" ? "selected":"" ?>>Buku</option>
                    <option value="Lainnya"   <?= $form['jenis']=="Lainnya" ? "selected":"" ?>>Lainnya</option>
                </select>
            </div>

            <!-- TEMPAT -->
            <div class="pub-group">
                <label>Nama Jurnal / Konferensi</label>
                <input type="text" name="tempat"
                       value="<?= htmlspecialchars($form['tempat']) ?>">
            </div>

            <!-- TAHUN -->
            <div class="pub-group">
                <label>Tahun</label>
                <input type="number" name="tahun"
                       value="<?= htmlspecialchars($form['tahun']) ?>">
            </div>

            <!-- DOI -->
            <div class="pub-group">
                <label>DOI</label>
                <input type="text" name="doi"
                       value="<?= htmlspecialchars($form['doi']) ?>">
            </div>

            <!-- URL SINTA -->
            <div class="pub-group">
                <label>URL Publikasi</label>
                <input type="text" name="url_sinta"
                       value="<?= htmlspecialchars($form['url_sinta']) ?>">
            </div>

            <!-- COVER -->
            <div class="pub-group">
                <label>Cover Saat Ini</label><br>
                <?php if ($data['cover_file']): ?>
                    <img src="/uploads/<?= htmlspecialchars($data['cover_file']) ?>" class="cover-thumb">
                <?php else: ?>
                    <div class="cover-null">Tidak Ada</div>
                <?php endif; ?>
            </div>

            <div class="pub-group">
                <label>Upload Cover Baru (Opsional)</label>
                <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <button type="submit" class="pub-btn-save">Simpan Perubahan</button>
            <a href="index.php" class="pub-btn-back">Kembali</a>

        </form>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
