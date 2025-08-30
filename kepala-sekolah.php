<?php
// ==================== PHP BACKEND ====================
session_start();

// Konfigurasi Database MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'sipeka_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Fungsi untuk koneksi database
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

// Fungsi untuk inisialisasi database
function initializeDatabase($pdo) {
    // Buat tabel jika belum ada
    $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(36) PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','guru','kepala-sekolah') NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS reports (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            date DATE NOT NULL,
            type ENUM('harian','mingguan') NOT NULL,
            content TEXT NOT NULL,
            status ENUM('menunggu','dinilai') DEFAULT 'menunggu',
            score INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS evaluations (
            id VARCHAR(36) PRIMARY KEY,
            report_id VARCHAR(36) NOT NULL,
            evaluator_id VARCHAR(36) NOT NULL,
            score INT(11) NOT NULL,
            comment TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS notifications (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) DEFAULT NULL,
            message TEXT NOT NULL,
            icon VARCHAR(50) DEFAULT 'fa-bell',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS activities (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            action VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        die("Error creating tables: " . $e->getMessage());
    }
    
    // Cek apakah sudah ada data
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Buat user default
        $users = [
            [
                'id' => generateUUID(),
                'email' => 'kepsek@sipeka.com',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'kepala-sekolah',
                'name' => 'Kepala Sekolah'
            ],
            [
                'id' => generateUUID(),
                'email' => 'guru@sipeka.com',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'guru',
                'name' => 'Guru Contoh'
            ],
            [
                'id' => generateUUID(),
                'email' => 'admin@sipeka.com',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'admin',
                'name' => 'Administrator'
            ]
        ];
        
        foreach ($users as $user) {
            $stmt = $pdo->prepare("INSERT INTO users (id, email, password, role, name) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $user['email'], $user['password'], $user['role'], $user['name']]);
        }
        
        // Buat contoh laporan
        $reportId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO reports (id, user_id, date, type, content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $reportId,
            $users[1]['id'], // Guru ID
            date('Y-m-d'),
            'harian',
            'Hari ini saya mengajar matematika untuk kelas 5. Materi yang diajarkan adalah pecahan. Siswa cukup antusias mengikuti pelajaran.'
        ]);
        
        // Buat contoh evaluasi
        $evalId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO evaluations (id, report_id, evaluator_id, score, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $evalId,
            $reportId,
            $users[0]['id'], // Kepala sekolah ID
            85,
            'Laporan sangat baik, siswa terlihat antusias'
        ]);
        
        // Update status laporan
        $stmt = $pdo->prepare("UPDATE reports SET status = 'dinilai', score = ? WHERE id = ?");
        $stmt->execute([85, $reportId]);
        
        // Buat contoh notifikasi
        $notifId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO notifications (id, user_id, message, icon) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $notifId,
            $users[0]['id'], // Kepala sekolah ID
            'Selamat datang di Sistem Kinerja Guru',
            'fa-info-circle'
        ]);
        
        // Buat contoh aktivitas
        $activityId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO activities (id, user_id, action) VALUES (?, ?, ?)");
        $stmt->execute([
            $activityId,
            $users[0]['id'],
            'Sistem database diinisialisasi'
        ]);
    }
}

