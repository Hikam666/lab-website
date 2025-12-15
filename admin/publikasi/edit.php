<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$conn        = getDBConnection();
$page_title  = "Edit Publikasi";
$active_page = "publikasi";
$extra_css   = ['publikasi.css'];

function generateSlugLocal($text) {
    $text = trim($text);
    if ($text === '') return 'publikasi-' . time();

    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    if (function_exists('iconv')) {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    }
    $text = preg_replace('/[^A-Za-z0-9\-]+/', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);
    $text = strtolower($text);

    return $text ?: 'publikasi-' . time();
}


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

$id = $_GET['id'] ?? null;
if (!$id || !ctype_digit($id)) {
    setFlashMessage("ID publikasi tidak valid.", "danger");
    redirectAdmin("publikasi/index.php");
    exit;
}

$sql = "
    SELECT p.*, 
           m.lokasi_file AS cover_file, 
           m.tipe_file
    FROM publikasi p
    LEFT JOIN media m ON p.id_cover = m.id_media
    WHERE p.id_publikasi = $1
";

$res  = pg_query_params($conn, $sql, [$id]);
$data = pg_fetch_assoc($res);

if (!$data) {
    setFlashMessage("Data publikasi tidak ditemukan.", "danger");
    redirectAdmin("publikasi/index.php");
    exit;
}

// Form awal
$form = [
    'judul'     => $data['judul'],
    'abstrak'   => $data['abstrak'],
    'jenis'     => $data['jenis'],
    'tempat'    => $data['tempat'],
    'tahun'     => $data['tahun'],
    'doi'       => $data['doi'],
    'url_sinta' => $data['url_sinta'] ?? '',
    'penulis'   => $data['penulis'] ?? '' 
];

