<?php
require_once __DIR__ . '/../includes/config.php';

$active_page = 'fasilitas';
$page_title = 'Fasilitas Laboratorium';
$page_keywords = 'fasilitas, peralatan, laboratorium, teknologi';
$page_description = 'Fasilitas dan peralatan Laboratorium Teknologi Data';
$extra_css = ['fasilitas.css'];

$conn = getDBConnection();

$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

$sql = "SELECT 
            f.id_fasilitas,
            f.nama,
            f.slug,
            f.kategori,
            f.deskripsi,
            m.lokasi_file as foto,
            m.keterangan_alt as foto_alt
        FROM fasilitas f
        LEFT JOIN media m ON f.id_foto = m.id_media
        WHERE f.status = 'disetujui'";

if (!empty($kategori_filter)) {
    $sql .= " AND f.kategori = $1";
    $result = pg_query_params($conn, $sql . " ORDER BY f.nama ASC", array($kategori_filter));
} else {
    $result = pg_query($conn, $sql . " ORDER BY f.kategori ASC, f.nama ASC");
}

$sql_kategori = "SELECT DISTINCT kategori 
                 FROM fasilitas 
                 WHERE status = 'disetujui' AND kategori IS NOT NULL 
                 ORDER BY kategori ASC";
$result_kategori = pg_query($conn, $sql_kategori);

include __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Fasilitas Laboratorium</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Fasilitas</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Fasilitas Content Start -->
    <div class="container-fluid py-5">
        <div class="container">
            <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 700px;">
                <h1 class="display-6 mb-4">Fasilitas & Peralatan</h1>
                <p>Laboratorium Teknologi Data dilengkapi dengan berbagai fasilitas modern untuk mendukung kegiatan riset, praktikum, dan pengembangan teknologi.</p>
            </div>

            <!-- Category Filter -->
            <?php if ($result_kategori && pg_num_rows($result_kategori) > 0): ?>
            <div class="text-center mb-4">
                <div class="btn-group flex-wrap" role="group">
                    <a href="fasilitas.php" class="btn <?php echo empty($kategori_filter) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Semua
                    </a>
                    <?php while ($kat = pg_fetch_assoc($result_kategori)): ?>
                    <a href="?kategori=<?php echo urlencode($kat['kategori']); ?>" 
                       class="btn <?php echo ($kategori_filter == $kat['kategori']) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        <?php echo htmlspecialchars($kat['kategori']); ?>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Fasilitas Grid -->
            <div class="row g-4">
                <?php 
                if ($result && pg_num_rows($result) > 0):
                    $delay = 0.1;
                    $current_kategori = '';
                    
                    while ($row = pg_fetch_assoc($result)):
                        // Show kategori header if changed
                        if (empty($kategori_filter) && $row['kategori'] != $current_kategori) {
                            if ($current_kategori != '') {
                                echo '</div><div class="row g-4">';
                            }
                            $current_kategori = $row['kategori'];
                            ?>
                            <div class="col-12 mt-5">
                                <h3 class="fw-bold text-primary border-bottom pb-2">
                                    <?php echo htmlspecialchars($current_kategori); ?>
                                </h3>
                            </div>
                            <?php
                        }
                        if (!empty($row['foto'])) {
                            $rel_path  = ltrim($row['foto'], '/');
                            $image_src = SITE_URL . '/uploads/' . $rel_path;
                            $alt_text  = !empty($row['foto_alt'])
                                ? $row['foto_alt']
                                : $row['nama'];
                        } else {
                            $image_src = SITE_URL . '/assets/img/default-cover.jpg';
                            $alt_text  = $row['nama'];
                        }
                ?>
                
                <!-- Fasilitas Card -->
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                    <div class="card facility-card h-100 border-0 shadow-sm">
                        <div class="facility-image">
                            <img src="<?php echo $image_src; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($alt_text); ?>">
                        </div>
                        <div class="card-body">
                            <?php if ($row['kategori']): ?>
                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($row['kategori']); ?></span>
                            <?php endif; ?>
                            
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($row['nama']); ?></h5>
                            
                            <?php if ($row['deskripsi']): ?>
                            <p class="card-text text-muted">
                                <?php echo truncateText($row['deskripsi'], 150); ?>
                            </p>
                            <?php endif; ?>
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
                        Belum ada data fasilitas yang tersedia.
                    </div>
                </div>
                
                <?php endif; ?>
            </div>

        </div>
    </div>
    <!-- Fasilitas Content End -->

<?php
if ($conn) {
    pg_close($conn);
}

include __DIR__ . '/../includes/footer.php';
?>
