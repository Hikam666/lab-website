<?php
$extra_css = ['pesan.css'];
require_once "../includes/functions.php";
require_once "../includes/auth.php";

// PERBAIKAN: Atur Zona Waktu Default PHP ke Indonesia (WIB)
// Ini memastikan bahwa fungsi date() di bawah akan menampilkan waktu yang benar
date_default_timezone_set('Asia/Jakarta'); 

include "../includes/header.php";
include "../includes/sidebar.php";

$conn = getDBConnection();
$active_page = 'pesan';
$page_title  = 'Pesan & Permintaan';

// Ambil semua pesan (pastikan kolom diterima_pada adalah timestamptz)
$list = pg_query($conn, "SELECT * FROM pesan_kontak ORDER BY diterima_pada DESC");

// Detail pesan jika ada ID
$detail = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $qDetail = pg_query_params($conn,
        "SELECT * FROM pesan_kontak WHERE id_pesan = $1",
        [$id]
    );
    $detail = pg_fetch_assoc($qDetail);

    // Update status dibaca
    pg_query_params($conn,
        "UPDATE pesan_kontak SET status='dibaca' WHERE id_pesan=$1",
        [$id]
    );
}
?>

<div class="pesan-wrapper">
    <h2 class="judul-halaman">Pesan & Permintaan</h2>
    <p class="subjudul">Kelola pesan kontak dari publik</p>

    <div class="pesan-container">

        <div class="left-panel">

            <?php while ($row = pg_fetch_assoc($list)) : ?>
                <a href="index.php?id=<?= $row['id_pesan'] ?>"
                    class="pesan-item <?= (isset($_GET['id']) && $_GET['id'] == $row['id_pesan']) ? 'aktif' : '' ?>">

                    <div class="ikon">
                        <?php if ($row['status'] === 'baru') : ?>
                            <span class="dot-baru"></span>
                        <?php endif; ?>
                    </div>

                    <div class="info">
                        <div class="pengirim"><?= htmlspecialchars($row['nama_pengirim']) ?></div>
                        <div class="email"><?= htmlspecialchars($row['email_pengirim']) ?></div>

                        <?php if (!empty($row['tujuan'])) : ?>
                            <div class="tag"><?= htmlspecialchars(ucwords($row['tujuan'])) ?></div>
                        <?php endif; ?>

                        <div class="tanggal">
                            <?= date("d M, H:i", strtotime($row['diterima_pada'])) ?>
                        </div>
                    </div>

                </a>
            <?php endwhile; ?>
        </div>

        <div class="right-panel">
            <?php if ($detail) : ?>

                <div class="detail-actions">
                    <a href="hapus.php?id=<?= $detail['id_pesan'] ?>"
                        class="btn-hapus-pesan"
                        onclick="return confirm('Yakin ingin menghapus pesan ini?');">
                        Hapus Pesan
                    </a>
                </div>

                <h3 class="detail-subjek">
                    <?= htmlspecialchars($detail['subjek'] ?: "(Tanpa Subjek)") ?>
                </h3>

                <div class="detail-meta">
                    <p><strong>Dari:</strong> <?= htmlspecialchars($detail['nama_pengirim']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($detail['email_pengirim']) ?></p>

                    <?php if ($detail['tujuan']) : ?>
                        <p><strong>Kategori:</strong> <?= htmlspecialchars(ucwords($detail['tujuan'])) ?></p>
                    <?php endif; ?>

                    <p><strong>Diterima:</strong>
                        <?= date("d M Y, H:i", strtotime($detail['diterima_pada'])) ?>
                    </p>
                </div>

                <div class="isi-pesan">
                    <?= nl2br(htmlspecialchars($detail['isi'])) ?>
                </div>

            <?php else : ?>

                <div class="no-pesan">
                    <i class="ph ph-envelope-open-text icon-kosong"></i>
                    <p>Pilih pesan untuk melihat detail</p>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>