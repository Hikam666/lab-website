    <!-- Footer Start -->
    <div class="container-fluid footer-main-section position-relative bg-dark text-white-50 py-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container">
            <div class="row g-5 py-5">

                <div class="col-lg-6 pe-lg-5">
                    <a href="<?php echo SITE_URL; ?>/public/index.php" class="navbar-brand">
                        <h1 class="h1 text-primary mb-0" style="font-size: 2.0rem;">
                            Laboratorium <span class="text-white">Teknologi Data</span>
                        </h1>
                    </a>
                    <p class="fs-5 mb-4">
                        Laboratorium riset dan pengembangan di bawah Jurusan Teknologi Informasi,
                        Politeknik Negeri Malang. Fokus pada penerapan nyata teknologi data, jaringan,
                        keamanan, dan rekayasa perangkat lunak.
                    </p>
                    <p><i class="fa fa-map-marker-alt me-2"></i>Gedung JTI, Politeknik Negeri Malang</p>
                    <p><i class="fa fa-phone-alt me-2"></i>+62 812-0000-0000</p>
                    <p><i class="fa fa-envelope me-2"></i>lab.teknologidata@polinema.ac.id</p>

                    <div class="d-flex mt-4">
                        <a class="btn btn-lg-square btn-primary me-2" href="#"><i class="fab fa-instagram"></i></a>
                        <a class="btn btn-lg-square btn-primary me-2" href="#"><i class="fab fa-youtube"></i></a>
                        <a class="btn btn-lg-square btn-primary me-2" href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="col-lg-6 ps-lg-5">
                    <div class="row g-5">

                        <div class="col-sm-6">
                            <h4 class="text-light mb-4">Navigasi Cepat</h4>
                            <a class="btn btn-link" href="<?php echo SITE_URL; ?>/public/profil.php">Profil Lab</a>
                            <a class="btn btn-link" href="<?php echo SITE_URL; ?>/public/fasilitas.php">Fasilitas</a>
                            <a class="btn btn-link" href="<?php echo SITE_URL; ?>/public/publikasi.php">Publikasi & Jurnal</a>
                            <a class="btn btn-link" href="<?php echo SITE_URL; ?>/public/berita.php">Berita & Pengumuman</a>
                            <a class="btn btn-link" href="<?php echo SITE_URL; ?>/public/galeri.php">Galeri Kegiatan</a>
                            <a class="btn btn-link" href="<?php echo SITE_URL; ?>/public/kontak.php">Kontak & Kerja Sama</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- Footer End -->

    <!-- Copyright Start -->
    <div class="container-fluid footer-copyright-bar bg-dark text-white-50 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <a href="#">Laboratorium Teknologi Data</a>. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Copyright End -->

    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square rounded-circle back-to-top"><i class="bi bi-arrow-up"></i></a>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>


    <!-- Template Javascript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <?php if (isset($extra_js)): ?>
        <?php foreach ($extra_js as $js_file): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
