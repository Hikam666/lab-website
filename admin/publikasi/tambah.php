<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$conn        = getDBConnection();
$page_title  = "Tambah Publikasi";
$active_page = "publikasi";
$extra_css   = ['publikasi.css'];

/**
 * Helper lokal: buat slug dari teks.
 * Tidak mengubah functions.php
 */
function generateSlugLocal($text) {
    $text = trim($text);
    if ($text === '') return 'publikasi-' . time();
    // ganti non-alnum jadi '-'
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    // normalisasi
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^A-Za-z0-9\-]+/', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);
    $text = strtolower($text);
    return $text ?: 'publikasi-' . time();
}

/**
 * Ambil user id yang login tanpa mengubah auth.php
 * Cek beberapa kemungkinan helper/session
 */
function getLoggedUserIdFallback() {
    // 1) kalau ada fungsi getCurrentUser
    if (function_exists('getCurrentUser')) {
        $u = getCurrentUser();
        if (is_array($u) && !empty($u['id'])) return $u['id'];
        if (is_array($u) && !empty($u['id_pengguna'])) return $u['id_pengguna'];
    }
    // 2) cek session standar
    if (isset($_SESSION['user_id'])) return $_SESSION['user_id'];
    if (isset($_SESSION['id_pengguna'])) return $_SESSION['id_pengguna'];
    // 3) fallback null
    return null;
}

// default form values
$errors = [];
$form = [
    'judul'     => '',
    'abstrak'   => '',
    'jenis'     => 'Jurnal',
    'tempat'    => '',
    'tahun'     => date('Y'),
    'doi'       => '',
    'url_sinta' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ambil input dengan safe fallback
    $form['judul']     = trim($_POST['judul'] ?? '');
    $form['abstrak']   = trim($_POST['abstrak'] ?? '');
    $form['jenis']     = trim($_POST['jenis'] ?? '');
    $form['tempat']    = trim($_POST['tempat'] ?? '');
    $form['tahun']     = trim($_POST['tahun'] ?? '');
    $form['doi']       = trim($_POST['doi'] ?? '');
    $form['url_sinta'] = trim($_POST['url_sinta'] ?? '');
    // status default (kamu minta kolom status dihapus sebelumnya — kalau tetap ingin pakai, aktifkan)
    $status = 'draft'; // tetap simpan 'draft' sebagai nilai default di DB jika kolom ada

    // validasi
    if ($form['judul'] === '') {
        $errors[] = "Judul publikasi wajib diisi.";
    }

    if ($form['tahun'] !== '' && !ctype_digit((string)$form['tahun'])) {
        $errors[] = "Tahun harus berupa angka (atau kosongkan).";
    }

    // =========================
    // Upload cover (opsional)
    // =========================
    $id_cover = null;
    if (!empty($_FILES['cover']['name'])) {
        $file = $_FILES['cover'];
        // valid extension
        $allowed_ext = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $errors[] = "Format cover tidak didukung. Gunakan JPG/PNG/WEBP.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Terjadi kesalahan saat upload cover (error code: {$file['error']}).";
        } else {
            $uploads_dir = __DIR__ . '/../../uploads/publikasi';
            if (!is_dir($uploads_dir)) {
                if (!mkdir($uploads_dir, 0755, true) && !is_dir($uploads_dir)) {
                    $errors[] = "Gagal membuat folder upload.";
                }
            }
            if (empty($errors)) {
                $basename = 'cover_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $target = $uploads_dir . '/' . $basename;
                if (!move_uploaded_file($file['tmp_name'], $target)) {
                    $errors[] = "Gagal memindahkan file cover.";
                } else {
                    // simpan metadata ke tabel media
                    $lokasi_db = 'publikasi/' . $basename;
                    $tipe_file = $file['type'] ?? null;
                    $keterangan = 'Cover publikasi';
                    $dibuat_oleh = getLoggedUserIdFallback();

                    $sqlMedia = "INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh) VALUES ($1,$2,$3,$4) RETURNING id_media";
                    $mediaParams = [$lokasi_db, $tipe_file, $keterangan, $dibuat_oleh];
                    $r = pg_query_params($conn, $sqlMedia, $mediaParams);
                    if ($r && pg_num_rows($r) > 0) {
                        $m = pg_fetch_assoc($r);
                        $id_cover = $m['id_media'];
                    } else {
                        // jika gagal simpan media, hapus file yang sudah diupload
                        @unlink($target);
                        $errors[] = "Gagal menyimpan metadata cover: " . pg_last_error($conn);
                    }
                }
            }
        }
    }

    // =========================
    // Simpan publikasi
    // =========================
    if (empty($errors)) {
        $judul = $form['judul'];
        $slug  = generateSlugLocal($judul);
        $abstrak = $form['abstrak'] === '' ? null : $form['abstrak'];
        $jenis   = $form['jenis'] === '' ? null : $form['jenis'];
        $tempat  = $form['tempat'] === '' ? null : $form['tempat'];
        $tahun   = $form['tahun'] === '' ? null : (int)$form['tahun'];
        $doi     = $form['doi'] === '' ? null : $form['doi'];
        $url_sinta = $form['url_sinta'] === '' ? null : $form['url_sinta'];
        $dibuat_oleh = getLoggedUserIdFallback();

        // Pastikan kolom id_cover / url_sinta/dibuat_oleh ada di DB sesuai schema kamu
        $sql = "INSERT INTO publikasi
                (judul, slug, abstrak, jenis, tempat, tahun, doi, url_sinta, id_cover, dibuat_oleh)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
                RETURNING id_publikasi";

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
            $dibuat_oleh
        ];

        $res = pg_query_params($conn, $sql, $params);
        if ($res) {
            // opsional log aktivitas bila fungsi tersedia
            if (function_exists('log_aktivitas')) {
                $new = pg_fetch_assoc($res);
                $newId = $new['id_publikasi'] ?? null;
                log_aktivitas($conn, 'create', 'publikasi', $newId, 'Menambahkan publikasi: ' . $judul);
            }
            // flash + redirect
            setFlashMessage("Publikasi berhasil ditambahkan.", "success");
            redirectAdmin("publikasi/index.php");
            exit;
        } else {
            $errors[] = "Gagal menyimpan publikasi: " . pg_last_error($conn);
        }
    }
}

