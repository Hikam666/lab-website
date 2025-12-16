<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$active_page = 'anggota';
$page_title  = 'Anggota Peneliti';

$is_admin = function_exists('isAdmin') ? isAdmin() : false;
$conn     = getDBConnection();

/* ===============================
 * Pagination
 * =============================== */
$items_per_page = 20;
$current_page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset         = ($current_page - 1) * $items_per_page;

/* ===============================
 * Filter & Search
 * =============================== */
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_peran  = isset($_GET['peran']) ? trim($_GET['peran']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where   = [];
$params  = [];
$idx     = 1;

if ($search !== '') {
    $where[]  = "(a.nama ilike $".$idx." or a.email ilike $".$idx.")";
    $params[] = "%{$search}%";
    $idx++;
}

if ($filter_peran !== '') {
    $where[]  = "a.peran_lab ilike $".$idx;
    $params[] = "%{$filter_peran}%";
    $idx++;
}

if ($filter_status !== '') {
    $where[]  = "a.aktif = $".$idx;
    $params[] = $filter_status === '1' ? 't' : 'f';
    $idx++;
}

$where_sql = $where ? 'where ' . implode(' and ', $where) : '';

/* ===============================
 * Count Data
 * =============================== */
$count_sql   = "select count(*) as total from anggota_lab a {$where_sql}";
$count_query = pg_query_params($conn, $count_sql, $params);
$total_items = $count_query ? (int) pg_fetch_assoc($count_query)['total'] : 0;
$total_pages = max(1, ceil($total_items / $items_per_page));

/* ===============================
 * Main Query
 * =============================== */
$sql = "
    select
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
    from anggota_lab a
    left join media m on a.id_foto = m.id_media
    {$where_sql}
    order by a.urutan asc, a.nama asc
    limit {$items_per_page} offset {$offset}
";
$result = pg_query_params($conn, $sql, $params);

/* ===============================
 * List Peran
 * =============================== */
$peran_list = [];
$peran_q    = pg_query(
    $conn,
    "select distinct peran_lab from anggota_lab 
     where peran_lab is not null and peran_lab <> ''
     order by peran_lab"
);

if ($peran_q) {
    while ($r = pg_fetch_assoc($peran_q)) {
        $peran_list[] = $r['peran_lab'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-people me-2"></i>Anggota Peneliti</h1>
            <p class="text-muted mb-0">Kelola data anggota dan peneliti laboratorium</p>
        </div>
        <a href="<?= getAdminUrl('anggota/tambah.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Tambah Anggota
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cari Anggota</label>
                <input type="text" name="search" class="form-control"
                       placeholder="Nama atau email..."
                       value="<?= htmlspecialchars($search); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Peran Lab</label>
                <select name="peran" class="form-select">
                    <option value="">Semua Peran</option>
                    <?php foreach ($peran_list as $p): ?>
                        <option value="<?= htmlspecialchars($p); ?>" <?= $filter_peran === $p ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Status Aktif</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="1" <?= $filter_status === '1' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?= $filter_status === '0' ? 'selected' : ''; ?>>Non-aktif</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary flex-fill">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">

        <div class="text-muted mb-3">
            <?php if ($total_items): ?>
                Menampilkan <?= min($offset + 1, $total_items); ?> –
                <?= min($offset + $items_per_page, $total_items); ?>
                dari <?= $total_items; ?> anggota
            <?php else: ?>
                Tidak ada data anggota
            <?php endif; ?>
        </div>

        <?php if ($result && pg_num_rows($result)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ANGGOTA</th>
                            <th class="text-center" width="120">PERSETUJUAN</th>
                            <th class="text-center" width="120">KEAKTIFAN</th>
                            <th class="text-center" width="150">PERAN LAB</th>
                            <th class="text-center" width="140">DIBUAT</th>
                            <th class="text-center" width="150">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = pg_fetch_assoc($result)): 
                        $foto = $row['foto']
                            ? SITE_URL . '/uploads/' . $row['foto']
                            : SITE_URL . '/assets/img/default-avatar.jpg';

                        $aktif = ($row['aktif'] === 't' || $row['aktif'] == 1);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?= $foto; ?>" class="me-3"
                                         style="width:90px;height:90px;object-fit:cover;border-radius:8px"
                                         onerror="this.src='<?= SITE_URL ?>/assets/img/default-avatar.jpg'">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['nama']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['email']); ?></small>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <?= $row['status'] ? getStatusBadge($row['status']) : '<span class="text-muted">-</span>'; ?>
                            </td>

                            <td class="text-center"><?= getActiveBadge($aktif); ?></td>

                            <td class="text-center">
                                <?= $row['peran_lab']
                                    ? '<span class="badge bg-info text-dark px-3">'.htmlspecialchars($row['peran_lab']).'</span>'
                                    : '<span class="text-muted">-</span>'; ?>
                            </td>

                            <td class="text-center">
                                <small><?= formatDateTime($row['dibuat_pada'], 'd M Y, H:i'); ?></small>
                            </td>

                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= getAdminUrl('anggota/edit.php?id='.$row['id_anggota']); ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= SITE_URL.'/public/profil-anggota-detail.php?slug='.urlencode($row['slug']); ?>"
                                       target="_blank" class="btn btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <a href="<?= getAdminUrl('anggota/hapus.php?id='.$row['id_anggota']); ?>"
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

        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people anggota-empty-icon"></i>
                <h5 class="mt-3 text-muted">Tidak ada data anggota</h5>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
