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

$query = "SELECT id_media, lokasi_file, tipe_file, keterangan_alt, ukuran_file, dibuat_pada 
          FROM media 
          ORDER BY dibuat_pada DESC";

$result = pg_query($conn, $query);

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

if (!function_exists('getFileIcon')) {
    function getFileIcon($tipe_file) {
        $tipe_file = strtolower($tipe_file);
        
        if (strpos($tipe_file, 'image') !== false) {
            return 'bi-image text-primary';
        } elseif (strpos($tipe_file, 'video') !== false) {
            return 'bi-camera-video text-danger';
        } elseif (strpos($tipe_file, 'pdf') !== false) {
            return 'bi-file-pdf text-danger';
        } elseif (strpos($tipe_file, 'word') !== false || strpos($tipe_file, 'wordprocessing') !== false) {
            return 'bi-file-word text-info';
        } elseif (strpos($tipe_file, 'sheet') !== false || strpos($tipe_file, 'excel') !== false || strpos($tipe_file, 'spreadsheet') !== false) {
            return 'bi-file-spreadsheet text-success';
        } elseif (strpos($tipe_file, 'presentation') !== false || strpos($tipe_file, 'powerpoint') !== false) {
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
            <p class="text-muted">Kelola file gambar, video, dan dokumen</p>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['msg_type']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Cari file (nama)...">
                </div>
                <div class="col-md-6">
                    <select class="form-select" id="filterType">
                        <option value="">Semua File</option>
                        <optgroup label="Gambar">
                            <option value="jpg">JPG / JPEG</option>
                            <option value="png">PNG</option>
                            <option value="webp">WebP</option>
                        </optgroup>
                        <optgroup label="Video">
                            <option value="mp4">MP4</option>
                            <option value="webm">WebM</option>
                            <option value="ogg">OGG</option>
                            <option value="mov">MOV</option>
                        </optgroup>
                        <optgroup label="Dokumen">
                            <option value="pdf">PDF</option>
                            <option value="docx">DOCX (Word)</option>
                            <option value="xlsx">XLSX (Excel)</option>
                            <option value="pptx">PPTX (PowerPoint)</option>
                        </optgroup>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if ($result && pg_num_rows($result) > 0): ?>
            <?php while ($row = pg_fetch_assoc($result)): 
                $file_path = __DIR__ . '/../../uploads/' . $row['lokasi_file'];
                $is_image = strpos($row['tipe_file'], 'image') !== false;
                $is_video = strpos($row['tipe_file'], 'video') !== false;
                $icon_class = getFileIcon($row['tipe_file']);
                $nama_file = basename($row['lokasi_file']);
            ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 shadow-sm border-0 media-card" 
                         data-filename="<?php echo strtolower($nama_file); ?>" 
                         data-filetype="<?php echo strtolower($row['tipe_file']); ?>">
                        
                        <div class="position-relative" style="height: 180px; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                            <?php if ($is_image): ?>
                                <img src="../../uploads/<?php echo htmlspecialchars($row['lokasi_file']); ?>" 
                                     class="w-100 h-100" 
                                     style="object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($row['keterangan_alt'] ?? $nama_file); ?>"
                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'text-center\'><i class=\'bi bi-image-alt\' style=\'font-size: 3rem; color: white; opacity: 0.5;\'></i><br><small style=\'color: white;\'>Gambar tidak ditemukan</small></div>';">
                            <?php elseif ($is_video): ?>
                                <video class="w-100 h-100" style="object-fit: cover;" muted>
                                    <source src="../../uploads/<?php echo htmlspecialchars($row['lokasi_file']); ?>" type="<?php echo htmlspecialchars($row['tipe_file']); ?>">
                                </video>
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <i class="bi bi-play-circle" style="font-size: 3rem; color: white; opacity: 0.8;"></i>
                                </div>
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
                    <p class="text-muted mb-0 mt-3">Belum ada file media.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm mt-5 bg-light">
        <div class="card-body">
            <h6 class="card-title fw-bold mb-3">
                <i class="bi bi-info-circle me-2"></i>Informasi
            </h6>
            <ul class="small text-muted mb-0 list-unstyled">
                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Ukuran maksimal: 5MB (gambar/dokumen), 50MB (video)</li>
                <li><i class="bi bi-check-circle text-success me-2"></i>Format: JPG, PNG, WebP, GIF, MP4, WebM, OGG, MOV, PDF, DOCX, XLSX, PPTX</li>
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
            let matchesFilter = true;

            if (filterType) {
                if (filterType === 'jpg') {
                    matchesFilter = filetype.includes('jpeg') || filename.endsWith('.jpg') || filename.endsWith('.jpeg');
                } 
                else if (filterType === 'docx') {
                    matchesFilter = filetype.includes('wordprocessing') || filename.endsWith('.docx') || filename.endsWith('.doc');
                } 
                else if (filterType === 'xlsx') {
                    matchesFilter = filetype.includes('spreadsheet') || filename.endsWith('.xlsx') || filename.endsWith('.xls');
                } 
                else if (filterType === 'pptx') {
                    matchesFilter = filetype.includes('presentation') || filename.endsWith('.pptx') || filename.endsWith('.ppt');
                } 
                else if (filterType === 'mp4') {
                    matchesFilter = filetype.includes('mp4') || filename.endsWith('.mp4');
                }
                else if (filterType === 'webm') {
                    matchesFilter = filetype.includes('webm') || filename.endsWith('.webm');
                }
                else if (filterType === 'ogg') {
                    matchesFilter = filetype.includes('ogg') || filename.endsWith('.ogg') || filename.endsWith('.ogv');
                }
                else if (filterType === 'mov') {
                    matchesFilter = filetype.includes('quicktime') || filename.endsWith('.mov');
                }
                else {
                    matchesFilter = filetype.includes(filterType) || filename.endsWith('.' + filterType);
                }
            }
            
            card.parentElement.style.display = (matchesSearch && matchesFilter) ? 'block' : 'none';
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>