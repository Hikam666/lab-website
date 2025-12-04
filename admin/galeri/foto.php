<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); }
if (!isset($conn)) $conn = getDBConnection();

$currentUser = getCurrentUser();
$user_id     = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);

$id_album = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info album
$q_album = pg_query_params($conn, "SELECT * FROM galeri_album WHERE id_album = $1", [$id_album]);
if (!$q_album || pg_num_rows($q_album) == 0) {
    header("Location: index.php");
    exit();
}
$album = pg_fetch_assoc($q_album);

// --- PROSES UPLOAD FOTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {

    // cek izin edit
    if (!hasPermission('edit', 'galeri')) {
        setFlashMessage('Anda tidak memiliki izin menambah foto.', 'error');
        header("Location: foto.php?id=$id_album");
        exit();
    }

    $caption = trim($_POST['caption'] ?? '');
    $files   = $_FILES['fotos'];
    $success_count = 0;

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === 0) {
            $ext      = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $new_name = 'galeri_' . time() . '_' . $i . '.' . $ext;
            $target   = __DIR__ . '/../../uploads/' . $new_name;
            $size     = (int)$files['size'][$i];

            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                // 1. Insert ke media
                $alt       = $caption !== '' ? $caption : $files['name'][$i];
                $sql_media = "
                    INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh, ukuran_file)
                    VALUES ($1, $2, $3, $4, $5)
                    RETURNING id_media
                ";
                $res_media = pg_query_params(
                    $conn,
                    $sql_media,
                    [$new_name, $ext, $alt, $user_id, $size]
                );

                if ($res_media) {
                    $media_row = pg_fetch_assoc($res_media);
                    $id_media  = $media_row['id_media'];

                    // 2. Insert ke galeri_item
                    $sql_item = "
                        INSERT INTO galeri_item (id_album, id_media, caption, dibuat_oleh)
                        VALUES ($1, $2, $3, $4)
                    ";
                    pg_query_params($conn, $sql_item, [$id_album, $id_media, $caption, $user_id]);
                    $success_count++;
                }
            }
        }
    }

    if ($success_count > 0) {
        // LOG AKTIVITAS (tulis di galeri_album biar link di dashboard bisa diarahkan ke edit album)
        $ket = "Menambahkan $success_count foto pada album ID=$id_album (\"" . $album['judul'] . "\")";
        log_aktivitas($conn, 'CREATE', 'galeri_album', $id_album, $ket);

        $_SESSION['msg'] = "$success_count foto berhasil ditambahkan.";
    }
    header("Location: foto.php?id=$id_album");
    exit();
}

// --- HAPUS FOTO ---
if (isset($_GET['hapus_item'])) {
    $id_item = (int)$_GET['hapus_item'];

    // cek izin delete
    if (!hasPermission('delete', 'galeri')) {
        setFlashMessage('Hanya admin yang dapat menghapus foto.', 'error');
        header("Location: foto.php?id=$id_album");
        exit();
    }

    $q_cek = pg_query_params(
        $conn,
        "SELECT m.lokasi_file, m.id_media
         FROM galeri_item gi
         JOIN media m ON gi.id_media = m.id_media
         WHERE gi.id_item = $1",
        [$id_item]
    );

    if ($row = pg_fetch_assoc($q_cek)) {
        $path = __DIR__ . '/../../uploads/' . $row['lokasi_file'];
        if (file_exists($path)) {
            @unlink($path);
        }

        // hapus item + media
        pg_query_params($conn, "DELETE FROM galeri_item WHERE id_item = $1", [$id_item]);
        pg_query_params($conn, "DELETE FROM media       WHERE id_media = $1", [$row['id_media']]);

        // LOG AKTIVITAS
        $ket = "Menghapus satu foto dari album ID=$id_album (\"" . $album['judul'] . "\")";
        log_aktivitas($conn, 'DELETE', 'galeri_item', $id_item, $ket);
    }

    header("Location: foto.php?id=$id_album");
    exit();
}

// --- DATA FOTO ---
$q_items = "
    SELECT gi.id_item, gi.caption, gi.dibuat_pada, m.lokasi_file
    FROM galeri_item gi
    JOIN media m ON gi.id_media = m.id_media
    WHERE gi.id_album = $1
    ORDER BY gi.dibuat_pada DESC
";
$items = pg_query_params($conn, $q_items, [$id_album]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="my-4">
        <a href="index.php" class="text-decoration-none text-muted">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h2 class="mt-2"><?php echo htmlspecialchars($album['judul']); ?></h2>
        <p class="text-muted"><?php echo htmlspecialchars($album['deskripsi'] ?? ''); ?></p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title">Tambah Foto ke Album Ini</h6>
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-2">
                    <div class="col-md-5">
                        <input type="file" name="fotos[]" class="form-control" multiple required accept="image/*">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="caption" class="form-control" placeholder="Caption umum untuk foto.">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">Upload</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <?php if ($items && pg_num_rows($items) > 0): ?>
            <?php while($item = pg_fetch_assoc($items)): ?>
                <div class="col-md-3 col-6">
                    <div class="card h-100 shadow-sm galeri-admin-photo-card">
                        <div class="galeri-admin-photo-thumb">
                            <img src="<?php echo '../../uploads/' . htmlspecialchars($item['lokasi_file']); ?>"
                                 class="galeri-admin-photo-img"
                                 alt="<?php echo htmlspecialchars($item['caption'] ?: $album['judul']); ?>"
                                 onclick="window.open(this.src)">
                        </div>
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            <small class="text-truncate galeri-admin-photo-caption">
                                <?php echo $item['caption'] ? htmlspecialchars($item['caption']) : '-'; ?>
                            </small>
                            <a href="foto.php?id=<?php echo $id_album; ?>&hapus_item=<?php echo $item['id_item']; ?>"
                               class="text-danger"
                               onclick="return confirm('Hapus foto ini?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-4">Belum ada foto di album ini.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
