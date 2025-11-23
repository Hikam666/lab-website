<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'kontak';
$page_title = 'Kontak & Kerja Sama';
$page_keywords = 'kontak, kerja sama, kolaborasi, laboratorium';
$page_description = 'Hubungi kami untuk kerja sama dan kolaborasi';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Mengambil koneksi database
    $conn = getDBConnection();
    
    // Get form data
    $nama = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['mail']) ? trim($_POST['mail']) : '';
    $tujuan = isset($_POST['service']) ? trim($_POST['service']) : '';
    $pesan = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Validate
    if (empty($nama) || empty($email) || empty($tujuan) || empty($pesan)) {
        $error_message = 'Mohon lengkapi semua field yang wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid.';
    } else {
        // Insert to database
        $sql = "INSERT INTO pesan_kontak 
                (nama_pengirim, email_pengirim, subjek, isi, tujuan, status, diterima_pada) 
                VALUES ($1, $2, $3, $4, $5, 'baru', NOW())";
        
        $result = pg_query_params($conn, $sql, array(
            $nama,
            $email,
            $tujuan,
            $pesan,
            $tujuan
        ));
        
        if ($result) {
            $success_message = 'Pesan Anda berhasil dikirim. Kami akan segera menghubungi Anda.';
            // Clear form
            $_POST = array();
        } else {
            $error_message = 'Terjadi kesalahan. Mohon coba lagi.';
        }
    }
    
    if (isset($conn)) {
        pg_close($conn);
    }
}

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Kontak & Kerja Sama</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kontak & Kerja Sama</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Kontak & Kerja Sama Section Start -->
    <div class="container-fluid py-5" id="kontak">
        <div class="container">
            <div class="row g-5">

                <!-- Left Column: Info -->
                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.1s">
                    <h1 class="display-6 mb-4">Kontak & Kerja Sama</h1>
                    <p>
                        Ingin mengajukan kolaborasi riset, undangan pelatihan, magang industri,
                        atau membutuhkan dukungan teknis lab? Hubungi kami.
                    </p>

                    <div class="d-flex align-items-start wow fadeIn mb-4" data-wow-delay="0.3s">
                        <div class="icon-box-primary">
                            <i class="bi bi-geo-alt text-dark fs-1"></i>
                        </div>
                        <div class="ms-3">
                            <h5>Alamat Laboratorium</h5>
                            <span>Gedung Jurusan Teknologi Informasi - Lantai 8 Barat<br>
                                Politeknik Negeri Malang<br>
                                Jl. Soekarno Hatta No.9, Malang, Jawa Timur 65141</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex align-items-start wow fadeIn mb-4" data-wow-delay="0.4s">
                        <div class="icon-box-primary">
                            <i class="bi bi-clock text-dark fs-1"></i>
                        </div>
                        <div class="ms-3">
                            <h5>Jam Operasional</h5>
                            <span>Senin - Jumat: 08.00 - 16.00 WIB</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex align-items-start wow fadeIn" data-wow-delay="0.5s">
                        <div class="icon-box-primary">
                            <i class="bi bi-envelope text-dark fs-1"></i>
                        </div>
                        <div class="ms-3">
                            <h5>Email</h5>
                            <span>lab.teknologidata@polinema.ac.id</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Form -->
                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.5s">
                    <h2 class="mb-4">Form Pertanyaan / Permintaan Kerja Sama</h2>
                    
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name"
                                           placeholder="Nama Anda"
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           required>
                                    <label for="name">Nama Anda *</label>
                                </div>
                            </div>
                            
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <input type="email" 
                                           class="form-control" 
                                           id="mail" 
                                           name="mail"
                                           placeholder="Email Institusi"
                                           value="<?php echo isset($_POST['mail']) ? htmlspecialchars($_POST['mail']) : ''; ?>"
                                           required>
                                    <label for="mail">Email Institusi *</label>
                                </div>
                            </div>
                            
                            <div class="col-sm-12">
                                <div class="form-floating">
                                    <select class="form-select" id="service" name="service" required>
                                        <option value="">-- Pilih Keperluan --</option>
                                        <option value="Kolaborasi Riset" <?php echo (isset($_POST['service']) && $_POST['service'] == 'Kolaborasi Riset') ? 'selected' : ''; ?>>Kolaborasi Riset</option>
                                        <option value="Magang / Kunjungan Industri" <?php echo (isset($_POST['service']) && $_POST['service'] == 'Magang / Kunjungan Industri') ? 'selected' : ''; ?>>Magang / Kunjungan Industri</option>
                                        <option value="Permintaan Narasumber / Pelatihan" <?php echo (isset($_POST['service']) && $_POST['service'] == 'Permintaan Narasumber / Pelatihan') ? 'selected' : ''; ?>>Permintaan Narasumber / Pelatihan</option>
                                        <option value="Konsultasi Teknis" <?php echo (isset($_POST['service']) && $_POST['service'] == 'Konsultasi Teknis') ? 'selected' : ''; ?>>Konsultasi Teknis</option>
                                        <option value="Lainnya" <?php echo (isset($_POST['service']) && $_POST['service'] == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                    </select>
                                    <label for="service">Keperluan *</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" 
                                              placeholder="Tuliskan pesan Anda" 
                                              id="message"
                                              name="message"
                                              style="height: 130px"
                                              required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                    <label for="message">Pesan *</label>
                                </div>
                            </div>
                            
                            <div class="col-12 text-center">
                                <button class="btn btn-primary w-100 py-3" type="submit">
                                    <i class="bi bi-send me-2"></i>Kirim Pesan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
    <!-- Kontak End -->

<?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
