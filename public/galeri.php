<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'galeri';
$page_title = 'Galeri Kegiatan';
$page_keywords = 'galeri, foto, kegiatan, laboratorium';
$page_description = 'Galeri foto kegiatan dan dokumentasi Laboratorium Teknologi Data';
$extra_css = ['galeri.css'];

// Mengambil koneksi database
$conn = getDBConnection();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9; 
$offset = ($page - 1) * $per_page;

$sql_count = "SELECT COUNT(*) as total FROM galeri_album WHERE status = 'disetujui'";
$result_count = pg_query($conn, $sql_count);
$total_rows = pg_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $per_page);

$sql = "SELECT 
            ga.id_album,
            ga.judul,
            ga.slug,
            ga.deskripsi,
            ga.dibuat_pada,
            m.lokasi_file as cover_image,
            m.keterangan_alt as cover_alt,
            COUNT(gi.id_item) as jumlah_foto
        FROM galeri_album ga
        LEFT JOIN media m ON ga.id_cover = m.id_media
        LEFT JOIN galeri_item gi ON ga.id_album = gi.id_album AND gi.status = 'disetujui' 
        WHERE ga.status = 'disetujui'
        GROUP BY ga.id_album, m.lokasi_file, m.keterangan_alt
        ORDER BY ga.dibuat_pada DESC
        LIMIT $per_page OFFSET $offset";

$result = pg_query($conn, $sql);

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Galeri Kegiatan</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Galeri</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Galeri Albums Start -->
    <div class="container-fluid py-5">
        <div class="container">
            <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h1 class="display-6 mb-4">Album Galeri</h1>
                <p>Dokumentasi kegiatan, riset, dan event di Laboratorium Teknologi Data</p>
                <?php if ($total_rows > 0): ?>
                <p class="text-muted">
                    Menampilkan <?php echo min($per_page, $total_rows - $offset); ?> dari <?php echo $total_rows; ?> album
                    <?php if ($total_pages > 1): ?>
                        (Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <?php 
                if ($result && pg_num_rows($result) > 0):
                    $delay = 0.1;
                    while ($row = pg_fetch_assoc($result)):
                        $image_src = $row['cover_image'] ? '../uploads/' . $row['cover_image'] : '../assets/img/default-gallery.jpg';
                ?>
                
                <!-- Album Card -->
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                    <div class="gallery-album-card">
                        <div class="gallery-album-image">
                            <img src="<?php echo $image_src; ?>" 
                                 alt="<?php echo htmlspecialchars($row['judul']); ?>"
                                 onerror="this.src='../assets/img/default-gallery.jpg'">
                            <div class="gallery-album-overlay">
                                <div class="gallery-album-info">
                                    <i class="bi bi-images"></i>
                                    <span><?php echo $row['jumlah_foto']; ?> File</span>
                                </div>
                                <a href="galeri-detail.php?slug=<?php echo $row['slug']; ?>" class="btn btn-light">
                                    Lihat Album
                                </a>
                            </div>
                        </div>
                        <div class="gallery-album-content">
                            <h4>
                                <a href="galeri-detail.php?slug=<?php echo $row['slug']; ?>">
                                    <?php echo htmlspecialchars($row['judul']); ?>
                                </a>
                            </h4>
                            <?php if ($row['deskripsi']): ?>
                            <p><?php echo truncateText($row['deskripsi'], 100); ?></p>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo formatTanggalIndo($row['dibuat_pada']); ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <?php 
                        $delay += 0.2;
                        if ($delay > 0.5) $delay = 0.1;
                    endwhile;
                else:
                ?>
                
                <!-- No Data Message -->
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        Belum ada album galeri yang tersedia saat ini.
                    </div>
                </div>
                
                <?php endif; ?>
            </div>

            <!-- Pagination Start -->
            <?php if ($total_pages > 1): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <nav aria-label="Gallery pagination">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Button -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 3);
                            $end_page = min($total_pages, $page + 3);

                            if ($start_page > 1):
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <!-- Last page -->
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Next Button -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Galeri Albums End -->

<?php

if ($conn) {
    pg_close($conn);
}

include __DIR__ . '/../includes/footer.php';
?>