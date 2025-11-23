<?php
// Memuat konfigurasi
require_once __DIR__ . '/../includes/config.php';

// Page settings
$active_page = 'home';
$page_title = 'Beranda';
$page_keywords = 'laboratorium, teknologi data, JTI, Polinema, riset, publikasi';
$page_description = 'Laboratorium Teknologi Data JTI Politeknik Negeri Malang - Riset terapan dan pengembangan solusi berbasis data';

// Mengambil koneksi database
$conn = getDBConnection();

// Mengambil statistik
$sql_stats_anggota = "SELECT COUNT(*) as total FROM anggota_lab WHERE aktif = TRUE";
$result_stats_anggota = pg_query($conn, $sql_stats_anggota);
$total_anggota = pg_fetch_assoc($result_stats_anggota)['total'];

$sql_stats_publikasi = "SELECT COUNT(*) as total FROM publikasi WHERE status = 'disetujui'";
$result_stats_publikasi = pg_query($conn, $sql_stats_publikasi);
$total_publikasi = pg_fetch_assoc($result_stats_publikasi)['total'];

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <!-- Bagian Carousel / Hero Mulai -->
    <div class="container-fluid hero-carousel-main px-0 mb-5">
        <div id="header-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
            <div class="carousel-inner">

                <!-- Slide 1: identitas lab -->
                <div class="carousel-item active">
                    <img class="w-100" src="<?php echo SITE_URL; ?>/assets/img/gedungTI1.jpg" alt="Image">
                    <div class="carousel-caption">
                        <div class="container">
                            <div class="row justify-content-start">
                                <div class="col-lg-7 text-start">
                                    <h1 class="display-1 text-white animated slideInRight mb-3">
                                        Laboratorium Teknologi Data - JTI Polinema
                                    </h1>
                                    <p class="mb-5 animated slideInRight">
                                        Riset terapan, pengujian teknologi, dan pengembangan solusi berbasis data & jaringan.
                                        Fokus kami: penelitian, publikasi, dan kolaborasi industri.
                                    </p>
                                    <a href="profil.php" class="btn btn-primary py-3 px-5 animated slideInRight">Lihat Profil Lab</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 2: fasilitas -->
                <div class="carousel-item">
                    <img class="w-100" src="<?php echo SITE_URL; ?>/assets/img/gedungTI2.jpg" alt="Image">
                    <div class="carousel-caption">
                        <div class="container">
                            <div class="row justify-content-end">
                                <div class="col-lg-7 text-end">
                                    <h1 class="display-1 text-white animated slideInLeft mb-3">Fasilitas & Infrastruktur Riset</h1>
                                    <p class="mb-5 animated slideInLeft">
                                        Workstation high-performance, jaringan internal terkontrol, ruang uji,
                                        dokumentasi alat lengkap untuk penelitian mahasiswa dan dosen.
                                    </p>
                                    <a href="fasilitas.php" class="btn btn-primary py-3 px-5 animated slideInLeft">Lihat Fasilitas</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#header-carousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#header-carousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
    <!-- Akhir Carousel -->


<!-- About (Profil singkat) Start -->
<div class="container-fluid py-5 section-profile-lab">
    <div class="container">
        <div class="row g-5 align-items-start">

            <div class="col-lg-6 wow fadeIn" data-wow-delay="0.1s">
                <div class="profile-photos-wrapper no-stats-card">
                    <!-- Grid foto 2x2 -->
                    <div class="profile-photos-grid">
                        <div class="profile-photo-item">
                            <img src="<?php echo SITE_URL; ?>/assets/img/about-1.jpg" alt="foto lab 1">
                        </div>
                        <div class="profile-photo-item">
                            <img src="<?php echo SITE_URL; ?>/assets/img/about-2.jpg" alt="foto lab 2">
                        </div>
                        <div class="profile-photo-item">
                            <img src="<?php echo SITE_URL; ?>/assets/img/about-3.jpg" alt="foto lab 3">
                        </div>
                        <div class="profile-photo-item">
                            <img src="<?php echo SITE_URL; ?>/assets/img/about-4.jpg" alt="foto lab 4">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom kanan: profil + bubbles -->
            <div class="col-lg-6 wow fadeIn" data-wow-delay="0.5s">
                <h2 class="profile-section-title mb-3">Profil Laboratorium</h2>

                <p class="profile-section-description mb-4">
                    Unit penunjang akademik di Jurusan Teknologi Informasi yang berfokus pada kegiatan
                    pembelajaran, penelitian, serta pengembangan keilmuan di bidang teknologi berbasis data.
                    Laboratorium ini menyediakan fasilitas praktikum dan riset yang mendukung penguasaan
                    pengetahuan serta keterampilan mahasiswa dalam pengolahan data, analisis big data,
                    kecerdasan buatan, dan machine learning. Selain sebagai sarana praktikum, laboratorium
                    juga berperan sebagai pusat penelitian dan pengembangan bagi dosen maupun mahasiswa.
                </p>

                <!-- Bubble statistik (3 item rata horizontal) -->
                <div class="profile-statistics-bubbles row gx-4 gy-4">

                    <div class="col-12 col-md-4 d-flex justify-content-center">
                        <div class="profile-statistic-bubble bubble-color-blue bubble-size-fixed">
                            <div class="profile-bubble-label">Anggota Peneliti</div>
                            <div class="profile-bubble-number"><?php echo $total_anggota; ?></div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4 d-flex justify-content-center">
                        <div class="profile-statistic-bubble bubble-color-gray bubble-size-fixed">
                            <div class="profile-bubble-label">Proyek Riset</div>
                            <div class="profile-bubble-number"><?php echo $total_publikasi; ?></div>
                        </div>
                    </div>

                </div>

                <!-- CTA -->
                <a class="btn btn-primary py-3 px-5 profile-button-cta mt-4" href="profil.php">
                    Lihat Visi, Misi & Struktur »
                </a>
            </div>

        </div>
    </div>
