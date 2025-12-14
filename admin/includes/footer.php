<?php
?>

    </main>
    <!-- End Main Content -->
    
</div>
<!-- End Admin Wrapper -->

<!-- Footer -->
<footer class="admin-footer">
    <div class="admin-footer-content">
        <div class="admin-footer-left">
            &copy; <?php echo date('Y'); ?> 
            <strong><?php echo SITE_NAME; ?></strong>. 
            All rights reserved.
        </div>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo getAdminUrl('assets/js/admin.js'); ?>"></script>

<!-- Script untuk toggle sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    
    // Toggle sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            // Cek lebar layar
            if (window.innerWidth <= 768) {
                // Mobile: toggle show/hide
                sidebar.classList.toggle('show');
                sidebarBackdrop.classList.toggle('show');
            } else {
                // Desktop: toggle collapsed
                sidebar.classList.toggle('collapsed');
            }
        });
    }
    
    // Tutup sidebar saat klik backdrop (mobile)
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
        });
    }
    
    // Global search (bisa dikembangkan lebih lanjut)
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        globalSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value;
                if (query) {
                    // Redirect ke halaman search (bisa dibuat nanti)
                    console.log('Searching for:', query);
                    // window.location.href = 'search.php?q=' + encodeURIComponent(query);
                }
            }
        });
    }
});
</script>

<?php
// Extra JS jika ada
if (isset($extra_js) && is_array($extra_js)) {
    foreach ($extra_js as $js) {
        echo '<script src="' . getAdminUrl('assets/js/' . $js) . '"></script>';
    }
}
?>

</body>
</html>