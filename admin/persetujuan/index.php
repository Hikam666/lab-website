<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

// Hanya admin yang boleh akses halaman persetujuan
if (!isAdmin()) {
    setFlashMessage('Hanya admin yang dapat mengakses halaman persetujuan.', 'danger');
    header('Location: ../index.php');
    exit;
}

$conn = getDBConnection();

$active_page = 'persetujuan';
$page_title  = 'Persetujuan Konten';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'], $_POST['jenis'], $_POST['id'])) {
    $aksi       = $_POST['aksi'];       
    $jenis      = $_POST['jenis'];      
    $id         = (int)$_POST['id'];
    $catatan    = trim($_POST['catatan'] ?? '');

    $map = [
        'galeri'    => ['table' => 'galeri_album', 'pk' => 'id_album',     'title' => 'judul'],
        'berita'    => ['table' => 'berita',       'pk' => 'id_berita',    'title' => 'judul'],
        'publikasi' => ['table' => 'publikasi',    'pk' => 'id_publikasi', 'title' => 'judul'],
        'fasilitas' => ['table' => 'fasilitas',    'pk' => 'id_fasilitas', 'title' => 'nama'],
        'foto'      => ['table' => 'galeri_item',  'pk' => 'id_item',      'title' => 'caption'],
        'anggota'   => ['table' => 'anggota_lab',  'pk' => 'id_anggota',   'title' => 'nama'],
    ];

    if (!isset($map[$jenis])) {
        setFlashMessage('Jenis konten tidak dikenali.', 'danger');
        header('Location: index.php');
        exit;
    }

    $table     = $map[$jenis]['table'];
    $pk        = $map[$jenis]['pk'];
    $title_col = $map[$jenis]['title'];

    $currentUser = getCurrentUser();
    $admin_id    = $currentUser['id'] ?? null;

    pg_query($conn, "BEGIN");

    try {
        if ($jenis === 'foto') {
            $sql_get = "
                SELECT 
                    gi.$pk,
                    gi.$title_col AS caption,
                    gi.status,
                    gi.aksi_request,
                    gi.id_album,
                    m.id_media,
                    m.lokasi_file,
                    a.judul AS judul_album
                FROM galeri_item gi
                JOIN media m ON gi.id_media = m.id_media
                JOIN galeri_album a ON gi.id_album = a.id_album
                WHERE gi.$pk = $1
            ";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data foto tidak ditemukan.');
            }

            $row           = pg_fetch_assoc($res_get);
            $caption       = $row['caption'];
            $aksi_request  = $row['aksi_request'];
            $id_album      = (int)$row['id_album'];
            $judul_album   = $row['judul_album'];
            $id_media      = (int)$row['id_media'];
            $lokasi_file   = $row['lokasi_file'];

            $upload_dir_fs = __DIR__ . '/../../uploads';

            if ($aksi === 'approve') {

                if ($aksi_request === 'hapus') {
                    $filepath = $upload_dir_fs . '/' . $lokasi_file;
                    if (!empty($lokasi_file) && file_exists($filepath)) {
                        @unlink($filepath);
                    }
                    pg_query_params($conn, "DELETE FROM galeri_item WHERE id_item = $1", [$id]);
                    pg_query_params($conn, "DELETE FROM media      WHERE id_media = $1", [$id_media]);

                    $ket = 'Menyetujui penghapusan foto (ID_ITEM=' . $id . ') pada album "' . $judul_album .
                           '" (ID_ALBUM=' . $id_album . ').';
                    if ($catatan !== '') {
                        $ket .= ' Catatan: ' . $catatan;
                    }

                    log_aktivitas($conn, 'APPROVE_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE galeri_item
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE id_item = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);

                    $ket = 'Menyetujui ' . ($aksi_request ?: 'perubahan') .
                           ' foto (ID_ITEM=' . $id . ') pada album "' . $judul_album .
                           '" (ID_ALBUM=' . $id_album . ').';
                    if ($catatan !== '') {
                        $ket .= ' Catatan: ' . $catatan;
                    }

                    log_aktivitas($conn, 'APPROVE', $table, $id, $ket);
                }

            } else { 

                if ($aksi_request === 'tambah') {
                    $sql_update = "
                        UPDATE galeri_item
                        SET status = 'ditolak',
                            aksi_request = NULL
                        WHERE id_item = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);

                    $ket = 'Menolak penambahan foto (ID_ITEM=' . $id . ') pada album "' . $judul_album .
                           '" (ID_ALBUM=' . $id_album . ').';
                    if ($catatan !== '') {
                        $ket .= ' Catatan: ' . $catatan;
                    }

                    log_aktivitas($conn, 'REJECT_CREATE', $table, $id, $ket);

                } elseif ($aksi_request === 'edit' || $aksi_request === 'hapus') {
                    $sql_update = "
                        UPDATE galeri_item
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE id_item = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);

                    $ket = 'Menolak permintaan ' . $aksi_request . ' foto (ID_ITEM=' . $id .
                           ') pada album "' . $judul_album . '" (ID_ALBUM=' . $id_album . ').';
                    if ($catatan !== '') {
                        $ket .= ' Catatan: ' . $catatan;
                    }

                    log_aktivitas($conn, 'REJECT_' . strtoupper($aksi_request), $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE galeri_item
                        SET status = 'ditolak',
                            aksi_request = NULL
                        WHERE id_item = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);

                    $ket = 'Menolak perubahan foto (ID_ITEM=' . $id . ') pada album "' . $judul_album .
                           '" (ID_ALBUM=' . $id_album . ').';
                    if ($catatan !== '') {
                        $ket .= ' Catatan: ' . $catatan;
                    }

                    log_aktivitas($conn, 'REJECT', $table, $id, $ket);
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status foto berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }

        // ==== KHUSUS ANGGOTA LAB ====
        if ($jenis === 'anggota') {
            $sql_get = "SELECT nama, status, aktif FROM anggota_lab WHERE id_anggota = $1";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data anggota tidak ditemukan.');
            }

            $row   = pg_fetch_assoc($res_get);
            $nama  = $row['nama'];
            $aktif = ($row['aktif'] === 't');

            if ($aksi === 'approve') {

                if (!$aktif) {
                    pg_query_params($conn, "DELETE FROM anggota_lab WHERE id_anggota = $1", [$id]);

                    $ket = 'Menyetujui penghapusan anggota: ' . $nama . ' (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE_DELETE', 'anggota_lab', $id, $ket);

                } else {
                    pg_query_params(
                        $conn,
                        "UPDATE anggota_lab SET status = 'disetujui' WHERE id_anggota = $1",
                        [$id]
                    );

                    $ket = 'Menyetujui data anggota: ' . $nama . ' (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE', 'anggota_lab', $id, $ket);
                }

            } else {

                if (!$aktif) {
                    pg_query_params(
                        $conn,
                        "UPDATE anggota_lab 
                         SET status = 'disetujui', aktif = TRUE 
                         WHERE id_anggota = $1",
                        [$id]
                    );

                    $ket = 'Menolak permintaan penghapusan anggota: ' . $nama . ' (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT_DELETE', 'anggota_lab', $id, $ket);

                } else {
                    pg_query_params(
                        $conn,
                        "UPDATE anggota_lab SET status = 'ditolak' WHERE id_anggota = $1",
                        [$id]
                    );

                    $ket = 'Menolak pengajuan / perubahan anggota: ' . $nama . ' (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT', 'anggota_lab', $id, $ket);
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status anggota berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }

        if ($jenis === 'berita') {
            $sql_get = "SELECT judul, status, aksi_request, id_cover FROM berita WHERE id_berita = $1";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data berita tidak ditemukan.');
            }

            $row          = pg_fetch_assoc($res_get);
            $judul        = $row['judul'];
            $aksi_request = $row['aksi_request'];
            $id_cover     = $row['id_cover'];

            $is_delete_request = ($aksi_request === 'hapus');

            if ($aksi === 'approve') {
                if ($is_delete_request) {

                    $lokasi_file = null;
                    if ($id_cover) {
                        $q_media = pg_query_params($conn, "SELECT lokasi_file FROM media WHERE id_media = $1", [$id_cover]);
                        $lokasi_file = pg_fetch_assoc($q_media)['lokasi_file'] ?? null;
                    }
                    
                    $sql_del = "DELETE FROM berita WHERE id_berita = $1";
                    pg_query_params($conn, $sql_del, [$id]);

                    if ($lokasi_file) {
                        $filepath = __DIR__ . '/../../uploads/' . $lokasi_file;
                        if (file_exists($filepath)) {
                            @unlink($filepath);
                        }
                    }
                    if ($id_cover) {
                        pg_query_params($conn, "DELETE FROM media WHERE id_media = $1", [$id_cover]);
                    }

                    $ket = 'Menyetujui penghapusan berita: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = $1,
                            disetujui_oleh = $2,
                            disetujui_pada = NOW(),
                            catatan_review = NULL,
                            aksi_request = NULL
                        WHERE {$pk} = $3
                    ";
                    pg_query_params($conn, $sql_update, ['disetujui', $admin_id, $id]);
                    
                    $ket = 'Menyetujui berita: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE', $table, $id, $ket);
                }

            } else { 
                if ($is_delete_request) {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak permintaan penghapusan berita: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = $1,
                            disetujui_oleh = $2,
                            disetujui_pada = NOW(),
                            catatan_review = $3,
                            aksi_request = NULL
                        WHERE {$pk} = $4
                    ";
                    pg_query_params($conn, $sql_update, ['ditolak', $admin_id, $catatan !== '' ? $catatan : null, $id]);
                    
                    $ket = 'Menolak pengajuan berita: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT', $table, $id, $ket);
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status berita berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }

        if ($jenis === 'publikasi') {
            $sql_get = "SELECT judul, status, aksi_request FROM publikasi WHERE id_publikasi = $1";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data publikasi tidak ditemukan.');
            }

            $row          = pg_fetch_assoc($res_get);
            $judul        = $row['judul'];
            $aksi_request = $row['aksi_request'];

            $is_delete_request = ($aksi_request === 'hapus');

            if ($aksi === 'approve') {
                if ($is_delete_request) {
                    // APPROVE HAPUS -> Hapus Permanen
                    $sql_del = "DELETE FROM publikasi WHERE id_publikasi = $1";
                    pg_query_params($conn, $sql_del, [$id]);

                    $ket = 'Menyetujui penghapusan publikasi: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menyetujui publikasi: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE', $table, $id, $ket);
                }

            } else { 
                if ($is_delete_request) {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak permintaan penghapusan publikasi: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'ditolak',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak pengajuan publikasi: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT', $table, $id, $ket);
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status publikasi berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }

        if ($jenis === 'fasilitas') {
             $sql_get = "SELECT nama, status, aksi_request FROM fasilitas WHERE id_fasilitas = $1";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data fasilitas tidak ditemukan.');
            }

            $row          = pg_fetch_assoc($res_get);
            $nama         = $row['nama'];
            $aksi_request = $row['aksi_request'];

            $is_delete_request = ($aksi_request === 'hapus');

            if ($aksi === 'approve') {
                if ($is_delete_request) {
                    $sql_del = "DELETE FROM fasilitas WHERE id_fasilitas = $1";
                    pg_query_params($conn, $sql_del, [$id]);

                    $ket = 'Menyetujui penghapusan fasilitas: "' . $nama . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menyetujui fasilitas: "' . $nama . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE', $table, $id, $ket);
                }

            } else { 
                if ($is_delete_request) {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak permintaan penghapusan fasilitas: "' . $nama . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT_DELETE', $table, $id, $ket);

                } else {
                    $sql_update = "
                        UPDATE {$table}
                        SET status = 'ditolak',
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak pengajuan fasilitas: "' . $nama . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT', $table, $id, $ket);
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status fasilitas berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }

        // Ambil data konten
        $sql_get = "SELECT {$title_col}, status FROM {$table} WHERE {$pk} = $1";
        $res_get = pg_query_params($conn, $sql_get, [$id]);

        if (!$res_get || pg_num_rows($res_get) === 0) {
            throw new Exception('Data yang dimaksud tidak ditemukan.');
        }

        $row   = pg_fetch_assoc($res_get);
        $judul = $row[$title_col];

        $status_baru = ($aksi === 'approve') ? 'disetujui' : 'ditolak';

        $sql_update = "UPDATE {$table} SET status = $1 WHERE {$pk} = $2";
        pg_query_params($conn, $sql_update, [$status_baru, $id]);
        
        // LOG AKTIVITAS
        $log_aksi = ($aksi === 'approve') ? 'APPROVE' : 'REJECT';
        $ket      = ($aksi === 'approve' ? 'Menyetujui' : 'Menolak') .
                    " {$jenis}: \"{$judul}\" (ID={$id})";
        if ($aksi === 'reject' && $catatan !== '') {
            $ket .= " | Catatan: {$catatan}";
        }

        log_aktivitas($conn, $log_aksi, $table, $id, $ket);

        pg_query($conn, "COMMIT");

        setFlashMessage('Status konten berhasil diperbarui.', 'success');
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        setFlashMessage('Gagal memperbarui status: ' . $e->getMessage(), 'danger');
        header('Location: index.php');
        exit;
    }
}

