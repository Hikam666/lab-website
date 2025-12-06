<?php
if (function_exists('isLoggedIn') && !isLoggedIn()) {
    redirectAdmin('login.php');
}

// Get current user data
$user = function_exists('getCurrentUser') ? getCurrentUser() : null;

// Set page title default jika belum diset di halaman induk
if (!isset($page_title)) {
    $page_title = 'Admin Panel';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?php echo htmlspecialchars($page_title); ?> - Lab Admin</title>
    
    <link rel="icon" type="image/x-icon" href="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/assets/img/favicon.ico">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="<?php echo getAdminUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAdminUrl('assets/css/sidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAdminUrl('assets/css/header.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAdminUrl('assets/css/footer.css'); ?>">
    
    <?php
    // 3. Extra CSS per halaman (misal: dashboard.css)
    if (isset($extra_css) && is_array($extra_css)) {
        foreach ($extra_css as $css) {
            echo '<link rel="stylesheet" href="' . getAdminUrl('assets/css/' . $css) . '">';
        }
    }
    ?>
</head>
<body>

<div class="admin-wrapper">
    
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <header class="admin-header">
        
        <div class="header-left">
            <button class="header-toggle" id="sidebarToggle" title="Toggle Sidebar">
                <i class="bi bi-list"></i>
            </button>
        </div>
        
        <div class="header-right">
            
            <?php
            // Koneksi terisolasi untuk header agar tidak mengganggu koneksi halaman utama
            $notif_count = 0;
            $conn_header = getDBConnection(); 
            
            if ($conn_header) {
                // Pastikan tabel pesan_kontak ada, jika error query dilewati
                $sql_notif = "SELECT COUNT(*) as total FROM pesan_kontak WHERE status = 'baru'";
                $result_notif = @pg_query($conn_header, $sql_notif);
                
                if ($result_notif) {
                    $notif_count = pg_fetch_assoc($result_notif)['total'];
                }
                // Tutup koneksi header
                pg_close($conn_header);
            }
            ?>
            
            <div class="dropdown">
                <div class="header-user" 
                     id="userDropdown" 
                     data-bs-toggle="dropdown" 
                     aria-expanded="false"
                     role="button">
                    <div class="header-user-avatar">
                        <?php 
                        // Ambil inisial nama depan
                        $initial = isset($user['nama_lengkap']) ? strtoupper(substr($user['nama_lengkap'], 0, 1)) : 'U';
                        echo $initial; 
                        ?>
                    </div>
                    <div class="header-user-info">
                        <div class="header-user-name">
                            <?php echo htmlspecialchars($user['nama_lengkap'] ?? 'User'); ?>
                        </div>
                        <div class="header-user-role">
                            <?php 
                            $role = $user['peran'] ?? 'guest';
                            echo ($role === 'admin') ? 'Administrator' : 'Operator'; 
                            ?>
                        </div>
                    </div>
                    <i class="bi bi-chevron-down ms-2 small text-muted"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li class="dropdown-header">
                        <strong><?php echo htmlspecialchars($user['nama_lengkap'] ?? 'User'); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?php echo getAdminUrl('profil.php'); ?>">
                            <i class="bi bi-gear me-2"></i> Profil
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?php echo getAdminUrl('logout.php'); ?>">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
        </div>
        
    </header>
    
    <main class="admin-content">
        
        <?php
        // Tampilkan flash message global jika ada
        if (function_exists('getFlashMessage')) {
            $flash = getFlashMessage();
            if ($flash):
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php 
            endif; 
        }
        ?>