// include header/footer (tidak dirubah)
include __DIR__ . '/../includes/header.php';
?>

<div class="pub-container">
    <div class="pub-card">
        <h2 class="pub-title">Tambah Publikasi</h2>
        <p class="pub-sub">Isi data publikasi dengan lengkap</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="pub-form">

            <div class="pub-group">
                <label>Judul</label>
                <input type="text" name="judul" required value="<?= htmlspecialchars($form['judul']) ?>">
            </div>

            <div class="pub-group">
                <label>Abstrak</label>
                <textarea name="abstrak" rows="4"><?= htmlspecialchars($form['abstrak']) ?></textarea>
            </div>

            <div class="pub-group">
                <label>Jenis</label>
                <select name="jenis">
                    <option value="Jurnal">Jurnal</option>
                    <option value="Prosiding">Prosiding</option>
                    <option value="Buku">Buku</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>

            <div class="pub-group">
                <label>Nama Jurnal / Konferensi (Tempat)</label>
                <input type="text" name="tempat" value="<?= htmlspecialchars($form['tempat']) ?>">
            </div>

            <div class="pub-group">
                <label>Tahun</label>
                <input type="number" name="tahun" min="1900" max="2100" value="<?= htmlspecialchars($form['tahun']) ?>">
            </div>

            <div class="pub-group">
                <label>DOI</label>
                <input type="text" name="doi" placeholder="contoh: 10.1234/abcd.2024" value="<?= htmlspecialchars($form['doi']) ?>">
            </div>

            <div class="pub-group">
                <label>URL Publikasi</label>
                <input type="text" name="url_sinta" placeholder="https://sinta.kemdikbud.go.id/..." value="<?= htmlspecialchars($form['url_sinta']) ?>">
            </div>

            <div class="pub-group">
                <label>Upload Cover (opsional)</label>
                <input type="file" name="cover" accept="image/*">
            </div>

            <div class="pub-group">
                <button type="submit" class="pub-btn-save">Simpan</button>
                <a href="index.php" class="pub-btn-back">Kembali</a>
            </div>

        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
