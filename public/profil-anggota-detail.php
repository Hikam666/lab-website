<?php
require_once __DIR__ . '/../includes/config.php';

$active_page = 'profil';
$extra_css = ['profil.css', 'profil-detail.css'];

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
if (empty($slug)) {
    header('Location: profil.php');
    exit;
}

$conn = getDBConnection();

$sql = "SELECT a.*, m.lokasi_file as foto FROM anggota_lab a
        LEFT JOIN media m ON a.id_foto = m.id_media
        WHERE a.slug = $1 AND a.aktif = TRUE";
$result = pg_query_params($conn, $sql, array($slug));

if (!$result || pg_num_rows($result) == 0) {
    header('Location: profil.php');
    exit;
}

$anggota = pg_fetch_assoc($result);

// Pendidikan
$sql_pend = "SELECT * FROM anggota_pendidikan WHERE id_anggota = $1 ORDER BY urutan ASC";
$result_pend = pg_query_params($conn, $sql_pend, array($anggota['id_anggota']));
$pendidikan_list = [];
while ($row = pg_fetch_assoc($result_pend)) $pendidikan_list[] = $row;

// Sertifikasi
$sql_sert = "SELECT * FROM anggota_sertifikasi WHERE id_anggota = $1 ORDER BY urutan ASC";
$result_sert = pg_query_params($conn, $sql_sert, array($anggota['id_anggota']));
$sertifikasi_list = [];
while ($row = pg_fetch_assoc($result_sert)) $sertifikasi_list[] = $row;

// Mata Kuliah
$sql_mk = "SELECT * FROM anggota_matakuliah WHERE id_anggota = $1 ORDER BY semester, urutan ASC";
$result_mk = pg_query_params($conn, $sql_mk, array($anggota['id_anggota']));
$matakuliah_ganjil = [];
$matakuliah_genap = [];
while ($row = pg_fetch_assoc($result_mk)) {
    if ($row['semester'] === 'ganjil') $matakuliah_ganjil[] = $row;
    else $matakuliah_genap[] = $row;
}

// Publikasi
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 3;
$offset = ($page - 1) * $per_page;

$sql_pub = "SELECT p.* FROM publikasi_penulis pp
            JOIN publikasi p ON pp.id_publikasi = p.id_publikasi
            WHERE pp.id_anggota = $1 AND p.status = 'disetujui'";
$sql_pub .= ($sort == 'oldest') ? " ORDER BY p.tahun ASC" : " ORDER BY p.tahun DESC";
$sql_pub .= " LIMIT $per_page OFFSET $offset";
$result_pub = pg_query_params($conn, $sql_pub, array($anggota['id_anggota']));

$sql_count = "SELECT COUNT(*) as total FROM publikasi_penulis pp 
              JOIN publikasi p ON pp.id_publikasi = p.id_publikasi 
              WHERE pp.id_anggota = $1 AND p.status = 'disetujui'";
$result_count = pg_query_params($conn, $sql_count, array($anggota['id_anggota']));
$total_publikasi = pg_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_publikasi / $per_page);

$page_title = $anggota['nama'] . ' - Profil';
include __DIR__ . '/../includes/header.php';
$image_src = $anggota['foto'] ? SITE_URL . '/uploads/' . $anggota['foto'] : SITE_URL . '/assets/img/default-avatar.jpg';
?>