// ---------- AMBIL DATA PENDING (status = 'diajukan' ATAU aksi_request IS NOT NULL) ----------

$pending_galeri = pg_query(
    $conn,
    "SELECT id_album, judul, status, dibuat_pada 
      FROM galeri_album 
      WHERE status = 'diajukan'
      ORDER BY dibuat_pada DESC"
);

$pending_berita = pg_query(
    $conn,
    "SELECT id_berita, judul, status, dibuat_pada, aksi_request
      FROM berita 
      WHERE status = 'diajukan' OR aksi_request IS NOT NULL
      ORDER BY dibuat_pada DESC"
);

$pending_publikasi = pg_query(
    $conn,
    "SELECT id_publikasi, judul, status, dibuat_pada, aksi_request
      FROM publikasi 
      WHERE status = 'diajukan' OR aksi_request IS NOT NULL
      ORDER BY dibuat_pada DESC"
);

$pending_fasilitas = pg_query(
    $conn,
    "SELECT id_fasilitas, nama, status, dibuat_pada, aksi_request
      FROM fasilitas 
      WHERE status = 'diajukan' OR aksi_request IS NOT NULL
      ORDER BY dibuat_pada DESC"
);

$pending_anggota = pg_query(
    $conn,
    "SELECT 
        id_anggota,
        nama,
        email,
        peran_lab,
        aktif,
        status,
        dibuat_pada,
        diperbarui_pada
      FROM anggota_lab
      WHERE status = 'diajukan' OR aktif = FALSE 
      ORDER BY diperbarui_pada DESC, dibuat_pada DESC"
);

