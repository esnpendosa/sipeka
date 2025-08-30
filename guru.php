<?php
session_start();

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

// Cek apakah user sudah login
function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
    
    if ($requiredRole && $_SESSION['user']['role'] !== $requiredRole) {
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

// Cek autentikasi
$user = checkAuth('guru');

// Dapatkan koneksi database
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle create report form submission
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'create_report') {
        $userId = $_POST['userId'] ?? null;
        $type = $_POST['type'] ?? null;
        $date = $_POST['date'] ?? null;
        $content = $_POST['content'] ?? null;
        
        if ($userId && $type && $date && $content) {
            try {
                $pdo = getDBConnection();
                $id = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO reports (id, user_id, date, type, content, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'menunggu', NOW())
                ");
                $stmt->execute([$id, $userId, $date, $type, $content]);
                
                // Return success response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Laporan berhasil disimpan',
                    'id' => $id
                ]);
                exit;
            } catch (PDOException $e) {
                // Return error response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal menyimpan laporan: ' . $e->getMessage()
                ]);
                exit;
            }
        } else {
            // Return error response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Semua field harus diisi'
            ]);
            exit;
        }
    }
    
    // Handle get report detail request
    if (isset($_POST['action']) && $_POST['action'] === 'get_report_detail') {
        $reportId = $_POST['reportId'] ?? null;
        
        if ($reportId) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
                $stmt->execute([$reportId]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($report) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'data' => $report
                    ]);
                    exit;
                } else {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Laporan tidak ditemukan'
                    ]);
                    exit;
                }
            } catch (PDOException $e) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Report ID is required'
            ]);
            exit;
        }
    }
    
    // Handle get report evaluations request
    if (isset($_POST['action']) && $_POST['action'] === 'get_report_evaluations') {
        $reportId = $_POST['reportId'] ?? null;
        
        if ($reportId) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("
                    SELECT e.*, u.name as evaluator_name 
                    FROM evaluations e 
                    JOIN users u ON e.evaluator_id = u.id 
                    WHERE e.report_id = ?
                ");
                $stmt->execute([$reportId]);
                $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => $evaluations
                ]);
                exit;
            } catch (PDOException $e) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Report ID is required'
            ]);
            exit;
        }
    }
    
    // Handle refresh data request
    if (isset($_POST['action']) && $_POST['action'] === 'refresh_data') {
        $userId = $_POST['userId'] ?? null;
        
        if ($userId) {
            try {
                $pdo = getDBConnection();
                
                // Get reports
                $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$userId]);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get evaluations
                $stmt = $pdo->prepare("
                    SELECT e.*, r.date as report_date, r.type as report_type 
                    FROM evaluations e 
                    JOIN reports r ON e.report_id = r.id 
                    WHERE r.user_id = ? 
                    ORDER BY e.created_at DESC
                ");
                $stmt->execute([$userId]);
                $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get comments
                $stmt = $pdo->prepare("
                    SELECT e.*, u.name as evaluator_name, r.date as report_date 
                    FROM evaluations e 
                    JOIN reports r ON e.report_id = r.id 
                    JOIN users u ON e.evaluator_id = u.id 
                    WHERE r.user_id = ? AND e.comment IS NOT NULL AND e.comment != ''
                    ORDER BY e.created_at DESC 
                    LIMIT 5
                ");
                $stmt->execute([$userId]);
                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'reports' => $reports,
                    'evaluations' => $evaluations,
                    'comments' => $comments
                ]);
                exit;
            } catch (PDOException $e) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            exit;
        }
    }
}

