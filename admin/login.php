<?php
/**
 * LOGIN.PHP
 * =========
 * Halaman login untuk admin panel
 */

// Start session
session_start();

// Load dependencies
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectAdmin('index.php');
}

// Initialize variables
$error_message = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        $error_message = 'Email dan password harus diisi.';
    } elseif (!isValidEmail($email)) {
        $error_message = 'Format email tidak valid.';
    } else {
        // Attempt login
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // Check if there's a redirect URL
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            
            // Set success message
            setFlashMessage('Selamat datang, ' . $result['user']['nama_lengkap'] . '!', 'success');
            
            // Redirect to dashboard or intended page
            redirectAdmin($redirect);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Get any flash messages
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Panel Lab Teknologi Data</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-page">
    
    <div class="login-container">
        <div class="login-card">
            
            <!-- Logo & Header -->
            <div class="login-header text-center mb-4">
                <div class="login-logo mb-3">
                    <img src="assets/img/logo-login.png" alt="Logo Login" class="img-fluid" style="margin-top: 20px; max-height: 80px; width: auto;">
                </div>
                <h3 class="fw-bold mb-2">Admin Panel</h3>
                <p class="text-muted mb-0">Laboratorium Teknologi Data</p>
                <p class="text-muted small">JTI Politeknik Negeri Malang</p>
            </div>
            
            <!-- Flash Message -->
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="" class="login-form">
                
                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-1"></i> Email
                    </label>
                    <input type="email" 
                           class="form-control form-control-lg" 
                           id="email" 
                           name="email"
                           value="<?php echo htmlspecialchars($email); ?>"
                           required 
                           autofocus>
                </div>
                
                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i> Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control form-control-lg" 
                               id="password" 
                               name="password"
                               required>
                        <button class="btn btn-outline-secondary" 
                                type="button" 
                                id="togglePassword"
                                title="Tampilkan password">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Remember Me (Optional) -->
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Ingat saya
                        </label>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
                    </button>
                </div>
                
            </form>
            
            <!-- Footer Info -->
            <div class="login-footer text-center mt-4">
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Gunakan akun yang telah terdaftar
                </p>
                &copy; <?php echo date('Y'); ?> Laboratorium Teknologi Data JTI Polinema
            </div>
            
        </div>
        
 
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Login Script -->
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
                this.title = 'Sembunyikan password';
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                this.title = 'Tampilkan password';
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    
</body>
</html>