$pending_foto = pg_query(
    $conn,
    "SELECT 
        gi.id_item,
        gi.caption,
        gi.aksi_request,
        gi.dibuat_pada,
        a.id_album,
        a.judul AS judul_album
      FROM galeri_item gi
      JOIN galeri_album a ON gi.id_album = a.id_album
      WHERE gi.status = 'diajukan' OR gi.aksi_request IS NOT NULL
      ORDER BY gi.dibuat_pada DESC"
);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-check2-square me-2"></i>Persetujuan Konten</h1>
        <p class="text-muted mb-0">Tinjau dan setujui/tolak konten yang diajukan oleh operator.</p>
    </div>
</div>

<?php if (hasFlashMessage()): ?>
    <?php $flash = getFlashMessage(); ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show mt-3" role="alert">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="mt-4">

    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-images me-2 text-primary"></i>Album Galeri - Menunggu Persetujuan</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_galeri && pg_num_rows($pending_galeri) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID</th>
                                <th>Judul Album</th>
                                <th width="180">Diajukan Pada</th>
                                <th width="220" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_galeri)): ?>
                            <tr>
                                <td><?php echo (int)$row['id_album']; ?></td>
                                <td><?php echo htmlspecialchars($row['judul']); ?></td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="jenis" value="galeri">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_album']; ?>">
                                        <input type="hidden" name="aksi" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i>Setujui
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="jenis" value="galeri">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_album']; ?>">
                                        <input type="hidden" name="aksi" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Tolak
                                        </button>
                                    </form>
                                    <a href="../galeri/edit.php?id=<?php echo (int)$row['id_album']; ?>"
                                        class="btn btn-sm btn-outline-secondary ms-1">
                                        <i class="bi bi-pencil-square me-1"></i>Lihat / Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada album galeri yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-newspaper me-2 text-primary"></i>Berita & Pengumuman - Menunggu Persetujuan</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_berita && pg_num_rows($pending_berita) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID</th>
                                <th>Judul</th>
                                <th width="180">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="260" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_berita)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td><?php echo (int)$row['id_berita']; ?></td>
                                <td><?php echo htmlspecialchars($row['judul']); ?></td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="jenis" value="berita">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_berita']; ?>">
                                        <input type="hidden" name="aksi" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            <?php echo $is_delete_req ? 'Setujui Hapus' : 'Setujui'; ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="jenis" value="berita">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_berita']; ?>">
                                        <input type="hidden" name="aksi" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Tolak
                                        </button>
                                    </form>
                                    <a href="../berita/edit.php?id=<?php echo (int)$row['id_berita']; ?>"
                                        class="btn btn-sm btn-outline-secondary ms-1">
                                        <i class="bi bi-pencil-square me-1"></i>Lihat / Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada berita yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Publikasi - Menunggu Persetujuan</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_publikasi && pg_num_rows($pending_publikasi) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID</th>
                                <th>Judul</th>
                                <th width="180">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="200" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_publikasi)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td><?php echo (int)$row['id_publikasi']; ?></td>
                                <td><?php echo htmlspecialchars($row['judul']); ?></td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="jenis" value="publikasi">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_publikasi']; ?>">
                                        <input type="hidden" name="aksi" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            <?php echo $is_delete_req ? 'Setujui Hapus' : 'Setujui'; ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="jenis" value="publikasi">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_publikasi']; ?>">
                                        <input type="hidden" name="aksi" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Tolak
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada publikasi yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Fasilitas - Menunggu Persetujuan</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_fasilitas && pg_num_rows($pending_fasilitas) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID</th>
                                <th>Nama Fasilitas</th>
                                <th width="180">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="200" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_fasilitas)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td><?php echo (int)$row['id_fasilitas']; ?></td>
                                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="jenis" value="fasilitas">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_fasilitas']; ?>">
                                        <input type="hidden" name="aksi" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            <?php echo $is_delete_req ? 'Setujui Hapus' : 'Setujui'; ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="jenis" value="fasilitas">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_fasilitas']; ?>">
                                        <input type="hidden" name="aksi" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Tolak
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada fasilitas yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>Anggota Lab - Menunggu Persetujuan</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_anggota && pg_num_rows($pending_anggota) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th width="160">Peran Lab</th>
                                <th width="140">Jenis Pengajuan</th>
                                <th width="180">Diajukan / Diubah</th>
                                <th width="260" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_anggota)): ?>
                            <?php
                                $is_delete_req = ($row['aktif'] !== 't');
                            ?>
                            <tr>
                                <td><?php echo (int)$row['id_anggota']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                                <td>
                                    <?php if ($row['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>">
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
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        Dibuat: <?php echo formatTanggalWaktu($row['dibuat_pada']); ?><br>
                                        Diubah: <?php echo formatTanggalWaktu($row['diperbarui_pada']); ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="jenis" value="anggota">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_anggota']; ?>">
                                        <input type="hidden" name="aksi" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i>Setujui
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="jenis" value="anggota">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id_anggota']; ?>">
                                        <input type="hidden" name="aksi" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Tolak
                                        </button>
                                    </form>
                                    <a href="../anggota/edit.php?id=<?php echo (int)$row['id_anggota']; ?>"
                                        class="btn btn-sm btn-outline-secondary ms-1">
                                        <i class="bi bi-pencil-square me-1"></i>Lihat / Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada data anggota yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-image me-2 text-primary"></i>Foto Galeri - Menunggu Persetujuan</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_foto && pg_num_rows($pending_foto) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">ID Foto</th>
                                <th>Album</th>
                                <th>Caption</th>
                                <th width="120">Jenis Request</th>
                                <th width="180">Diajukan Pada</th>
                                <th width="260" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($f = pg_fetch_assoc($pending_foto)): ?>
                            <tr>
                                <td><?php echo (int)$f['id_item']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($f['judul_album']); ?></strong>
                                    <div><small class="text-muted">ID Album: <?php echo (int)$f['id_album']; ?></small></div>
                                </td>
                                <td><?php echo htmlspecialchars($f['caption']); ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo htmlspecialchars($f['aksi_request'] ?: '-'); ?>
                                    </span>
                                </td>
                                <td><?php echo formatTanggalWaktu($f['dibuat_pada']); ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="jenis" value="foto">
                                        <input type="hidden" name="id" value="<?php echo (int)$f['id_item']; ?>">
                                        <input type="hidden" name="aksi" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle me-1"></i>Setujui
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="jenis" value="foto">
                                        <input type="hidden" name="id" value="<?php echo (int)$f['id_item']; ?>">
                                        <input type="hidden" name="aksi" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Tolak
                                        </button>
                                    </form>
                                    <a href="../galeri/foto.php?id=<?php echo (int)$f['id_album']; ?>"
                                        class="btn btn-sm btn-outline-secondary ms-1">
                                        <i class="bi bi-images me-1"></i>Lihat Album
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada foto galeri yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>