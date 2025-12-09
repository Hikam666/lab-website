<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('requireLogin')) { requireLogin(); } 
elseif (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

if (!isset($conn)) $conn = getDBConnection();

$active_page = 'media';
$page_title = 'Media Library';
$extra_css = ['media.css'];  


// --- QUERY MEDIA FILES ---
// Mengambil semua file dari tabel media
$query = "SELECT id_media, lokasi_file, tipe_file, keterangan_alt, ukuran_file, dibuat_pada 
          FROM media 
          ORDER BY dibuat_pada DESC";

$result = pg_query($conn, $query);

// Helper function untuk format ukuran file (jika belum ada)
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Helper function untuk mendapatkan icon berdasarkan tipe file
if (!function_exists('getFileIcon')) {
    function getFileIcon($tipe_file) {
        $tipe_file = strtolower($tipe_file);
        
        if (strpos($tipe_file, 'image') !== false) {
            return 'bi-image text-primary';
        } elseif (strpos($tipe_file, 'pdf') !== false) {
            return 'bi-file-pdf text-danger';
        } elseif (strpos($tipe_file, 'word') !== false) {
            return 'bi-file-word text-info';
        } elseif (strpos($tipe_file, 'sheet') !== false || strpos($tipe_file, 'excel') !== false) {
            return 'bi-file-spreadsheet text-success';
        } elseif (strpos($tipe_file, 'presentation') !== false) {
            return 'bi-file-slides text-warning';
        } else {
            return 'bi-file-earmark text-secondary';
        }
    }
}

include __DIR__ . '/../includes/header.php'; 
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Media Library</h1>
            <p class="text-muted">Kelola file gambar dan dokumen</p>
        </div>
        <a href="upload.php" class="btn btn-primary">
            <i class="bi bi-cloud-upload me-2"></i> Upload File
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['msg_type']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Cari file...">
                </div>
                <div class="col-md-6">
                    <select class="form-select" id="filterType">
                        <option value="">Semua File</option>
                        <option value="image">Gambar</option>
                        <option value="pdf">PDF</option>
                        <option value="word">Dokumen Word</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Media Grid -->
    <div class="row g-4">
        <?php if ($result && pg_num_rows($result) > 0): ?>
            <?php while ($row = pg_fetch_assoc($result)): 
                // Cek file existence dengan path yang benar
                $file_path = __DIR__ . '/../../uploads/' . $row['lokasi_file'];
                $file_exists = file_exists($file_path);
                $is_image = strpos($row['tipe_file'], 'image') !== false;
                $icon_class = getFileIcon($row['tipe_file']);
                // Extract filename dari path
                $nama_file = basename($row['lokasi_file']);
                
                // Debug: Log file path (hapus setelah debugging)
                // error_log("Checking file: " . $file_path . " - Exists: " . ($file_exists ? 'YES' : 'NO'));
            ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 shadow-sm border-0 media-card" 
                         data-filename="<?php echo strtolower($nama_file); ?>" 
                         data-filetype="<?php echo strtolower($row['tipe_file']); ?>">
                        <!-- Thumbnail -->
                        <div class="position-relative" style="height: 180px; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                            <?php if ($is_image): ?>
                                <img src="../../uploads/<?php echo htmlspecialchars($row['lokasi_file']); ?>" 
                                     class="w-100 h-100" 
                                     style="object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($row['keterangan_alt'] ?? $nama_file); ?>"
                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'text-center\'><i class=\'bi bi-image-alt\' style=\'font-size: 3rem; color: white; opacity: 0.5;\'></i><br><small style=\'color: white;\'>Gambar tidak ditemukan</small></div>';">
                            <?php else: ?>
                                <div class="text-center">
                                    <i class="<?php echo $icon_class; ?>" style="font-size: 3rem; opacity: 0.75;"></i>
                                    <div class="mt-2" style="color: white; font-size: 0.85rem;">
                                        <?php 
                                        $ext = strtoupper(pathinfo($nama_file, PATHINFO_EXTENSION));
                                        echo $ext ? $ext : 'FILE';
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- File Info -->
                        <div class="card-body pb-2">
                            <h6 class="card-title fw-bold mb-2 text-truncate" title="<?php echo htmlspecialchars($nama_file); ?>">
                                <?php echo htmlspecialchars($nama_file); ?>
                            </h6>

                            <?php if (!empty($row['keterangan_alt'])): ?>
                                <p class="card-text text-muted small mb-1">
                                    <?php echo htmlspecialchars(truncate($row['keterangan_alt'], 100)); ?>
                                </p>
                            <?php endif; ?>

                            <p class="card-text text-muted small mb-0">
                                <i class="bi bi-file-earmark me-1"></i>
                                <?php echo formatFileSize($row['ukuran_file']); ?>
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="card-footer bg-white border-top">
                            <div class="d-flex gap-2">
                                <a href="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($row['lokasi_file']); ?>" 
                                   class="btn btn-sm btn-outline-primary flex-fill" 
                                   target="_blank" 
                                   title="Buka file">
                                    <i class="bi bi-eye me-1"></i> Lihat
                                </a>
                                
                                <a href="hapus.php?id=<?php echo $row['id_media']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Yakin ingin menghapus media ini?');"
                                   title="Hapus file">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-light border text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #adb5bd;"></i>
                    <p class="text-muted mb-0 mt-3">Belum ada file media. <a href="upload.php">Upload file sekarang</a></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info Section -->
    <div class="card border-0 shadow-sm mt-5 bg-light">
        <div class="card-body">
            <h6 class="card-title fw-bold mb-3">
                <i class="bi bi-info-circle me-2"></i>Informasi
            </h6>
            <ul class="small text-muted mb-0 list-unstyled">
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Ukuran maksimal gambar: 5MB
                </li>
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Ukuran maksimal dokumen: 10MB
                </li>
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Format gambar yang didukung: JPG, PNG, WebP, GIF
                </li>
                <li>
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Format dokumen yang didukung: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
    // Search & Filter functionality
    document.getElementById('searchInput').addEventListener('keyup', filterMedia);
    document.getElementById('filterType').addEventListener('change', filterMedia);
    
    function filterMedia() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const filterType = document.getElementById('filterType').value.toLowerCase();
        const cards = document.querySelectorAll('.media-card');
        
        cards.forEach(card => {
            const filename = card.getAttribute('data-filename');
            const filetype = card.getAttribute('data-filetype');
            
            const matchesSearch = filename.includes(searchTerm);
            const matchesFilter = !filterType || filetype.includes(filterType);
            
            card.parentElement.style.display = (matchesSearch && matchesFilter) ? 'block' : 'none';
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>