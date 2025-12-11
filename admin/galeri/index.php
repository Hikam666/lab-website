<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$conn = getDBConnection();

$active_page = 'galeri';
$page_title  = 'Manajemen Galeri Album';
$extra_css = ['galeri.css']; 

$sql = "
    SELECT 
        ga.id_album,
        ga.judul,
        ga.slug,
        ga.deskripsi,
        ga.status,
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
                                <th>Album</th>
                                <th width="120">Status</th>
                                <th width="120">Jumlah Foto</th>
                                <th width="180">Dibuat</th>
                                <th width="220" class="text-end">Aksi</th>
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
                                                 style="width: 60px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light border rounded me-3 d-flex align-items-center justify-content-center"
                                                 style="width: 60px; height: 40px;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['judul']); ?></div>
                                            <small class="text-muted">Slug: <?php echo htmlspecialchars($row['slug']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($row['status']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo (int)$row['total_foto']; ?> foto
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatTanggalWaktu($row['dibuat_pada']); ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <a href="edit.php?id=<?php echo (int)$row['id_album']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <a href="foto.php?id=<?php echo (int)$row['id_album']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                        <i class="bi bi-images"></i> Foto
                                    </a>
                                    <?php if (isAdmin()): ?>
                                        <a href="hapus.php?id=<?php echo (int)$row['id_album']; ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Yakin ingin menghapus album ini beserta semua fotonya?');">
                                            <i class="bi bi-trash"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    Belum ada album galeri.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>