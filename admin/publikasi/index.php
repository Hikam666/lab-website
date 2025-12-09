<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$conn        = getDBConnection();
$active_page = 'publikasi';
$page_title  = 'Publikasi & Jurnal';
$extra_css   = ['publikasi.css'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';


// =====================
// QUERY
// =====================
$where_sql = '';
$params    = [];

if ($search !== '') {
    $where_sql = "WHERE (p.judul ILIKE $1 
                      OR p.jenis ILIKE $1 
                      OR p.doi ILIKE $1)";
    $params[]  = '%' . $search . '%';
}

$sql = "
    SELECT 
        p.id_publikasi,
        p.judul,
        p.slug,
        p.jenis,
        p.tempat,
        p.tahun,
        p.status,
        p.id_cover,
        p.doi,
        p.dibuat_pada,
        m.lokasi_file AS cover_file,
        m.tipe_file AS cover_tipe,
        COUNT(DISTINCT pp.id_anggota) AS jumlah_penulis
    FROM publikasi p
    LEFT JOIN media m ON p.id_cover = m.id_media
    LEFT JOIN publikasi_penulis pp ON p.id_publikasi = pp.id_publikasi
    $where_sql
    GROUP BY p.id_publikasi, m.lokasi_file, m.tipe_file
    ORDER BY p.dibuat_pada DESC
";

$result = $params
    ? pg_query_params($conn, $sql, $params)
    : pg_query($conn, $sql);

if (!$result) {
    die("Query error: " . pg_last_error($conn));
}

$publikasi = pg_fetch_all($result) ?: [];


include __DIR__ . '/../includes/header.php';
?>

<div class="publikasi-page-wrap">
    <div class="pub-top">
        <div>
            <h1>Publikasi & Jurnal</h1>
            <p>Kelola publikasi penelitian</p>
        </div>

        <a href="tambah.php">+ Tambah Publikasi</a>
    </div>

    <div class="pub-search">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <circle cx="8" cy="8" r="6" stroke="#9CA3AF" stroke-width="1.5"/>
            <path d="M12.5 12.5L15.5 15.5" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round"/>
        </svg>

        <form method="GET">
            <input type="text"
                   name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari judul, jenis, atau DOI...">
        </form>
    </div>

    <table class="pub-tabel">
        <thead>
        <tr>
            <th>Cover</th>
            <th>Judul</th>
            <th>Jenis</th>
            <th>Tahun</th>
            <th>Penulis</th>
            <th>DOI</th>
            <th>Aksi</th>
        </tr>
        </thead>

        <tbody>
        <?php if (empty($publikasi)): ?>
            <tr>
                <td colspan="7" class="empty-txt">Belum ada data publikasi.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($publikasi as $row): ?>
                <tr>

                    <!-- COVER -->
                    <td>
                        <?php if (!empty($row['cover_file'])): ?>
                            <img src="/uploads/<?= htmlspecialchars($row['cover_file']) ?>" 
                                 class="cover-thumb">
                        <?php else: ?>
                            <div class="cover-null">Tidak Ada</div>
                        <?php endif; ?>
                    </td>

                    <!-- JUDUL -->
                    <td>
                        <div class="judul-utama"><?= htmlspecialchars($row['judul']) ?></div>
                        <div class="judul-venue"><?= htmlspecialchars($row['tempat'] ?? '') ?></div>
                    </td>

                    <td><?= htmlspecialchars($row['jenis']) ?></td>
                    <td><?= htmlspecialchars($row['tahun']) ?></td>

                    <!-- PENULIS -->
                    <td>
                        <?php if ($row['jumlah_penulis'] > 0): ?>
                            <?= $row['jumlah_penulis'] ?> penulis
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- DOI -->
                    <td>
                        <?= $row['doi'] 
                            ? htmlspecialchars($row['doi']) 
                            : '<span class="text-muted">-</span>' ?>
                    </td>

                    <!-- AKSI -->
                    <td>
                        <a href="edit.php?id=<?= $row['id_publikasi'] ?>" class="aksi-btn">✏️</a>
                        <a href="hapus.php?id=<?= $row['id_publikasi'] ?>"
                           onclick="return confirm('Hapus publikasi ini?')"
                           class="aksi-btn">🗑️</a>
                    </td>

                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
