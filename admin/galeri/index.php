<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Manajemen Galeri Album';

$sql = "
    SELECT 
        ga.id_album,
        ga.judul,
        ga.slug,
        ga.deskripsi,
        ga.status,
        ga.aksi_request,
        ga.dibuat_pada,
        ga.diperbarui_pada,
        m.lokasi_file AS cover_image,
        COUNT(gi.id_item) AS total_foto
    FROM galeri_album ga
    LEFT JOIN media m ON ga.id_cover = m.id_media
    LEFT JOIN galeri_item gi ON gi.id_album = ga.id_album
    GROUP BY ga.id_album, m.lokasi_file
    ORDER BY ga.dibuat_pada DESC
";

$res_album = pg_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <div>
            <h1 class="mt-4">Galeri Album</h1>
            <p class="text-muted mb-0">Kelola album galeri dan foto-foto di dalamnya.</p>
        </div>
        <a href="tambah.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Tambah Album
        </a>
    </div>

    <?php if (hasFlashMessage()): ?>
        <?php $flash = getFlashMessage(); ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if ($res_album && pg_num_rows($res_album) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ALBUM</th>
                                <th class="text-center" width="120">STATUS</th>
                                <th class="text-center" width="120">JUMLAH FOTO</th>
                                <th class="text-center" width="160">DIBUAT</th>
                                <th class="text-center" width="180">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($res_album)): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($row['cover_image'])): ?>
                                            <img src="../../uploads/<?php echo htmlspecialchars($row['cover_image']); ?>"
                                                alt=""
                                                class="rounded me-3"
                                                style="width: 90px; height: 90px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light border rounded me-3 d-flex align-items-center justify-content-center"
                                                style="width: 90px; height: 90px;">
                                                <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['judul']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['slug']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                        <?php 
                                        if ($row['aksi_request'] === 'hapus') {
                                            echo '<span class="badge bg-danger text-white">Diajukan</span>';
                                        } else {
                                            echo getStatusBadge($row['status']); 
                                        }
                                        ?>
                                    </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary px-3">
                                        <?php echo (int)$row['total_foto']; ?> foto
                                    </span>
                                </td>
                                <td class="text-center">
                                    <small><?php echo formatDateTime($row['dibuat_pada'], 'd M Y, H:i'); ?></small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit.php?id=<?php echo (int)$row['id_album']; ?>" 
                                           class="btn btn-outline-primary"
                                           title="Edit Album">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="foto.php?id=<?php echo (int)$row['id_album']; ?>" 
                                           class="btn btn-outline-secondary"
                                           title="Kelola Foto">
                                            <i class="bi bi-images"></i>
                                        </a>
                                        <a href="hapus.php?id=<?php echo (int)$row['id_album']; ?>"
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Yakin ingin menghapus album ini?');"
                                           title="Hapus Album">
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
                    <i class="bi bi-images text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-muted">Tidak ada album galeri</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>