// Fungsi untuk mendapatkan data dari database
function getReportsByUser($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEvaluationsByUser($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT e.*, r.date as report_date, r.type as report_type 
        FROM evaluations e 
        JOIN reports r ON e.report_id = r.id 
        WHERE r.user_id = ? 
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCommentsByUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, u.name as evaluator_name, r.date as report_date 
            FROM evaluations e 
            JOIN reports r ON e.report_id = r.id 
            JOIN users u ON e.evaluator_id = u.id 
            WHERE r.user_id = ? AND e.comment IS NOT NULL AND e.comment != ''
            ORDER BY e.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting comments: " . $e->getMessage());
        return [];
    }
}

// Ambil data untuk dashboard
$reports = getReportsByUser($pdo, $user['id']);
$evaluations = getEvaluationsByUser($pdo, $user['id']);
$comments = getCommentsByUser($pdo, $user['id']);

// Fungsi helper untuk format waktu
function formatTimeAgo($timestamp) {
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);
    
    if ($diff->days > 0) return $diff->days . ' hari yang lalu';
    if ($diff->h > 0) return $diff->h . ' jam yang lalu';
    if ($diff->i > 0) return $diff->i . ' menit yang lalu';
    return $diff->s . ' detik yang lalu';
}

function formatDate($dateString) {
    if (!$dateString) return '-';
    $date = new DateTime($dateString);
    return $date->format('d/m/Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Sistem Kinerja</title>
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
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        .profile-info {
            display: flex;
            flex-direction: column;
        }
        .username {
            font-weight: 500;
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
        }
        .btn i {
            margin-right: 0.5rem;
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
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
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
            color: var(--primary-color);
        }
        .comment-date {
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-bottom: 0.5rem;
        }
        .comment-text {
            margin-top: 0.5rem;
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 5px;
            border-left: 3px solid var(--secondary-color);
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
            font-size: 0.9rem;
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
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .report-detail {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
        }
        
        .evaluation-item {
            border-left: 3px solid var(--secondary-color);
            padding-left: 10px;
            margin-bottom: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 20px;
            color: var(--gray-color);
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--dark-color);
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            background-color: var(--secondary-color);
        }
        
        .toast.error {
            background-color: var(--danger-color);
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
                    <div class="profile-img">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <span class="username"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="role">Guru</span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="#" id="navDashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="#" id="navCreateReport"><i class="fas fa-file-alt"></i> <span>Buat Laporan Baru</span></a></li>
                    <li><a href="#" id="navPerformance"><i class="fas fa-chart-line"></i> <span>Hasil Penilaian</span></a></li>
                    <li><a href="#" id="navComments"><i class="fas fa-comments"></i> <span>Komentar</span></a></li>
                    <li><a href="#" id="navExport"><i class="fas fa-file-export"></i> <span>Export Laporan</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Keluar</span></a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <h1>Dashboard Guru</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" id="btnNewReport">
                        <i class="fas fa-plus"></i> Buat Laporan Baru
                    </button>
                    <button class="btn btn-secondary" id="btnRefresh">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>
            </header>
            <div class="dashboard-grid">
                <section class="card card-wide">
                    <h2>Laporan Terakhir 
                        <span id="lastUpdated" style="font-size: 0.8rem; color: var(--gray-color); font-weight: normal; margin-left: 10px;">
                            Terakhir diperbarui: <span id="updateTime"><?= date('H:i:s') ?></span>
                        </span>
                    </h2>
                    <div class="table-responsive">
                        <table id="reportsTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Nilai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($reports) > 0): ?>
                                    <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td><?= formatDate($report['date']) ?></td>
                                        <td><?= $report['type'] === 'harian' ? 'Harian' : 'Mingguan' ?></td>
                                        <td>
                                            <span class="status-badge <?= $report['status'] === 'dinilai' ? 'badge-success' : 'badge-warning' ?>">
                                                <?= $report['status'] === 'dinilai' ? 'Dinilai' : 'Menunggu' ?>
                                            </span>
                                        </td>
                                        <td><?= $report['score'] ?? '-' ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-report" data-id="<?= $report['id'] ?>">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                            <?php if ($report['status'] === 'dinilai'): ?>
                                                <button class="btn btn-sm btn-secondary view-evaluation" data-id="<?= $report['id'] ?>" style="margin-left: 5px;">
                                                    <i class="fas fa-star"></i> Nilai
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>Belum ada laporan</p>
                                            <button class="btn btn-sm btn-primary" id="btnCreateEmptyReport">
                                                Buat Laporan Pertama
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="card">
                    <h2>Buat Laporan Baru</h2>
                    <p>Isi laporan kinerja harian/mingguan</p>
                    <button class="btn btn-secondary" id="btnCreateDaily">
                        <i class="fas fa-calendar-day"></i> Harian
                    </button>
                    <button class="btn btn-secondary" id="btnCreateWeekly">
                        <i class="fas fa-calendar-week"></i> Mingguan
                    </button>
                </section>
                <section class="card">
                    <h2>Hasil Penilaian</h2>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </section>
                <section class="card">
                    <h2>Komentar Terakhir</h2>
                    <div class="comments-container" id="latestComments">
                        <?php if (count($comments) > 0): ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-author"><?= htmlspecialchars($comment['evaluator_name'] ?? 'Penilai') ?></div>
                                <div class="comment-date"><?= formatDate($comment['created_at'] ?? $comment['report_date']) ?></div>
                                <div class="comment-text"><?= htmlspecialchars($comment['comment'] ?? 'Tidak ada komentar') ?></div>
                                <?php if (isset($comment['score'])): ?>
                                    <div><strong>Nilai: <?= $comment['score'] ?></strong></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-comment-slash"></i>
                                <p>Belum ada komentar</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <!-- Modal Buat Laporan -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Buat Laporan Baru</h2>
            <form id="reportForm">
                <input type="hidden" name="form_type" value="create_report">
                <input type="hidden" name="userId" value="<?= $user['id'] ?>">
                <input type="hidden" id="reportType" name="type">
                <div class="form-group">
                    <label for="reportDate">Tanggal</label>
                    <input type="date" id="reportDate" name="date" required>
                </div>
                <div class="form-group">
                    <label for="reportContent">Isi Laporan</label>
                    <textarea id="reportContent" name="content" rows="8" placeholder="Deskripsikan aktivitas dan pencapaian Anda hari/minggu ini..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="btnSubmitReport">
                    <span id="submitText">Simpan Laporan</span>
                    <span id="submitLoading" class="loading" style="display: none;"></span>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal Lihat Laporan -->
    <div id="viewReportModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Detail Laporan</h2>
            <div id="reportDetailContent">
                <!-- Detail laporan akan diisi oleh JavaScript -->
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button class="btn btn-secondary" id="btnCloseView">Tutup</button>
            </div>
        </div>
    </div>
    
    <!-- Modal Evaluasi -->
    <div id="evaluationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Detail Penilaian</h2>
            <div id="evaluationContent">
                <!-- Detail evaluasi akan diisi oleh JavaScript -->
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button class="btn btn-secondary" id="btnCloseEvaluation">Tutup</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Data user dari PHP
        const currentUser = {
            id: '<?= $user['id'] ?>',
            name: '<?= htmlspecialchars($user['name']) ?>',
            role: '<?= $user['role'] ?>'
        };
        
        // Data awal dari PHP
        let reportsData = <?= json_encode($reports) ?>;
        let evaluationsData = <?= json_encode($evaluations) ?>;
        let commentsData = <?= json_encode($comments) ?>;
        
        // Variabel global
        let performanceChart = null;
        
        // Fungsi untuk menampilkan notifikasi toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + type;
            
            setTimeout(() => {
                toast.className = 'toast';
            }, 3000);
        }
        
        // Fungsi untuk mengirim data ke server dengan AJAX
        async function sendDataToServer(url, data) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                });
                
                return await response.json();
            } catch (error) {
                console.error('Error sending data:', error);
                showToast('Terjadi kesalahan saat mengirim data', 'error');
                return {success: false, message: 'Terjadi kesalahan saat mengirim data'};
            }
        }
        
        // Muat data dashboard
        async function loadDashboardData() {
            try {
                showLoading(true);
                
                // Kirim request untuk refresh data
                const result = await sendDataToServer('', {
                    action: 'refresh_data',
                    userId: currentUser.id
                });
                
                if (result.success) {
                    reportsData = result.reports;
                    evaluationsData = result.evaluations;
                    commentsData = result.comments;
                    
                    renderReportsTable(reportsData);
                    renderPerformanceChart(evaluationsData);
                    renderLatestComments(commentsData);
                } else {
                    showToast(result.message || 'Gagal memuat data', 'error');
                }
                
                // Update waktu terakhir refresh
                updateLastRefreshTime();
                
            } catch (error) {
                console.error('Error loading dashboard data:', error);
                showToast('Terjadi kesalahan saat memuat data dashboard', 'error');
            } finally {
                showLoading(false);
            }
        }
        
        // Render tabel laporan
        function renderReportsTable(reports) {
            const tbody = document.querySelector('#reportsTable tbody');
            
            if (!reports || reports.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada laporan</p>
                            <button class="btn btn-sm btn-primary" id="btnCreateEmptyReport">
                                Buat Laporan Pertama
                            </button>
                        </td>
                    </tr>
                `;
                
                // Tambahkan event listener untuk tombol buat laporan
                document.getElementById('btnCreateEmptyReport').addEventListener('click', function() {
                    document.getElementById('btnCreateDaily').click();
                });
                
                return;
            }
            
            tbody.innerHTML = '';
            
            reports.forEach(report => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${formatDate(report.date)}</td>
                    <td>${report.type === 'harian' ? 'Harian' : 'Mingguan'}</td>
                    <td>
                        <span class="status-badge ${report.status === 'dinilai' ? 'badge-success' : 'badge-warning'}">
                            ${report.status === 'dinilai' ? 'Dinilai' : 'Menunggu'}
                        </span>
                    </td>
                    <td>${report.score || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary view-report" data-id="${report.id}">
                            <i class="fas fa-eye"></i> Lihat
                        </button>
                        ${report.status === 'dinilai' ? 
                            `<button class="btn btn-sm btn-secondary view-evaluation" data-id="${report.id}" style="margin-left: 5px;">
                                <i class="fas fa-star"></i> Nilai
                            </button>` : ''
                        }
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            // Tambahkan event listener untuk tombol lihat laporan
            document.querySelectorAll('.view-report').forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-id');
                    openViewModal(reportId);
                });
            });
            
            // Tambahkan event listener untuk tombol lihat evaluasi
            document.querySelectorAll('.view-evaluation').forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-id');
                    openEvaluationModal(reportId);
                });
            });
        }
        
        // Render grafik performa
        function renderPerformanceChart(performanceData) {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            
            // Hancurkan chart sebelumnya jika ada
            if (performanceChart) {
                performanceChart.destroy();
            }
            
            if (!performanceData || performanceData.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = "14px Poppins";
                ctx.fillStyle = "#999";
                ctx.textAlign = "center";
                ctx.fillText("Belum ada data penilaian", ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            // Siapkan data untuk chart
            const labels = [];
            const scores = [];
            
            performanceData.forEach(item => {
                if (item.score) {
                    labels.push(formatDate(item.date || item.created_at || item.report_date));
                    scores.push(item.score);
                }
            });
            
            if (scores.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = "14px Poppins";
                ctx.fillStyle = "#999";
                ctx.textAlign = "center";
                ctx.fillText("Belum ada data penilaian", ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nilai Kinerja',
                        data: scores,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        fill: true,
                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 10
                            },
                            title: {
                                display: true,
                                text: 'Nilai'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Tanggal'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
        }
        
        // Render komentar terbaru
        function renderLatestComments(comments) {
            const container = document.getElementById('latestComments');
            
            if (!comments || comments.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p>Belum ada komentar</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            
            comments.forEach(comment => {
                const commentEl = document.createElement('div');
                commentEl.className = 'comment-item';
                
                commentEl.innerHTML = `
                    <div class="comment-author">${comment.evaluator_name || comment.evaluator || 'Penilai'}</div>
                    <div class="comment-date">${formatDate(comment.date || comment.created_at || comment.report_date)}</div>
                    <div class="comment-text">${comment.comment || 'Tidak ada komentar'}</div>
                    ${comment.score ? `<div><strong>Nilai: ${comment.score}</strong></div>` : ''}
                `;
                
                container.appendChild(commentEl);
            });
        }
        
        // Buka modal lihat laporan
        async function openViewModal(reportId) {
            try {
                showLoading(true, 'viewReportModal');
                
                // Kirim request untuk mendapatkan detail laporan
                const result = await sendDataToServer('', {
                    action: 'get_report_detail',
                    reportId: reportId
                });
                
                if (result.success) {
                    const report = result.data;
                    
                    document.getElementById('reportDetailContent').innerHTML = `
                        <div class="report-detail">
                            <h3>Laporan ${report.type === 'harian' ? 'Harian' : 'Mingguan'}</h3>
                            <p><strong>Tanggal:</strong> ${formatDate(report.date)}</p>
                            <p><strong>Status:</strong> 
                                <span class="status-badge ${report.status === 'dinilai' ? 'badge-success' : 'badge-warning'}">
                                    ${report.status === 'dinilai' ? 'Sudah Dinilai' : 'Menunggu Penilaian'}
                                </span>
                            </p>
                            ${report.score ? `<p><strong>Nilai:</strong> ${report.score}</p>` : ''}
                            <div style="margin-top: 15px;">
                                <h4>Isi Laporan:</h4>
                                <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #eee;">
                                    ${report.content.replace(/\n/g, '<br>')}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    openModal(document.getElementById('viewReportModal'));
                } else {
                    // Coba cari data dari array lokal
                    const report = reportsData.find(r => r.id == reportId);
                    if (report) {
                        document.getElementById('reportDetailContent').innerHTML = `
                            <div class="report-detail">
                                <h3>Laporan ${report.type === 'harian' ? 'Harian' : 'Mingguan'}</h3>
                                <p><strong>Tanggal:</strong> ${formatDate(report.date)}</p>
                                <p><strong>Status:</strong> 
                                    <span class="status-badge ${report.status === 'dinilai' ? 'badge-success' : 'badge-warning'}">
                                        ${report.status === 'dinilai' ? 'Sudah Dinilai' : 'Menunggu Penilaian'}
                                    </span>
                                </p>
                                ${report.score ? `<p><strong>Nilai:</strong> ${report.score}</p>` : ''}
                                <div style="margin-top: 15px;">
                                    <h4>Isi Laporan:</h4>
                                    <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #eee;">
                                        ${report.content.replace(/\n/g, '<br>')}
                                    </div>
                                </div>
                            </div>
                        `;
                        openModal(document.getElementById('viewReportModal'));
                    } else {
                        showToast('Gagal memuat detail laporan', 'error');
                    }
                }
            } catch (error) {
                console.error('Error opening view modal:', error);
                showToast('Terjadi kesalahan saat memuat detail laporan', 'error');
            } finally {
                showLoading(false, 'viewReportModal');
            }
        }
        
        // Buka modal evaluasi
        async function openEvaluationModal(reportId) {
            try {
                showLoading(true, 'evaluationModal');
                
                // Kirim request untuk mendapatkan evaluasi
                const result = await sendDataToServer('', {
                    action: 'get_report_evaluations',
                    reportId: reportId
                });
                
                if (result.success && result.data.length > 0) {
                    const evaluations = result.data;
                    
                    let evaluationHTML = '';
                    evaluations.forEach(eval => {
                        evaluationHTML += `
                            <div class="evaluation-item">
                                <h4>Penilaian oleh ${eval.evaluator_name || 'Penilai'}</h4>
                                <p><strong>Tanggal:</strong> ${formatDate(eval.created_at)}</p>
                                <p><strong>Nilai:</strong> ${eval.score}</p>
                                <p><strong>Komentar:</strong> ${eval.comment || 'Tidak ada komentar'}</p>
                            </div>
                        `;
                    });
                    
                    document.getElementById('evaluationContent').innerHTML = evaluationHTML;
                    openModal(document.getElementById('evaluationModal'));
                } else {
                    // Coba cari data dari array lokal
                    const evaluation = evaluationsData.find(e => e.report_id == reportId);
                    if (evaluation) {
                        document.getElementById('evaluationContent').innerHTML = `
                            <div class="evaluation-item">
                                <h4>Penilaian</h4>
                                <p><strong>Tanggal:</strong> ${formatDate(evaluation.created_at)}</p>
                                <p><strong>Nilai:</strong> ${evaluation.score}</p>
                                <p><strong>Komentar:</strong> ${evaluation.comment || 'Tidak ada komentar'}</p>
                            </div>
                        `;
                        openModal(document.getElementById('evaluationModal'));
                    } else {
                        document.getElementById('evaluationContent').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>Belum ada detail penilaian</p>
                            </div>
                        `;
                        openModal(document.getElementById('evaluationModal'));
                    }
                }
            } catch (error) {
                console.error('Error opening evaluation modal:', error);
                showToast('Terjadi kesalahan saat memuat detail penilaian', 'error');
            } finally {
                showLoading(false, 'evaluationModal');
            }
        }
        
        // Format tanggal
        function formatDate(dateString) {
            if (!dateString) return '-';
            
            try {
                const options = { day: 'numeric', month: 'long', year: 'numeric' };
                const date = new Date(dateString);
                
                // Cek jika date valid
                if (isNaN(date.getTime())) {
                    return dateString;
                }
                
                return date.toLocaleDateString('id-ID', options);
            } catch (error) {
                return dateString;
            }
        }
        
        // Update waktu terakhir refresh
        function updateLastRefreshTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('updateTime').textContent = timeString;
        }
        
        // Tampilkan loading
        function showLoading(isLoading, context = 'global') {
            if (context === 'global') {
                const refreshBtn = document.getElementById('btnRefresh');
                if (refreshBtn) {
                    refreshBtn.disabled = isLoading;
                    const icon = refreshBtn.querySelector('i');
                    if (isLoading) {
                        icon.className = 'fas fa-sync-alt fa-spin';
                    } else {
                        icon.className = 'fas fa-sync-alt';
                    }
                }
            } else if (context === 'reportForm') {
                const submitBtn = document.getElementById('btnSubmitReport');
                const submitText = document.getElementById('submitText');
                const submitLoading = document.getElementById('submitLoading');
                
                if (submitBtn) {
                    submitBtn.disabled = isLoading;
                }
                if (submitText && submitLoading) {
                    submitText.style.display = isLoading ? 'none' : 'inline';
                    submitLoading.style.display = isLoading ? 'inline-block' : 'none';
                }
            }
        }
        
        // Event listener untuk form laporan
        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const content = document.getElementById('reportContent').value.trim();
            
            if (!content) {
                showToast('Isi laporan tidak boleh kosong', 'error');
                return;
            }
            
            try {
                showLoading(true, 'reportForm');
                
                // Kirim form data dengan FormData
                const formData = new FormData(this);
                const result = await sendDataToServer('', Object.fromEntries(formData));
                
                if (result.success) {
                    showToast('Laporan berhasil disimpan!');
                    this.reset();
                    closeModal(document.getElementById('reportModal'));
                    await loadDashboardData(); // Muat ulang data
                } else {
                    showToast(result.message || 'Gagal menyimpan laporan', 'error');
                }
            } catch (error) {
                console.error('Error creating report:', error);
                showToast('Terjadi kesalahan saat menyimpan laporan', 'error');
            } finally {
                showLoading(false, 'reportForm');
            }
        });
        
        // Event listener untuk tombol buat laporan
        document.getElementById('btnCreateDaily').addEventListener('click', function() {
            document.getElementById('reportType').value = 'harian';
            const today = new Date();
            document.getElementById('reportDate').value = today.toISOString().split('T')[0];
            openModal(document.getElementById('reportModal'));
        });
        
        document.getElementById('btnCreateWeekly').addEventListener('click', function() {
            document.getElementById('reportType').value = 'mingguan';
            const today = new Date();
            document.getElementById('reportDate').value = today.toISOString().split('T')[0];
            openModal(document.getElementById('reportModal'));
        });
        
        // Event listener untuk tombol refresh
        document.getElementById('btnRefresh').addEventListener('click', function() {
            loadDashboardData();
        });
        
        // Event listener untuk tombol baru
        document.getElementById('btnNewReport').addEventListener('click', function() {
            // Buka modal dengan laporan harian sebagai default
            document.getElementById('btnCreateDaily').click();
        });
        
        // Fungsi untuk membuka modal
        function openModal(modal) {
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Fungsi untuk menutup modal
        function closeModal(modal) {
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Inisialisasi event listener untuk modal
        function initModalListeners() {
            // Tutup modal ketika klik tombol close
            document.querySelectorAll('.modal .close').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    closeModal(modal);
                });
            });
            
            // Tutup modal ketika klik di luar konten modal
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this);
                    }
                });
            });
            
            // Tombol tutup di modal
            document.getElementById('btnCloseView').addEventListener('click', function() {
                closeModal(document.getElementById('viewReportModal'));
            });
            
            document.getElementById('btnCloseEvaluation').addEventListener('click', function() {
                closeModal(document.getElementById('evaluationModal'));
            });
            
            // Tutup modal dengan ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal').forEach(modal => {
                        if (modal.style.display === 'block') {
                            closeModal(modal);
                        }
                    });
                }
            });
        }
        
        // Navigasi sidebar
        function initSidebarNavigation() {
            document.getElementById('navDashboard').addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            
            document.getElementById('navCreateReport').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('btnCreateDaily').click();
            });
            
            document.getElementById('navPerformance').addEventListener('click', function(e) {
                e.preventDefault();
                const performanceSection = document.getElementById('performanceChart').closest('.card');
                if (performanceSection) {
                    performanceSection.scrollIntoView({ behavior: 'smooth' });
                }
            });
            
            document.getElementById('navComments').addEventListener('click', function(e) {
                e.preventDefault();
                const commentsSection = document.getElementById('latestComments').closest('.card');
                if (commentsSection) {
                    commentsSection.scrollIntoView({ behavior: 'smooth' });
                }
            });
            
            document.getElementById('navExport').addEventListener('click', function(e) {
                e.preventDefault();
                showToast('Fitur export akan segera hadir!', 'error');
            });
        }
        
        // Auto-refresh data setiap 30 detik
        function startAutoRefresh() {
            setInterval(() => {
                loadDashboardData();
            }, 30000); // 30 detik
        }
        
        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            initModalListeners();
            initSidebarNavigation();
            
            // Render data awal dari PHP
            renderReportsTable(reportsData);
            renderPerformanceChart(evaluationsData);
            renderLatestComments(commentsData);
            updateLastRefreshTime();
            
            // Set tanggal default untuk form laporan
            const today = new Date();
            document.getElementById('reportDate').value = today.toISOString().split('T')[0];
            
            // Start auto refresh
            startAutoRefresh();
        });
    </script>
</body>
</html>
