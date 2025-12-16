<?php
require_once __DIR__ . '/../includes/config.php';

$active_page = 'publikasi';
$page_title = 'Riset & Publikasi';
$page_keywords = 'publikasi, jurnal, riset, penelitian, laboratorium';
$page_description = 'Publikasi ilmiah dan riset dari Laboratorium Teknologi Data';
$extra_css = ['profil.css'];

$conn = getDBConnection();

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9; 
$offset = ($page - 1) * $per_page;

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
            COUNT(DISTINCT pp.id_anggota) as jumlah_penulis
        FROM publikasi p
        LEFT JOIN media m ON p.id_cover = m.id_media
        LEFT JOIN publikasi_penulis pp ON p.id_publikasi = pp.id_publikasi
        WHERE p.status = 'disetujui'";

// Add filters
$params = array();
$param_count = 1;

if ($tahun > 0) {
    $sql .= " AND p.tahun = $" . $param_count;
    $params[] = $tahun;
    $param_count++;
}

if (!empty($search)) {
    $sql .= " AND (p.judul ILIKE $" . $param_count . " OR p.abstrak ILIKE $" . $param_count . ")";
    $params[] = '%' . $search . '%';
    $param_count++;
}

$sql .= " GROUP BY p.id_publikasi, m.lokasi_file";

// Add sorting
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY p.tahun ASC, p.dibuat_pada ASC";
        break;
    case 'latest':
    default:
        $sql .= " ORDER BY p.tahun DESC, p.dibuat_pada DESC";
        break;
}

$sql .= " LIMIT $" . $param_count . " OFFSET $" . ($param_count + 1);
$params[] = $per_page;
$params[] = $offset;

$result = pg_query_params($conn, $sql, $params);
$sql_count = "SELECT COUNT(*) as total FROM publikasi WHERE status = 'disetujui'";
if ($tahun > 0) {
    $sql_count .= " AND tahun = " . $tahun;
}
if (!empty($search)) {
    $sql_count .= " AND (judul ILIKE '%" . escapeString($conn, $search) . "%' OR abstrak ILIKE '%" . escapeString($conn, $search) . "%')";
}
$result_count = pg_query($conn, $sql_count);
$total_rows = pg_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $per_page);

$sql_years = "SELECT DISTINCT tahun FROM publikasi WHERE status = 'disetujui' AND tahun IS NOT NULL ORDER BY tahun DESC";
$result_years = pg_query($conn, $sql_years);

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Publikasi</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Publikasi</li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="container my-5">
        
        <h3 class="mb-3 fw-bold text-dark">Daftar Publikasi</h3>

        <?php if ($total_rows > 0): ?>
        <p class="text-muted mb-4">
            Menampilkan <?php echo min($per_page, $total_rows - $offset); ?> dari <?php echo $total_rows; ?> publikasi
            <?php if ($total_pages > 1): ?>
                (Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <form method="GET" action="" class="mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div class="btn-group" role="group">
                    
                    <a href="?sort=latest<?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                        class="btn <?php echo ($sort == 'latest' || $sort == 'cited') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Terbaru
                    </a>
                    <a href="?sort=oldest<?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                        class="btn <?php echo ($sort == 'oldest') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Terlama
                    </a>
                </div>
                
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select name="tahun" class="form-select" style="width: auto; min-width: 120px;" onchange="this.form.submit()">
                        <option value="">Semua Tahun</option>
                        <?php 
                        if ($result_years) {
                            pg_result_seek($result_years, 0); 
                            while ($year = pg_fetch_assoc($result_years)) {
                                $selected = ($tahun == $year['tahun']) ? 'selected' : '';
                                echo '<option value="' . $year['tahun'] . '" ' . $selected . '>' . $year['tahun'] . '</option>';
                            }
                        }
                        ?>
                    </select>
                    
                    <div class="input-group" style="width: auto; min-width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari publikasi..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="sort" value="<?php echo $sort; ?>">
        </form>

        <div class="row gy-4">
            <?php 
            if ($result && pg_num_rows($result) > 0):
                $is_first = true;
                while ($row = pg_fetch_assoc($result)):
                    $highlight_class = '';
                    $highlight_label = '';
                    $is_first = false;
            ?>
            
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <?php if ($highlight_label): ?>
                    <div class="card-header <?php echo $highlight_class; ?> fw-semibold">
                        <?php echo $highlight_label; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h6 class="fw-bold">
                            <a href="publikasi-detail.php?slug=<?php echo $row['slug']; ?>" class="text-decoration-none text-dark">
                                <?php echo htmlspecialchars($row['judul']); ?>
                            </a>
                        </h6>
                        
                        <p class="mb-2 text-secondary small">
                            <?php if ($row['jenis']): ?>
                                <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($row['jenis']); ?></span>
                            <?php endif; ?>
                            <?php echo $row['tahun']; ?>
                        </p>
                        
                        <?php if ($row['tempat']): ?>
                        <p class="mb-2 small text-muted">
                            <i class="fas fa-book me-1"></i> <?php echo truncateText($row['tempat'], 50); ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($row['jumlah_penulis'] > 0): ?>
                        <p class="mb-0 small text-muted">
                            <i class="fas fa-users me-1"></i> <?php echo $row['jumlah_penulis']; ?> Penulis
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer bg-transparent">
                        <a href="publikasi-detail.php?slug=<?php echo $row['slug']; ?>" class="btn btn-outline-primary w-100">
                            Baca Detail
                        </a>
                    </div>
                </div>
            </div>
            
            <?php 
                endwhile;
            else:
            ?>
            
            <div class="col-12">
                <div class="alert alert-info text-center" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    Belum ada publikasi yang tersedia saat ini.
                </div>
            </div>
            
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="row mt-5">
            <div class="col-12">
                <nav aria-label="Publication pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?><?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        // Smart pagination
                        $start_page = max(1, $page - 3);
                        $end_page = min($total_pages, $page + 3);
                        
                        // First page
                        if ($start_page > 1):
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&sort=<?php echo $sort; ?><?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&sort=<?php echo $sort; ?><?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?><?php echo $tahun ? '&tahun='.$tahun : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>

    </div>

<?php
// Close database connection
if ($conn) {
    pg_close($conn);
}

// Include footer
include __DIR__ . '/../includes/footer.php';
?>