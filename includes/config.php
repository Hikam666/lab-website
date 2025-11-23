<?php

define('BASE_PATH', realpath(__DIR__ . '/..')); 

// ---------- .env loader sederhana (tanpa composer) ----------
function loadDotEnvFile($path) {
    if (!file_exists($path) || !is_readable($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;

        list($name, $value) = array_map('trim', explode('=', $line, 2));

        if (strlen($value) >= 2) {
            if (($value[0] === '"' && $value[strlen($value)-1] === '"') ||
                ($value[0] === "'" && $value[strlen($value)-1] === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($name) === false) {
            putenv("$name=$value");
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    return true;
}

// Coba load .env dari root project terlebih dahulu
$rootEnv = BASE_PATH . '/.env';
$includesEnv = __DIR__ . '/.env';
if (!loadDotEnvFile($rootEnv)) {

    loadDotEnvFile($includesEnv);
}


function env($key, $fallback = null) {
    $v = getenv($key);
    if ($v === false || $v === null) return $fallback;
    return $v;
}

$defaults = [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '5432',
    'DB_NAME' => 'BD_LAB_TEKNODATA',
    'DB_USER' => 'postgres',
    'DB_PASS' => '',
    'SITE_URL' => 'http://localhost/lab-website',
    'SITE_NAME' => 'Laboratorium Teknologi Data JTI Polinema',
    'UPLOAD_DIR' => 'uploads',
    'MAX_FILE_SIZE' => 5 * 1024 * 1024,
];


define('DB_HOST', env('DB_HOST', $defaults['DB_HOST']));
define('DB_PORT', env('DB_PORT', $defaults['DB_PORT']));
define('DB_NAME', env('DB_NAME', $defaults['DB_NAME']));
define('DB_USER', env('DB_USER', $defaults['DB_USER']));
define('DB_PASS', env('DB_PASS', $defaults['DB_PASS']));

define('SITE_URL', env('SITE_URL', $defaults['SITE_URL']));
define('SITE_NAME', env('SITE_NAME', $defaults['SITE_NAME']));

$uploadDirEnv = env('UPLOAD_DIR', $defaults['UPLOAD_DIR']);
if ($uploadDirEnv !== '' && ($uploadDirEnv[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $uploadDirEnv))) {
    define('UPLOAD_PATH', rtrim($uploadDirEnv, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
} else {
    define('UPLOAD_PATH', rtrim(BASE_PATH . DIRECTORY_SEPARATOR . $uploadDirEnv, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
}

define('MAX_FILE_SIZE', intval(env('MAX_FILE_SIZE', $defaults['MAX_FILE_SIZE'])));

// ---------- Database Connection Function (Postgres) ----------
function getDBConnection() {
    $connection_string = sprintf(
        "host=%s port=%s dbname=%s user=%s password=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASS
    );

    $koneksi = @pg_connect($connection_string);

    if (!$koneksi) {
        error_log("Database connection failed: " . pg_last_error());
        return false;
    }

    return $koneksi;
}

// ---------- Helper functions ----------
function escapeString($conn, $string) {
    return pg_escape_string($conn, $string);
}

function formatTanggalIndo($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($date);
    $hari = date('d', $timestamp);
    $bulan_angka = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    return $hari . ' ' . $bulan[$bulan_angka] . ' ' . $tahun;
}

function truncateText($text, $length = 150) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
?>
