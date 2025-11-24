<?php
/**
 * LOGOUT.PHP
 * ==========
 * Handler untuk logout admin
 */

// Load dependencies
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectAdmin('login.php');
}

// Get user name before logout
$user = getCurrentUser();
$userName = $user['nama'] ?? 'User';

// Logout user
logoutUser();

// Start new session for flash message
session_start();

// Set success message
setFlashMessage('Anda telah berhasil logout. Sampai jumpa, ' . $userName . '!', 'success');

// Redirect to login page
redirectAdmin('login.php');
?>