<?php
require "../includes/auth.php";
require "../includes/functions.php";
require_once "../../includes/config.php";

requireLogin();
$conn = getDBConnection();

// Search
$search = $_GET['s'] ?? '';

$q = "
    SELECT id_pengguna, nama_lengkap, email, peran, aktif
    FROM pengguna
    WHERE 
        nama_lengkap ILIKE $1
        OR email ILIKE $1
    ORDER BY peran DESC, nama_lengkap ASC
";

$res = pg_query_params($conn, $q, ["%$search%"]);

include '../includes/header.php';
?>
   <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
             <h1><i class="bi bi-people-fill"></i> Pengguna & Peran</h1>
            <p class="text-muted">Kelola akun pengguna dan hak akses</p>
        </div>
        <a href="tambah.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Tambah Pengguna
        </a>
    </div>

<div class="card mt-3">
  <div class="card-body">

    <form method="get" class="mb-3">
      <input type="text" name="s" class="form-control" 
        placeholder="Cari nama atau email..." 
        value="<?= htmlspecialchars($search) ?>">
    </form>

    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Peran</th>
          <th>Status</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && pg_num_rows($res) > 0): ?>
          <?php while ($row = pg_fetch_assoc($res)) : ?>
          <tr>
            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td>
              <?= $row['peran'] === 'admin'
                  ? '<span class="badge bg-warning">Admin</span>'
                  : '<span class="badge bg-info text-dark">Operator</span>' ?>
            </td>
            <td>
              <?= $row['aktif'] === 't'
                  ? '<span class="badge bg-success">Aktif</span>'
                  : '<span class="badge bg-danger">Nonaktif</span>' ?>
            </td>
            <td class="text-end">
              <a href="edit.php?id=<?= $row['id_pengguna'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
              <a href="hapus.php?id=<?= $row['id_pengguna'] ?>" 
                class="btn btn-sm btn-outline-danger"
                onclick="return confirm('Yakin hapus pengguna ini?')">
                Hapus
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted">Tidak ada pengguna.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
