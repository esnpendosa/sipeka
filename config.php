<?php
// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_NAME', 'sipeka_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Koneksi database
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

// Generate UUID
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Format response JSON
function jsonResponse($success, $data = [], $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// Cek apakah user sudah login
function checkAuth($requiredRole = null) {
    session_start();
    
    if (!isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
    
    if ($requiredRole && $_SESSION['user']['role'] !== $requiredRole) {
        // Redirect ke halaman yang sesuai dengan role user
        header('Location: ' . $_SESSION['user']['role'] . '.php');
        exit;
    }
    
    return $_SESSION['user'];
}

// Fungsi untuk mendapatkan nama bulan
function getMonthName($month) {
    $months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", 
                "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    return $months[$month - 1] ?? "Bulan tidak valid";
}
?>