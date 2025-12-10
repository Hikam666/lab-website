<?php
// admin/pengguna/edit.php (robust & debug-friendly)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$conn = getDBConnection();

// ----- BASIC CHECK ID -----
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die("ID pengguna tidak diberikan atau tidak valid. Pastikan URL berformat: edit.php?id=123");
}
$id = (int) $_GET['id'];

// ----- FETCH USER -----
$q = "SELECT * FROM pengguna WHERE id_pengguna = $1";
$res = pg_query_params($conn, $q, [$id]);

if ($res === false) {
    $dbErr = pg_last_error($conn);
    http_response_code(500);
    die("Query gagal: " . htmlspecialchars($dbErr));
}

$user = pg_fetch_assoc($res);
if (!$user) {
    http_response_code(404);
    die("Pengguna dengan id={$id} tidak ditemukan.");
}

// Determine name column
$name_column = null;
if (array_key_exists('nama_lengkap', $user)) {
    $name_column = 'nama_lengkap';
} elseif (array_key_exists('nama', $user)) {
    $name_column = 'nama';
} else {
    $cols = implode(', ', array_keys($user));
    http_response_code(500);
    die("Kolom nama tidak ditemukan. Kolom yang ada: {$cols}");
}

// ----- HANDLE FORM SUBMIT -----
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $nama_input = trim($_POST[$name_column] ?? '');
    $email = trim($_POST['email'] ?? '');
    $peran_input = trim($_POST['peran'] ?? '');
    $peran = strtolower($peran_input);

    // Checkbox aktif: jika dicentang => 't', jika tidak => 'f'
    $aktif = isset($_POST['aktif']) ? 't' : 'f';

    if ($nama_input === '' || $email === '' || $peran === '') {
        $error = "Nama, email, dan peran harus diisi.";
    } else {
        $update_sql = "UPDATE pengguna 
                       SET {$name_column} = $1, email = $2, peran = $3, aktif = $4, diperbarui_pada = NOW() 
                       WHERE id_pengguna = $5";
        $params = [$nama_input, $email, $peran, $aktif, $id];

        $upd = pg_query_params($conn, $update_sql, $params);
        if ($upd === false) {
            $dbErr = pg_last_error($conn);
            $error = "Gagal update: " . htmlspecialchars($dbErr);
        } else {
            header("Location: index.php?status=updated");
            exit;
        }
    }
}

// Prefill form
$prefill_name = $user[$name_column];
$prefill_email = $user['email'] ?? '';
$prefill_peran = $user['peran'] ?? '';
$prefill_aktif = ($user['aktif'] === 't' || $user['aktif'] === true || $user['aktif'] === 1);

// ----- RENDER FORM -----
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-3">
    <h2><i class="bi bi-pencil-square"></i> Edit Pengguna</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Nama:</label>
            <input type="text" name="<?= $name_column ?>" value="<?= htmlspecialchars($prefill_name) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($prefill_email) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Peran:</label>
            <input type="text" class="form-control" 
                value="<?= htmlspecialchars(ucfirst($prefill_peran)) ?>" 
                disabled>
            <input type="hidden" name="peran" value="<?= htmlspecialchars($prefill_peran) ?>">
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="aktifCheck" name="aktif" <?= $prefill_aktif ? 'checked' : '' ?>>
            <label class="form-check-label" for="aktifCheck">Akun Aktif</label>
        </div>

        <button type="submit" name="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
