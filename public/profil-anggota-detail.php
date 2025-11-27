<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'profil';
$extra_css = ['profil.css', 'profil-detail.css'];

// Mengambil slug dari URL
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: profil.php');
    exit;
}

// Mengambil koneksi database
$conn = getDBConnection();

// Get anggota detail
$sql = "SELECT 
            a.*,
            m.lokasi_file as foto,
            m.keterangan_alt as foto_alt
        FROM anggota_lab a
        LEFT JOIN media m ON a.id_foto = m.id_media
        WHERE a.slug = $1 AND a.aktif = TRUE";

$result = pg_query_params($conn, $sql, array($slug));

if (!$result || pg_num_rows($result) == 0) {
    header('Location: profil.php');
    exit;
}

$anggota = pg_fetch_assoc($result);

// Get pendidikan
$sql_pendidikan = "SELECT * FROM anggota_pendidikan WHERE id_anggota = $1 ORDER BY urutan ASC, tahun_selesai DESC";
$result_pendidikan = pg_query_params($conn, $sql_pendidikan, array($anggota['id_anggota']));
$pendidikan_list = [];
if ($result_pendidikan) {
    while ($row = pg_fetch_assoc($result_pendidikan)) {
        $pendidikan_list[] = $row;
    }
}

// Get sertifikasi
$sql_sertifikasi = "SELECT * FROM anggota_sertifikasi WHERE id_anggota = $1 ORDER BY urutan ASC, tahun DESC";
$result_sertifikasi = pg_query_params($conn, $sql_sertifikasi, array($anggota['id_anggota']));
$sertifikasi_list = [];
if ($result_sertifikasi) {
    while ($row = pg_fetch_assoc($result_sertifikasi)) {
        $sertifikasi_list[] = $row;
    }
}

// Get mata kuliah
$sql_matakuliah = "SELECT * FROM anggota_matakuliah WHERE id_anggota = $1 ORDER BY semester, urutan ASC";
$result_matakuliah = pg_query_params($conn, $sql_matakuliah, array($anggota['id_anggota']));
$matakuliah_ganjil = [];
$matakuliah_genap = [];
if ($result_matakuliah) {
    while ($row = pg_fetch_assoc($result_matakuliah)) {
        if ($row['semester'] === 'ganjil') {
            $matakuliah_ganjil[] = $row;
        } else {
            $matakuliah_genap[] = $row;
        }
    }
}

// Get publikasi
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 3;
$offset = ($page - 1) * $per_page;

// Build SQL for publikasi
$sql_publikasi = "SELECT 
            p.id_publikasi,
            p.judul,
            p.slug,
            p.jenis,
            p.tempat,
            p.tahun,
            pp.urutan as urutan_penulis
        FROM publikasi_penulis pp
        JOIN publikasi p ON pp.id_publikasi = p.id_publikasi
        WHERE pp.id_anggota = $1 AND p.status = 'disetujui'";

// Add sorting
switch ($sort) {
    case 'oldest':
        $sql_publikasi .= " ORDER BY p.tahun ASC, p.dibuat_pada ASC";
        break;
    case 'cited':
        $sql_publikasi .= " ORDER BY p.tahun DESC, p.dibuat_pada DESC";
        break;
    case 'latest':
    default:
        $sql_publikasi .= " ORDER BY p.tahun DESC, p.dibuat_pada DESC";
        break;
}

$sql_publikasi .= " LIMIT $per_page OFFSET $offset";

$result_publikasi = pg_query_params($conn, $sql_publikasi, array($anggota['id_anggota']));

// Get total publikasi count for pagination
$sql_count = "SELECT COUNT(*) as total FROM publikasi_penulis pp 
              JOIN publikasi p ON pp.id_publikasi = p.id_publikasi 
              WHERE pp.id_anggota = $1 AND p.status = 'disetujui'";
$result_count = pg_query_params($conn, $sql_count, array($anggota['id_anggota']));
$total_publikasi = pg_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_publikasi / $per_page);

// Set page meta
$page_title = $anggota['nama'] . ' - Profil';
$page_description = ($anggota['jabatan'] ?? 'Anggota') . ' - Laboratorium Teknologi Data';
$page_keywords = 'profil, dosen, peneliti, ' . $anggota['nama'];

