<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) { 
    header("Location: index.php"); 
    exit(); 
}

$id = (int)$_GET['id'];
$q = pg_query_params($conn, "SELECT * FROM galeri WHERE id = $1", array($id));

if (!$q || pg_num_rows($q) === 0) {
    $_SESSION['message'] = "Album tidak ditemukan!";
    $_SESSION['msg_type'] = "danger";
    header("Location: index.php");
    exit();
}

$album = pg_fetch_assoc($q);
$page_title = "Edit Album";
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = pg_escape_string($conn, trim($_POST['judul'] ?? ''));
    $deskripsi = pg_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
    $urutan = (int)($_POST['urutan'] ?? $album['urutan']);
    $status = pg_escape_string($conn, $_POST['status'] ?? $album['status']);

    if ($judul === '') {
        $error = "Judul album tidak boleh kosong.";
    } else {
        $upd = "UPDATE galeri SET judul = '$judul', deskripsi = '$deskripsi', 
                urutan = $urutan, status = '$status', updated_at = NOW() WHERE id = $id";
        if (pg_query($conn, $upd)) {
            $_SESSION['message'] = "Album berhasil diperbarui!";
            $_SESSION['msg_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Gagal memperbarui album: " . pg_last_error($conn);
        }
    }
}

$foto_count_q = pg_query_params($conn, "SELECT COUNT(*) as total FROM foto WHERE album_id = $1", array($id));
$foto_count = (int)pg_fetch_result($foto_count_q, 0, 'total');

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
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Judul Album <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" required 
                                   value="<?php echo htmlspecialchars($album['judul']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="4"><?php echo htmlspecialchars($album['deskripsi']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Urutan</label>
                                <input type="number" name="urutan" class="form-control" min="1"
                                       value="<?php echo (int)$album['urutan']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="publish" <?php echo $album['status']=='publish' ? 'selected' : ''; ?>>Publish</option>
                                    <option value="draft" <?php echo $album['status']=='draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i> 
                            Album ini memiliki <strong><?php echo $foto_count; ?> foto</strong>
                            <a href="foto.php?album_id=<?php echo $id; ?>" class="alert-link">Kelola Foto →</a>
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
                            <p class="mb-2 small">Menghapus album akan menghapus semua foto di dalamnya secara permanen.</p>
                            <a href="index.php?delete=<?php echo $id; ?>" 
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