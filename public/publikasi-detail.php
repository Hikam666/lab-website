<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'publikasi';

// Mengambil slug dari URL
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: publikasi.php');
    exit;
}

// Mengambil koneksi database
$conn = getDBConnection();

// Get publikasi detail with authors
$sql = "SELECT 
            p.id_publikasi,
            p.judul,
            p.slug,
            p.abstrak,
            p.jenis,
            p.tempat,
            p.tahun,
            p.doi,
            m.lokasi_file as cover_image,
            m.keterangan_alt as cover_alt
        FROM publikasi p
        LEFT JOIN media m ON p.id_cover = m.id_media
        WHERE p.slug = $1 AND p.status = 'disetujui'";

$result = pg_query_params($conn, $sql, array($slug));

if (!$result || pg_num_rows($result) == 0) {
    header('Location: publikasi.php');
    exit;
}

$publikasi = pg_fetch_assoc($result);

// Get authors
$sql_authors = "SELECT 
                    a.nama,
                    a.email,
                    a.peran_lab,
                    pp.urutan
                FROM publikasi_penulis pp
                JOIN anggota_lab a ON pp.id_anggota = a.id_anggota
                WHERE pp.id_publikasi = $1
                ORDER BY pp.urutan ASC";
$result_authors = pg_query_params($conn, $sql_authors, array($publikasi['id_publikasi']));

// Set page meta
$page_title = $publikasi['judul'];
$page_description = $publikasi['abstrak'] ? truncateText($publikasi['abstrak'], 150) : $publikasi['judul'];
$page_keywords = 'publikasi, ' . $publikasi['jenis'] . ', penelitian, ' . $publikasi['tahun'];

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Detail Publikasi</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="publikasi.php">Publikasi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Detail</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Publikasi Detail Start -->
    <div class="container-fluid py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    
                    <!-- Publication Header -->
                    <div class="mb-4">
                        <span class="badge bg-primary mb-3"><?php echo htmlspecialchars($publikasi['jenis']); ?></span>
                        <h1 class="mb-3"><?php echo htmlspecialchars($publikasi['judul']); ?></h1>
                        
                        <!-- Authors -->
                        <?php if ($result_authors && pg_num_rows($result_authors) > 0): ?>
                        <div class="mb-3">
                            <h5 class="text-muted">Penulis:</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <?php while ($author = pg_fetch_assoc($result_authors)): ?>
                                <div class="author-badge">
                                    <i class="bi bi-person-circle me-1"></i>
                                    <?php echo htmlspecialchars($author['nama']); ?>
                                    <?php if ($author['peran_lab']): ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($author['peran_lab']); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Meta Information -->
                        <div class="publication-meta mb-4">
                            <?php if ($publikasi['tempat']): ?>
                            <div class="meta-item">
                                <i class="bi bi-journal-text me-2"></i>
                                <strong>Published in:</strong> <?php echo htmlspecialchars($publikasi['tempat']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="meta-item">
                                <i class="bi bi-calendar-event me-2"></i>
                                <strong>Tahun:</strong> <?php echo $publikasi['tahun']; ?>
                            </div>
                            
                            <?php if ($publikasi['doi']): ?>
                            <div class="meta-item">
                                <i class="bi bi-link-45deg me-2"></i>
                                <strong>DOI:</strong> 
                                <a href="https://doi.org/<?php echo htmlspecialchars($publikasi['doi']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($publikasi['doi']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cover Image -->
                    <?php if ($publikasi['cover_image']): ?>
                    <div class="mb-4">
                        <img src="<?php echo SITE_URL . '/uploads/' . $publikasi['cover_image']; ?>" 
                             alt="<?php echo htmlspecialchars($publikasi['cover_alt'] ? $publikasi['cover_alt'] : $publikasi['judul']); ?>" 
                             class="img-fluid rounded">
                    </div>
                    <?php endif; ?>

                    <!-- Abstract -->
                    <?php if ($publikasi['abstrak']): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title mb-3">Abstrak</h4>
                            <p class="text-justify"><?php echo nl2br(htmlspecialchars($publikasi['abstrak'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mb-4">
                        <?php if ($publikasi['doi']): ?>
                        <a href="https://doi.org/<?php echo htmlspecialchars($publikasi['doi']); ?>" 
                           target="_blank" 
                           class="btn btn-primary">
                            <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Full Text
                        </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print
                        </button>
                        
                        <a href="publikasi.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Kembali
                        </a>
                    </div>

                    <!-- Citation -->
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Cara Sitasi (APA Style)</h5>
                            <p class="mb-0 font-monospace small">
                                <?php
                                // Generate APA citation
                                $authors_list = array();
                                pg_result_seek($result_authors, 0);
                                while ($auth = pg_fetch_assoc($result_authors)) {
                                    $authors_list[] = $auth['nama'];
                                }
                                $authors_str = implode(', ', $authors_list);
                                
                                echo htmlspecialchars($authors_str) . ' (' . $publikasi['tahun'] . '). ';
                                echo htmlspecialchars($publikasi['judul']) . '. ';
                                if ($publikasi['tempat']) {
                                    echo '<em>' . htmlspecialchars($publikasi['tempat']) . '</em>. ';
                                }
                                if ($publikasi['doi']) {
                                    echo 'https://doi.org/' . htmlspecialchars($publikasi['doi']);
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Related Publications -->
            <?php
            $sql_related = "SELECT 
                                p.judul,
                                p.slug,
                                p.jenis,
                                p.tahun
                            FROM publikasi p
                            WHERE p.status = 'disetujui' 
                            AND p.id_publikasi != $1
                            AND p.jenis = $2
                            ORDER BY p.tahun DESC
                            LIMIT 3";
            
            $result_related = pg_query_params($conn, $sql_related, array($publikasi['id_publikasi'], $publikasi['jenis']));
            
            if ($result_related && pg_num_rows($result_related) > 0):
            ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="mb-4">Publikasi Terkait</h3>
                    <div class="row g-4">
                        <?php while ($related = pg_fetch_assoc($result_related)): ?>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($related['jenis']); ?></span>
                                    <h6 class="card-title">
                                        <a href="publikasi-detail.php?slug=<?php echo $related['slug']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($related['judul']); ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted">Tahun: <?php echo $related['tahun']; ?></small>
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
    <!-- Publikasi Detail End -->

<?php
// Close database connection
if ($conn) {
    pg_close($conn);
}

// Include footer
include __DIR__ . '/../includes/footer.php';
?>
