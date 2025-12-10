<?php
require __DIR__ . "/../../includes/config.php";
require "../includes/auth.php";
require "../includes/functions.php";

requireLogin();
$conn = getDBConnection();

$page_title   = "Fasilitas";
$active_page  = "fasilitas";

/* Ambil data fasilitas + foto*/
$query = "
    SELECT f.*, m.lokasi_file
    FROM fasilitas f
    LEFT JOIN media m ON f.id_foto = m.id_media
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
            <input type="text" class="form-control" placeholder="Cari nama atau kategori...">
        </div>
    </div>

    <div class="row g-3">
        <?php while ($row = pg_fetch_assoc($result)): ?>
            <?php
            if (!empty($row['lokasi_file'])) {
                // lokasi_file contoh: "fasilitas/nama-file.jpg"
                $rel_path = ltrim($row['lokasi_file'], '/');
                $img = SITE_URL . '/uploads/' . $rel_path;
            } else {
                $img = SITE_URL . '/assets/img/default-cover.jpg';
            }
            ?>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">

                    <!-- Foto -->
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
        <?php endwhile; ?>
    </div>

</div>

<?php include "../includes/footer.php"; ?>
