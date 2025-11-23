<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'profil';
$page_title = 'Profil Laboratorium';
$page_keywords = 'profil, visi misi, anggota, tim, laboratorium';
$page_description = 'Profil, visi misi, dan tim Laboratorium Teknologi Data';
$extra_css = ['profil.css'];

// Mengambil koneksi database
$conn = getDBConnection();

// Get team members
$sql = "SELECT 
            a.id_anggota,
            a.nama,
            a.slug,
            a.email,
            a.peran_lab,
            a.bio_html,
            a.urutan,
            m.lokasi_file as foto,
            m.keterangan_alt as foto_alt
        FROM anggota_lab a
        LEFT JOIN media m ON a.id_foto = m.id_media
        WHERE a.aktif = TRUE
        ORDER BY a.urutan ASC, a.nama ASC";

$result = pg_query($conn, $sql);

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header Start -->
    <div class="container-fluid page-header-banner py-5 mb-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center py-5">
            <h1 class="display-3 text-white mb-4 animated slideInDown">Profil Laboratorium</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Profil</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->


    <!-- Profil Lab Section Start -->
    <div class="container-fluid py-5" id="profil-lab">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.1s">
                    <div class="profile-page-img-container">
                        <img src="<?php echo SITE_URL; ?>/assets/img/about-1.jpg" alt="Lab Teknologi Data" class="img-fluid rounded profile-page-main-image">
                    </div>
                </div>
                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.5s">
                    <div class="h-100">
                        <h1 class="display-6 mb-4">Laboratorium Teknologi Data</h1>
                        <p class="text-muted mb-4">
                            Unit penunjang akademik di Jurusan Teknologi Informasi Politeknik Negeri Malang yang berfokus pada kegiatan pembelajaran, penelitian, serta pengembangan keilmuan di bidang teknologi berbasis data.
                        </p>
                        <p class="mb-4">
                            Laboratorium ini menyediakan fasilitas praktikum dan riset yang mendukung penguasaan pengetahuan serta keterampilan mahasiswa dalam pengolahan data, analisis big data, kecerdasan buatan, dan machine learning. Selain sebagai sarana praktikum, laboratorium juga berperan sebagai pusat penelitian dan pengembangan bagi dosen maupun mahasiswa dalam menghasilkan inovasi teknologi yang dapat diterapkan di industri, pendidikan, dan pemerintahan.
                        </p>
                        <div class="row g-4 mb-4">
                            <div class="col-sm-6">
                                <div class="profile-page-feature-box">
                                    <i class="bi bi-award text-primary mb-3"></i>
                                    <h5>Penelitian Berkualitas</h5>
                                    <p class="mb-0">Riset yang berkontribusi pada kemajuan ilmu pengetahuan</p>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="profile-page-feature-box">
                                    <i class="bi bi-people text-primary mb-3"></i>
                                    <h5>Kolaborasi Luas</h5>
                                    <p class="mb-0">Kerjasama dengan akademisi, industri, dan pemerintah</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Profil Lab Section End -->


    <!-- Visi Misi Section Start -->
    <div class="container-fluid py-5 bg-light" id="visi-misi">
        <div class="container">
            <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h1 class="display-6 mb-4">Visi & Misi</h1>
                <p class="mb-0">Komitmen kami dalam mengembangkan teknologi data untuk masa depan yang lebih baik</p>
            </div>

            <div class="row g-4">
                <!-- Visi -->
                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.1s">
                    <div class="profile-page-vision-card h-100">
                        <div class="profile-page-vision-icon">
                            <i class="bi bi-lightbulb"></i>
                        </div>
                        <h3 class="profile-page-vision-title">VISI</h3>
                        <p class="profile-page-vision-text">
                            Menjadi organisasi riset terkemuka dalam penelitian maupun pengembangan untuk mendorong inovasi teknologi serta keilmuan di bidang penyimpanan, pengolahan, dan rekayasa sistem data yang berkelanjutan.
                        </p>
                    </div>
                </div>

                <!-- Misi -->
                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.3s">
                    <div class="profile-page-mission-card h-100">
                        <div class="profile-page-mission-icon">
                            <i class="bi bi-flag"></i>
                        </div>
                        <h3 class="profile-page-mission-title">MISI</h3>
                        <ul class="profile-page-mission-list">
                            <li>Mendukung visi dan misi Jurusan Teknologi Informasi Polinema melalui penelitian dan pengembangan di bidang penyimpanan, pengolahan, serta rekayasa sistem data.</li>
                            <li>Melakukan penelitian berkualitas tinggi yang berkontribusi pada kemajuan ilmu pengetahuan dan teknologi di bidang data, selaras dengan agenda riset JTI Polinema.</li>
                            <li>Mengembangkan inovasi teknologi data yang dapat diterapkan dalam dunia industri, pendidikan, dan pemerintahan guna meningkatkan daya saing lulusan JTI Polinema.</li>
                            <li>Membangun infrastruktur dan sistem data yang skalabel dan efisien untuk mendukung kebutuhan analitik, kecerdasan buatan, dan Big Data.</li>
                            <li>Menjalin kolaborasi dengan akademisi, industri, dan pemerintah dalam pengembangan solusi teknologi data yang inovatif.</li>
                            <li>Meningkatkan kapasitas dan kompetensi SDM di lingkungan JTI Polinema melalui pelatihan, penelitian, seminar, dan publikasi ilmiah.</li>
                            <li>Menyediakan layanan dan rekomendasi berbasis riset bagi JTI Polinema serta mitra industri dan akademik.</li>
                            <li>Menjaga etika dan keamanan data dalam setiap penelitian dan pengembangan teknologi.</li>
                            <li>Mengembangkan praktik riset dan infrastruktur teknologi data yang berkelanjutan melalui penerapan prinsip efisiensi energi dan pengelolaan siklus hidup data yang ramah lingkungan.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Visi Misi Section End -->


    <!-- Team Section Start -->
    <div class="container-fluid py-5" id="anggota">
        <div class="container">
            <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h1 class="display-6 mb-4">Tim Laboratorium</h1>
                <p class="mb-0">Tim peneliti dan pengelola Laboratorium Teknologi Data</p>
            </div>

            <?php 
            if ($result && pg_num_rows($result) > 0):
                // Separate head and members
                $kepala_lab = null;
                $anggota_list = [];
                
                while ($row = pg_fetch_assoc($result)) {
                    if (stripos($row['peran_lab'], 'kepala') !== false) {
                        $kepala_lab = $row;
                    } else {
                        $anggota_list[] = $row;
                    }
                }
                
                // Display Kepala Lab if exists
                if ($kepala_lab):
                    $image_src = $kepala_lab['foto'] ? SITE_URL . '/uploads/' . $kepala_lab['foto'] : SITE_URL . '/assets/img/default-avatar.jpg';
            ?>
            <!-- Kepala Lab - Center Top -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.1s">
                    <div class="profile-page-team-card profile-page-team-head">
                        <div class="profile-page-team-img">
                            <img src="<?php echo $image_src; ?>" class="img-fluid" alt="Kepala Lab">
                            <div class="profile-page-team-social">
                                <a href=""><i class="fab fa-linkedin-in"></i></a>
                                <a href=""><i class="fas fa-envelope"></i></a>
                                <a href=""><i class="fab fa-google-scholar"></i></a>
                            </div>
                        </div>
                        <div class="profile-page-team-content">
                            <h5><?php echo htmlspecialchars($kepala_lab['nama']); ?></h5>
                            <span class="profile-page-team-position kepala-lab"><?php echo htmlspecialchars($kepala_lab['peran_lab']); ?></span>
                            <?php if ($kepala_lab['bio_html']): ?>
                            <p class="profile-page-team-expertise"><?php echo strip_tags($kepala_lab['bio_html']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Anggota Team -->
            <div class="row g-4">
                <?php 
                $delay = 0.2;
                foreach ($anggota_list as $anggota):
                    $image_src = $anggota['foto'] ? SITE_URL . '/uploads/' . $anggota['foto'] : SITE_URL . '/assets/img/default-avatar.jpg';
                ?>
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                    <div class="profile-page-team-card">
                        <div class="profile-page-team-img">
                            <img src="<?php echo $image_src; ?>" class="img-fluid" alt="<?php echo htmlspecialchars($anggota['nama']); ?>">
                            <div class="profile-page-team-social">
                                <a href=""><i class="fab fa-linkedin-in"></i></a>
                                <a href=""><i class="fas fa-envelope"></i></a>
                                <a href=""><i class="fab fa-google-scholar"></i></a>
                            </div>
                        </div>
                        <div class="profile-page-team-content">
                            <h5><?php echo htmlspecialchars($anggota['nama']); ?></h5>
                            <span class="profile-page-team-position"><?php echo htmlspecialchars($anggota['peran_lab']); ?></span>
                            <?php if ($anggota['bio_html']): ?>
                            <p class="profile-page-team-expertise"><?php echo strip_tags($anggota['bio_html']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php 
                    $delay += 0.1;
                    if ($delay > 0.5) $delay = 0.2;
                endforeach;
                ?>
            </div>

            <?php else: ?>
            <!-- No Data Message -->
            <div class="alert alert-info text-center" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                Belum ada data anggota tim yang tersedia.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Team Section End -->

<?php
// Close database connection
if ($conn) {
    pg_close($conn);
}

// Include footer
include __DIR__ . '/../includes/footer.php';
?>
