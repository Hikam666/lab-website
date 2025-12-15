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
        'pesan'     => ['table' => 'pesan_kontak', 'pk' => 'id_pesan',     'title' => 'subjek'],
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
        // ==== KHUSUS ALBUM GALERI ====
        if ($jenis === 'galeri') {
            $sql_get = "SELECT judul, status, aksi_request FROM galeri_album WHERE id_album = $1";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data album tidak ditemukan.');
            }

            $row          = pg_fetch_assoc($res_get);
            $judul        = $row['judul'];
            $aksi_request = $row['aksi_request'];
            $is_delete_request = ($aksi_request === 'hapus');

            if ($aksi === 'approve') {
                if ($is_delete_request) {
                    // Setujui Hapus -> Hapus Data
                    $sql_del = "DELETE FROM galeri_album WHERE id_album = $1";
                    pg_query_params($conn, $sql_del, [$id]);

                    $ket = 'Menyetujui penghapusan album: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE_DELETE', $table, $id, $ket);
                } else {
                    // Setujui Tambah/Edit -> Update Status
                    $sql_update = "
                        UPDATE galeri_album
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE id_album = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menyetujui album: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'APPROVE', $table, $id, $ket);
                }
            } else { 
                // REJECT
                if ($is_delete_request) {
                    // Tolak Hapus -> Hapus Flag request, status tetap disetujui
                    $sql_update = "
                        UPDATE galeri_album
                        SET status = 'disetujui',
                            aksi_request = NULL
                        WHERE id_album = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak permintaan penghapusan album: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT_DELETE', $table, $id, $ket);
                } else {
                    // Tolak Tambah -> Status ditolak
                    $sql_update = "
                        UPDATE galeri_album
                        SET status = 'ditolak',
                            aksi_request = NULL
                        WHERE id_album = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak pengajuan album: "' . $judul . '" (ID=' . $id . ')';
                    log_aktivitas($conn, 'REJECT', $table, $id, $ket);
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status album galeri berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }
        
        // ==== KHUSUS FOTO GALERI ====
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

                    $ket = 'Menyetujui penghapusan foto pada album "' . $judul_album . '" (ID_ALBUM=' . $id_album . ').';
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

                    $ket = 'Menyetujui ' . ($aksi_request ?: 'perubahan') . ' foto pada album "' . $judul_album . '" (ID_ALBUM=' . $id_album . ').';
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

                    $ket = 'Menolak penambahan foto pada album "' . $judul_album . '" (ID_ALBUM=' . $id_album . ').';
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

                    $ket = 'Menolak permintaan ' . $aksi_request . ' foto pada album "' . $judul_album . '" (ID_ALBUM=' . $id_album . ').';
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

                    $ket = 'Menolak perubahan foto pada album "' . $judul_album . '" (ID_ALBUM=' . $id_album . ').';
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

        // ==== KHUSUS BERITA ====
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

        // ==== KHUSUS PUBLIKASI ====
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

        // ==== KHUSUS FASILITAS ====
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

        // ==== KHUSUS PESAN KONTAK ====
        if ($jenis === 'pesan') {
            // Ambil detail pesan
            $sql_get = "SELECT subjek, nama_pengirim, aksi_request, status_request FROM pesan_kontak WHERE id_pesan = $1";
            $res_get = pg_query_params($conn, $sql_get, [$id]);

            if (!$res_get || pg_num_rows($res_get) === 0) {
                throw new Exception('Data pesan tidak ditemukan.');
            }

            $row          = pg_fetch_assoc($res_get);
            $subjek       = $row['subjek'] ?: '(Tanpa Subjek)';
            $pengirim     = $row['nama_pengirim'] ?: 'Anonim';
            $aksi_request = $row['aksi_request'];

            if ($aksi === 'approve') {
                // APPROVE: Hapus pesan dari database (karena ini hanya request hapus)
                if ($aksi_request === 'hapus') {
                    pg_query_params($conn, "DELETE FROM pesan_kontak WHERE id_pesan = $1", [$id]);
                    $ket = 'Menyetujui penghapusan pesan dari ' . $pengirim . ' (Subjek: "' . $subjek . '")';
                    log_aktivitas($conn, 'APPROVE_DELETE', $table, $id, $ket);
                } else {
                    throw new Exception('Aksi persetujuan untuk pesan tidak valid.');
                }
            } else { 
                // REJECT: Tolak permintaan hapus, bersihkan request status
                if ($aksi_request === 'hapus') {
                    $sql_update = "
                        UPDATE {$table}
                        SET status_request = 'ditolak', 
                            aksi_request = NULL
                        WHERE {$pk} = $1
                    ";
                    pg_query_params($conn, $sql_update, [$id]);
                    
                    $ket = 'Menolak permintaan penghapusan pesan dari ' . $pengirim . ' (Subjek: "' . $subjek . '")';
                    log_aktivitas($conn, 'REJECT_DELETE', $table, $id, $ket);
                } else {
                     throw new Exception('Aksi penolakan untuk pesan tidak valid.');
                }
            }

            pg_query($conn, "COMMIT");
            setFlashMessage('Status pesan berhasil diproses.', 'success');
            header('Location: index.php');
            exit;
        }

        // UNTUK KONTEN NON-KHUSUS YANG TERSISA (Jaga-jaga)
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

$pending_galeri = pg_query(
    $conn,
    "SELECT id_album, judul, status, dibuat_pada, aksi_request 
      FROM galeri_album 
      WHERE status = 'diajukan' OR aksi_request IS NOT NULL
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

$pending_pesan = pg_query(
    $conn,
    "SELECT id_pesan, subjek, nama_pengirim AS pengirim, diterima_pada AS dibuat_pada, aksi_request
      FROM pesan_kontak 
      WHERE aksi_request = 'hapus' AND status_request = 'diajukan'
      ORDER BY diterima_pada DESC"
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
            <h5 class="mb-0"><i class="bi bi-inbox me-2 text-danger"></i>Pesan Kontak - Menunggu Persetujuan Hapus</h5>
        </div>
        <div class="card-body p-0">
            <?php if ($pending_pesan && pg_num_rows($pending_pesan) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Subjek & Pengirim</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="200">Diajukan Pada</th>
                                <th width="250" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_pesan)): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['subjek'] ?: '(Tanpa Subjek)'); ?></div>
                                    <small class="text-muted">Dari: <?php echo htmlspecialchars($row['pengirim']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-danger">Pengajuan Hapus</span>
                                </td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td class="text-end">
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Pesan">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="pesan">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_pesan']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui Penghapusan">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <a href="../pesan/detail.php?id=<?php echo (int)$row['id_pesan']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat Pesan">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="pesan">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_pesan']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak Penghapusan">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3 text-muted fst-italic">
                    Tidak ada permintaan penghapusan pesan yang menunggu persetujuan.
                </div>
            <?php endif; ?>
        </div>
    </div>

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
                                <th>Judul Album</th>
                                <th width="200">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="250" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_galeri)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['judul']); ?></div>
                                </td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Album Galeri">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="galeri">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_album']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui <?php echo $is_delete_req ? 'Penghapusan' : 'Album Ini'; ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <a href="../galeri/edit.php?id=<?php echo (int)$row['id_album']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat/Edit Album">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="galeri">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_album']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak <?php echo $is_delete_req ? 'Penghapusan' : 'Album Ini'; ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
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
                                <th>Judul</th>
                                <th width="200">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="280" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_berita)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['judul']); ?></div>
                                </td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Berita">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="berita">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_berita']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui <?php echo $is_delete_req ? 'Penghapusan' : 'Berita Ini'; ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        
                                        <a href="../berita/edit.php?id=<?php echo (int)$row['id_berita']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat/Edit Berita">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="berita">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_berita']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak Berita Ini">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
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
                                <th>Judul</th>
                                <th width="200">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="250" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_publikasi)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['judul']); ?></div>
                                </td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Publikasi">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="publikasi">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_publikasi']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui <?php echo $is_delete_req ? 'Penghapusan' : 'Publikasi Ini'; ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <a href="../publikasi/edit.php?id=<?php echo (int)$row['id_publikasi']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat/Edit Publikasi">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="publikasi">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_publikasi']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak Publikasi Ini">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
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
                                <th>Nama Fasilitas</th>
                                <th width="200">Diajukan Pada</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="250" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_fasilitas)): ?>
                            <?php $is_delete_req = ($row['aksi_request'] === 'hapus'); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['nama']); ?></div>
                                </td>
                                <td><?php echo formatTanggalWaktu($row['dibuat_pada']); ?></td>
                                <td>
                                    <?php if ($is_delete_req): ?>
                                        <span class="badge bg-danger">Pengajuan Hapus</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pengajuan Tambah/Edit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Fasilitas">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="fasilitas">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_fasilitas']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui <?php echo $is_delete_req ? 'Penghapusan' : 'Fasilitas Ini'; ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <a href="../fasilitas/edit.php?id=<?php echo (int)$row['id_fasilitas']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat/Edit Fasilitas">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="fasilitas">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_fasilitas']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak Fasilitas Ini">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
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
                                <th>Nama</th>
                                <th>Email</th>
                                <th width="140">Peran Lab</th>
                                <th width="160">Jenis Pengajuan</th>
                                <th width="180">Diajukan / Diubah</th>
                                <th width="250" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = pg_fetch_assoc($pending_anggota)): ?>
                            <?php
                                $is_delete_req = ($row['aktif'] !== 't');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['nama']); ?></div>
                                </td>
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
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Anggota Lab">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="anggota">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_anggota']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui <?php echo $is_delete_req ? 'Penghapusan' : 'Anggota Ini'; ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <a href="../anggota/edit.php?id=<?php echo (int)$row['id_anggota']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat/Edit Anggota">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="anggota">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id_anggota']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak <?php echo $is_delete_req ? 'Pembatalan Penghapusan' : 'Anggota Ini'; ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
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
                                <th>Album / Caption</th>
                                <th width="160">Jenis Request</th>
                                <th width="200">Diajukan Pada</th>
                                <th width="250" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($f = pg_fetch_assoc($pending_foto)): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($f['judul_album']); ?></div>
                                    <small class="text-muted">"<?php echo htmlspecialchars($f['caption']); ?>"</small><br>
                                    </td>
                                <td>
                                    <?php 
                                        $request_type = htmlspecialchars($f['aksi_request'] ?: 'tambah/edit');
                                        $badge_color = ($f['aksi_request'] === 'hapus') ? 'bg-danger' : 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $badge_color; ?>">
                                        <?php echo $request_type; ?>
                                    </span>
                                </td>
                                <td><?php echo formatTanggalWaktu($f['dibuat_pada']); ?></td>
                                <td class="text-end">
                                    <div class="btn-group border rounded overflow-hidden" role="group" aria-label="Aksi Foto Galeri">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="foto">
                                            <input type="hidden" name="id" value="<?php echo (int)$f['id_item']; ?>">
                                            <input type="hidden" name="aksi" value="approve">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-0" title="Setujui Foto Ini">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <a href="../galeri/foto.php?id=<?php echo (int)$f['id_album']; ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-0 border-start border-end" title="Lihat Album">
                                            <i class="bi bi-images"></i>
                                        </a>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="jenis" value="foto">
                                            <input type="hidden" name="id" value="<?php echo (int)$f['id_item']; ?>">
                                            <input type="hidden" name="aksi" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Tolak Foto Ini">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
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