// Fungsi untuk menghasilkan UUID yang kompatibel dengan MySQL
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Inisialisasi database
$pdo = getDBConnection();
initializeDatabase($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'evaluate_report':
            $reportId = $_POST['reportId'] ?? '';
            $score = $_POST['score'] ?? '';
            $comment = $_POST['comment'] ?? '';
            
            if (empty($reportId) || empty($score)) {
                $_SESSION['error'] = 'Data tidak lengkap';
                break;
            }
            
            // Dapatkan ID kepala sekolah yang sesungguhnya dari database
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'kepala-sekolah' LIMIT 1");
            $stmt->execute();
            $kepalaSekolah = $stmt->fetch();
            
            if (!$kepalaSekolah) {
                $_SESSION['error'] = 'Kepala sekolah tidak ditemukan';
                break;
            }
            
            $evaluatorId = $kepalaSekolah['id'];
            
            // Update status laporan
            $stmt = $pdo->prepare("UPDATE reports SET status = 'dinilai', score = ? WHERE id = ?");
            $stmt->execute([$score, $reportId]);
            
            // Tambahkan evaluasi
            $evalId = generateUUID();
            $stmt = $pdo->prepare("INSERT INTO evaluations (id, report_id, evaluator_id, score, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$evalId, $reportId, $evaluatorId, $score, $comment]);
            
            // Dapatkan user_id dari laporan
            $stmt = $pdo->prepare("SELECT user_id FROM reports WHERE id = ?");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();
            
            if ($report) {
                // Tambahkan notifikasi untuk guru
                $notifId = generateUUID();
                $stmt = $pdo->prepare("INSERT INTO notifications (id, user_id, message, icon) VALUES (?, ?, ?, ?)");
                $stmt->execute([$notifId, $report['user_id'], "Laporan Anda telah dinilai oleh kepala sekolah", "fa-star"]);
                
                // Catat aktivitas
                $teacherName = getUsername($pdo, $report['user_id']);
                logActivity($pdo, $evaluatorId, "Menilai laporan dari $teacherName");
            }
            
            $_SESSION['success'] = 'Penilaian berhasil disimpan';
            break;
            
        default:
            $_SESSION['error'] = 'Action tidak dikenali';
    }
    
    // Redirect untuk menghindari resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Fungsi bantu
function getUsername($pdo, $userId) {
    if ($userId === 'system') return 'System';
    
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user ? $user['name'] : 'Unknown';
}

function logActivity($pdo, $userId, $action) {
    $id = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO activities (id, user_id, action) VALUES (?, ?, ?)");
    $stmt->execute([$id, $userId, $action]);
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Dapatkan user kepala sekolah yang sesungguhnya dari database
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'kepala-sekolah' LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    die("User kepala sekolah tidak ditemukan di database");
}

// Cek role
if ($user['role'] !== 'kepala-sekolah') {
    echo "Akses ditolak. Hanya kepala sekolah yang dapat mengakses halaman ini.";
    exit;
}

// Ambil data untuk ditampilkan langsung
$statusFilter = $_GET['status'] ?? '';
$teacherFilter = $_GET['teacherId'] ?? '';
$monthFilter = $_GET['month'] ?? '';

// Query untuk laporan dengan filter
$reportsQuery = "
    SELECT r.id, r.date, r.type, r.status, r.score, u.name as teacher
    FROM reports r 
    JOIN users u ON r.user_id = u.id 
    WHERE 1=1
";

$params = [];

if (!empty($statusFilter) && $statusFilter !== 'semua') {
    $reportsQuery .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($teacherFilter) && $teacherFilter !== 'semua') {
    $reportsQuery .= " AND r.user_id = ?";
    $params[] = $teacherFilter;
}

if (!empty($monthFilter)) {
    $reportsQuery .= " AND DATE_FORMAT(r.date, '%Y-%m') = ?";
    $params[] = $monthFilter;
}

$reportsQuery .= " ORDER BY r.created_at DESC LIMIT 10";

$stmt = $pdo->prepare($reportsQuery);
$stmt->execute($params);
$latestReports = $stmt->fetchAll();

// Query lainnya
$totalReports = $pdo->query("SELECT COUNT(*) as total FROM reports")->fetch()['total'];
$teacherPerformance = $pdo->query("
    SELECT u.id, u.name as teacher, COALESCE(AVG(r.score), 0) as averageScore
    FROM users u
    LEFT JOIN reports r ON u.id = r.user_id
    WHERE u.role = 'guru'
    GROUP BY u.id, u.name
    ORDER BY averageScore DESC
")->fetchAll();
$teachers = $pdo->query("SELECT id, name FROM users WHERE role = 'guru'")->fetchAll();
$notifications = $pdo->query("
    SELECT * FROM notifications 
    WHERE user_id = '" . $user['id'] . "'
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();
$activities = $pdo->query("
    SELECT a.*, u.name as user_name 
    FROM activities a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

// Hitung rata-rata nilai
$averageScore = 0;
if (count($teacherPerformance) > 0) {
    $sum = 0;
    foreach ($teacherPerformance as $teacher) {
        $sum += $teacher['averageScore'];
    }
    $averageScore = $sum / count($teacherPerformance);
}

// Ambil detail laporan untuk modal jika diperlukan
$reportDetail = null;
$reportEvaluations = [];
if (isset($_GET['view_report'])) {
    $reportId = $_GET['view_report'];
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as teacher_name 
        FROM reports r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$reportId]);
    $reportDetail = $stmt->fetch();
    
    if ($reportDetail) {
        $stmt = $pdo->prepare("
            SELECT e.*, u.name as evaluator_name 
            FROM evaluations e 
            JOIN users u ON e.evaluator_id = u.id 
            WHERE e.report_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$reportId]);
        $reportEvaluations = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Sekolah - Sistem Kinerja</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --gray-color: #95a5a6;
            --sidebar-width: 250px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: white;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e1e1e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-weight: bold;
        }
        .profile-info {
            display: flex;
            flex-direction: column;
        }
        .username {
            font-weight: 500;
            color: white;
        }
        .role {
            font-size: 0.8rem;
            color: var(--gray-color);
        }
        .sidebar-nav ul {
            list-style: none;
            padding: 1rem 0;
        }
        .sidebar-nav li {
            margin-bottom: 0.25rem;
        }
        .sidebar-nav a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        .sidebar-nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar-nav a i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        .sidebar-nav .active a {
            background-color: var(--primary-color);
            color: white;
        }
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            position: sticky;
            top: 0;
            background: #f5f7fa;
            z-index: 100;
            padding: 1rem 0;
        }
        .main-header h1 {
            font-size: 1.75rem;
            color: var(--dark-color);
        }
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        .card-wide {
            grid-column: span 2;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .btn i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        .btn-secondary:hover {
            background-color: #27ae60;
        }
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        .table-responsive {
            overflow-x: auto;
            max-height: 400px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            font-weight: 600;
            color: var(--dark-color);
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .comments-container, .notifications-container {
            max-height: 300px;
            overflow-y: auto;
        }
        .comment-item, .notification-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .comment-item:last-child, .notification-item:last-child {
            border-bottom: none;
        }
        .comment-author {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .comment-date {
            font-size: 0.8rem;
            color: var(--gray-color);
        }
        .comment-text {
            margin-top: 0.5rem;
        }
        .notification-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }
        .notification-text {
            flex: 1;
        }
        .notification-time {
            font-size: 0.8rem;
            color: var(--gray-color);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--primary-color);
            text-align: center;
            margin: 1rem 0;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            position: relative;
        }
        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            color: var(--gray-color);
            cursor: pointer;
        }
        .close:hover {
            color: var(--dark-color);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .badge-success {
            background-color: var(--secondary-color);
            color: white;
        }
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        .filter-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .filter-group label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .report-content {
            margin-top: 1rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid var(--primary-color);
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .export-section {
            margin-top: 1rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid var(--secondary-color);
        }
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 10000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 300px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .notification-toast.success {
            background-color: var(--secondary-color);
            color: white;
        }
        .notification-toast.error {
            background-color: var(--danger-color);
            color: white;
        }
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .card-wide {
                grid-column: span 1;
            }
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h2,
            .profile-info,
            .sidebar-nav a span {
                display: none;
            }
            
            .sidebar-nav a {
                text-align: center;
                padding: 0.75rem;
            }
            
            .sidebar-nav a i {
                margin-right: 0;
                font-size: 1.25rem;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 1rem;
            }
            .main-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Sistem Kinerja</h2>
                <div class="user-profile">
                    <div class="profile-img">KS</div>
                    <div class="profile-info">
                        <span class="username"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="role">Kepala Sekolah</span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="#" id="viewReportsLink"><i class="fas fa-list"></i> <span>Lihat Laporan Guru</span></a></li>
                    <li><a href="#" id="evaluateReportsLink"><i class="fas fa-star"></i> <span>Nilai & Komentar</span></a></li>
                    <li><a href="#" id="filterReportsLink"><i class="fas fa-filter"></i> <span>Filter & Cari Laporan</span></a></li>
                    <li><a href="#" id="exportReportsLink"><i class="fas fa-file-export"></i> <span>Export Laporan</span></a></li>
                    <li><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> <span>Keluar</span></a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <h1>Dashboard Kepala Sekolah</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" id="btnViewReports">
                        <i class="fas fa-list"></i> Lihat Semua Laporan
                    </button>
                    <button class="btn btn-secondary" id="btnEvaluateReports">
                        <i class="fas fa-star"></i> Nilai Laporan
                    </button>
                </div>
            </header>
            
            <!-- Notifikasi -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="notification-toast success">
                    <div style="display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
                        <span><?= $_SESSION['success'] ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; margin-left: 15px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="notification-toast error">
                    <div style="display: flex; align-items: center;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
                        <span><?= $_SESSION['error'] ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; margin-left: 15px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="dashboard-grid">
                <section class="card">
                    <h2>Total Laporan Masuk</h2>
                    <div class="stat-number" id="totalReports">
                        <?= $totalReports ?>
                    </div>
                </section>
                <section class="card">
                    <h2>Rata-rata Nilai</h2>
                    <div class="stat-number" id="averageScore">
                        <?= number_format($averageScore, 1) ?>
                    </div>
                </section>
                <section class="card card-wide">
                    <h2>Grafik Penilaian Guru</h2>
                    <div class="chart-container">
                        <canvas id="teacherPerformanceChart"></canvas>
                    </div>
                </section>
                <section class="card card-wide">
                    <h2>Laporan Terbaru</h2>
                    <div class="filter-section">
                        <div class="filter-group">
                            <label for="filterStatus">Status</label>
                            <select id="filterStatus" onchange="applyFilters()">
                                <option value="semua" <?= $statusFilter === '' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="menunggu" <?= $statusFilter === 'menunggu' ? 'selected' : '' ?>>Menunggu Penilaian</option>
                                <option value="dinilai" <?= $statusFilter === 'dinilai' ? 'selected' : '' ?>>Sudah Dinilai</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filterTeacher">Guru</label>
                            <select id="filterTeacher" onchange="applyFilters()">
                                <option value="semua" <?= $teacherFilter === '' ? 'selected' : '' ?>>Semua Guru</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>" <?= $teacherFilter === $teacher['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($teacher['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filterDate">Bulan</label>
                            <input type="month" id="filterDate" value="<?= $monthFilter ?>" onchange="applyFilters()">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-sm btn-primary" onclick="clearFilters()">
                                <i class="fas fa-times"></i> Reset
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="latestReportsTable">
                            <thead>
                                <tr>
                                    <th>Nama Guru</th>
                                    <th>Tanggal Laporan</th>
                                    <th>Jenis</th>
                                    <th>Status Penilaian</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($latestReports) > 0): ?>
                                    <?php foreach ($latestReports as $report): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($report['teacher']) ?></td>
                                            <td><?= date('d M Y', strtotime($report['date'])) ?></td>
                                            <td><?= $report['type'] === 'harian' ? 'Harian' : 'Mingguan' ?></td>
                                            <td>
                                                <span class="status-badge <?= $report['status'] === 'dinilai' ? 'badge-success' : 'badge-warning' ?>">
                                                    <?= $report['status'] === 'dinilai' ? 'Sudah dinilai' : 'Belum dinilai' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="openViewModal('<?= $report['id'] ?>')">
                                                    <i class="fas fa-eye"></i> Lihat
                                                </button>
                                                <?php if ($report['status'] !== 'dinilai'): ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="openEvaluateModal('<?= $report['id'] ?>')" style="margin-left: 5px;">
                                                        <i class="fas fa-star"></i> Nilai
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 20px;">
                                            <i class="fas fa-inbox" style="font-size: 2rem; color: var(--gray-color); margin-bottom: 10px; display: block;"></i>
                                            <p>Belum ada laporan</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="export-section">
                        <h3>Export Laporan</h3>
                        <p>Export laporan yang telah difilter ke format CSV</p>
                        <button class="btn btn-secondary" onclick="exportReports()">
                            <i class="fas fa-file-export"></i> Export ke CSV
                        </button>
                    </div>
                </section>
                <section class="card">
                    <h2>Notifikasi</h2>
                    <div class="notifications-container" id="notifications">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <i class="fas <?= $notification['icon'] ?? 'fa-bell' ?>"></i>
                                    </div>
                                    <div class="notification-text"><?= htmlspecialchars($notification['message']) ?></div>
                                    <div class="notification-time"><?= date('H:i', strtotime($notification['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: var(--gray-color);">
                                <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                <p>Tidak ada notifikasi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="card">
                    <h2>Aktivitas Terbaru</h2>
                    <div class="notifications-container" id="activities">
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div class="notification-text">
                                        <strong><?= htmlspecialchars($activity['user_name'] ?? 'System') ?></strong>: <?= htmlspecialchars($activity['action']) ?>
                                    </div>
                                    <div class="notification-time"><?= date('H:i', strtotime($activity['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: var(--gray-color);">
                                <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                <p>Tidak ada aktivitas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <!-- Modal Nilai Laporan -->
    <div id="evaluateModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('evaluateModal')">&times;</span>
            <h2>Penilaian Laporan</h2>
            <div id="reportDetails" class="report-content">
                <?php if (isset($_GET['evaluate_report'])): ?>
                    <?php
                    $reportId = $_GET['evaluate_report'];
                    $stmt = $pdo->prepare("
                        SELECT r.*, u.name as teacher_name 
                        FROM reports r 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.id = ?
                    ");
                    $stmt->execute([$reportId]);
                    $report = $stmt->fetch();
                    ?>
                    <?php if ($report): ?>
                        <h3>Laporan <?= $report['type'] === 'harian' ? 'Harian' : 'Mingguan' ?> - <?= htmlspecialchars($report['teacher_name']) ?></h3>
                        <p><strong>Tanggal:</strong> <?= date('d M Y', strtotime($report['date'])) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="status-badge <?= $report['status'] === 'dinilai' ? 'badge-success' : 'badge-warning' ?>">
                                <?= $report['status'] === 'dinilai' ? 'Sudah dinilai' : 'Belum dinilai' ?>
                            </span>
                        </p>
                        <div style="margin-top: 15px;">
                            <h4>Isi Laporan:</h4>
                            <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #eee;">
                                <?= nl2br(htmlspecialchars($report['content'])) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>Laporan tidak ditemukan</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Pilih laporan untuk dinilai</p>
                <?php endif; ?>
            </div>
            <form id="evaluateForm" method="POST" action="">
                <input type="hidden" name="action" value="evaluate_report">
                <input type="hidden" id="reportId" name="reportId" value="<?= $_GET['evaluate_report'] ?? '' ?>">
                <div class="form-group">
                    <label for="reportScore">Nilai (0-100)</label>
                    <input type="number" id="reportScore" name="score" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label for="reportComment">Komentar</label>
                    <textarea id="reportComment" name="comment" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Penilaian</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Lihat Laporan -->
    <div id="viewReportModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewReportModal')">&times;</span>
            <h2>Detail Laporan</h2>
            <div id="fullReportDetails" class="report-content">
                <?php if ($reportDetail): ?>
                    <h3>Laporan <?= $reportDetail['type'] === 'harian' ? 'Harian' : 'Mingguan' ?> - <?= htmlspecialchars($reportDetail['teacher_name']) ?></h3>
                    <p><strong>Tanggal:</strong> <?= date('d M Y', strtotime($reportDetail['date'])) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge <?= $reportDetail['status'] === 'dinilai' ? 'badge-success' : 'badge-warning' ?>">
                            <?= $reportDetail['status'] === 'dinilai' ? 'Sudah dinilai' : 'Belum dinilai' ?>
                        </span>
                    </p>
                    <?php if ($reportDetail['score']): ?>
                        <p><strong>Nilai:</strong> <?= $reportDetail['score'] ?></p>
                    <?php endif; ?>
                    <div style="margin-top: 15px;">
                        <h4>Isi Laporan:</h4>
                        <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #eee;">
                            <?= nl2br(htmlspecialchars($reportDetail['content'])) ?>
                        </div>
                    </div>
                    
                    <?php if (count($reportEvaluations) > 0): ?>
                        <div style="margin-top: 15px;">
                            <h4>Evaluasi:</h4>
                            <?php foreach ($reportEvaluations as $evaluation): ?>
                                <div class="evaluation-item" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <p><strong>Penilai:</strong> <?= htmlspecialchars($evaluation['evaluator_name']) ?></p>
                                    <p><strong>Nilai:</strong> <?= $evaluation['score'] ?></p>
                                    <p><strong>Komentar:</strong> <?= nl2br(htmlspecialchars($evaluation['comment'])) ?></p>
                                    <p><strong>Tanggal:</strong> <?= date('d M Y H:i', strtotime($evaluation['created_at'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Pilih laporan untuk dilihat</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <button class="btn btn-secondary" onclick="closeModal('viewReportModal')">Tutup</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Data dari PHP
        const teacherPerformanceData = <?= json_encode($teacherPerformance) ?>;
        
        // Render grafik performa guru
        function renderTeacherPerformanceChart() {
            const ctx = document.getElementById('teacherPerformanceChart').getContext('2d');
            
            if (window.teacherPerformanceChartInstance) {
                window.teacherPerformanceChartInstance.destroy();
            }
            
            if (!teacherPerformanceData || teacherPerformanceData.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = "14px Arial";
                ctx.fillStyle = "#999";
                ctx.textAlign = "center";
                ctx.fillText("Belum ada data penilaian", ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            const teachers = teacherPerformanceData.map(item => item.teacher);
            const scores = teacherPerformanceData.map(item => item.averageScore);
            
            window.teacherPerformanceChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: teachers,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: scores,
                        backgroundColor: 'rgba(46, 204, 113, 0.7)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Nilai'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Guru'
                            }
                        }
                    }
                }
            });
        }
        
        // Fungsi untuk menerapkan filter
        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const teacherId = document.getElementById('filterTeacher').value;
            const date = document.getElementById('filterDate').value;
            const month = date ? date.substring(0, 7) : '';
            
            const params = new URLSearchParams();
            
            if (status !== 'semua') params.append('status', status);
            if (teacherId !== 'semua') params.append('teacherId', teacherId);
            if (month) params.append('month', month);
            
            window.location.href = `${window.location.pathname}?${params}`;
        }
        
        // Fungsi untuk menghapus filter
        function clearFilters() {
            window.location.href = window.location.pathname;
        }
        
        // Fungsi untuk export laporan
        function exportReports() {
            const status = document.getElementById('filterStatus').value;
            const teacherId = document.getElementById('filterTeacher').value;
            const date = document.getElementById('filterDate').value;
            const month = date ? date.substring(0, 7) : '';
            
            const params = new URLSearchParams({
                action: 'export_reports',
                status: status !== 'semua' ? status : '',
                teacherId: teacherId !== 'semua' ? teacherId : '',
                month: month
            });
            
            window.location.href = `${window.location.pathname}?${params}`;
        }
        
        // Fungsi untuk membuka modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Fungsi untuk menutup modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Hapus parameter dari URL
            const url = new URL(window.location.href);
            url.searchParams.delete('view_report');
            url.searchParams.delete('evaluate_report');
            window.history.replaceState({}, '', url);
        }
        
        // Fungsi untuk membuka modal lihat laporan
        function openViewModal(reportId) {
            const url = new URL(window.location.href);
            url.searchParams.set('view_report', reportId);
            window.location.href = url;
        }
        
        // Fungsi untuk membuka modal penilaian
        function openEvaluateModal(reportId) {
            const url = new URL(window.location.href);
            url.searchParams.set('evaluate_report', reportId);
            window.location.href = url;
        }
        
        // Event listener untuk tombol lihat semua laporan
        document.getElementById('btnViewReports').addEventListener('click', function() {
            window.location.href = window.location.pathname;
        });
        
        // Event listener untuk tombol nilai laporan
        document.getElementById('btnEvaluateReports').addEventListener('click', function() {
            // Set filter ke "menunggu"
            document.getElementById('filterStatus').value = 'menunggu';
            applyFilters();
        });
        
        // Event listener untuk menu sidebar
        document.getElementById('viewReportsLink').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('btnViewReports').click();
        });
        
        document.getElementById('evaluateReportsLink').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('btnEvaluateReports').click();
        });
        
        document.getElementById('filterReportsLink').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('.filter-section').scrollIntoView({ behavior: 'smooth' });
        });
        
        document.getElementById('exportReportsLink').addEventListener('click', function(e) {
            e.preventDefault();
            exportReports();
        });
        
        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            renderTeacherPerformanceChart();
            
            // Auto-tutup notifikasi setelah 5 detik
            setTimeout(() => {
                document.querySelectorAll('.notification-toast').forEach(toast => {
                    toast.remove();
                });
            }, 5000);
            
            // Buka modal jika ada parameter di URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('view_report')) {
                openModal('viewReportModal');
            }
            if (urlParams.has('evaluate_report')) {
                openModal('evaluateModal');
            }
            
            // Tutup modal ketika klik di luar konten modal
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });
        });
    </script>
</body>
</html>
