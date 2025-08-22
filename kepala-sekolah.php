<?php
// ==================== PHP BACKEND ====================
session_start();

// Konfigurasi Database MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'sipeka_db');
define('DB_USER', 'root'); // Ganti dengan username database Anda
define('DB_PASS', '');     // Ganti dengan password database Anda

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

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'get_total_reports':
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reports");
                $stmt->execute();
                $result = $stmt->fetch();
                echo json_encode(['success' => true, 'data' => ['total' => $result['total']]]);
                break;
                
            case 'get_teacher_performance':
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name as teacher, COALESCE(AVG(e.score), 0) as averageScore
                    FROM users u
                    LEFT JOIN reports r ON u.id = r.user_id
                    LEFT JOIN evaluations e ON r.id = e.report_id
                    WHERE u.role = 'guru'
                    GROUP BY u.id, u.name
                    ORDER BY averageScore DESC
                ");
                $stmt->execute();
                $performance = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $performance]);
                break;
                
            case 'get_teachers':
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'guru'");
                $stmt->execute();
                $teachers = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $teachers]);
                break;
                
            case 'get_latest_reports':
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    // Fixed: Use string concatenation for LIMIT as it can't be parameterized
    $query = "
        SELECT r.id, r.date, r.type, r.status, r.score, u.name as teacher
        FROM reports r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT " . intval($limit); // Convert to integer for safety
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $reports = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $reports]);
    break;