// Include header
include __DIR__ . '/../includes/header.php';

$image_src = $anggota['foto'] ? SITE_URL . '/uploads/' . $anggota['foto'] : SITE_URL . '/assets/img/default-avatar.jpg';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown"><?php echo htmlspecialchars($anggota['nama']); ?></h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="profil.php">Profil</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($anggota['nama']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Profile Detail Start -->
    <div class="container-fluid py-5">
        <div class="container">
            
            <!-- Profile Header Card -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <img src="<?php echo $image_src; ?>" 
                                 alt="<?php echo htmlspecialchars($anggota['nama']); ?>" 
                                 class="img-fluid rounded-circle border border-5 border-primary" 
                                 style="width: 200px; height: 200px; object-fit: cover;">
                        </div>
                        <div class="col-md-9">
                            <h2 class="mb-2"><?php echo htmlspecialchars($anggota['nama']); ?></h2>
                            
                            <?php if ($anggota['jabatan']): ?>
                            <p class="text-muted mb-2"><i class="bi bi-briefcase me-2"></i><strong><?php echo htmlspecialchars($anggota['jabatan']); ?></strong></p>
                            <?php endif; ?>
                            
                            <?php if ($anggota['program_studi']): ?>
                            <p class="text-muted mb-2"><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($anggota['program_studi']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($anggota['email']): ?>
                            <p class="text-muted mb-2"><i class="bi bi-envelope me-2"></i><a href="mailto:<?php echo htmlspecialchars($anggota['email']); ?>"><?php echo htmlspecialchars($anggota['email']); ?></a></p>
                            <?php endif; ?>
                            
                            <?php if ($anggota['nip']): ?>
                            <p class="text-muted mb-2"><i class="bi bi-card-text me-2"></i>NIP: <?php echo htmlspecialchars($anggota['nip']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($anggota['nidn']): ?>
                            <p class="text-muted mb-2"><i class="bi bi-card-list me-2"></i>NIDN: <?php echo htmlspecialchars($anggota['nidn']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($anggota['alamat_kantor']): ?>
                            <p class="text-muted mb-3"><i class="bi bi-geo-alt me-2"></i><?php echo nl2br(htmlspecialchars($anggota['alamat_kantor'])); ?></p>
                            <?php endif; ?>
                            
                            <!-- Social Media Links -->
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($anggota['linkedin']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['linkedin']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-linkedin me-1"></i>LinkedIn
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['google_scholar']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['google_scholar']); ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-google me-1"></i>Google Scholar
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['sinta']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['sinta']); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-file-earmark-text me-1"></i>Sinta
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['website']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['website']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-globe me-1"></i>Website
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($anggota['email']); ?>" class="btn btn-outline-dark btn-sm">
                                    <i class="bi bi-envelope me-1"></i>Email
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['cv_file']): ?>
                                <a href="<?php echo SITE_URL . '/uploads/' . $anggota['cv_file']; ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>CV
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Keahlian Tags -->
                    <?php if ($anggota['keahlian']): ?>
                    <div class="mt-4">
                        <h6 class="text-muted mb-2">Keahlian:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                            $keahlian_array = array_map('trim', explode(',', $anggota['keahlian']));
                            foreach ($keahlian_array as $skill):
                            ?>
                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($skill); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pendidikan & Sertifikasi -->
            <div class="row mb-4">
                <!-- Pendidikan -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-mortarboard me-2"></i>Pendidikan</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pendidikan_list)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($pendidikan_list as $pend): ?>
                                <li class="mb-3">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <div class="badge bg-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-mortarboard"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($pend['jenjang']); ?> - <?php echo htmlspecialchars($pend['jurusan'] ?? 'N/A'); ?></h6>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($pend['institusi']); ?></p>
                                            <small class="text-muted">
                                                <?php 
                                                if ($pend['tahun_mulai'] && $pend['tahun_selesai']) {
                                                    echo htmlspecialchars($pend['tahun_mulai']) . ' - ' . htmlspecialchars($pend['tahun_selesai']);
                                                } elseif ($pend['tahun_selesai']) {
                                                    echo htmlspecialchars($pend['tahun_selesai']);
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">Belum ada data pendidikan.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sertifikasi -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-award me-2"></i>Sertifikasi</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($sertifikasi_list)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($sertifikasi_list as $sert): ?>
                                <li class="mb-3">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <div class="badge bg-success rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-award"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($sert['nama_sertifikasi']); ?></h6>
                                            <?php if ($sert['penerbit']): ?>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($sert['penerbit']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($sert['tahun']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($sert['tahun']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">Belum ada data sertifikasi.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mata Kuliah -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-book me-2"></i>Mata Kuliah</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Semester Ganjil -->
                        <div class="col-md-6 mb-3">
                            <h6 class="mb-3"><strong>Semester Ganjil</strong></h6>
                            <?php if (!empty($matakuliah_ganjil)): ?>
                            <ul class="list-group">
                                <?php foreach ($matakuliah_ganjil as $mk): ?>
                                <li class="list-group-item"><i class="bi bi-check-circle text-success me-2"></i><?php echo htmlspecialchars($mk['nama_matakuliah']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">Belum ada data mata kuliah semester ganjil.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Semester Genap -->
                        <div class="col-md-6 mb-3">
                            <h6 class="mb-3"><strong>Semester Genap</strong></h6>
                            <?php if (!empty($matakuliah_genap)): ?>
                            <ul class="list-group">
                                <?php foreach ($matakuliah_genap as $mk): ?>
                                <li class="list-group-item"><i class="bi bi-check-circle text-success me-2"></i><?php echo htmlspecialchars($mk['nama_matakuliah']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">Belum ada data mata kuliah semester genap.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sorotan Publikasi -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Sorotan Publikasi</h5>
                </div>
                <div class="card-body">
                    
                    <!-- Filter Tabs -->
                    <div class="d-flex justify-content-center mb-4">
                        <div class="btn-group" role="group">
                            <a href="?slug=<?php echo $slug; ?>&sort=cited" class="btn <?php echo ($sort == 'cited') ? 'btn-dark' : 'btn-outline-dark'; ?> btn-sm">Most Cited</a>
                            <a href="?slug=<?php echo $slug; ?>&sort=latest" class="btn <?php echo ($sort == 'latest') ? 'btn-dark' : 'btn-outline-dark'; ?> btn-sm">Latest</a>
                            <a href="?slug=<?php echo $slug; ?>&sort=oldest" class="btn <?php echo ($sort == 'oldest') ? 'btn-dark' : 'btn-outline-dark'; ?> btn-sm">Oldest</a>
                        </div>
                    </div>
                    
                    <?php if ($result_publikasi && pg_num_rows($result_publikasi) > 0): ?>
                    <div class="row g-3">
                        <?php while ($pub = pg_fetch_assoc($result_publikasi)): ?>
                        <div class="col-md-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <?php if ($pub['jenis']): ?>
                                    <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($pub['jenis']); ?></span>
                                    <?php endif; ?>
                                    <h6 class="card-title">
                                        <a href="publikasi-detail.php?slug=<?php echo $pub['slug']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($pub['judul']); ?>
                                        </a>
                                    </h6>
                                    <?php if ($pub['tempat']): ?>
                                    <p class="card-text small text-muted"><?php echo truncateText($pub['tempat'], 50); ?></p>
                                    <?php endif; ?>
                                    <p class="card-text"><small class="text-muted">Tahun: <?php echo $pub['tahun']; ?></small></p>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="publikasi-detail.php?slug=<?php echo $pub['slug']; ?>" class="btn btn-sm btn-outline-dark w-100">Baca Detail</a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Publikasi pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?slug=<?php echo $slug; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?slug=<?php echo $slug; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?slug=<?php echo $slug; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <p class="text-center text-muted">Belum ada publikasi yang tersedia.</p>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <!-- Back Button -->
            <div class="text-center mt-4">
                <a href="profil.php" class="btn btn-primary py-3 px-5">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Profil Laboratorium
                </a>
            </div>
            
        </div>
    </div>
    <!-- Profile Detail End -->

<?php
// Close database connection
if ($conn) {
    pg_close($conn);
}

// Include footer
include __DIR__ . '/../includes/footer.php';
?>