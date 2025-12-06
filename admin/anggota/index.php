<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$active_page = 'anggota';
$page_title  = 'Anggota Peneliti';

// Cek role
$is_admin = function_exists('isAdmin') ? isAdmin() : false;

// Get database connection
$conn = getDBConnection();

// Pagination settings
$items_per_page = 20;
$current_page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Search & Filter
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_peran  = isset($_GET['peran']) ? trim($_GET['peran']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = [];
$params           = [];
$param_count      = 1;

if (!empty($search)) {
    $where_conditions[] = "(a.nama ILIKE $" . $param_count . " OR a.email ILIKE $" . $param_count . ")";
    $params[]           = "%$search%";
    $param_count++;
}

if (!empty($filter_peran)) {
    $where_conditions[] = "a.peran_lab ILIKE $" . $param_count;
    $params[]           = "%$filter_peran%";
    $param_count++;
}

if ($filter_status !== '') {
    $where_conditions[] = "a.aktif = $" . $param_count;
    $params[]           = $filter_status === '1' ? 't' : 'f';
    $param_count++;
}

$where_sql = !empty($where_conditions)
    ? 'WHERE ' . implode(' AND ', $where_conditions)
    : '';

// Get total count
$count_sql    = "SELECT COUNT(*) as total FROM anggota_lab a $where_sql";
$count_result = pg_query_params($conn, $count_sql, $params);
$total_items  = $count_result ? (int) pg_fetch_assoc($count_result)['total'] : 0;
$total_pages  = $total_items > 0 ? ceil($total_items / $items_per_page) : 1;

// Get anggota data
$sql = "SELECT 
            a.id_anggota,
            a.nama,
            a.slug,
            a.email,
            a.peran_lab,
            a.aktif,
            a.status,
            a.urutan,
            a.dibuat_pada,
            m.lokasi_file as foto,
            m.keterangan_alt as foto_alt
        FROM anggota_lab a
        LEFT JOIN media m ON a.id_foto = m.id_media
        $where_sql
        ORDER BY a.urutan ASC, a.nama ASC
        LIMIT $items_per_page OFFSET $offset";

$result = pg_query_params($conn, $sql, $params);

// Get unique peran for filter dropdown
$peran_sql    = "SELECT DISTINCT peran_lab FROM anggota_lab WHERE peran_lab IS NOT NULL AND peran_lab <> '' ORDER BY peran_lab";
$peran_result = pg_query($conn, $peran_sql);
$peran_list   = [];
if ($peran_result) {
    while ($row = pg_fetch_assoc($peran_result)) {
        $peran_list[] = $row['peran_lab'];
    }
}

$extra_css = ['/assets/css/anggota.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-people me-2"></i>Anggota Peneliti</h1>
            <p class="text-muted mb-0">Kelola data anggota dan peneliti laboratorium</p>
        </div>
        <div>
            <a href="<?php echo getAdminUrl('anggota/tambah.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>
                Tambah Anggota
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cari Anggota</label>
                <input type="text" name="search" class="form-control" placeholder="Nama atau email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Peran Lab</label>
                <select name="peran" class="form-select">
                    <option value="">Semua Peran</option>
                    <?php foreach ($peran_list as $peran): ?>
                        <option value="<?php echo htmlspecialchars($peran); ?>" <?php echo $filter_peran === $peran ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($peran); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Status Aktif</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Non-aktif</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
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
                <?php echo $total_items; ?> anggota
            <?php else: ?>
                Tidak ada data anggota
            <?php endif; ?>
        </div>

        <?php if ($result && pg_num_rows($result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ANGGOTA</th>
                            <th class="text-center" width="150">STATUS</th>
                            <th class="text-center" width="200">PERAN LAB</th>
                            <th class="text-center" width="160">DIBUAT</th>
                            <th class="text-center" width="180">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = pg_fetch_assoc($result)): 
                            $foto_url = $row['foto']
                                ? SITE_URL . '/uploads/' . $row['foto']
                                : SITE_URL . '/assets/img/default-avatar.jpg';

                            $is_active = ($row['aktif'] === 't' || $row['aktif'] === true || $row['aktif'] == 1);
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <img src="<?php echo $foto_url; ?>"
                                             alt="<?php echo htmlspecialchars($row['nama']); ?>"
                                             style="width: 90px; height: 90px; object-fit: cover; border-radius: 8px;"
                                             onerror="this.src='<?php echo SITE_URL; ?>/assets/img/default-avatar.jpg'">
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($row['nama']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <?php
                                    if (isset($row['status']) && $row['status'] !== '') {
                                        echo getStatusBadge($row['status']);
                                    }
                                    echo getActiveBadge($is_active);
                                    ?>
                                </div>
                            </td>

                            <td class="text-center">
                                <?php if (!empty($row['peran_lab'])): ?>
                                    <span class="badge bg-info text-dark px-3"><?php echo htmlspecialchars($row['peran_lab']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <small><?php echo formatDateTime($row['dibuat_pada'], 'd M Y, H:i'); ?></small>
                            </td>

                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo getAdminUrl('anggota/edit.php?id=' . $row['id_anggota']); ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?php echo SITE_URL . '/public/profil-anggota-detail.php?slug=' . urlencode($row['slug']); ?>" class="btn btn-outline-secondary" target="_blank">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <a href="<?php echo getAdminUrl('anggota/hapus.php?id=' . $row['id_anggota']); ?>"
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Yakin ingin menghapus anggota ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">&laquo;</a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people anggota-empty-icon"></i>
                <h5 class="mt-3 text-muted">Tidak ada data anggota</h5>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
