<?php
// api.php
require_once 'config.php';

// Set header JSON pertama kali
header("Content-Type: application/json");

// Handle preflight CORS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit;
}

header("Access-Control-Allow-Origin: *");

// Ambil method request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Handle request berdasarkan action
try {
    $pdo = getDBConnection();
    
    if ($method == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Data JSON tidak valid');
        }
        
        switch($action) {
            case 'login':
                handleLogin($pdo, $input);
                break;
            case 'create_report':
                handleCreateReport($pdo, $input);
                break;
            case 'evaluate_report':
                handleEvaluateReport($pdo, $input);
                break;
            case 'add_user':
                handleAddUser($pdo, $input);
                break;
            default:
                jsonResponse(false, [], 'Action tidak dikenali');
        }
    } else if ($method == 'GET') {
        switch($action) {
            case 'get_reports':
                handleGetReports($pdo, $_GET);
                break;
            case 'get_performance':
                handleGetPerformance($pdo, $_GET);
                break;
            case 'get_comments':
                handleGetComments($pdo, $_GET);
                break;
            case 'get_report_detail':
                handleGetReportDetail($pdo, $_GET);
                break;
            case 'get_report_evaluations':
                handleGetReportEvaluations($pdo, $_GET);
                break;
            case 'get_total_reports':
                handleGetTotalReports($pdo, $_GET);
                break;
            case 'get_teacher_performance':
                handleGetTeacherPerformance($pdo, $_GET);
                break;
            case 'get_teachers':
                handleGetTeachers($pdo);
                break;
            case 'get_latest_reports':
                handleGetLatestReports($pdo, $_GET);
                break;
            case 'get_notifications':
                handleGetNotifications($pdo, $_GET);
                break;
            case 'get_filtered_reports':
                handleGetFilteredReports($pdo, $_GET);
                break;
            case 'get_activities':
                handleGetActivities($pdo, $_GET);
                break;
            default:
                jsonResponse(false, [], 'Action tidak dikenali');
        }
    } else {
        jsonResponse(false, [], 'Method tidak didukung');
    }
} catch (Exception $e) {
    jsonResponse(false, [], $e->getMessage());
}

// Fungsi-fungsi handler
function handleLogin($pdo, $data) {
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';
    
    if (empty($email) || empty($password) || empty($role)) {
        jsonResponse(false, [], 'Email, password, dan role harus diisi');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        jsonResponse(true, [
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role']
            ]
        ], 'Login berhasil');
    } else {
        jsonResponse(false, [], 'Email, password, atau role salah');
    }
}

function handleCreateReport($pdo, $data) {
    $userId = $data['userId'] ?? '';
    $type = $data['type'] ?? '';
    $date = $data['date'] ?? '';
    $content = $data['content'] ?? '';
    
    if (empty($userId) || empty($type) || empty($date) || empty($content)) {
        jsonResponse(false, [], 'Data tidak lengkap');
    }
    
    $id = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO reports (id, user_id, date, type, content, status) VALUES (?, ?, ?, ?, ?, 'menunggu')");
    $stmt->execute([$id, $userId, $date, $type, $content]);
    
    // Catat aktivitas
    logActivity($pdo, $userId, "Membuat laporan $type");
    
    jsonResponse(true, ['id' => $id], 'Laporan berhasil dibuat');
}

function handleEvaluateReport($pdo, $data) {
    $reportId = $data['reportId'] ?? '';
    $evaluatorId = $data['evaluatorId'] ?? '';
    $score = $data['score'] ?? '';
    $comment = $data['comment'] ?? '';
    
    if (empty($reportId) || empty($evaluatorId) || empty($score)) {
        jsonResponse(false, [], 'Data tidak lengkap');
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
    
    jsonResponse(true, [], 'Penilaian berhasil disimpan');
}

function handleAddUser($pdo, $data) {
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $role = $data['role'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($role) || empty($password)) {
        jsonResponse(false, [], 'Data tidak lengkap');
    }
    
    // Cek apakah email sudah ada
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, [], 'Email sudah terdaftar');
    }
    
    $id = generateUUID();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (id, email, password, role, name) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id, $email, $hashedPassword, $role, $name]);
    
    // Catat aktivitas
    logActivity($pdo, 'system', "Menambahkan pengguna baru: $name");
    
    jsonResponse(true, ['id' => $id], 'Pengguna berhasil ditambahkan');
}

