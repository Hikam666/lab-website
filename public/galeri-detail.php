<?php
require_once __DIR__ . '/../includes/config.php';

$active_page = 'galeri';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: galeri.php');
    exit;
}

$conn = getDBConnection();

// Get album detail
$sql = "SELECT 
            ga.id_album,
            ga.judul,
            ga.slug,
            ga.deskripsi,
            ga.dibuat_pada,
            m.lokasi_file as cover_image,
            p.nama_lengkap as dibuat_oleh_nama
        FROM galeri_album ga
        LEFT JOIN media m ON ga.id_cover = m.id_media
        LEFT JOIN pengguna p ON ga.dibuat_oleh = p.id_pengguna
        WHERE ga.slug = $1 AND ga.status = 'disetujui'";

$result = pg_query_params($conn, $sql, array($slug));

if (!$result || pg_num_rows($result) == 0) {
    header('Location: galeri.php');
    exit;
}

$album = pg_fetch_assoc($result);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$per_page = 12; 
$offset   = ($page - 1) * $per_page;

$sql_count = "SELECT COUNT(*) as total 
              FROM galeri_item 
              WHERE id_album = $1
                AND status = 'disetujui'";
$result_count = pg_query_params($conn, $sql_count, array($album['id_album']));
$row_count    = pg_fetch_assoc($result_count);
$total_items = (int)($row_count['total'] ?? 0);

$total_pages = $total_items > 0 ? (int)ceil($total_items / $per_page) : 1;

// PERBAIKAN: Tambahkan m.tipe_file untuk deteksi video
$sql_items = "SELECT 
                gi.id_item,
                gi.caption,
                gi.urutan,
                gi.dibuat_pada,
                m.lokasi_file,
                m.tipe_file,
                m.keterangan_alt
            FROM galeri_item gi
            JOIN media m ON gi.id_media = m.id_media
            WHERE gi.id_album = $1
              AND gi.status = 'disetujui'
            ORDER BY gi.urutan ASC, gi.dibuat_pada ASC
            LIMIT $per_page OFFSET $offset";

$result_items = pg_query_params($conn, $sql_items, array($album['id_album']));

// Set page meta
$page_title       = $album['judul'] . ' - Galeri';
$page_description = $album['deskripsi'] ? $album['deskripsi'] : 'Album foto ' . $album['judul'];
$page_keywords    = 'galeri, foto, ' . $album['judul'];
$extra_css        = ['galeri.css'];

include __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">
                <?php echo htmlspecialchars($album['judul']); ?>
            </h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="galeri.php">Galeri</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php echo htmlspecialchars($album['judul']); ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Album Detail Start -->
    <div class="container-fluid py-5">
        <div class="container">
            
            <!-- Album Info -->
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto">

                    <?php if ($album['deskripsi']): ?>
                        <p class="lead text-center">
                            <?php echo nl2br(htmlspecialchars($album['deskripsi'])); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="text-center text-muted">
                        <i class="bi bi-calendar3 me-2"></i>
                        <?php echo formatTanggalIndo($album['dibuat_pada']); ?>
                        
                        <?php if ($album['dibuat_oleh_nama']): ?>
                        <span class="ms-3">
                            <i class="bi bi-person me-2"></i>
                            <?php echo htmlspecialchars($album['dibuat_oleh_nama']); ?>
                        </span>
                        <?php endif; ?>
                        
                        <span class="ms-3">
                            <i class="bi bi-images me-2"></i>
                            <?php echo $total_items; ?> Item
                        </span>
                    </div>

                    <?php if ($total_items > 0 && $total_pages > 1): ?>
                        <p class="text-center text-muted mt-2">
                            Menampilkan item 
                            <?php echo $offset + 1; ?> - 
                            <?php echo min($offset + $per_page, $total_items); ?>
                            (Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Photo & Video Grid -->
            <div class="row g-4">
                <?php 
                if ($result_items && pg_num_rows($result_items) > 0):
                    $delay = 0.1;
                    while ($item = pg_fetch_assoc($result_items)):
                        $media_src = '../uploads/' . $item['lokasi_file'];
                        $is_video = strpos($item['tipe_file'], 'video/') === 0;
                ?>
                
                <!-- Media Item -->
                <div class="col-lg-3 col-md-4 col-sm-6 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                    <div class="gallery-photo-item">
                        <?php if ($is_video): ?>
                            <!-- Video Item -->
                            <div class="video-container">
                                <video controls class="img-fluid rounded" style="width: 100%; height: 250px; object-fit: cover;">
                                    <source src="<?php echo $media_src; ?>" type="<?php echo htmlspecialchars($item['tipe_file']); ?>">
                                    Browser Anda tidak mendukung tag video.
                                </video>
                                <?php if ($item['caption']): ?>
                                <div class="photo-caption">
                                    <i class="bi bi-camera-video me-1"></i>
                                    <?php echo htmlspecialchars($item['caption']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Photo Item -->
                            <a href="<?php echo $media_src; ?>" 
                               data-lightbox="album-<?php echo $album['id_album']; ?>" 
                               data-title="<?php echo htmlspecialchars($item['caption'] ? $item['caption'] : $item['keterangan_alt']); ?>">
                                <img src="<?php echo $media_src; ?>" 
                                     alt="<?php echo htmlspecialchars($item['keterangan_alt']); ?>" 
                                     class="img-fluid rounded"
                                     style="width: 100%; height: 250px; object-fit: cover;"
                                     onerror="this.src='../assets/img/default-gallery.jpg'">
                                <?php if ($item['caption']): ?>
                                <div class="photo-caption">
                                    <?php echo htmlspecialchars($item['caption']); ?>
                                </div>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php 
                        $delay += 0.1;
                        if ($delay > 0.5) $delay = 0.1;
                    endwhile;
                else:
                ?>
                
                <!-- No Items Message -->
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        Album ini belum memiliki foto atau video.
                    </div>
                </div>
                
                <?php endif; ?>
            </div>

            <!-- Pagination Start -->
            <?php if ($total_items > 0 && $total_pages > 1): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <nav aria-label="Photo pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?slug=<?php echo urlencode($slug); ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 3);
                            $end_page   = min($total_pages, $page + 3);
                            if ($start_page > 1):
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="?slug=<?php echo urlencode($slug); ?>&page=1">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?slug=<?php echo urlencode($slug); ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?slug=<?php echo urlencode($slug); ?>&page=<?php echo $total_pages; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?slug=<?php echo urlencode($slug); ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
            <!-- Pagination End -->

            <!-- Back Button -->
            <div class="text-center mt-5">
                <a href="galeri.php" class="btn btn-primary py-3 px-5">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Galeri
                </a>
            </div>

        </div>
    </div>
    <!-- Album Detail End -->

    <!-- Lightbox2 CSS & JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
<?php
if ($conn) {
    pg_close($conn);
}

include __DIR__ . '/../includes/footer.php';
?>