case 'get_activities':
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    // Fixed: Use string concatenation for LIMIT as it can't be parameterized
    $query = "
        SELECT a.*, u.name as user_name 
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT " . intval($limit); // Convert to integer for safety
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $activities = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $activities]);
    break;
                
            case 'get_notifications':
                $userId = $_GET['userId'] ?? '';
                if (empty($userId)) {
                    echo json_encode(['success' => false, 'message' => 'User ID harus diisi']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$userId]);
                $notifications = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $notifications]);
                break;
                
            case 'get_activities':
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $stmt = $pdo->prepare("
                    SELECT a.*, u.name as user_name 
                    FROM activities a
                    LEFT JOIN users u ON a.user_id = u.id
                    ORDER BY a.created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $activities = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $activities]);
                break;
                
            case 'get_report_detail':
                $reportId = $_GET['reportId'] ?? '';
                if (empty($reportId)) {
                    echo json_encode(['success' => false, 'message' => 'Report ID harus diisi']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    SELECT r.*, u.name as teacher_name 
                    FROM reports r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.id = ?
                ");
                $stmt->execute([$reportId]);
                $report = $stmt->fetch();
                
                if ($report) {
                    echo json_encode(['success' => true, 'data' => $report]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Laporan tidak ditemukan']);
                }
                break;
                
            case 'get_report_evaluations':
                $reportId = $_GET['reportId'] ?? '';
                if (empty($reportId)) {
                    echo json_encode(['success' => false, 'message' => 'Report ID harus diisi']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    SELECT e.*, u.name as evaluator_name 
                    FROM evaluations e 
                    JOIN users u ON e.evaluator_id = u.id 
                    WHERE e.report_id = ?
                    ORDER BY e.created_at DESC
                ");
                $stmt->execute([$reportId]);
                $evaluations = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $evaluations]);
                break;
                
            case 'get_filtered_reports':
                $status = $_GET['status'] ?? '';
                $teacherId = $_GET['teacherId'] ?? '';
                $month = $_GET['month'] ?? '';
                
                $query = "
                    SELECT r.id, u.name as teacher, r.date, r.type, r.status, r.score 
                    FROM reports r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE 1=1
                ";
                
                $params = [];
                
                if (!empty($status) && $status !== 'semua') {
                    $query .= " AND r.status = ?";
                    $params[] = $status;
                }
                
                if (!empty($teacherId) && $teacherId !== 'semua') {
                    $query .= " AND r.user_id = ?";
                    $params[] = $teacherId;
                }
                
                if (!empty($month)) {
                    $query .= " AND DATE_FORMAT(r.date, '%Y-%m') = ?";
                    $params[] = $month;
                }
                
                $query .= " ORDER BY r.created_at DESC";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $reports = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $reports]);
                break;
                
            case 'evaluate_report':
                $input = json_decode(file_get_contents('php://input'), true);
                $reportId = $input['reportId'] ?? '';
                $evaluatorId = $input['evaluatorId'] ?? '';
                $score = $input['score'] ?? '';
                $comment = $input['comment'] ?? '';
                
                if (empty($reportId) || empty($evaluatorId) || empty($score)) {
                    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
                    break;
                }
                
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
                
                echo json_encode(['success' => true, 'message' => 'Penilaian berhasil disimpan']);
                break;
                
            case 'export_reports':
                $status = $_GET['status'] ?? '';
                $teacherId = $_GET['teacherId'] ?? '';
                $month = $_GET['month'] ?? '';
                
                $query = "
                    SELECT r.*, u.name as teacher_name 
                    FROM reports r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE 1=1
                ";
                
                $params = [];
                
                if (!empty($status) && $status !== 'semua') {
                    $query .= " AND r.status = ?";
                    $params[] = $status;
                }
                
                if (!empty($teacherId) && $teacherId !== 'semua') {
                    $query .= " AND r.user_id = ?";
                    $params[] = $teacherId;
                }
                
                if (!empty($month)) {
                    $query .= " AND DATE_FORMAT(r.date, '%Y-%m') = ?";
                    $params[] = $month;
                }
                
                $query .= " ORDER BY r.created_at DESC";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $reports = $stmt->fetchAll();
                
                // Header untuk file CSV
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=laporan_guru_' . date('Y-m-d') . '.csv');
                
                $output = fopen('php://output', 'w');
                
                // Header CSV
                fputcsv($output, [
                    'ID Laporan', 
                    'Nama Guru', 
                    'Tanggal', 
                    'Jenis Laporan', 
                    'Status', 
                    'Nilai', 
                    'Isi Laporan',
                    'Tanggal Dibuat'
                ]);
                
                // Data CSV
                foreach ($reports as $report) {
                    fputcsv($output, [
                        $report['id'],
                        $report['teacher_name'],
                        $report['date'],
                        $report['type'],
                        $report['status'],
                        $report['score'] ?? 'Belum dinilai',
                        strip_tags($report['content']),
                        $report['created_at']
                    ]);
                }
                
                fclose($output);
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
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

// Set user default sebagai kepala sekolah
$user = [
    'id' => 'system',
    'name' => 'Kepala Sekolah',
    'role' => 'kepala-sekolah'
];
$_SESSION['user'] = $user;

// Cek role
if ($user['role'] !== 'kepala-sekolah') {
    echo "Akses ditolak. Hanya kepala sekolah yang dapat mengakses halaman ini.";
    exit;
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

            <div class="dashboard-grid">
                <section class="card">
                    <h2>Total Laporan Masuk</h2>
                    <div class="stat-number" id="totalReports">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>Rata-rata Nilai</h2>
                    <div class="stat-number" id="averageScore">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                        </div>
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
                            <select id="filterStatus">
                                <option value="semua">Semua Status</option>
                                <option value="menunggu">Menunggu Penilaian</option>
                                <option value="dinilai">Sudah Dinilai</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filterTeacher">Guru</label>
                            <select id="filterTeacher">
                                <option value="semua">Semua Guru</option>
                                <!-- Options akan diisi oleh JavaScript -->
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filterDate">Bulan</label>
                            <input type="month" id="filterDate">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-sm btn-primary" id="btnApplyFilter">Terapkan Filter</button>
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
                                <tr>
                                    <td colspan="5" class="loading">
                                        <div class="loading-spinner"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="export-section">
                        <h3>Export Laporan</h3>
                        <p>Export laporan yang telah difilter ke format CSV</p>
                        <button class="btn btn-secondary" id="btnExportReports">
                            <i class="fas fa-file-export"></i> Export ke CSV
                        </button>
                    </div>
                </section>

                <section class="card">
                    <h2>Notifikasi</h2>
                    <div class="notifications-container" id="notifications">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>Aktivitas Terbaru</h2>
                    <div class="notifications-container" id="activities">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Modal Nilai Laporan -->
    <div id="evaluateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Penilaian Laporan</h2>
            <div id="reportDetails" class="report-content">
                <div class="loading">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            <form id="evaluateForm">
                <input type="hidden" id="reportId">
                <div class="form-group">
                    <label for="reportScore">Nilai (0-100)</label>
                    <input type="number" id="reportScore" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label for="reportComment">Komentar</label>
                    <textarea id="reportComment" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Penilaian</button>
            </form>
        </div>
    </div>

    <!-- Modal Lihat Laporan -->
    <div id="viewReportModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Detail Laporan</h2>
            <div id="fullReportDetails" class="report-content">
                <div class="loading">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            <div class="form-group">
                <button class="btn btn-secondary" id="btnCloseView">Tutup</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Konfigurasi API - menggunakan file yang sama
        const API_BASE_URL = window.location.href;
        const currentUser = <?= json_encode($user) ?>;

        // Variabel global untuk data
        let reportsData = [];
        let teachersData = [];
        let teacherPerformanceData = [];

        // Fungsi untuk mengambil data dari API
        async function fetchData(action, params = {}) {
            try {
                const urlParams = new URLSearchParams({action, ...params});
                const response = await fetch(`${API_BASE_URL}?${urlParams}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Terjadi kesalahan');
                }
                
                return result;
            } catch (error) {
                console.error('Error fetching data:', error);
                showError('Terjadi kesalahan: ' + error.message);
                throw error;
            }
        }

        // Fungsi untuk mengirim data ke API
        async function postData(action, data) {
            try {
                const response = await fetch(API_BASE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({action, ...data})
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Terjadi kesalahan');
                }
                
                return result;
            } catch (error) {
                console.error('Error posting data:', error);
                showError('Terjadi kesalahan: ' + error.message);
                throw error;
            }
        }

        // Muat data dashboard
        async function loadDashboardData() {
            try {
                // Muat total laporan
                const totalData = await fetchData('get_total_reports');
                document.getElementById('totalReports').textContent = totalData.data.total || 0;

                // Muat rata-rata nilai
                const performanceData = await fetchData('get_teacher_performance');
                if (performanceData.data && performanceData.data.length > 0) {
                    const averageScore = performanceData.data.reduce((sum, item) => sum + parseFloat(item.averageScore), 0) / performanceData.data.length;
                    document.getElementById('averageScore').textContent = isNaN(averageScore) ? '0' : averageScore.toFixed(1);
                } else {
                    document.getElementById('averageScore').textContent = '0';
                }

                // Muat grafik performa guru
                teacherPerformanceData = performanceData.data || [];
                renderTeacherPerformanceChart(teacherPerformanceData);

                // Muat daftar guru
                const teachersResponse = await fetchData('get_teachers');
                teachersData = teachersResponse.data || [];
                populateTeacherFilter(teachersData);

                // Muat laporan terbaru
                await loadReports();

                // Muat notifikasi
                const notificationsData = await fetchData('get_notifications', {userId: currentUser.id});
                renderNotifications(notificationsData.data || []);

                // Muat aktivitas
                const activitiesData = await fetchData('get_activities', {limit: 5});
                renderActivities(activitiesData.data || []);
            } catch (error) {
                console.error('Error loading dashboard data:', error);
                showError('Terjadi kesalahan saat memuat data dashboard');
            }
        }

        // Muat data laporan
        async function loadReports() {
            try {
                const reportsResponse = await fetchData('get_latest_reports', {limit: 10});
                reportsData = reportsResponse.data || [];
                renderLatestReportsTable(reportsData);
            } catch (error) {
                console.error('Error loading reports:', error);
                showError('Terjadi kesalahan saat memuat data laporan');
                
                // Tampilkan pesan error di tabel
                const tbody = document.querySelector('#latestReportsTable tbody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: var(--danger-color);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            <p>Gagal memuat data laporan</p>
                            <button class="btn btn-sm btn-primary" onclick="loadReports()">Coba Lagi</button>
                        </td>
                    </tr>
                `;
            }
        }

        // Isi filter guru
        function populateTeacherFilter(teachers) {
            const filterTeacher = document.getElementById('filterTeacher');
            filterTeacher.innerHTML = '<option value="semua">Semua Guru</option>';
            
            teachers.forEach(teacher => {
                const option = document.createElement('option');
                option.value = teacher.id;
                option.textContent = teacher.name;
                filterTeacher.appendChild(option);
            });
        }

        // Render grafik performa guru
        function renderTeacherPerformanceChart(performanceData) {
            const ctx = document.getElementById('teacherPerformanceChart').getContext('2d');
            
            // Hapus chart sebelumnya jika ada
            if (window.teacherPerformanceChartInstance) {
                window.teacherPerformanceChartInstance.destroy();
            }
            
            if (!performanceData || performanceData.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = "14px Arial";
                ctx.fillStyle = "#999";
                ctx.textAlign = "center";
                ctx.fillText("Belum ada data penilaian", ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            const teachers = performanceData.map(item => item.teacher);
            const scores = performanceData.map(item => item.averageScore);
            
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

        // Render tabel laporan terbaru
        function renderLatestReportsTable(reports) {
            const tbody = document.querySelector('#latestReportsTable tbody');
            
            if (!reports || reports.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            <i class="fas fa-inbox" style="font-size: 2rem; color: var(--gray-color); margin-bottom: 10px; display: block;"></i>
                            <p>Belum ada laporan</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = '';
            
            reports.forEach(report => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${report.teacher || 'Tidak diketahui'}</td>
                    <td>${formatDate(report.date)}</td>
                    <td>${report.type === 'harian' ? 'Harian' : 'Mingguan'}</td>
                    <td>
                        <span class="status-badge ${report.status === 'dinilai' ? 'badge-success' : 'badge-warning'}">
                            ${report.status === 'dinilai' ? 'Sudah dinilai' : 'Belum dinilai'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-id="${report.id}" data-action="view">
                            <i class="fas fa-eye"></i> Lihat
                        </button>
                        ${report.status !== 'dinilai' ? 
                            `<button class="btn btn-sm btn-secondary" data-id="${report.id}" data-action="evaluate" style="margin-left: 5px;">
                                <i class="fas fa-star"></i> Nilai
                            </button>` : ''
                        }
                    </td>
                `;
                
                // Tambahkan event listener untuk tombol
                const buttons = row.querySelectorAll('button');
                buttons.forEach(button => {
                    button.addEventListener('click', function() {
                        const reportId = this.getAttribute('data-id');
                        const action = this.getAttribute('data-action');
                        
                        if (action === 'evaluate') {
                            openEvaluateModal(reportId);
                        } else {
                            openViewModal(reportId);
                        }
                    });
                });
                
                tbody.appendChild(row);
            });
        }

        // Render notifikasi
        function renderNotifications(notifications) {
            const container = document.getElementById('notifications');
            
            if (!notifications || notifications.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--gray-color);">
                        <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        <p>Tidak ada notifikasi</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            
            notifications.forEach(notification => {
                const notificationEl = document.createElement('div');
                notificationEl.className = 'notification-item';
                
                notificationEl.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas ${notification.icon || 'fa-bell'}"></i>
                    </div>
                    <div class="notification-text">${notification.message || 'Tidak ada pesan'}</div>
                    <div class="notification-time">${formatTimeAgo(notification.created_at)}</div>
                `;
                
                container.appendChild(notificationEl);
            });
        }

        // Render aktivitas
        function renderActivities(activities) {
            const container = document.getElementById('activities');
            
            if (!activities || activities.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--gray-color);">
                        <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        <p>Tidak ada aktivitas</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            
            activities.forEach(activity => {
                const activityEl = document.createElement('div');
                activityEl.className = 'notification-item';
                
                activityEl.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="notification-text">
                        <strong>${activity.user_name || 'System'}</strong>: ${activity.action || 'Tidak ada aksi'}
                    </div>
                    <div class="notification-time">${formatTimeAgo(activity.created_at)}</div>
                `;
                
                container.appendChild(activityEl);
            });
        }

        // Buka modal penilaian
        async function openEvaluateModal(reportId) {
            try {
                document.getElementById('reportDetails').innerHTML = `
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                `;
                
                openModal(document.getElementById('evaluateModal'));
                
                const reportResponse = await fetchData('get_report_detail', {reportId});
                const report = reportResponse.data;
                
                document.getElementById('reportId').value = reportId;
                document.getElementById('reportDetails').innerHTML = `
                    <h3>Laporan ${report.type === 'harian' ? 'Harian' : 'Mingguan'} - ${report.teacher_name || 'Tidak diketahui'}</h3>
                    <p><strong>Tanggal:</strong> ${formatDate(report.date)}</p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge ${report.status === 'dinilai' ? 'badge-success' : 'badge-warning'}">
                            ${report.status === 'dinilai' ? 'Sudah dinilai' : 'Belum dinilai'}
                        </span>
                    </p>
                    <div style="margin-top: 15px;">
                        <h4>Isi Laporan:</h4>
                        <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #eee;">
                            ${report.content ? report.content.replace(/\n/g, '<br>') : 'Tidak ada konten'}
                        </div>
                    </div>
                `;
                
                // Reset form
                document.getElementById('reportScore').value = '';
                document.getElementById('reportComment').value = '';
                
            } catch (error) {
                console.error('Error opening evaluate modal:', error);
                showError('Terjadi kesalahan saat membuka form penilaian');
                closeModal(document.getElementById('evaluateModal'));
            }
        }

        // Buka modal lihat laporan
        async function openViewModal(reportId) {
            try {
                document.getElementById('fullReportDetails').innerHTML = `
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                `;
                
                openModal(document.getElementById('viewReportModal'));
                
                const reportResponse = await fetchData('get_report_detail', {reportId});
                const report = reportResponse.data;
                
                // Dapatkan evaluasi jika ada
                let evaluationsHTML = '';
                if (report.status === 'dinilai') {
                    try {
                        const evaluationsResponse = await fetchData('get_report_evaluations', {reportId});
                        if (evaluationsResponse.data && evaluationsResponse.data.length > 0) {
                            evaluationsHTML = '<div style="margin-top: 15px;"><h4>Evaluasi:</h4>';
                            evaluationsResponse.data.forEach(evaluation => {
                                evaluationsHTML += `
                                    <div class="evaluation-item" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                        <p><strong>Penilai:</strong> ${evaluation.evaluator_name || 'Tidak diketahui'}</p>
                                        <p><strong>Nilai:</strong> ${evaluation.score || '0'}</p>
                                        <p><strong>Komentar:</strong> ${evaluation.comment || 'Tidak ada komentar'}</p>
                                        <p><strong>Tanggal:</strong> ${formatDate(evaluation.created_at)}</p>
                                    </div>
                                `;
                            });
                            evaluationsHTML += '</div>';
                        }
                    } catch (error) {
                        console.error('Error loading evaluations:', error);
                        evaluationsHTML = '<p style="color: var(--danger-color);">Gagal memuat evaluasi</p>';
                    }
                }
                
                document.getElementById('fullReportDetails').innerHTML = `
                    <h3>Laporan ${report.type === 'harian' ? 'Harian' : 'Mingguan'} - ${report.teacher_name || 'Tidak diketahui'}</h3>
                    <p><strong>Tanggal:</strong> ${formatDate(report.date)}</p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge ${report.status === 'dinilai' ? 'badge-success' : 'badge-warning'}">
                            ${report.status === 'dinilai' ? 'Sudah dinilai' : 'Belum dinilai'}
                        </span>
                    </p>
                    ${report.score ? `<p><strong>Nilai:</strong> ${report.score}</p>` : ''}
                    <div style="margin-top: 15px;">
                        <h4>Isi Laporan:</h4>
                        <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #eee;">
                            ${report.content ? report.content.replace(/\n/g, '<br>') : 'Tidak ada konten'}
                        </div>
                    </div>
                    ${evaluationsHTML}
                `;
                
            } catch (error) {
                console.error('Error opening view modal:', error);
                showError('Terjadi kesalahan saat membuka detail laporan');
                closeModal(document.getElementById('viewReportModal'));
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

        // Format waktu (contoh: "5 menit yang lalu")
        function formatTimeAgo(timestamp) {
            if (!timestamp) return '';
            
            try {
                const now = new Date();
                const past = new Date(timestamp);
                
                // Cek jika timestamp valid
                if (isNaN(past.getTime())) {
                    return timestamp;
                }
                
                const diff = now - past;
                
                const seconds = Math.floor(diff / 1000);
                const minutes = Math.floor(seconds / 60);
                const hours = Math.floor(minutes / 60);
                const days = Math.floor(hours / 24);
                
                if (days > 0) return `${days} hari yang lalu`;
                if (hours > 0) return `${hours} jam yang lalu`;
                if (minutes > 0) return `${minutes} menit yang lalu`;
                return `${seconds} detik yang lalu`;
            } catch (error) {
                return timestamp;
            }
        }

        // Tampilkan error
        function showError(message) {
            // Buat notifikasi error
            const errorDiv = document.createElement('div');
            errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: var(--danger-color);
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                z-index: 10000;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                max-width: 300px;
            `;
            errorDiv.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; margin-left: 15px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(errorDiv);
            
            // Hapus otomatis setelah 5 detik
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }

        // Tampilkan sukses
        function showSuccess(message) {
            // Buat notifikasi sukses
            const successDiv = document.createElement('div');
            successDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: var(--secondary-color);
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                z-index: 10000;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                max-width: 300px;
            `;
            successDiv.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center;">
                        <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; margin-left: 15px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(successDiv);
            
            // Hapus otomatis setelah 5 detik
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                }
            }, 5000);
        }

        // Event listener untuk form penilaian
        document.getElementById('evaluateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const reportId = document.getElementById('reportId').value;
            const score = document.getElementById('reportScore').value;
            const comment = document.getElementById('reportComment').value;
            
            if (!score || score < 0 || score > 100) {
                showError('Nilai harus antara 0 dan 100');
                return;
            }
            
            try {
                const result = await postData('evaluate_report', {
                    reportId: reportId,
                    evaluatorId: currentUser.id,
                    score: score,
                    comment: comment
                });
                
                showSuccess('Penilaian berhasil disimpan');
                closeModal(document.getElementById('evaluateModal'));
                await loadDashboardData(); // Muat ulang data
            } catch (error) {
                console.error('Error evaluating report:', error);
                showError('Terjadi kesalahan saat menyimpan penilaian');
            }
        });

        // Event listener untuk filter
        document.getElementById('btnApplyFilter').addEventListener('click', async function() {
            const status = document.getElementById('filterStatus').value;
            const teacherId = document.getElementById('filterTeacher').value;
            const date = document.getElementById('filterDate').value;
            const month = date ? date.substring(0, 7) : ''; // Format YYYY-MM
            
            try {
                const filteredResponse = await fetchData('get_filtered_reports', {
                    status: status !== 'semua' ? status : '',
                    teacherId: teacherId !== 'semua' ? teacherId : '',
                    month: month
                });
                
                renderLatestReportsTable(filteredResponse.data);
            } catch (error) {
                console.error('Error applying filter:', error);
                showError('Terjadi kesalahan saat menerapkan filter');
            }
        });

        // Event listener untuk export laporan
        document.getElementById('btnExportReports').addEventListener('click', function() {
            const status = document.getElementById('filterStatus').value;
            const teacherId = document.getElementById('filterTeacher').value;
            const date = document.getElementById('filterDate').value;
            const month = date ? date.substring(0, 7) : ''; // Format YYYY-MM
            
            const params = new URLSearchParams({
                action: 'export_reports',
                status: status !== 'semua' ? status : '',
                teacherId: teacherId !== 'semua' ? teacherId : '',
                month: month
            });
            
            window.location.href = `${API_BASE_URL}?${params}`;
        });

        // Event listener untuk tombol lihat semua laporan
        document.getElementById('btnViewReports').addEventListener('click', function() {
            // Reset filter
            document.getElementById('filterStatus').value = 'semua';
            document.getElementById('filterTeacher').value = 'semua';
            document.getElementById('filterDate').value = '';
            
            // Tampilkan semua laporan
            renderLatestReportsTable(reportsData);
        });

        // Event listener untuk tombol nilai laporan
        document.getElementById('btnEvaluateReports').addEventListener('click', function() {
            // Filter hanya laporan yang belum dinilai
            const pendingReports = reportsData.filter(report => report.status !== 'dinilai');
            
            if (pendingReports.length === 0) {
                showError('Tidak ada laporan yang perlu dinilai');
                return;
            }
            
            // Set filter ke "menunggu"
            document.getElementById('filterStatus').value = 'menunggu';
            
            // Terapkan filter
            document.getElementById('btnApplyFilter').click();
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
            
            // Tombol tutup di modal lihat laporan
            document.getElementById('btnCloseView').addEventListener('click', function() {
                closeModal(document.getElementById('viewReportModal'));
            });
        }

        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            initModalListeners();
            loadDashboardData();
            
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
                // Scroll ke bagian filter
                document.querySelector('.filter-section').scrollIntoView({ behavior: 'smooth' });
            });

            document.getElementById('exportReportsLink').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('btnExportReports').click();
            });
        });
    </script>
</body>
</html>