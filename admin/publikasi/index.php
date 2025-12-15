<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$conn        = getDBConnection();
$active_page = 'publikasi';
$page_title  = 'Publikasi & Jurnal';

$is_admin = function_exists('isAdmin') ? isAdmin() : false;

$items_per_page = 20;
$current_page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_jenis  = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_conditions = [];
$params           = [];
$param_count      = 1;

if ($search !== '') {
    $where_conditions[] = "(p.judul ILIKE $" . $param_count .
                          " OR p.tempat ILIKE $" . $param_count .
                          " OR p.doi ILIKE $" . $param_count . 
                          " OR p.penulis ILIKE $" . $param_count . ")";
    $params[] = '%' . $search . '%';
    $param_count++;
}

if ($filter_jenis !== '') {
    $where_conditions[] = "p.jenis = $" . $param_count;
    $params[] = $filter_jenis;
    $param_count++;
}

if ($filter_status !== '') {
    $where_conditions[] = "p.status = $" . $param_count;
    $params[] = $filter_status;
    $param_count++;
}

$where_sql = !empty($where_conditions)
    ? 'WHERE ' . implode(' AND ', $where_conditions)
    : '';

$count_sql      = "SELECT COUNT(*) AS total FROM publikasi p $where_sql";
$count_result = pg_query_params($conn, $count_sql, $params);
$total_items    = $count_result ? (int) pg_fetch_assoc($count_result)['total'] : 0;
$total_pages    = $total_items > 0 ? ceil($total_items / $items_per_page) : 1;

$sql = "
    SELECT 
        p.id_publikasi,
        p.judul,
        p.slug,
        p.jenis,
        p.tempat,
        p.tahun,
        p.doi,
        p.status,
        p.dibuat_pada,
        p.penulis,            
        m.lokasi_file AS cover_file,
        u.nama_lengkap AS pembuat
    FROM publikasi p
    LEFT JOIN media m ON p.id_cover = m.id_media
    LEFT JOIN pengguna u ON p.dibuat_oleh = u.id_pengguna
    {$where_sql}
    ORDER BY p.dibuat_pada DESC
    LIMIT $items_per_page OFFSET $offset
";

$result = pg_query_params($conn, $sql, $params);
$publikasi = $result ? pg_fetch_all($result) : [];

$extra_css = ['/assets/css/anggota.css']; 
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-journal-text me-2"></i>Publikasi</h1>
            <p class="text-muted mb-0">Kelola publikasi penelitian lab</p>
        </div>
        <div>
            <a href="<?php echo getAdminUrl('publikasi/tambah.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>
                Tambah Publikasi
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Cari Publikasi</label>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="Judul, venue, DOI, atau Penulis..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Jenis</label>
                <select name="jenis" class="form-select">
                    <option value="">Semua Jenis</option>
                    <option value="Jurnal"      <?php echo $filter_jenis === 'Jurnal' ? 'selected' : ''; ?>>Jurnal</option>
                    <option value="Prosiding"   <?php echo $filter_jenis === 'Prosiding' ? 'selected' : ''; ?>>Prosiding</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="diajukan"    <?php echo $filter_status === 'diajukan' ? 'selected' : ''; ?>>Diajukan</option>
                    <option value="disetujui"   <?php echo $filter_status === 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                    <option value="ditolak"     <?php echo $filter_status === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?php echo getAdminUrl('publikasi/index.php'); ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>

        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">

        <div class="text-muted mb-3">
            <?php if ($total_items > 0): ?>
                Menampilkan <?php echo min($offset + 1, $total_items); ?> -
                <?php echo min($offset + $items_per_page, $total_items); ?> dari
                <?php echo $total_items; ?> publikasi
            <?php else: ?>
                Tidak ada data publikasi
            <?php endif; ?>
        </div>

        <?php if (!empty($publikasi)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Publikasi</th>
                            <th class="text-center" width="150">Status</th>
                            <th class="text-center" width="160">Jenis & Tahun</th>
                            <th class="text-center" width="160">Penulis</th> <th class="text-center" width="180">Dibuat</th>
                            <th class="text-center" width="180">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publikasi as $row): 
                            $cover_url = $row['cover_file']
                                ? SITE_URL . '/uploads/' . $row['cover_file']
                                : SITE_URL . '/assets/img/default-cover.jpg';
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <img src="<?php echo $cover_url; ?>"
                                             alt="<?php echo htmlspecialchars($row['judul']); ?>"
                                             style="width: 70px; height: 90px; object-fit: cover; border-radius: 4px;"
                                             onerror="this.src='<?php echo SITE_URL; ?>/assets/img/default-cover.jpg'">
                                    </div>
                                    <div>
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($row['judul']); ?>
                                        </div>
                                        <?php if (!empty($row['tempat'])): ?>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($row['tempat']); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($row['doi'])): ?>
                                            <small class="text-muted">
                                                <i class="bi bi-link-45deg"></i>
                                                <?php echo htmlspecialchars($row['doi']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <?php
                                if (isset($row['status']) && $row['status'] !== '') {
                                    echo getStatusBadge($row['status']);
                                }
                                ?>
                            </td>

                            <td class="text-center">
                                <?php if (!empty($row['jenis'])): ?>
                                    <span class="badge bg-info text-dark px-3 mb-1">
                                        <?php echo htmlspecialchars($row['jenis']); ?>
                                    </span>
                                    <br>
                                <?php endif; ?>
                                <?php if (!empty($row['tahun'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['tahun']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if (!empty($row['penulis'])): ?>
                                    <small class="text-wrap d-block" style="max-width: 150px; margin: 0 auto;">
                                        <?php 
                                            // Hanya tampilkan 2 baris
                                            $penulis_trimmed = substr($row['penulis'], 0, 80);
                                            if (strlen($row['penulis']) > 80) {
                                                $penulis_trimmed .= '...';
                                            }
                                            echo nl2br(htmlspecialchars($penulis_trimmed));
                                        ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <small class="d-block">
                                    <?php echo formatDateTime($row['dibuat_pada'], 'd M Y, H:i'); ?>
                                </small>
                                <?php if (!empty($row['pembuat'])): ?>
                                    <small class="text-muted">
                                        oleh <?php echo htmlspecialchars($row['pembuat']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo getAdminUrl('publikasi/edit.php?id=' . $row['id_publikasi']); ?>"
                                       class="btn btn-outline-primary"
                                       title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?php echo getAdminUrl('publikasi/hapus.php?id=' . $row['id_publikasi']); ?>"
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Yakin ingin menghapus publikasi ini?');"
                                       title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">
                                &laquo;
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">
                                &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-journal-x anggota-empty-icon"></i>
                <h5 class="mt-3 text-muted">Tidak ada data publikasi</h5>
                <p class="text-muted mb-4">
                    <?php if ($search !== '' || $filter_jenis !== '' || $filter_status !== ''): ?>
                        Tidak ada publikasi yang sesuai dengan filter
                    <?php else: ?>
                        Belum ada publikasi yang ditambahkan
                    <?php endif; ?>
                </p>
                <?php if ($search === '' && $filter_jenis === '' && $filter_status === ''): ?>
                    <a href="<?php echo getAdminUrl('publikasi/tambah.php'); ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        Tambah Publikasi Pertama
                    </a>
                <?php else: ?>
                    <a href="<?php echo getAdminUrl('publikasi/index.php'); ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>
                        Reset Filter
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>