$errors   = [];
$id_cover = $data['id_cover'];   
$old_status = $data['status'];   

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // input text
    $form['judul']     = trim($_POST['judul']   ?? '');
    $form['abstrak']   = trim($_POST['abstrak'] ?? '');
    $form['jenis']     = trim($_POST['jenis']   ?? '');
    $form['tempat']    = trim($_POST['tempat']  ?? '');
    $form['tahun']     = trim($_POST['tahun']   ?? '');
    $form['doi']       = trim($_POST['doi']     ?? '');
    $form['url_sinta'] = trim($_POST['url_sinta'] ?? '');
    $form['penulis']   = trim($_POST['penulis'] ?? ''); 

    if ($form['judul'] === '') {
        $errors[] = "Judul wajib diisi.";
    }
    if ($form['penulis'] === '') {
        $errors[] = "Nama penulis wajib diisi.";
    }
    if ($form['tahun'] !== '' && !ctype_digit($form['tahun'])) {
        $errors[] = "Tahun harus berupa angka.";
    }

    if (!empty($_FILES['cover']['name'])) {

        $file         = $_FILES['cover'];
        $allowed_ext = ['jpg','jpeg','png','webp'];
        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext, true)) {
            $errors[] = "Format cover harus JPG/PNG/WEBP.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Terjadi kesalahan saat upload cover (error code: {$file['error']}).";
        } else {
            $upload_folder = __DIR__ . '/../../uploads/publikasi';
            if (!is_dir($upload_folder)) {
                if (!mkdir($upload_folder, 0755, true) && !is_dir($upload_folder)) {
                    $errors[] = "Gagal membuat folder upload.";
                }
            }

            if (empty($errors)) {
                $new_name = 'cover_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $path     = $upload_folder . '/' . $new_name;

                if (!move_uploaded_file($file['tmp_name'], $path)) {
                    $errors[] = "Gagal upload cover.";
                } else {
                    $lokasi_file = 'publikasi/' . $new_name;
                    $tipe_file   = $file['type'];
                    $alt         = 'Cover publikasi';
                    $dibuat_oleh = getLoggedUserIdFallback();

                    $sqlMedia = "
                        INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh)
                        VALUES ($1, $2, $3, $4)
                        RETURNING id_media
                    ";

                    $mediaParams = [
                        $lokasi_file,
                        $tipe_file,
                        $alt,
                        $dibuat_oleh
                    ];

                    $rsMedia   = pg_query_params($conn, $sqlMedia, $mediaParams);
                    $dataMedia = pg_fetch_assoc($rsMedia);

                    if ($dataMedia) {
                        $id_cover = $dataMedia['id_media'];
                        
                    } else {
                        $errors[] = "Gagal menyimpan data cover: " . pg_last_error($conn);
                    }
                }
            }
        }
    }

    if (empty($errors)) {

        $slug      = generateSlugLocal($form['judul']);
        $tahun     = $form['tahun'] !== '' ? (int)$form['tahun'] : null;
        $judul     = $form['judul'];
        $abstrak   = $form['abstrak'] === '' ? null : $form['abstrak'];
        $jenis     = $form['jenis']   === '' ? null : $form['jenis'];
        $tempat    = $form['tempat']  === '' ? null : $form['tempat'];
        $doi       = $form['doi']     === '' ? null : $form['doi'];
        $url_sinta = $form['url_sinta'] === '' ? null : $form['url_sinta'];
        $penulis   = $form['penulis'] === '' ? null : $form['penulis']; 

        if (function_exists('isAdmin') && isAdmin()) {
            $status_baru = 'disetujui';
        } else {
            $status_baru = 'diajukan';
        }

        $anggota_id = getLoggedUserIdFallback();
        
        if ($anggota_id) {
            $checkPenulis = pg_query_params($conn, "SELECT 1 FROM publikasi_penulis WHERE id_publikasi = $1 AND id_anggota = $2", [$id, $anggota_id]);
            if (!$checkPenulis || pg_num_rows($checkPenulis) === 0) {
                $sqlInsertPenulis = "
                    INSERT INTO publikasi_penulis (id_publikasi, id_anggota, urutan)
                    VALUES ($1, $2, 1) 
                    ON CONFLICT DO NOTHING;
                ";
                pg_query_params($conn, $sqlInsertPenulis, [$id, $anggota_id]);
            }
        }

        $sqlUpdate = "
            UPDATE publikasi
            SET judul               = $1,
                slug                = $2,
                abstrak             = $3,
                jenis               = $4,
                tempat              = $5,
                tahun               = $6,
                doi                 = $7,
                url_sinta           = $8,
                id_cover            = $9,
                status              = $10,
                penulis             = $11,  
                diperbarui_pada     = NOW()
            WHERE id_publikasi      = $12
        ";

        $params = [
            $judul,
            $slug,
            $abstrak,
            $jenis,
            $tempat,
            $tahun,
            $doi,
            $url_sinta,
            $id_cover,
            $status_baru,
            $penulis, 
            $id     
        ];

        $result = pg_query_params($conn, $sqlUpdate, $params);

        if ($result) {

            if (function_exists('log_aktivitas')) {
                $ket = "Mengubah publikasi: {$judul} (status: {$status_baru})";
                log_aktivitas($conn, 'update', 'publikasi', $id, $ket);
            }

            if ($status_baru === 'disetujui') {
                setFlashMessage("Publikasi berhasil diperbarui dan disetujui.", "success");
            } else {
                setFlashMessage("Perubahan publikasi berhasil diajukan dan menunggu persetujuan admin.", "success");
            }

            redirectAdmin("publikasi/index.php");
            exit;
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

            <div class="pub-group">
                <label>Judul</label>
                <input type="text" name="judul" required
                       value="<?= htmlspecialchars($form['judul']) ?>">
            </div>

            <div class="pub-group">
                <label>Penulis</label>
                <input type="text" name="penulis" required
                       value="<?= htmlspecialchars($form['penulis']) ?>">
                <small class="text-muted">Masukkan nama semua penulis. Pisahkan dengan koma.</small>
            </div>
            <div class="pub-group">
                <label>Abstrak</label>
                <textarea name="abstrak"><?= htmlspecialchars($form['abstrak']) ?></textarea>
            </div>

            <div class="pub-group">
                <label>Jenis</label>
                <select name="jenis">
                    <option value="Jurnal"      <?= $form['jenis']=="Jurnal"      ? "selected":"" ?>>Jurnal</option>
                    <option value="Prosiding"   <?= $form['jenis']=="Prosiding"   ? "selected":"" ?>>Prosiding</option>
                </select>
            </div>

            <div class="pub-group">
                <label>Nama Jurnal / Konferensi</label>
                <input type="text" name="tempat"
                       value="<?= htmlspecialchars($form['tempat']) ?>">
            </div>

            <div class="pub-group">
                <label>Tahun</label>
                <input type="number" name="tahun"
                       value="<?= htmlspecialchars($form['tahun']) ?>">
            </div>

            <div class="pub-group">
                <label>DOI</label>
                <input type="text" name="doi"
                       value="<?= htmlspecialchars($form['doi']) ?>">
            </div>

            <div class="pub-group">
                <label>URL Publikasi</label>
                <input type="text" name="url_sinta"
                       value="<?= htmlspecialchars($form['url_sinta']) ?>">
            </div>

            <div class="pub-group">
                <label>Cover Saat Ini</label><br>
                <?php if (!empty($data['cover_file'])): ?>
                    <img src="<?= SITE_URL . '/uploads/' . htmlspecialchars($data['cover_file']) ?>"
         style="max-width:220px; max-height:300px; object-fit:contain; border:1px solid #ddd; border-radius:8px; display:block;">

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