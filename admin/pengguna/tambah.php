<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$conn = getDBConnection();

$error = "";
$success = "";

// Submit
if (isset($_POST['submit'])) {
    $nama = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $peran = trim($_POST['peran']);

    if ($peran === 'admin') {
        $default_password = 'admin123';
    } elseif ($peran === 'operator') {
        $default_password = 'operator123';
    } else {
        $default_password = '123456'; 
    }

    $password = password_hash($default_password, PASSWORD_BCRYPT);

    if ($nama == "" || $email == "" || $peran == "") {
        $error = "Semua field harus diisi!";
    } else {
        $query = "INSERT INTO pengguna (nama_lengkap, email, password_hash, peran, aktif, dibuat_pada) 
                  VALUES ($1, $2, $3, $4, TRUE, NOW())";
        $result = pg_query_params($conn, $query, [$nama, $email, $password, $peran]);

        if ($result) {
            header("Location: index.php?status=sukses");
            exit;
        } else {
            $error = "Gagal menambahkan data: " . pg_last_error($conn);
        }
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="container mt-3">
    <h2><i class="bi bi-person-fill-add"></i> Tambah Pengguna</h2>

    <?php if ($error != ""): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">Nama Lengkap:</label>
            <input type="text" name="nama_lengkap" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email:</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Peran:</label>
            <select name="peran" class="form-select" required>
                <option value="">-- Pilih Peran --</option>
                <option value="admin">Admin</option>
                <option value="operator">Operator</option>
            </select>
        </div>

        <p class="text-muted">
            * Password awal otomatis: <strong>admin123 (untuk Admin) / operator123 (untuk Operator)</strong><br>
        </p>

        <button type="submit" name="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