function handleGetReports($pdo, $data) {
    $userId = $data['userId'] ?? '';
    
    if (empty($userId)) {
        jsonResponse(false, [], 'User ID harus diisi');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $reports = $stmt->fetchAll();
    
    jsonResponse(true, $reports, 'Data laporan berhasil diambil');
}

function handleGetPerformance($pdo, $data) {
    $userId = $data['userId'] ?? '';
    
    if (empty($userId)) {
        jsonResponse(false, [], 'User ID harus diisi');
    }
    
    $stmt = $pdo->prepare("
        SELECT r.date, e.score 
        FROM evaluations e 
        JOIN reports r ON e.report_id = r.id 
        WHERE r.user_id = ? 
        ORDER BY r.date
    ");
    $stmt->execute([$userId]);
    $performance = $stmt->fetchAll();
    
    jsonResponse(true, $performance, 'Data performa berhasil diambil');
}

function handleGetComments($pdo, $data) {
    $userId = $data['userId'] ?? '';
    
    if (empty($userId)) {
        jsonResponse(false, [], 'User ID harus diisi');
    }
    
    $stmt = $pdo->prepare("
        SELECT u.name as evaluator_name, e.created_at as date, e.comment, e.score
        FROM evaluations e 
        JOIN users u ON e.evaluator_id = u.id 
        JOIN reports r ON e.report_id = r.id 
        WHERE r.user_id = ? AND e.comment IS NOT NULL 
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $comments = $stmt->fetchAll();
    
    jsonResponse(true, $comments, 'Data komentar berhasil diambil');
}

function handleGetReportDetail($pdo, $data) {
    $reportId = $data['reportId'] ?? '';
    
    if (empty($reportId)) {
        jsonResponse(false, [], 'Report ID harus diisi');
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
        jsonResponse(true, $report, 'Detail laporan berhasil diambil');
    } else {
        jsonResponse(false, [], 'Laporan tidak ditemukan');
    }
}

function handleGetReportEvaluations($pdo, $data) {
    $reportId = $data['reportId'] ?? '';
    
    if (empty($reportId)) {
        jsonResponse(false, [], 'Report ID harus diisi');
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
    
    jsonResponse(true, $evaluations, 'Evaluasi laporan berhasil diambil');
}

// Fungsi-fungsi baru untuk endpoint yang dibutuhkan
function handleGetTotalReports($pdo, $data) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reports");
    $stmt->execute();
    $result = $stmt->fetch();
    
    jsonResponse(true, ['total' => $result['total']], 'Total laporan berhasil diambil');
}

function handleGetTeacherPerformance($pdo, $data) {
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
    
    jsonResponse(true, $performance, 'Data performa guru berhasil diambil');
}

function handleGetTeachers($pdo) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'guru'");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
    
    jsonResponse(true, $teachers, 'Data guru berhasil diambil');
}

function handleGetLatestReports($pdo, $data) {
    $limit = $data['limit'] ?? 10;
    
    $stmt = $pdo->prepare("
        SELECT r.id, r.date, r.type, r.status, r.score, u.name as teacher
        FROM reports r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $reports = $stmt->fetchAll();
    
    jsonResponse(true, $reports, 'Laporan terbaru berhasil diambil');
}

function handleGetNotifications($pdo, $data) {
    $userId = $data['userId'] ?? '';
    
    if (empty($userId)) {
        jsonResponse(false, [], 'User ID harus diisi');
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    jsonResponse(true, $notifications, 'Notifikasi berhasil diambil');
}

function handleGetFilteredReports($pdo, $data) {
    $status = $data['status'] ?? '';
    $teacherId = $data['teacherId'] ?? '';
    $month = $data['month'] ?? '';
    
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
    
    jsonResponse(true, $reports, 'Laporan berhasil diambil dengan filter');
}

function handleGetActivities($pdo, $data) {
    $limit = $data['limit'] ?? 10;
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as user_name 
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $activities = $stmt->fetchAll();
    
    jsonResponse(true, $activities, 'Aktivitas berhasil diambil');
}

function logActivity($pdo, $userId, $action) {
    $id = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO activities (id, user_id, action) VALUES (?, ?, ?)");
    $stmt->execute([$id, $userId, $action]);
}

function getUsername($pdo, $userId) {
    if ($userId === 'system') return 'System';
    
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user ? $user['name'] : 'Unknown';
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function jsonResponse($success, $data, $message) {
    // Pastikan tidak ada output sebelumnya
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

?>