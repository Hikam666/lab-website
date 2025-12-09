<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Wajib login
if (function_exists('requireLogin')) {
    requireLogin();
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($conn)) {
    $conn = getDBConnection();
}

$active_page = 'media';
$page_title  = 'Upload Media';

// lokasi direktori uploads (filesystem)
$upload_dir_fs  = __DIR__ . '/../../uploads';
// lokasi relatif web (kalau nanti mau dipakai untuk <img src> / link)
$upload_dir_web = '../../uploads';

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi dasar file
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message']  = 'Tidak ada file yang diupload atau terjadi error upload.';
        $_SESSION['msg_type'] = 'danger';
        header('Location: upload.php');
        exit();
    }

    // Batas maksimal 5MB
    $max_size = 5 * 1024 * 1024;

    // Daftar mime yang diizinkan
    $allowed_types = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];

    // Panggil helper uploadFile (definisi ada di includes/functions.php)
    $uploadResult = uploadFile($_FILES['file'], $upload_dir_fs, $allowed_types, $max_size);

    if (!$uploadResult['success']) {
        $_SESSION['message']  = 'Upload gagal: ' . $uploadResult['message'];
        $_SESSION['msg_type'] = 'danger';
        header('Location: upload.php');
        exit();
    }

    // Nama file yang disimpan (unik) dikembalikan oleh uploadFile()
    $filename = $uploadResult['filename']; // ✅ Gunakan langsung dari uploadFile()

    // Deteksi mime type dari file hasil upload (lebih akurat)
    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $upload_dir_fs . '/' . $filename);
    finfo_close($finfo);

    // Ambil ukuran file (byte)
    $filesize = file_exists($upload_dir_fs . '/' . $filename)
        ? filesize($upload_dir_fs . '/' . $filename)
        : 0;

    // Ambil keterangan_alt dari form (optional)
    $keterangan_alt = trim($_POST['keterangan_alt'] ?? '');

    // Siapa yang mengupload (pakai helper getCurrentUser kalau ada)
    $currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
    $dibuat_oleh = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

    // Insert ke tabel media (pakai RETURNING supaya dapat id_media untuk log)
    $sql = "INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh, ukuran_file)
            VALUES ($1, $2, $3, $4, $5)
            RETURNING id_media";
    $res = pg_query_params(
        $conn,
        $sql,
        [
            $filename,
            $mime_type,
            $keterangan_alt !== '' ? $keterangan_alt : null,
            $dibuat_oleh,
            $filesize
        ]
    );

    if ($res && ($row = pg_fetch_assoc($res))) {
        $id_media = (int)$row['id_media'];

        // 🔹 LOG AKTIVITAS
        // Pastikan kamu sudah punya fungsi log_aktivitas($conn, $aksi, $tabel, $id_entitas, $keterangan)
        // di admin/includes/functions.php
        $ket = 'Upload media: "' . ($keterangan_alt !== '' ? $keterangan_alt : $filename) .
               '" (file: ' . $filename . ')';
        log_aktivitas(
            $conn,
            'CREATE',
            'media',
            $id_media,
            $ket
        );

        $_SESSION['message']  = 'File berhasil diupload.';
        $_SESSION['msg_type'] = 'success';
        header('Location: index.php');
        exit();
    } else {
        // Jika insert DB gagal, hapus file yang sudah diupload
        if (file_exists($upload_dir_fs . '/' . $filename)) {
            @unlink($upload_dir_fs . '/' . $filename);
        }

        $_SESSION['message']  = 'Upload gagal (DB): ' . pg_last_error($conn);
        $_SESSION['msg_type'] = 'danger';
        header('Location: upload.php');
        exit();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Upload Media</h1>
            <p class="text-muted">Unggah gambar atau dokumen (JPG, PNG, WebP, PDF, DOCX, dll)</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Media
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?php
            echo $_SESSION['message'];
            unset($_SESSION['message'], $_SESSION['msg_type']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-3">
                    <label for="file" class="form-label">File</label>
                    <input
                        class="form-control"
                        type="file"
                        id="file"
                        name="file"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                        required
                    >
                    <div class="form-text">
                        Maks 5MB. Format: JPG, PNG, WebP, PDF, DOCX, XLSX, PPTX.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="keterangan_alt" class="form-label">Keterangan / ALT (opsional)</label>
                    <input
                        type="text"
                        id="keterangan_alt"
                        name="keterangan_alt"
                        class="form-control"
                        maxlength="200"
                        placeholder="Deskripsi singkat untuk gambar"
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cloud-upload-alt me-2"></i> Upload
                </button>
                <a href="index.php" class="btn btn-link">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
