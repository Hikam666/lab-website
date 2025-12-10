<?php
if (!isset($active_page)) {
    $active_page = '';
}

// Get current user
$current_user = getCurrentUser();
?>

<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    
    <!-- Logo Lab -->
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
            <img src="<?php echo getAdminUrl('assets/img/logoLab-bg.png'); ?>" alt="Logo Lab Admin" class="img-fluid">
        </div>
        <div class="sidebar-logo-text">
            <h4>Lab Admin</h4>
            <p>Teknologi Data</p>
        </div>
    </div>
    
    <!-- Menu Navigasi -->
    <nav class="sidebar-menu">
        
        <!-- Section: Main -->
        <div class="sidebar-menu-section">
            <div class="sidebar-menu-title">Main</div>
            
            <!-- Dashboard -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'dashboard') ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>
        
        <!-- Section: Konten -->
        <div class="sidebar-menu-section">
            <div class="sidebar-menu-title">Konten</div>
            
            <!-- Anggota Peneliti -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('anggota/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'anggota') ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Anggota Peneliti</span>
                </a>
            </div>
            
            <!-- Fasilitas -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('fasilitas/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'fasilitas') ? 'active' : ''; ?>">
                    <i class="bi bi-building"></i>
                    <span>Fasilitas</span>
                </a>
            </div>
            
            <!-- Publikasi -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('publikasi/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'publikasi') ? 'active' : ''; ?>">
                    <i class="bi bi-file-text"></i>
                    <span>Publikasi</span>
                </a>
            </div>
            
            <!-- Berita & Agenda -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('berita/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'berita') ? 'active' : ''; ?>">
                    <i class="bi bi-newspaper"></i>
                    <span>Berita & Pengumuman</span>
                </a>
            </div>
            
            <!-- Galeri -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('galeri/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'galeri') ? 'active' : ''; ?>">
                    <i class="bi bi-images"></i>
                    <span>Galeri</span>
                </a>
            </div>
        </div>
        
        <!-- Section: Kelola -->
        <div class="sidebar-menu-section">
            <div class="sidebar-menu-title">Kelola</div>
            
            <!-- Pesan & Permintaan -->
            <?php
            // Hitung pesan belum dibaca
            $conn_sidebar = getDBConnection();
            $unread_count = 0;
            if ($conn_sidebar) {
                $sql_unread = "SELECT COUNT(*) as total FROM pesan_kontak WHERE status = 'baru'";
                $result_unread = pg_query($conn_sidebar, $sql_unread);
                if ($result_unread) {
                    $unread_count = pg_fetch_assoc($result_unread)['total'];
                }
            }
            ?>
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('pesan/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'pesan') ? 'active' : ''; ?>">
                    <i class="bi bi-envelope"></i>
                    <span>Pesan</span>
                    <?php if ($unread_count > 0): ?>
                    <span class="sidebar-menu-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Persetujuan (Hanya untuk Admin) -->
            <?php if (isAdmin()): ?>
            <?php
            // Hitung item menunggu persetujuan
            $pending_count = 0;
            if ($conn_sidebar) {
                $sql_pending = "SELECT 
                    (SELECT COUNT(*) FROM berita WHERE status = 'diajukan') +
                    (SELECT COUNT(*) FROM galeri_album WHERE status = 'diajukan') +
                    (SELECT COUNT(*) FROM publikasi WHERE status = 'diajukan') +
                    (SELECT COUNT(*) FROM fasilitas WHERE status = 'diajukan') as total";
                $result_pending = pg_query($conn_sidebar, $sql_pending);
                if ($result_pending) {
                    $pending_count = pg_fetch_assoc($result_pending)['total'];
                }
            }
            ?>
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('persetujuan/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'persetujuan') ? 'active' : ''; ?>">
                    <i class="bi bi-check-square"></i>
                    <span>Persetujuan</span>
                    <?php if ($pending_count > 0): ?>
                    <span class="sidebar-menu-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Pengguna (Hanya untuk Admin) -->
            <?php if (isAdmin()): ?>
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('pengguna/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'pengguna') ? 'active' : ''; ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>Pengguna</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Media Library -->
            <div class="sidebar-menu-item">
                <a href="<?php echo getAdminUrl('media/index.php'); ?>" 
                   class="sidebar-menu-link <?php echo ($active_page == 'media') ? 'active' : ''; ?>">
                    <i class="bi bi-folder"></i>
                    <span>Media Library</span>
                </a>
            </div>
        </div>
        
    </nav>
    
</aside>

<!-- Backdrop untuk mobile (klik di luar sidebar untuk tutup) -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<?php
// Close koneksi sidebar jika ada
if (isset($conn_sidebar) && $conn_sidebar) {
    pg_close($conn_sidebar);
}
?>