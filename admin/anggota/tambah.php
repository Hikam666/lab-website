<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$active_page = 'anggota';
$page_title = 'Tambah Anggota';
$conn = getDBConnection();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = isAdmin() ? 'disetujui' : 'diajukan';

    $errors = [];
    
    // Ambil data form
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $nidn = trim($_POST['nidn'] ?? '');
    $program_studi = trim($_POST['program_studi'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $alamat_kantor = trim($_POST['alamat_kantor'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $google_scholar = trim($_POST['google_scholar'] ?? '');
    $sinta = trim($_POST['sinta'] ?? '');
    $peran_lab = trim($_POST['peran_lab'] ?? '');
    $keahlian = trim($_POST['keahlian'] ?? '');
    $aktif = isset($_POST['aktif']) ? true : false;
    
    // Validation
    if (empty($nama)) {
        $errors[] = "Nama wajib diisi";
    } elseif (mb_strlen($nama) > 255) {
        $errors[] = "Nama maksimal 255 karakter";
    }
    
    if (empty($email)) {
        $errors[] = "Email wajib diisi";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    } else {
        $check_email = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE email = $1", [$email]);
        if ($check_email && pg_num_rows($check_email) > 0) {
            $errors[] = "Email sudah digunakan anggota lain";
        }
    }
    
    // Validasi URL (opsional, hanya jika diisi)
    if (!empty($linkedin) && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
        $errors[] = "Format URL LinkedIn tidak valid";
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "Format URL Website tidak valid";
    }
    
    if (!empty($google_scholar) && !filter_var($google_scholar, FILTER_VALIDATE_URL)) {
        $errors[] = "Format URL Google Scholar tidak valid";
    }
    
    if (!empty($sinta) && !filter_var($sinta, FILTER_VALIDATE_URL)) {
        $errors[] = "Format URL SINTA tidak valid";
    }
    
    // Validasi CV upload (opsional)
    if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
        $cv = $_FILES['cv_file'];
        
        $allowed_cv_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $cv_mime = finfo_file($finfo, $cv['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($cv_mime, $allowed_cv_types)) {
            $errors[] = "Format CV harus PDF, DOC, atau DOCX";
        }
        
        if ($cv['size'] > 5 * 1024 * 1024) {
            $errors[] = "Ukuran CV maksimal 5MB";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
    } else {
        pg_query($conn, "BEGIN");
        try {
            // Generate slug
            $slug = generateSlug($nama);
            $check_slug = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE slug = $1", [$slug]);
            if ($check_slug && pg_num_rows($check_slug) > 0) {
                $counter = 1;
                $original_slug = $slug;
                while ($check_slug && pg_num_rows($check_slug) > 0) {
                    $slug = $original_slug . '-' . $counter;
                    $check_slug = pg_query_params($conn, "SELECT id_anggota FROM anggota_lab WHERE slug = $1", [$slug]);
                    $counter++;
                }
            }
            
            $id_foto = null;
            
            // Handle foto upload
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto = $_FILES['foto'];
                
                $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                $filename = 'anggota-' . time() . '-' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../uploads/anggota/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                if (move_uploaded_file($foto['tmp_name'], $upload_path . $filename)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $upload_path . $filename);
                    finfo_close($finfo);
                    
                    $media_sql = "INSERT INTO media (lokasi_file, ukuran_file, tipe_file, keterangan_alt, dibuat_oleh, dibuat_pada) 
                                  VALUES ($1, $2, $3, $4, $5, NOW()) RETURNING id_media";
                    $media_result = pg_query_params($conn, $media_sql, [
                        'anggota/' . $filename,
                        $foto['size'],
                        $mime_type,
                        $nama,
                        $_SESSION['user_id'] ?? null
                    ]);
                    
                    if ($media_result) {
                        $id_foto = pg_fetch_assoc($media_result)['id_media'];
                    }
                }
            }
            
            // Handle CV upload
            $cv_file = null;
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
                $cv = $_FILES['cv_file'];
                $ext = strtolower(pathinfo($cv['name'], PATHINFO_EXTENSION));
                $filename = 'cv-' . time() . '-' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../uploads/cv/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                if (move_uploaded_file($cv['tmp_name'], $upload_path . $filename)) {
                    $cv_file = 'cv/' . $filename;
                }
            }
            
            // Get next urutan
            $urutan_result = pg_query($conn, "SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM anggota_lab");
            $urutan = $urutan_result ? pg_fetch_assoc($urutan_result)['next_urutan'] : 1;
            
            // Insert anggota (TAMBAH kolom status)
            $sql = "INSERT INTO anggota_lab (
                        nama, slug, email, nip, nidn, program_studi, jabatan, alamat_kantor,
                        linkedin, website, google_scholar, sinta, peran_lab, keahlian, 
                        id_foto, cv_file, aktif, urutan, status, dibuat_pada, diperbarui_pada
                    ) VALUES (
                        $1, $2, $3, $4, $5, $6, $7, $8,
                        $9, $10, $11, $12, $13, $14,
                        $15, $16, $17, $18, $19, NOW(), NOW()
                    )
                    RETURNING id_anggota";
            
            $result = pg_query_params($conn, $sql, [
                $nama,
                $slug,
                $email,
                $nip ?: null,
                $nidn ?: null,
                $program_studi ?: null,
                $jabatan ?: null,
                $alamat_kantor ?: null,
                $linkedin ?: null,
                $website ?: null,
                $google_scholar ?: null,
                $sinta ?: null,
                $peran_lab ?: null,
                $keahlian ?: null,
                $id_foto,
                $cv_file,
                $aktif ? 't' : 'f',
                $urutan,
                $status   
            ]);
            
            if (!$result) {
                throw new Exception("Gagal menyimpan data anggota");
            }
            
            $id_anggota = pg_fetch_assoc($result)['id_anggota'];
            
            // Insert Pendidikan
            if (isset($_POST['pendidikan'])) {
                foreach ($_POST['pendidikan'] as $idx => $pend) {
                    if (!empty($pend['jenjang']) && !empty($pend['institusi'])) {
                        $sql_pend = "INSERT INTO anggota_pendidikan 
                            (id_anggota, jenjang, institusi, jurusan, tahun_mulai, tahun_selesai, urutan) 
                            VALUES ($1, $2, $3, $4, $5, $6, $7)";
                        pg_query_params($conn, $sql_pend, [
                            $id_anggota,
                            $pend['jenjang'],
                            $pend['institusi'],
                            $pend['jurusan'] ?? null,
                            $pend['tahun_mulai'] ?? null,
                            $pend['tahun_selesai'] ?? null,
                            $idx
                        ]);
                    }
                }
            }
            
            // Insert Sertifikasi
            if (isset($_POST['sertifikasi'])) {
                foreach ($_POST['sertifikasi'] as $idx => $sert) {
                    if (!empty($sert['nama_sertifikasi'])) {
                        $sql_sert = "INSERT INTO anggota_sertifikasi 
                            (id_anggota, nama_sertifikasi, penerbit, tahun, urutan) 
                            VALUES ($1, $2, $3, $4, $5)";
                        pg_query_params($conn, $sql_sert, [
                            $id_anggota,
                            $sert['nama_sertifikasi'],
                            $sert['penerbit'] ?? null,
                            $sert['tahun'] ?? null,
                            $idx
                        ]);
                    }
                }
            }
            
            // Insert Mata Kuliah Ganjil
            if (isset($_POST['matakuliah_ganjil'])) {
                foreach ($_POST['matakuliah_ganjil'] as $idx => $mk) {
                    if (!empty($mk)) {
                        $sql_mk = "INSERT INTO anggota_matakuliah 
                            (id_anggota, nama_matakuliah, semester, urutan) 
                            VALUES ($1, $2, 'ganjil', $3)";
                        pg_query_params($conn, $sql_mk, [$id_anggota, $mk, $idx]);
                    }
                }
            }
            
            // Insert Mata Kuliah Genap
            if (isset($_POST['matakuliah_genap'])) {
                foreach ($_POST['matakuliah_genap'] as $idx => $mk) {
                    if (!empty($mk)) {
                        $sql_mk = "INSERT INTO anggota_matakuliah 
                            (id_anggota, nama_matakuliah, semester, urutan) 
                            VALUES ($1, $2, 'genap', $3)";
                        pg_query_params($conn, $sql_mk, [$id_anggota, $mk, $idx]);
                    }
                }
            }
            
            pg_query($conn, "COMMIT");

            // === LOG & FLASH BERDASARKAN PERAN ===
            if (isAdmin()) {
                $keterangan_log = 'Admin menambahkan anggota: ' . $nama . ' (ID ' . $id_anggota . ')';
                log_aktivitas($conn, 'create', 'anggota_lab', $id_anggota, $keterangan_log);
                setFlashMessage('Anggota berhasil ditambahkan.', 'success');
            } else {
                $keterangan_log = 'Operator mengajukan anggota baru: ' . $nama . ' (ID ' . $id_anggota . ')';
                log_aktivitas($conn, 'request_create', 'anggota_lab', $id_anggota, $keterangan_log);
                setFlashMessage('Data anggota diajukan dan menunggu persetujuan admin.', 'warning');
            }

            header('Location: ' . getAdminUrl('anggota/index.php'));
            exit;

        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $errors[] = $e->getMessage();
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    }
}

$form_errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-person-plus me-2"></i>Tambah Anggota</h1>
            <p class="text-muted mb-0">Tambah anggota peneliti baru</p>
        </div>
        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<?php if (!empty($form_errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <h5><i class="bi bi-exclamation-triangle me-2"></i>Terjadi Kesalahan</h5>
    <ul class="mb-0">
        <?php foreach ($form_errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="formAnggota">
    <div class="row">
        <div class="col-lg-8">
            <!-- Data Utama -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Data Utama</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" maxlength="255"
                               value="<?php echo htmlspecialchars($form_data['nama'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nip" class="form-label">NIP</label>
                            <input type="text" class="form-control" id="nip" name="nip"
                                   value="<?php echo htmlspecialchars($form_data['nip'] ?? ''); ?>" maxlength="50">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nidn" class="form-label">NIDN</label>
                            <input type="text" class="form-control" id="nidn" name="nidn"
                                   value="<?php echo htmlspecialchars($form_data['nidn'] ?? ''); ?>" maxlength="20">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program_studi" class="form-label">Program Studi</label>
                        <input type="text" class="form-control" id="program_studi" name="program_studi"
                               value="<?php echo htmlspecialchars($form_data['program_studi'] ?? ''); ?>" maxlength="150">
                    </div>
                    
                    <div class="mb-3">
                        <label for="jabatan" class="form-label">Jabatan</label>
                        <input type="text" class="form-control" id="jabatan" name="jabatan"
                               value="<?php echo htmlspecialchars($form_data['jabatan'] ?? ''); ?>" maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="peran_lab" class="form-label">Peran Lab</label>
                        <input type="text" class="form-control" id="peran_lab" name="peran_lab"
                               value="<?php echo htmlspecialchars($form_data['peran_lab'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat_kantor" class="form-label">Alamat Kantor</label>
                        <textarea class="form-control" id="alamat_kantor" name="alamat_kantor" rows="2"><?php
                            echo htmlspecialchars($form_data['alamat_kantor'] ?? '');
                        ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Keahlian -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-star me-2"></i>Keahlian</h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="keahlian" name="keahlian" rows="3"><?php
                        echo htmlspecialchars($form_data['keahlian'] ?? '');
                    ?></textarea>
                    <div class="form-text">
                        Pisahkan dengan koma (,). Contoh: Software Engineering, Machine Learning, Database
                    </div>
                </div>
            </div>
            
            <!-- Social Media -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Social Media & Links</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="linkedin" class="form-label">LinkedIn URL</label>
                        <input type="text" class="form-control" id="linkedin" name="linkedin"
                               value="<?php echo htmlspecialchars($form_data['linkedin'] ?? ''); ?>"
                               placeholder="https://linkedin.com/in/...">
                    </div>
                    <div class="mb-3">
                        <label for="website" class="form-label">Website Pribadi</label>
                        <input type="text" class="form-control" id="website" name="website"
                               value="<?php echo htmlspecialchars($form_data['website'] ?? ''); ?>"
                               placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="google_scholar" class="form-label">Google Scholar URL</label>
                        <input type="text" class="form-control" id="google_scholar" name="google_scholar"
                               value="<?php echo htmlspecialchars($form_data['google_scholar'] ?? ''); ?>"
                               placeholder="https://scholar.google.com/...">
                    </div>
                    <div class="mb-3">
                        <label for="sinta" class="form-label">SINTA URL</label>
                        <input type="text" class="form-control" id="sinta" name="sinta"
                               value="<?php echo htmlspecialchars($form_data['sinta'] ?? ''); ?>"
                               placeholder="https://sinta.kemdikbud.go.id/...">
                    </div>
                </div>
            </div>
            
            <!-- Pendidikan -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-mortarboard me-2"></i>Pendidikan</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addPendidikan()">
                        <i class="bi bi-plus-circle me-1"></i>Tambah
                    </button>
                </div>
                <div class="card-body">
                    <div id="pendidikanContainer">
                        <div class="pendidikan-item mb-3 border p-3 rounded">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <input type="text" class="form-control" name="pendidikan[0][jenjang]" placeholder="Jenjang (S2, S1)" maxlength="50">
                                </div>
                                <div class="col-md-5 mb-2">
                                    <input type="text" class="form-control" name="pendidikan[0][institusi]" placeholder="Institusi" maxlength="200">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <input type="text" class="form-control" name="pendidikan[0][jurusan]" placeholder="Jurusan" maxlength="150">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="text" class="form-control" name="pendidikan[0][tahun_mulai]" placeholder="Tahun Mulai" maxlength="20">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="text" class="form-control" name="pendidikan[0][tahun_selesai]" placeholder="Tahun Selesai" maxlength="20">
                                </div>
                                <div class="col-md-6 mb-2 text-end">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removePendidikan(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sertifikasi -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-award me-2"></i>Sertifikasi</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addSertifikasi()">
                        <i class="bi bi-plus-circle me-1"></i>Tambah
                    </button>
                </div>
                <div class="card-body">
                    <div id="sertifikasiContainer">
                        <div class="sertifikasi-item mb-3 border p-3 rounded">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <input type="text" class="form-control" name="sertifikasi[0][nama_sertifikasi]"
                                           placeholder="Nama Sertifikasi" maxlength="200">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <input type="text" class="form-control" name="sertifikasi[0][penerbit]"
                                           placeholder="Penerbit" maxlength="150">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <input type="text" class="form-control" name="sertifikasi[0][tahun]"
                                           placeholder="Tahun" maxlength="20">
                                </div>
                                <div class="col-md-1 mb-2 text-end">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSertifikasi(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mata Kuliah -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-book me-2"></i>Mata Kuliah</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Semester Ganjil</strong>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addMatakuliah('ganjil')">
                                    <i class="bi bi-plus-circle me-1"></i>Tambah
                                </button>
                            </div>
                            <div id="matakuliahGanjilContainer">
                                <div class="mb-2">
                                    <input type="text" class="form-control" name="matakuliah_ganjil[0]"
                                           placeholder="Nama Mata Kuliah" maxlength="200">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Semester Genap</strong>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addMatakuliah('genap')">
                                    <i class="bi bi-plus-circle me-1"></i>Tambah
                                </button>
                            </div>
                            <div id="matakuliahGenapContainer">
                                <div class="mb-2">
                                    <input type="text" class="form-control" name="matakuliah_genap[0]"
                                           placeholder="Nama Mata Kuliah" maxlength="200">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Foto -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-image me-2"></i>Foto Profil</h5>
                </div>
                <div class="card-body">
                    <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                    <div class="form-text">Upload foto. Max 5MB</div>
                    <div id="fotoPreview" class="anggota-foto-preview-container mt-3" style="display:none">
                        <img src="" class="img-fluid rounded anggota-new-photo-preview">
                    </div>
                </div>
            </div>
            
            <!-- CV -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>CV / Resume</h5>
                </div>
                <div class="card-body">
                    <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx">
                    <div class="form-text">Format: PDF, DOC, DOCX. Max 5MB</div>
                </div>
            </div>
            
            <!-- Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-toggle-on me-2"></i>Status</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="aktif" name="aktif" checked>
                        <label class="form-check-label" for="aktif">Aktif (tampil di website)</label>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Simpan Anggota
                        </button>
                        <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('foto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5*1024*1024) {
            alert('Max 5MB');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('#fotoPreview img').src = e.target.result;
            document.getElementById('fotoPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

let pendidikanCount = 1;
let sertifikasiCount = 1;
let matakuliahGanjilCount = 1;
let matakuliahGenapCount = 1;

function addPendidikan() {
    const container = document.getElementById('pendidikanContainer');
    const html = `
        <div class="pendidikan-item mb-3 border p-3 rounded">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <input type="text" class="form-control" name="pendidikan[${pendidikanCount}][jenjang]"
                           placeholder="Jenjang" maxlength="50">
                </div>
                <div class="col-md-5 mb-2">
                    <input type="text" class="form-control" name="pendidikan[${pendidikanCount}][institusi]"
                           placeholder="Institusi" maxlength="200">
                </div>
                <div class="col-md-4 mb-2">
                    <input type="text" class="form-control" name="pendidikan[${pendidikanCount}][jurusan]"
                           placeholder="Jurusan" maxlength="150">
                </div>
                <div class="col-md-3 mb-2">
                    <input type="text" class="form-control" name="pendidikan[${pendidikanCount}][tahun_mulai]"
                           placeholder="Tahun Mulai" maxlength="20">
                </div>
                <div class="col-md-3 mb-2">
                    <input type="text" class="form-control" name="pendidikan[${pendidikanCount}][tahun_selesai]"
                           placeholder="Tahun Selesai" maxlength="20">
                </div>
                <div class="col-md-6 mb-2 text-end">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removePendidikan(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    pendidikanCount++;
}

function removePendidikan(btn) {
    btn.closest('.pendidikan-item').remove();
}

function addSertifikasi() {
    const container = document.getElementById('sertifikasiContainer');
    const html = `
        <div class="sertifikasi-item mb-3 border p-3 rounded">
            <div class="row">
                <div class="col-md-5 mb-2">
                    <input type="text" class="form-control" name="sertifikasi[${sertifikasiCount}][nama_sertifikasi]"
                           placeholder="Nama Sertifikasi" maxlength="200">
                </div>
                <div class="col-md-4 mb-2">
                    <input type="text" class="form-control" name="sertifikasi[${sertifikasiCount}][penerbit]"
                           placeholder="Penerbit" maxlength="150">
                </div>
                <div class="col-md-2 mb-2">
                    <input type="text" class="form-control" name="sertifikasi[${sertifikasiCount}][tahun]"
                           placeholder="Tahun" maxlength="20">
                </div>
                <div class="col-md-1 mb-2 text-end">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSertifikasi(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    sertifikasiCount++;
}

function removeSertifikasi(btn) {
    btn.closest('.sertifikasi-item').remove();
}

function addMatakuliah(semester) {
    const container = document.getElementById(
        semester === 'ganjil' ? 'matakuliahGanjilContainer' : 'matakuliahGenapContainer'
    );
    const count = semester === 'ganjil' ? matakuliahGanjilCount++ : matakuliahGenapCount++;
    const html = `
        <div class="mb-2 input-group">
            <input type="text" class="form-control" name="matakuliah_${semester}[${count}]"
                   placeholder="Nama Mata Kuliah" maxlength="200">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
