<?php
/**
 * AUTH.PHP
 * ========
 * Sistem autentikasi dan manajemen sesi untuk CMS
 * * Fitur:
 * - Login/Logout
 * - Manajemen sesi
 * - Pengecekan peran (admin/operator)
 * - Pengecekan izin (permission)
 * - Pembuatan token CSRF
 */

// Mulai sesi jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat konfigurasi
require_once __DIR__ . '/../../includes/config.php';

/**
 * Cek apakah pengguna sudah login
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Ambil data pengguna yang sedang login saat ini
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'nama' => $_SESSION['admin_nama'] ?? '',
        'email' => $_SESSION['admin_email'] ?? '',
        'peran' => $_SESSION['admin_peran'] ?? 'operator',
        'aktif' => $_SESSION['admin_aktif'] ?? false
    ];
}

/**
 * Wajibkan pengguna untuk login, alihkan ke login jika belum
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . getAdminUrl('login.php'));
        exit;
    }
}

/**
 * Cek apakah pengguna saat ini memiliki peran tertentu
 * @param string $role 'admin' atau 'operator'
 * @return bool
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['peran'] === $role;
}

/**
 * Wajibkan pengguna memiliki peran tertentu, alihkan jika tidak sesuai
 * @param string $role
 * @param string $message
 * @return void
 */
function requireRole($role, $message = 'Anda tidak memiliki akses ke halaman ini.') {
    requireLogin();
    
    if (!hasRole($role)) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . getAdminUrl('index.php'));
        exit;
    }
}

/**
 * Cek apakah pengguna adalah admin
 * @return bool
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Cek apakah pengguna adalah operator
 * @return bool
 */
function isOperator() {
    return hasRole('operator');
}

/**
 * Cek apakah pengguna memiliki izin untuk tindakan tertentu
 * @param string $action 'create', 'edit', 'delete', 'approve'
 * @param string $module 'berita', 'galeri', 'publikasi', dll.
 * @return bool
 */
function hasPermission($action, $module = '') {
    // Admin memiliki semua izin
    if (isAdmin()) {
        return true;
    }
    
    // Izin operator
    if (isOperator()) {
        // Operator bisa membuat dan mengedit, tapi butuh persetujuan
        if (in_array($action, ['create', 'edit', 'view'])) {
            return true;
        }
        
        // Operator tidak bisa menghapus atau menyetujui
        if (in_array($action, ['delete', 'approve', 'reject'])) {
            return false;
        }
    }
    
    return false;
}

/**
 * Login pengguna
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser($email, $password) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return [
            'success' => false,
            'message' => 'Koneksi database gagal.',
            'user' => null
        ];
    }
    
    // Ambil pengguna berdasarkan email
    $sql = "SELECT id_pengguna, nama_lengkap, email, password_hash, peran, aktif 
            FROM pengguna 
            WHERE email = $1 AND aktif = TRUE";
    
    $result = pg_query_params($conn, $sql, array($email));
    
    if (!$result || pg_num_rows($result) === 0) {
        pg_close($conn);
        return [
            'success' => false,
            'message' => 'Email atau password salah.',
            'user' => null
        ];
    }
    
    $user = pg_fetch_assoc($result);
    
    // Verifikasi password
    if (!password_verify($password, $user['password_hash'])) {
        pg_close($conn);
        return [
            'success' => false,
            'message' => 'Email atau password salah.',
            'user' => null
        ];
    }
    
    // Set sesi
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $user['id_pengguna'];
    $_SESSION['admin_nama'] = $user['nama_lengkap'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_peran'] = $user['peran'];
    $_SESSION['admin_aktif'] = $user['aktif'];
    $_SESSION['admin_login_time'] = time();
    
    // Regenerasi ID sesi untuk keamanan
    session_regenerate_id(true);
    
    pg_close($conn);
    
    return [
        'success' => true,
        'message' => 'Login berhasil!',
        'user' => $user
    ];
}

/**
 * Logout pengguna
 * @return void
 */
function logoutUser() {
    // Hapus semua variabel sesi
    $_SESSION = array();
    
    // Hancurkan cookie sesi
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Hancurkan sesi
    session_destroy();
}

/**
 * Buat token CSRF
 * @return string
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi token CSRF
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Ambil HTML input token CSRF
 * @return string
 */
function csrfTokenInput() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Ambil URL admin
 * @param string $path
 * @return string
 */
function getAdminUrl($path = '') {
    // Ambil URL dasar dari SITE_URL
    $base = rtrim(SITE_URL, '/');
    return $base . '/admin/' . ltrim($path, '/');
}

/**
 * Alihkan ke halaman admin
 * @param string $path
 * @return void
 */
function redirectAdmin($path = '') {
    header('Location: ' . getAdminUrl($path));
    exit;
}

/**
 * Set pesan flash (pesan sementara)
 * @param string $message
 * @param string $type 'success', 'error', 'warning', 'info'
 * @return void
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Ambil dan bersihkan pesan flash
 * @return array|null ['message' => string, 'type' => string]
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $flash;
    }
    
    return null;
}

/**
 * Cek apakah sesi masih valid (belum kadaluarsa)
 * @param int $timeout Batas waktu sesi dalam detik (default: 2 jam)
 * @return bool
 */
function isSessionValid($timeout = 7200) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (isset($_SESSION['admin_login_time'])) {
        $elapsed = time() - $_SESSION['admin_login_time'];
        
        if ($elapsed > $timeout) {
            logoutUser();
            return false;
        }
        
        // Perbarui waktu aktivitas terakhir
        $_SESSION['admin_login_time'] = time();
    }
    
    return true;
}
?>