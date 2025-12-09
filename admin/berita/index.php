<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$active_page = 'berita';
$page_title  = 'Berita & Agenda';
$is_admin    = function_exists('isAdmin') ? isAdmin() : false;

$conn = getDBConnection();

// Pagination
$items_per_page = 20;
$current_page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Search & Filter
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// WHERE builder
$where_conditions = [];
$params = [];
$param_count = 1;

if (!empty($search)) {
    $where_conditions[] = "(b.judul ILIKE $" . $param_count . ")";
    $params[] = "%$search%";
    $param_count++;
}

if (!empty($filter_status)) {
    $where_conditions[] = "b.status = $" . $param_count;
    $params[] = $filter_status;
    $param_count++;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// === COUNT total data ===
$count_sql = "SELECT COUNT(*) as total FROM berita b $where_sql";
$count_res = pg_query_params($conn, $count_sql, $params);
$total_items = $count_res ? (int) pg_fetch_assoc($count_res)['total'] : 0;
$total_pages = $total_items > 0 ? ceil($total_items / $items_per_page) : 1;

// === Ambil data berita ===
$sql = "SELECT 
            b.id_berita,
            b.judul,
            b.slug,
            b.dibuat_pada,
            b.status,
            b.jenis,
            m.lokasi_file AS foto,
            u.nama_lengkap AS creator
        FROM berita b
        LEFT JOIN media m ON b.id_cover = m.id_media
        LEFT JOIN pengguna u ON b.dibuat_oleh = u.id_pengguna
        $where_sql
        ORDER BY b.dibuat_pada DESC
        LIMIT $items_per_page OFFSET $offset";

$result = pg_query_params($conn, $sql, $params);

$extra_css = ['/assets/css/anggota.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-newspaper me-2"></i>Berita & Agenda</h1>
            <p class="text-muted mb-0">Kelola daftar berita dan pengumuman</p>
        </div>
        <div>
            <a href="<?php echo getAdminUrl('berita/tambah.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i> Tambah Berita
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Cari Judul</label>
                <input type="text" name="search" class="form-control" placeholder="Judul..." 
                       value="<?= htmlspecialchars($search); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="diajukan"   <?= $filter_status==='diajukan'?'selected':'' ?>>Diajukan</option>
                    <option value="disetujui"  <?= $filter_status==='disetujui'?'selected':'' ?>>Disetujui</option>
                    <option value="ditolak"    <?= $filter_status==='ditolak'?'selected':'' ?>>Ditolak</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?php echo getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary">
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
                Menampilkan <?= min($offset + 1, $total_items); ?> - <?= min($offset + $items_per_page, $total_items); ?> dari <?= $total_items; ?> berita
            <?php else: ?>
                Tidak ada data
            <?php endif; ?>
        </div>

        <?php if ($result && pg_num_rows($result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>BERITA</th>
                            <th class="text-center" width="160">STATUS</th>
                            <th class="text-center" width="130">JENIS</th>
                            <th class="text-center" width="200">CREATOR</th>
                            <th class="text-center" width="150">DIBUAT</th>
                            <th class="text-center" width="180">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php while ($row = pg_fetch_assoc($result)): 
                            $foto = $row['foto'] 
                                ? SITE_URL.'/uploads/'.$row['foto']
                                : SITE_URL.'/assets/img/default-avatar.jpg';
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?= $foto; ?>" 
                                         style="width: 90px; height: 90px; object-fit: cover; border-radius: 8px;"
                                         class="me-3">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['judul']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['slug']); ?></small>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <?= getStatusBadge($row['status']); ?>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-info text-dark px-3"><?= $row['jenis']; ?></span>
                            </td>

                            <td class="text-center">
                                <?= $row['creator'] ?: '<span class="text-muted">-</span>' ?>
                            </td>

                            <td class="text-center">
                                <small><?= formatDateTime($row['dibuat_pada'], 'd M Y, H:i'); ?></small>
                            </td>

                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= getAdminUrl('berita/edit.php?id=' . $row['id_berita']); ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= getAdminUrl('berita/hapus.php?id=' . $row['id_berita']); ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Yakin ingin menghapus berita ini?');">
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
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $current_page - 1; ?>">&laquo;</a>
                        </li>

                        <?php for ($i=1; $i<=$total_pages; $i++): ?>
                            <li class="page-item <?= $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $current_page + 1; ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-newspaper anggota-empty-icon"></i>
                <h5 class="mt-3 text-muted">Tidak ada berita</h5>

                <?php if (empty($search) && empty($filter_status)): ?>
                <a href="<?= getAdminUrl('berita/tambah.php'); ?>" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-circle me-2"></i> Tambah Berita Pertama
                </a>
                <?php else: ?>
                <a href="<?= getAdminUrl('berita/index.php'); ?>" class="btn btn-secondary mt-3">
                    <i class="bi bi-x-circle me-2"></i> Reset Filter
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
