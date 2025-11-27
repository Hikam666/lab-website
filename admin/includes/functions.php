<?php
/**
 * @param string $input
 * @return string
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validasi format email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format ukuran file
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Ambil HTML badge status
 * @param string $status 'draft', 'diajukan', 'disetujui', 'ditolak', 'arsip'
 * @return string
 */
function getStatusBadge($status) {
    $badges = [
        'draft' => '<span class="badge bg-secondary">Draft</span>',
        'diajukan' => '<span class="badge bg-warning text-dark">Diajukan</span>',
        'disetujui' => '<span class="badge bg-success">Disetujui</span>',
        'ditolak' => '<span class="badge bg-danger">Ditolak</span>',
        'arsip' => '<span class="badge bg-dark">Arsip</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Ambil HTML badge peran
 * @param string $role 'admin', 'operator'
 * @return string
 */
function getRoleBadge($role) {
    $badges = [
        'admin' => '<span class="badge bg-primary">Admin</span>',
        'operator' => '<span class="badge bg-info text-dark">Operator</span>'
    ];
    
    return $badges[$role] ?? '<span class="badge bg-secondary">' . ucfirst($role) . '</span>';
}

/**
 * Ambil badge status aktif
 * @param bool $aktif
 * @return string
 */
function getActiveBadge($aktif) {
    return $aktif 
        ? '<span class="badge bg-success">Aktif</span>' 
        : '<span class="badge bg-secondary">Non-aktif</span>';
}

/**
 * Format timestamp ke tanggal yang mudah dibaca
 * @param string $timestamp
 * @param string $format
 * @return string
 */
function formatDateTime($timestamp, $format = 'd M Y, H:i') {
    if (empty($timestamp)) return '-';
    return date($format, strtotime($timestamp));
}

/**
 * Ambil waktu relatif (misal: "2 jam yang lalu")
 * @param string $timestamp
 * @return string
 */
function getRelativeTime($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam yang lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' hari yang lalu';
    } else {
        return date('d M Y', $time);
    }
}

/**
 * Buat (generate) password acak
 * @param int $length
 * @return string
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
}

/**
 * Upload file
 * @param array $file Array $_FILES
 * @param string $destination Path direktori tujuan
 * @param array $allowed_types Tipe mime yang diizinkan
 * @param int $max_size Ukuran file maksimal dalam bytes
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function uploadFile($file, $destination, $allowed_types = [], $max_size = 5242880) {
    // Cek apakah file terupload
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Upload file tidak valid.', 'filename' => null];
    }
    
    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error upload: ' . $file['error'], 'filename' => null];
    }
    
    // Cek ukuran file
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File terlalu besar. Maksimal ' . formatFileSize($max_size), 'filename' => null];
    }
    
    // Cek tipe file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!empty($allowed_types) && !in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diperbolehkan.', 'filename' => null];
    }
    
    // Buat nama file unik
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = rtrim($destination, '/') . '/' . $filename;
    
    // Buat direktori jika belum ada
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Pindahkan file yang diupload
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Gagal memindahkan file.', 'filename' => null];
    }
    
    return ['success' => true, 'message' => 'File berhasil diupload.', 'filename' => $filename];
}

/**
 * Hapus file
 * @param string $filepath
 * @return bool
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Paginasi array
 * @param array $items
 * @param int $page Halaman saat ini
 * @param int $per_page Item per halaman
 * @return array ['items' => array, 'total' => int, 'pages' => int, 'current' => int]
 */
function paginateArray($items, $page = 1, $per_page = 10) {
    $total = count($items);
    $pages = ceil($total / $per_page);
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $per_page;
    
    return [
        'items' => array_slice($items, $offset, $per_page),
        'total' => $total,
        'pages' => $pages,
        'current' => $page
    ];
}

/**
 * Buat HTML breadcrumb
 * @param array $items [['label' => 'Home', 'url' => '/'], ...]
 * @return string
 */
function generateBreadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $last_index = count($items) - 1;
    foreach ($items as $index => $item) {
        if ($index === $last_index) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['label']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Potong teks dengan ellipsis (titik-titik)
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}
?>