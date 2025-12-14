<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    redirectAdmin('login.php');
}

// Get user name before logout
$user = getCurrentUser();
$userName = $user['nama'] ?? 'User';

logoutUser();

session_start();

setFlashMessage('Anda telah berhasil logout. Sampai jumpa, ' . $userName . '!', 'success');

redirectAdmin('login.php');
?>