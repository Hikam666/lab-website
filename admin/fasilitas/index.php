<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/auth.php";
require "../includes/functions.php";

requireLogin();
$conn = getDBConnection();

$page_title   = "Fasilitas";
$active_page  = "fasilitas";

$search_query = $_GET['cari'] ?? ''; 
$search_sql   = ''; 

if (!empty($search_query)) {
    $safe_search = pg_escape_string($conn, $search_query);

    $search_sql = "
        WHERE f.nama ILIKE '%{$safe_search}%' 
        OR f.kategori ILIKE '%{$safe_search}%'
    ";
}

$query = "
    SELECT f.*, m.lokasi_file
    FROM fasilitas f
    LEFT JOIN media m ON f.id_foto = m.id_media
    {$search_sql} -- Sisipkan kondisi pencarian di sini
    ORDER BY f.dibuat_pada DESC
";
$result = pg_query($conn, $query);

include "../includes/header.php";
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1><i class="bi bi-building"></i> Fasilitas</h1>
            <p class="text-muted">Kelola fasilitas dan peralatan laboratorium</p>
        </div>
        <a href="tambah.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Tambah Fasilitas
        </a>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <form action="" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" name="cari" 
                           placeholder="Cari nama atau kategori..." 
                           value="<?= htmlspecialchars($search_query) ?>"> <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    
                    <?php if (!empty($search_query)): ?>
                        <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <?php 
        if (pg_num_rows($result) > 0): 
            while ($row = pg_fetch_assoc($result)): 
        ?>
            <?php
            if (!empty($row['lokasi_file'])) {
                $rel_path = ltrim($row['lokasi_file'], '/');
                $img = SITE_URL . '/uploads/' . $rel_path; 
            } else {
                $img = SITE_URL . '/assets/img/default-cover.jpg';
            }
            ?>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">

                    <div class="bg-light border-bottom" style="height: 180px; display:flex;justify-content:center;align-items:center;">
                        <img src="<?= $img ?>" class="img-fluid w-100 h-100" style="object-fit: cover;">
                    </div>

                    <div class="card-body">
                        <h5 class="card-title mb-0">
                            <?= htmlspecialchars($row['nama']) ?>
                        </h5>
                        <small class="text-muted d-block mb-2">
                            <?= htmlspecialchars($row['kategori'] ?? '-') ?>
                        </small>

                        <p class="text-muted small">
                            <?= htmlspecialchars(mb_strimwidth($row['deskripsi'], 0, 80, "...")) ?>
                        </p>
                    </div>

                    <div class="card-footer bg-white border-0 d-flex gap-2">
                        <a href="edit.php?id=<?= $row['id_fasilitas'] ?>" class="btn btn-outline-primary w-50">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </a>
                        <a onclick="return confirm('Yakin hapus fasilitas ini?')" 
                           href="hapus.php?id=<?= $row['id_fasilitas'] ?>" 
                           class="btn btn-outline-danger w-50">
                            <i class="bi bi-trash me-1"></i>Hapus
                        </a>
                    </div>

                </div>
            </div>
        <?php 
            endwhile; 
        else:
        ?>
        <div class="col-12">
            <div class="alert alert-info text-center">
                Tidak ada fasilitas yang ditemukan<?php if(!empty($search_query)) echo " untuk **'{$search_query}'**"; ?>.
            </div>
        </div>
        <?php 
        endif; 
        ?>
    </div>

</div>

<?php include "../includes/footer.php"; ?>