<div class="container-fluid page-header-banner py-5 mb-5">
    <div class="container text-center py-5">
        <h1 class="display-3 text-white mb-4"><?php echo htmlspecialchars($anggota['nama']); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="profil.php">Profil</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($anggota['nama']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Profile Header -->
            <div class="card profile-header-card mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($anggota['nama']); ?>" class="rounded-circle img-fluid border border-3 border-primary" style="max-width: 180px; height: 180px; object-fit: cover;">
                        </div>
                        <div class="col-md-9">
                            <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($anggota['nama']); ?></h2>
                            
                            <table class="table table-borderless table-sm mb-3">
                                <?php if ($anggota['jabatan']): ?>
                                <tr>
                                    <td width="30"><i class="bi bi-briefcase text-primary"></i></td>
                                    <td><?php echo htmlspecialchars($anggota['jabatan']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($anggota['program_studi']): ?>
                                <tr>
                                    <td><i class="bi bi-building text-primary"></i></td>
                                    <td><?php echo htmlspecialchars($anggota['program_studi']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($anggota['email']): ?>
                                <tr>
                                    <td><i class="bi bi-envelope text-primary"></i></td>
                                    <td><?php echo htmlspecialchars($anggota['email']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($anggota['nip']): ?>
                                <tr>
                                    <td><i class="bi bi-card-text text-primary"></i></td>
                                    <td>NIP: <?php echo htmlspecialchars($anggota['nip']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($anggota['nidn']): ?>
                                <tr>
                                    <td><i class="bi bi-card-list text-primary"></i></td>
                                    <td>NIDN: <?php echo htmlspecialchars($anggota['nidn']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($anggota['alamat_kantor']): ?>
                                <tr>
                                    <td><i class="bi bi-geo-alt text-primary"></i></td>
                                    <td><?php echo htmlspecialchars($anggota['alamat_kantor']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                            
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($anggota['linkedin']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['linkedin']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-linkedin"></i> LinkedIn
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['google_scholar']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['google_scholar']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-google"></i> Scholar
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['sinta']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['sinta']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-text"></i> Sinta
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['website']): ?>
                                <a href="<?php echo htmlspecialchars($anggota['website']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-globe"></i> Website
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($anggota['cv_file']): ?>
                                <a href="<?php echo SITE_URL . '/uploads/' . $anggota['cv_file']; ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-download"></i> Download CV
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($anggota['keahlian']): ?>
                            <div class="mt-3 pt-3 border-top">
                                <h6 class="text-muted small mb-2">KEAHLIAN</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach (array_map('trim', explode(',', $anggota['keahlian'])) as $skill): ?>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pendidikan & Sertifikasi -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0"><i class="bi bi-mortarboard text-primary me-2"></i>Pendidikan</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pendidikan_list)): ?>
                            <?php foreach ($pendidikan_list as $idx => $pend): ?>
                            <div class="mb-3 <?php echo $idx < count($pendidikan_list) - 1 ? 'pb-3 border-bottom' : ''; ?>">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($pend['jenjang']); ?></h6>
                                <div class="text-muted small"><?php echo htmlspecialchars($pend['institusi']); ?></div>
                                <?php if ($pend['jurusan']): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($pend['jurusan']); ?></div>
                                <?php endif; ?>
                                <?php if ($pend['tahun_mulai'] || $pend['tahun_selesai']): ?>
                                <div class="text-primary small fw-semibold">
                                    <?php echo ($pend['tahun_mulai'] && $pend['tahun_selesai']) ? 
                                        htmlspecialchars($pend['tahun_mulai']) . ' - ' . htmlspecialchars($pend['tahun_selesai']) : 
                                        htmlspecialchars($pend['tahun_selesai']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted text-center py-3 mb-0">Belum ada data</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0"><i class="bi bi-award text-primary me-2"></i>Sertifikasi</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($sertifikasi_list)): ?>
                            <?php foreach ($sertifikasi_list as $idx => $sert): ?>
                            <div class="mb-3 <?php echo $idx < count($sertifikasi_list) - 1 ? 'pb-3 border-bottom' : ''; ?>">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($sert['nama_sertifikasi']); ?></h6>
                                <?php if ($sert['penerbit']): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($sert['penerbit']); ?></div>
                                <?php endif; ?>
                                <?php if ($sert['tahun']): ?>
                                <div class="text-primary small fw-semibold"><?php echo htmlspecialchars($sert['tahun']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted text-center py-3 mb-0">Belum ada data</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mata Kuliah -->
            <?php if (!empty($matakuliah_ganjil) || !empty($matakuliah_genap)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-book text-primary me-2"></i>Mata Kuliah</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Semester Ganjil</h6>
                            <?php if (!empty($matakuliah_ganjil)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($matakuliah_ganjil as $mk): ?>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i><?php echo htmlspecialchars($mk['nama_matakuliah']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">-</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Semester Genap</h6>
                            <?php if (!empty($matakuliah_genap)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($matakuliah_genap as $mk): ?>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i><?php echo htmlspecialchars($mk['nama_matakuliah']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">-</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Publikasi -->
            <div class="card mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-file-text text-primary me-2"></i>Publikasi</h5>
                </div>
                <div class="card-body">
                    <div class="btn-group mb-4" role="group">
                        <a href="?slug=<?php echo $slug; ?>&sort=latest" class="btn btn-sm <?php echo ($sort == 'latest') ? 'btn-primary' : 'btn-outline-primary'; ?>">Terbaru</a>
                        <a href="?slug=<?php echo $slug; ?>&sort=oldest" class="btn btn-sm <?php echo ($sort == 'oldest') ? 'btn-primary' : 'btn-outline-primary'; ?>">Terlama</a>
                        <a href="?slug=<?php echo $slug; ?>&sort=cited" class="btn btn-sm <?php echo ($sort == 'cited') ? 'btn-primary' : 'btn-outline-primary'; ?>">Most Cited</a>
                    </div>
                    
                    <?php if ($result_pub && pg_num_rows($result_pub) > 0): ?>
                    <div class="row g-3">
                        <?php while ($pub = pg_fetch_assoc($result_pub)): ?>
                        <div class="col-md-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <?php if ($pub['jenis']): ?>
                                    <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($pub['jenis']); ?></span>
                                    <?php endif; ?>
                                    <h6><a href="publikasi-detail.php?slug=<?php echo $pub['slug']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($pub['judul']); ?></a></h6>
                                    <?php if ($pub['tempat']): ?>
                                    <p class="small text-muted mb-1"><?php echo htmlspecialchars($pub['tempat']); ?></p>
                                    <?php endif; ?>
                                    <p class="small text-primary fw-semibold mb-0"><?php echo $pub['tahun']; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?slug=<?php echo $slug; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i></a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>"><a class="page-link" href="?slug=<?php echo $slug; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?slug=<?php echo $slug; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>"><i class="bi bi-chevron-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted text-center py-4 mb-0">Belum ada publikasi</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center">
                <a href="profil.php" class="btn btn-primary py-3 px-5"><i class="bi bi-arrow-left me-2"></i>Kembali ke Profil</a>
            </div>
            
        </div>
    </div>
</div>

<?php
if ($conn) pg_close($conn);
include __DIR__ . '/../includes/footer.php';
?>