</div>
<!-- About End -->




    <!-- Riset & Publikasi / highlight -->
    <div class="container-fluid section-research-publications mt-5 wow fadeInUp" data-wow-delay="0.1s">
        <div class="container">
            <div class="row g-0">
                <div class="col-lg-6 pt-lg-5">
                    <div class="bg-white p-5 mt-lg-5">
                        <h1 class="display-6 mb-4 wow fadeIn" data-wow-delay="0.3s">Penelitian & Publikasi Ilmiah</h1>
                        <p class="mb-4 wow fadeIn" data-wow-delay="0.4s">
                            Laboratorium aktif mempublikasikan karya ilmiah pada jurnal nasional maupun internasional,
                            menghasilkan prototipe teknologi terapan, dan mendukung kompetisi inovasi.
                            Data publikasi dan HKI terdokumentasi secara terbuka.
                        </p>

                        <div class="row g-5 pt-2 mb-5">
                            <div class="col-sm-6 wow fadeIn" data-wow-delay="0.3s">
                                <div class="icon-box-primary mb-4">
                                    <i class="bi bi-journal-text text-dark"></i>
                                </div>
                                <h5 class="mb-3">Daftar Publikasi</h5>
                                <span>Artikel jurnal, prosiding konferensi, laporan teknis.</span>
                            </div>
                            <div class="col-sm-6 wow fadeIn" data-wow-delay="0.4s">
                                <div class="icon-box-primary mb-4">
                                    <i class="bi bi-shield-check text-dark"></i>
                                </div>
                                <h5 class="mb-3">HKI & Paten</h5>
                                <span>Hak cipta perangkat lunak, modul praktikum, metode pengujian.</span>
                            </div>
                        </div>

                        <a class="btn btn-primary py-3 px-5 wow fadeIn" data-wow-delay="0.5s" href="publikasi.php">
                            Lihat Semua Publikasi &raquo;
                        </a>
                    </div>
                </div>
            </div> <!-- row -->
        </div> <!-- container -->
    </div>
    <!-- Riset & Publikasi End -->


    <!-- Kontak & Kerja Sama / Form Start -->
    <div class="container-fluid py-5" id="kontak">
        <div class="container">
            <div class="row g-5">

                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.1s">
                    <h1 class="display-6 mb-4">Kontak & Kerja Sama</h1>
                    <p>
                        Ingin mengajukan kolaborasi riset, undangan pelatihan, magang industri,
                        atau membutuhkan dukungan teknis lab? Hubungi kami.
                    </p>

                    <div class="d-flex align-items-start wow fadeIn" data-wow-delay="0.3s">
                        <div class="icon-box-primary">
                            <i class="bi bi-geo-alt text-dark fs-1"></i>
                        </div>
                        <div class="ms-3">
                            <h5>Alamat Laboratorium</h5>
                            <span>Gedung Jurusan Teknologi Informasi — Lantai 8 Barat</span>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex align-items-start wow fadeIn" data-wow-delay="0.4s">
                        <div class="icon-box-primary">
                            <i class="bi bi-clock text-dark fs-1"></i>
                        </div>
                        <div class="ms-3">
                            <h5>Jam Operasional</h5>
                            <span>Senin—Jumat 08.00—16.00</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.5s">
                    <h2 class="mb-4">Form Pertanyaan / Permintaan Kerja Sama</h2>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="name" placeholder="Nama Anda">
                                <label for="name">Nama Anda</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="mail" placeholder="Email Institusi">
                                <label for="mail">Email Institusi</label>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-floating">
                                <select class="form-select" id="service">
                                    <option selected>Kolaborasi Riset</option>
                                    <option value="">Magang / Kunjungan Industri</option>
                                    <option value="">Permintaan Narasumber / Pelatihan</option>
                                    <option value="">Konsultasi Teknis</option>
                                </select>
                                <label for="service">Keperluan</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" placeholder="Tuliskan pesan Anda" id="message"
                                    style="height: 130px"></textarea>
                                <label for="message">Pesan</label>
                            </div>
                        </div>
                        <div class="col-12 text-center">
                            <button class="btn btn-primary w-100 py-3" type="submit">Kirim</button>
                        </div>
                    </div>
                </div>

            </div> <!-- row -->
        </div> <!-- container -->
    </div>
    <!-- Kontak End -->

<?php
// Close database connection
if ($conn) {
    pg_close($conn);
}

// Include footer
include __DIR__ . '/../includes/footer.php';
?>
