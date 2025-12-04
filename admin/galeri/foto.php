<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); }
if (!isset($conn)) $conn = getDBConnection();

$id_album = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'] ?? 1; // Fallback jika session kosong

// Ambil Info Album
$q_album = pg_query_params($conn, "SELECT * FROM galeri_album WHERE id_album = $1", [$id_album]);
if (pg_num_rows($q_album) == 0) { header("Location: index.php"); exit(); }
$album = pg_fetch_assoc($q_album);

// --- PROSES UPLOAD ITEM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {
    $caption = pg_escape_string($conn, $_POST['caption']);
    $files = $_FILES['fotos'];
    
    $success_count = 0;
    
    // Loop multiple files
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] == 0) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $new_name = 'galeri_' . time() . '_' . $i . '.' . $ext;
            $target = __DIR__ . '/../../uploads/' . $new_name;
            
            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                // 1. Insert ke tabel MEDIA
                $sql_media = "INSERT INTO media (nama_file, lokasi_file, tipe_file, diunggah_oleh) 
                              VALUES ($1, $2, $3, $4) RETURNING id_media";
                $res_media = pg_query_params($conn, $sql_media, 
                    [$files['name'][$i], $new_name, $ext, $user_id]);
                
                if ($res_media) {
                    $media_row = pg_fetch_assoc($res_media);
                    $id_media = $media_row['id_media'];
                    
                    // 2. Hubungkan ke GALERI_ITEM
                    $sql_item = "INSERT INTO galeri_item (id_album, id_media, caption, dibuat_oleh) 
                                 VALUES ($1, $2, $3, $4)";
                    pg_query_params($conn, $sql_item, [$id_album, $id_media, $caption, $user_id]);
                    $success_count++;
                }
            }
        }
    }
    
    if ($success_count > 0) {
        $_SESSION['msg'] = "$success_count foto berhasil ditambahkan.";
    }
    header("Location: foto.php?id=$id_album");
    exit();
}

// --- HAPUS ITEM ---
if (isset($_GET['hapus_item'])) {
    $id_item = (int)$_GET['hapus_item'];
    
    // Cek dulu file fisiknya di tabel media
    $q_cek = pg_query_params($conn, 
        "SELECT m.lokasi_file, m.id_media 
         FROM galeri_item gi 
         JOIN media m ON gi.id_media = m.id_media 
         WHERE gi.id_item = $1", [$id_item]);
         
    if ($row = pg_fetch_assoc($q_cek)) {
        // Hapus file fisik
        $path = __DIR__ . '/../../uploads/' . $row['lokasi_file'];
        if (file_exists($path)) @unlink($path);
        
        // Hapus data di media (karena galeri_item punya ON DELETE CASCADE ke media? 
        // Kalau terbalik relasinya, hapus galeri_item dulu, baru media)
        
        // Hapus item galeri
        pg_query_params($conn, "DELETE FROM galeri_item WHERE id_item = $1", [$id_item]);
        // Hapus record media (opsional, jika media tidak dipakai di tempat lain)
        pg_query_params($conn, "DELETE FROM media WHERE id_media = $1", [$row['id_media']]);
    }
    
    header("Location: foto.php?id=$id_album");
    exit();
}

// --- QUERY DATA FOTO ---
$q_items = "SELECT gi.*, m.lokasi_file 
            FROM galeri_item gi 
            JOIN media m ON gi.id_media = m.id_media 
            WHERE gi.id_album = $1 
            ORDER BY gi.dibuat_pada DESC";
$items = pg_query_params($conn, $q_items, [$id_album]);

include __DIR__ . '/../includes/header.php'; 
?>

<div class="container-fluid px-4">
    <div class="my-4">
        <a href="index.php" class="text-decoration-none text-muted"><i class="fas fa-arrow-left"></i> Kembali</a>
        <h2 class="mt-2"><?php echo htmlspecialchars($album['judul']); ?></h2>
        <p class="text-muted"><?php echo htmlspecialchars($album['deskripsi']); ?></p>
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
                        <input type="text" name="caption" class="form-control" placeholder="Caption umum untuk foto...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">Upload</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <?php if (pg_num_rows($items) > 0): ?>
            <?php while($item = pg_fetch_assoc($items)): ?>
                <div class="col-md-3 col-6">
                    <div class="card h-100 shadow-sm">
                        <div style="height: 180px; overflow: hidden;">
                            <img src="../../uploads/<?php echo $item['lokasi_file']; ?>" 
                                 class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                                 onclick="window.open(this.src)">
                        </div>
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            <small class="text-truncate" style="max-width: 70%;">
                                <?php echo $item['caption'] ? htmlspecialchars($item['caption']) : '-'; ?>
                            </small>
                            <a href="foto.php?id=<?php echo $id_album; ?>&hapus_item=<?php echo $item['id_item']; ?>" 
                               class="text-danger" onclick="return confirm('Hapus foto ini?');">
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