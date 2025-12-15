<?php
require_once __DIR__ . '/../includes/config.php';

$active_page = 'berita';
$page_title = 'Berita & Agenda';
$page_keywords = 'berita laboratorium, agenda kegiatan, pengumuman';
$page_description = 'Berita terkini, agenda kegiatan, dan pengumuman Laboratorium Teknologi Data';
$extra_css = ['StyleBerita.css'];

$conn = getDBConnection();

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9; 
$offset = ($page - 1) * $per_page;

// Query COUNT
$sql_count = "SELECT COUNT(*) as total FROM berita WHERE status = 'disetujui'";
if ($filter != 'all') {
    $sql_count .= " AND jenis = '" . escapeString($conn, $filter) . "'";
}
$result_count = pg_query($conn, $sql_count);
$total_rows = pg_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $per_page);

$sql = "SELECT 
            b.id_berita,
            b.jenis,
            b.judul,
            b.slug,
            b.ringkasan,
            b.tanggal_mulai,
            b.dibuat_pada,
            m.lokasi_file as cover_image,
            m.keterangan_alt as cover_alt,
            b.penulis as penulis_nama  
        FROM berita b
        LEFT JOIN media m ON b.id_cover = m.id_media
        WHERE b.status = 'disetujui'";

if ($filter != 'all') {
    $sql .= " AND b.jenis = '" . escapeString($conn, $filter) . "'";
}

$sql .= " ORDER BY b.dibuat_pada DESC LIMIT $per_page OFFSET $offset";

$result = pg_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
?>

    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Berita & Pengumuman</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Berita & Pengumuman</li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="container-fluid py-3 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="news-filter-wrapper">
                        <div class="news-filter-tabs">
                            <a href="?filter=all" class="news-filter-btn <?php echo ($filter == 'all') ? 'active' : ''; ?>">
                                <i class="bi bi-grid-fill me-2"></i>Semua
                            </a>
                            <a href="?filter=berita" class="news-filter-btn <?php echo ($filter == 'berita') ? 'active' : ''; ?>">
                                <i class="bi bi-newspaper me-2"></i>Berita
                            </a>
                            <a href="?filter=pengumuman" class="news-filter-btn <?php echo ($filter == 'pengumuman') ? 'active' : ''; ?>">
                                <i class="bi bi-megaphone me-2"></i>Pengumuman
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid py-5 bg-light" id="semua-berita">
        <div class="container">
            <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h1 class="display-6 mb-4">
                    <?php 
                        if ($filter == 'berita') echo 'Semua Berita';
                        elseif ($filter == 'pengumuman') echo 'Pengumuman';
                        else echo 'Semua Berita & Pengumuman';
                    ?>
                </h1>
            </div>

<div class="row g-4" id="newsGrid">
    <?php 
    if ($result && pg_num_rows($result) > 0):
        $delay = 0.1;
        while ($row = pg_fetch_assoc($result)):
            $tanggal = strtotime($row['tanggal_mulai'] ? $row['tanggal_mulai'] : $row['dibuat_pada']);
            $hari = date('d', $tanggal);
            $bulan_short = date('M', $tanggal);
            $badge_class = 'badge-' . $row['jenis'];
            $badge_label = ucfirst($row['jenis']);
            $image_src = $row['cover_image'] ? '../uploads/' . $row['cover_image'] : '../assets/img/default-news.jpg';
            $image_alt = $row['cover_alt'] ? $row['cover_alt'] : $row['judul'];
    ?>
    
    <div class="col-lg-4 col-md-6 news-item" 
          data-category="<?php echo $row['jenis']; ?>" 
          style="opacity: 1 !important; visibility: visible !important; display: block !important;">
        
        <div class="news-card" style="display: block !important; opacity: 1 !important; visibility: visible !important;">
            <div class="news-card-image">
                <img src="<?php echo $image_src; ?>" 
                    alt="<?php echo htmlspecialchars($image_alt); ?>" 
                    onerror="this.src='../assets/img/default-news.jpg'"
                    style="display: block !important;">
                <div class="news-card-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></div>
                <div class="news-card-date">
                    <div class="date-day"><?php echo $hari; ?></div>
                    <div class="date-month"><?php echo $bulan_short; ?></div>
                </div>
            </div>
            <div class="news-card-content">
                <h4 class="news-card-title">
                    <a href="berita-detail.php?slug=<?php echo $row['slug']; ?>">
                        <?php echo htmlspecialchars($row['judul']); ?>
                    </a>
                </h4>
                <p class="news-card-excerpt">
                    <?php echo truncateText($row['ringkasan'] ? $row['ringkasan'] : strip_tags($row['judul']), 120); ?>
                </p>
                <div class="news-card-footer">
                    <span>
                        <i class="bi bi-person me-1"></i> 
                        <?php 
                        echo htmlspecialchars($row['penulis_nama'] ?: 'Admin'); 
                        ?>
                    </span>
                    <a href="berita-detail.php?slug=<?php echo $row['slug']; ?>" class="news-read-more">
                        Baca <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
            $delay += 0.2;
            if ($delay > 0.5) $delay = 0.1;
        endwhile;
    else:
    ?>
    
    <div class="col-12">
        <div class="alert alert-info text-center" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            Belum ada <?php echo ($filter == 'all') ? 'berita atau agenda' : $filter; ?> yang tersedia saat ini.
        </div>
    </div>
    
    <?php endif; ?>
</div>

            <?php if ($total_pages > 1): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <nav aria-label="News pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 3);
                            $end_page = min($total_pages, $page + 3);
                            
                            if ($start_page > 1):
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=1">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                            <?php endif; ?>

                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
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
    </div>

<?php
if ($conn) {
    pg_close($conn);
}

include __DIR__ . '/../includes/footer.php';
?>