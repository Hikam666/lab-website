<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'berita';
$extra_css = ['StyleBerita.css'];

// Mengambil slug dari URL
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: berita.php');
    exit;
}

// Mengambil koneksi database
$conn = getDBConnection();

// Get berita detail
$sql = "SELECT 
            b.id_berita,
            b.jenis,
            b.judul,
            b.slug,
            b.ringkasan,
            b.isi_html,
            b.tanggal_mulai,
            b.tanggal_selesai,
            b.dibuat_pada,
            m.lokasi_file as cover_image,
            m.keterangan_alt as cover_alt,
            p.nama_lengkap as penulis_nama,
            p.email as penulis_email
        FROM berita b
        LEFT JOIN media m ON b.id_cover = m.id_media
        LEFT JOIN pengguna p ON b.dibuat_oleh = p.id_pengguna
        WHERE b.slug = $1 AND b.status = 'disetujui'";

$result = pg_query_params($conn, $sql, array($slug));

if (!$result || pg_num_rows($result) == 0) {
    header('Location: berita.php');
    exit;
}

$berita = pg_fetch_assoc($result);

// Set page meta
$page_title = $berita['judul'];
$page_description = $berita['ringkasan'] ? $berita['ringkasan'] : truncateText(strip_tags($berita['isi_html']), 150);
$page_keywords = 'berita, ' . $berita['jenis'] . ', laboratorium teknologi data';

// Include header
include __DIR__ . '/../includes/header.php';

// Default image if no cover
$image_src = $berita['cover_image'] ? SITE_URL . '/uploads/' . $berita['cover_image'] : SITE_URL . '/assets/img/default-news.jpg';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown"><?php echo htmlspecialchars($berita['judul']); ?></h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="berita.php">Berita</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Detail</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Berita Detail Start -->
    <div class="container-fluid py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    
                    <!-- Berita Header -->
                    <div class="mb-4">
                        <span class="badge bg-primary mb-3"><?php echo ucfirst($berita['jenis']); ?></span>
                        <h1 class="mb-3"><?php echo htmlspecialchars($berita['judul']); ?></h1>
                        
                        <div class="d-flex align-items-center text-muted mb-4">
                            <i class="bi bi-calendar3 me-2"></i>
                            <span class="me-4"><?php echo formatTanggalIndo($berita['tanggal_mulai'] ? $berita['tanggal_mulai'] : $berita['dibuat_pada']); ?></span>
                            
                            <?php if ($berita['penulis_nama']): ?>
                            <i class="bi bi-person me-2"></i>
                            <span><?php echo htmlspecialchars($berita['penulis_nama']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cover Image -->
                    <?php if ($berita['cover_image']): ?>
                    <div class="mb-4">
                        <img src="<?php echo $image_src; ?>" 
                             alt="<?php echo htmlspecialchars($berita['cover_alt'] ? $berita['cover_alt'] : $berita['judul']); ?>" 
                             class="img-fluid rounded">
                    </div>
                    <?php endif; ?>

                    <!-- Ringkasan -->
                    <?php if ($berita['ringkasan']): ?>
                    <div class="alert alert-light border-start border-primary border-4 mb-4">
                        <strong>Ringkasan:</strong><br>
                        <?php echo nl2br(htmlspecialchars($berita['ringkasan'])); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Isi Berita -->
                    <div class="berita-content mb-5">
                        <?php echo $berita['isi_html']; ?>
                    </div>

                    <!-- Info Tanggal untuk Agenda -->
                    <?php if ($berita['jenis'] == 'agenda' && $berita['tanggal_mulai']): ?>
                    <div class="alert alert-info mb-4">
                        <h5><i class="bi bi-calendar-event me-2"></i>Informasi Waktu</h5>
                        <p class="mb-0">
                            <strong>Tanggal:</strong> <?php echo formatTanggalIndo($berita['tanggal_mulai']); ?>
                            <?php if ($berita['tanggal_selesai']): ?>
                            <br><strong>Tanggal :</strong> <?php echo formatTanggalIndo($berita['tanggal_selesai']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mb-4">
                        <a href="berita.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                        </a>
                    </div>

                </div>
            </div>

            <!-- Related News -->
            <?php
            // Get related news
            $sql_related = "SELECT 
                                b.judul,
                                b.slug,
                                b.jenis,
                                b.dibuat_pada,
                                m.lokasi_file as cover_image
                            FROM berita b
                            LEFT JOIN media m ON b.id_cover = m.id_media
                            WHERE b.status = 'disetujui' 
                            AND b.jenis = $1 
                            AND b.id_berita != $2
                            ORDER BY b.dibuat_pada DESC
                            LIMIT 3";
            
            $result_related = pg_query_params($conn, $sql_related, array($berita['jenis'], $berita['id_berita']));
            
            if ($result_related && pg_num_rows($result_related) > 0):
            ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="mb-4">Berita Terkait</h3>
                    <div class="row g-4">
                        <?php while ($related = pg_fetch_assoc($result_related)): ?>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <?php if ($related['cover_image']): ?>
                                <img src="<?php echo SITE_URL . '/uploads/' . $related['cover_image']; ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($related['judul']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <span class="badge bg-secondary mb-2"><?php echo ucfirst($related['jenis']); ?></span>
                                    <h5 class="card-title">
                                        <a href="berita-detail.php?slug=<?php echo $related['slug']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($related['judul']); ?>
                                        </a>
                                    </h5>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo formatTanggalIndo($related['dibuat_pada']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <!-- Berita Detail End -->

<?php
// Close database connection
if ($conn) {
    pg_close($conn);
}

// Include footer
include __DIR__ . '/../includes/footer.php';
?>
