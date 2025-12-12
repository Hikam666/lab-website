<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="<?php echo isset($page_keywords) ? $page_keywords : 'laboratorium, teknologi data, JTI, Polinema'; ?>" name="keywords">
    <meta content="<?php echo isset($page_description) ? $page_description : 'Laboratorium Teknologi Data JTI Politeknik Negeri Malang'; ?>" name="description">

    <!-- Favicon -->
    <link href="<?php echo SITE_URL; ?>/assets/img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&family=Red+Rose:wght@600;700&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css">

    <!-- Libraries Stylesheet -->
    <link href="<?php echo SITE_URL; ?>/assets/lib/animate/animate.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="<?php echo SITE_URL; ?>/assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <link href="<?php echo SITE_URL; ?>/assets/css/<?php echo $css_file; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
    </div>
    <!-- Spinner End -->

    <!-- Decorative Top Bar -->
    <div class="top-bar-gradient d-none d-md-block"></div>

    <!-- Header Container -->
    <div class="header-wrapper sticky-top bg-white">
        <div class="container-fluid">
            <div class="row align-items-center justify-content-center py-3 border-bottom">
                <!-- Left: Logos Polinema & JTI -->
                <div class="col-lg-2 col-md-3 col-4">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <img src="<?php echo SITE_URL; ?>/assets/img/logo-polinema.png" alt="Polinema" class="logo-img">
                        <img src="<?php echo SITE_URL; ?>/assets/img/logo-jti.png" alt="JTI" class="logo-img">
                    </div>
                </div>

                <!-- Center: Title -->
                <div class="col-lg-6 col-md-4 col-4 text-center px-3">
                    <h5 class="mb-0 fw-bold text-primary">LABORATORIUM TEKNOLOGI DATA</h5>
                    <small class="text-muted fw-semibold">Jurusan Teknologi Informasi</small>
                </div>

                <!-- Right: Lab Logo -->
                <div class="col-lg-2 col-md-3 col-4">
                    <div class="d-flex align-items-center justify-content-start">
                        <img src="<?php echo SITE_URL; ?>/assets/img/logoLab.png" alt="Lab Teknologi Data" class="logo-img">
                    </div>
                </div>
            </div>

            <!-- Navigation Bar -->
            <nav class="navbar navbar-expand-lg navbar-light px-0">
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" 
                        data-bs-target="#navMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navMenu">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/public/index.php" 
                               class="nav-link px-3 <?php echo ($active_page == 'home') ? 'active' : ''; ?>">
                                Beranda
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle px-3 <?php echo ($active_page == 'profil') ? 'active' : ''; ?>" 
                               data-bs-toggle="dropdown">
                                Profil Lab
                            </a>
                            <ul class="dropdown-menu shadow-sm">
                                <li><a href="<?php echo SITE_URL; ?>/public/profil.php#profil-lab" class="dropdown-item">Profil Lab</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/public/profil.php#visi-misi" class="dropdown-item">Visi & Misi</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/public/profil.php#anggota" class="dropdown-item">Anggota Tim</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/public/fasilitas.php" 
                               class="nav-link px-3 <?php echo ($active_page == 'fasilitas') ? 'active' : ''; ?>">
                                Fasilitas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/public/publikasi.php" 
                               class="nav-link px-3 <?php echo ($active_page == 'publikasi') ? 'active' : ''; ?>">
                                Publikasi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/public/berita.php" 
                               class="nav-link px-3 <?php echo ($active_page == 'berita') ? 'active' : ''; ?>">
                                Berita & Pengumuman
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/public/galeri.php" 
                               class="nav-link px-3 <?php echo ($active_page == 'galeri') ? 'active' : ''; ?>">
                                Galeri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/public/kontak.php" 
                               class="nav-link px-3 <?php echo ($active_page == 'kontak') ? 'active' : ''; ?>">
                                Kontak & Kerja Sama
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>
    </div>
