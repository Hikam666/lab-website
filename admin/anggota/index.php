<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$active_page = 'anggota';
$page_title = 'Anggota Peneliti';

// Get database connection
$conn = getDBConnection();

// Pagination settings
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_peran = isset($_GET['peran']) ? trim($_GET['peran']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_count = 1;

if (!empty($search)) {
    $where_conditions[] = "(a.nama ILIKE $" . $param_count . " OR a.email ILIKE $" . $param_count . ")";
    $params[] = "%$search%";
    $param_count++;
}

if (!empty($filter_peran)) {
    $where_conditions[] = "a.peran_lab ILIKE $" . $param_count;
    $params[] = "%$filter_peran%";
    $param_count++;
}

if ($filter_status !== '') {
    $where_conditions[] = "a.aktif = $" . $param_count;
    $params[] = $filter_status === '1' ? 't' : 'f';
    $param_count++;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM anggota_lab a $where_sql";
$count_result = pg_query_params($conn, $count_sql, $params);
$total_items = $count_result ? pg_fetch_assoc($count_result)['total'] : 0;
$total_pages = ceil($total_items / $items_per_page);

// Get anggota data
$sql = "SELECT 
            a.id_anggota,
            a.nama,
            a.slug,
            a.email,
            a.peran_lab,
            a.aktif,
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
$peran_sql = "SELECT DISTINCT peran_lab FROM anggota_lab WHERE peran_lab IS NOT NULL AND peran_lab != '' ORDER BY peran_lab";
$peran_result = pg_query($conn, $peran_sql);
$peran_list = [];
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
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Nama atau email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Peran Lab</label>
                <select name="peran" class="form-select">
                    <option value="">Semua Peran</option>
                    <?php foreach ($peran_list as $peran): ?>
                    <option value="<?php echo htmlspecialchars($peran); ?>" 
                            <?php echo $filter_peran === $peran ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($peran); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Status</label>
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
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted">
                Menampilkan <?php echo min($offset + 1, $total_items); ?> - <?php echo min($offset + $items_per_page, $total_items); ?> 
                dari <?php echo $total_items; ?> anggota
            </div>
        </div>
        
        <?php if ($result && pg_num_rows($result) > 0): ?>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th width="80">Foto</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Peran Lab</th>
                        <th width="80" class="text-center">Urutan</th>
                        <th width="100" class="text-center">Status</th>
                        <th width="150" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = pg_fetch_assoc($result)): 
                        $foto_url = $row['foto'] ? SITE_URL . '/uploads/' . $row['foto'] : SITE_URL . '/assets/img/default-avatar.jpg';
                    ?>
                    <tr>
                        <td>
                            <img src="<?php echo $foto_url; ?>" 
                                 alt="<?php echo htmlspecialchars($row['nama']); ?>"
                                 class="rounded anggota-table-foto">
                        </td>
                        
                        <td>
                            <strong><?php echo htmlspecialchars($row['nama']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-link-45deg"></i>
                                <?php echo htmlspecialchars($row['slug']); ?>
                            </small>
                        </td>
                        
                        <td>
                            <?php if ($row['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>">
                                <i class="bi bi-envelope me-1"></i>
                                <?php echo htmlspecialchars($row['email']); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if ($row['peran_lab']): ?>
                            <span class="badge bg-info">
                                <?php echo htmlspecialchars($row['peran_lab']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-center">
                            <span class="badge bg-secondary"><?php echo $row['urutan']; ?></span>
                        </td>
                        
                        <td class="text-center">
                            <?php if ($row['aktif']): ?>
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle"></i> Aktif
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary">
                                <i class="bi bi-x-circle"></i> Non-aktif
                            </span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?php echo getAdminUrl('anggota/edit.php?id=' . $row['id_anggota']); ?>" 
                                   class="btn btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?php echo getAdminUrl('anggota/hapus.php?id=' . $row['id_anggota']); ?>" 
                                   class="btn btn-outline-danger"
                                   title="Hapus"
                                   onclick="return confirm('Yakin ingin menghapus anggota ini?')">
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
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                
                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&peran=<?php echo urlencode($filter_peran); ?>&status=<?php echo urlencode($filter_status); ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&peran=<?php echo urlencode($filter_peran); ?>&status=<?php echo urlencode($filter_status); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&peran=<?php echo urlencode($filter_peran); ?>&status=<?php echo urlencode($filter_status); ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        
        <div class="text-center py-5">
            <i class="bi bi-people anggota-empty-icon"></i>
            <h5 class="mt-3 text-muted">Tidak ada data anggota</h5>
            <p class="text-muted mb-4">
                <?php if (!empty($search) || !empty($filter_peran) || $filter_status !== ''): ?>
                    Tidak ada anggota yang sesuai dengan filter
                <?php else: ?>
                    Belum ada anggota yang ditambahkan
                <?php endif; ?>
            </p>
            <?php if (empty($search) && empty($filter_peran) && $filter_status === ''): ?>
            <a href="<?php echo getAdminUrl('anggota/tambah.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>
                Tambah Anggota Pertama
            </a>
            <?php else: ?>
            <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
                <i class="bi bi-x-circle me-2"></i>
                Reset Filter
            </a>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
        
    </div>
</div>

<?php

// Include footer
include __DIR__ . '/../includes/footer.php';
?>