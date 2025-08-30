<?php
require_once 'config.php';

// Pastikan user sudah login dan memiliki role admin
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Fungsi untuk mendapatkan data dari database
function getTotalUsers($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getTotalReports($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reports");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getTotalEvaluations($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM evaluations");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getRecentActivities($pdo) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as user_name, u.role as user_role 
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPerformanceOverview($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(r.created_at, '%Y-%m') as month,
            COUNT(r.id) as reports,
            COUNT(e.id) as evaluations
        FROM reports r
        LEFT JOIN evaluations e ON r.id = e.report_id
        WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAdminNotifications($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id IS NULL OR user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllUsers($pdo) {
    $stmt = $pdo->prepare("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();
    
    // Tambah pengguna
    if (isset($_POST['add_user'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (id, name, email, role, password) VALUES (UUID(), ?, ?, ?, ?)");
        $success = $stmt->execute([$name, $email, $role, $password]);
        
        if ($success) {
            // Tambahkan aktivitas
            $stmt = $pdo->prepare("INSERT INTO activities (id, user_id, action) VALUES (UUID(), ?, ?)");
            $stmt->execute([$user['id'], "Menambah pengguna baru: $name"]);
            
            $_SESSION['message'] = "Pengguna berhasil ditambahkan";
        } else {
            $_SESSION['error'] = "Gagal menambahkan pengguna";
        }
        
        header("Location: admin.php");
        exit;
    }
    
    // Edit pengguna
    if (isset($_POST['edit_user'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $success = $stmt->execute([$name, $email, $role, $password, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $success = $stmt->execute([$name, $email, $role, $id]);
        }
        
        if ($success) {
            // Tambahkan aktivitas
            $stmt = $pdo->prepare("INSERT INTO activities (id, user_id, action) VALUES (UUID(), ?, ?)");
            $stmt->execute([$user['id'], "Memperbarui data pengguna: $name"]);
            
            $_SESSION['message'] = "Pengguna berhasil diperbarui";
        } else {
            $_SESSION['error'] = "Gagal memperbarui pengguna";
        }
        
        header("Location: admin.php");
        exit;
    }
    
    // Hapus pengguna
    if (isset($_POST['delete_user'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        
        // Pastikan admin tidak menghapus dirinya sendiri
        if ($id !== $user['id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                // Tambahkan aktivitas
                $stmt = $pdo->prepare("INSERT INTO activities (id, user_id, action) VALUES (UUID(), ?, ?)");
                $stmt->execute([$user['id'], "Menghapus pengguna: $name"]);
                
                $_SESSION['message'] = "Pengguna berhasil dihapus";
            } else {
                $_SESSION['error'] = "Gagal menghapus pengguna";
            }
        } else {
            $_SESSION['error'] = "Tidak dapat menghapus akun sendiri";
        }
        
        header("Location: admin_dashboard.php");
        exit;
    }
    
    // Simpan pengaturan sistem
    if (isset($_POST['save_settings'])) {
        // Dalam implementasi nyata, ini akan menyimpan ke tabel settings
        $_SESSION['message'] = "Pengaturan sistem berhasil disimpan";
        header("Location: admin.php");
        exit;
    }
}

// Dapatkan koneksi database
$pdo = getDBConnection();

// Ambil data untuk dashboard
$totalUsers = getTotalUsers($pdo);
$totalReports = getTotalReports($pdo);
$totalEvaluations = getTotalEvaluations($pdo);
$recentActivities = getRecentActivities($pdo);
$performanceData = getPerformanceOverview($pdo);
$notifications = getAdminNotifications($pdo, $user['id']);
$allUsers = getAllUsers($pdo);

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

// Tentukan panel aktif berdasarkan parameter URL
$activePanel = isset($_GET['panel']) ? $_GET['panel'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Kinerja</title>
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
            object-fit: cover;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .panel {
            display: none;
        }

        .panel.active {
            display: block;
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
                        <span class="role">Admin</span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?= $activePanel === 'dashboard' ? 'active' : '' ?>">
                        <a href="admin_dashboard.php?panel=dashboard" id="navDashboard">
                            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?= $activePanel === 'users' ? 'active' : '' ?>">
                        <a href="admin_dashboard.php?panel=users" id="navManageUsers">
                            <i class="fas fa-users"></i> <span>Kelola Pengguna</span>
                        </a>
                    </li>
                    <li class="<?= $activePanel === 'settings' ? 'active' : '' ?>">
                        <a href="admin_dashboard.php?panel=settings" id="navSystemSettings">
                            <i class="fas fa-cog"></i> <span>Pengaturan Sistem</span>
                        </a>
                    </li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Keluar</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>
                    <?php
                    switch ($activePanel) {
                        case 'dashboard':
                            echo 'Dashboard Admin';
                            break;
                        case 'users':
                            echo 'Kelola Pengguna';
                            break;
                        case 'settings':
                            echo 'Pengaturan Sistem';
                            break;
                        default:
                            echo 'Dashboard Admin';
                    }
                    ?>
                </h1>
                <div class="header-actions">
                    <?php if ($activePanel === 'users'): ?>
                    <button class="btn btn-primary" id="btnAddUser">
                        <i class="fas fa-plus"></i> Tambah Pengguna
                    </button>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['message'] ?>
                    <?php unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Panel Dashboard -->
            <div class="panel <?= $activePanel === 'dashboard' ? 'active' : '' ?>" id="dashboardPanel">
                <div class="dashboard-grid">
                    <section class="card">
                        <h2>Total Pengguna</h2>
                        <div class="stat-number" id="totalUsers"><?= $totalUsers ?></div>
                    </section>

                    <section class="card">
                        <h2>Total Laporan</h2>
                        <div class="stat-number" id="totalReports"><?= $totalReports ?></div>
                    </section>

                    <section class="card">
                        <h2>Total Penilaian</h2>
                        <div class="stat-number" id="totalEvaluations"><?= $totalEvaluations ?></div>
                    </section>

                    <section class="card card-wide">
                        <h2>Grafik Penilaian</h2>
                        <div class="chart-container">
                            <canvas id="adminPerformanceChart"></canvas>
                        </div>
                    </section>

                    <section class="card card-wide">
                        <h2>Aktivitas Terbaru</h2>
                        <div class="table-responsive">
                            <table id="recentActivitiesTable">
                                <thead>
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Peran</th>
                                        <th>Aktivitas</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($activity['user_name'] ?? 'System') ?></td>
                                        <td><?= htmlspecialchars($activity['user_role'] ?? 'System') ?></td>
                                        <td><?= htmlspecialchars($activity['action']) ?></td>
                                        <td><?= formatTimeAgo($activity['created_at']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentActivities)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">Tidak ada aktivitas</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="card">
                        <h2>Notifikasi</h2>
                        <div class="notifications-container" id="adminNotifications">
                            <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <i class="fas <?= $notification['icon'] ?? 'fa-bell' ?>"></i>
                                </div>
                                <div class="notification-text"><?= htmlspecialchars($notification['message']) ?></div>
                                <div class="notification-time"><?= formatTimeAgo($notification['created_at']) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($notifications)): ?>
                            <div class="notification-item">
                                <div class="notification-text">Tidak ada notifikasi</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Panel Kelola Pengguna -->
            <div class="panel <?= $activePanel === 'users' ? 'active' : '' ?>" id="userManagementPanel">
                <div class="dashboard-grid">
                    <section class="card card-wide">
                        <h2>Kelola Pengguna</h2>
                        <div class="table-responsive">
                            <table id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Peran</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allUsers as $userItem): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($userItem['name']) ?></td>
                                        <td><?= htmlspecialchars($userItem['email']) ?></td>
                                        <td>
                                            <?php 
                                            $roleNames = [
                                                'admin' => 'Admin',
                                                'guru' => 'Guru',
                                                'kepala-sekolah' => 'Kepala Sekolah'
                                            ];
                                            echo $roleNames[$userItem['role']] ?? $userItem['role'];
                                            ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($userItem['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-user" data-id="<?= $userItem['id'] ?>" data-name="<?= htmlspecialchars($userItem['name']) ?>" data-email="<?= htmlspecialchars($userItem['email']) ?>" data-role="<?= $userItem['role'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($userItem['id'] !== $user['id']): ?>
                                            <button class="btn btn-sm btn-danger delete-user" data-id="<?= $userItem['id'] ?>" data-name="<?= htmlspecialchars($userItem['name']) ?>">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Panel Pengaturan Sistem -->
            <div class="panel <?= $activePanel === 'settings' ? 'active' : '' ?>" id="systemSettingsPanel">
                <div class="dashboard-grid">
                    <section class="card card-wide">
                        <h2>Pengaturan Sistem</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label for="systemName">Nama Sistem</label>
                                <input type="text" id="systemName" name="system_name" value="Sistem Kinerja Guru">
                            </div>
                            <div class="form-group">
                                <label for="adminEmail">Email Administrator</label>
                                <input type="email" id="adminEmail" name="admin_email" value="admin@sipeka.com">
                            </div>
                            <div class="form-group">
                                <label for="evaluationMethod">Metode Penilaian</label>
                                <select id="evaluationMethod" name="evaluation_method">
                                    <option value="numeric">Numerik (0-100)</option>
                                    <option value="categorical">Kategorikal (Baik, Cukup, Kurang)</option>
                                </select>
                            </div>
                            <button type="submit" name="save_settings" class="btn btn-primary">Simpan Pengaturan</button>
                        </form>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Tambah Pengguna -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Tambah Pengguna Baru</h2>
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                <div class="form-group">
                    <label for="userName">Nama Lengkap</label>
                    <input type="text" id="userName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="userEmail">Email</label>
                    <input type="email" id="userEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="userRole">Peran</label>
                    <select id="userRole" name="role" required>
                        <option value="">Pilih Peran</option>
                        <option value="guru">Guru</option>
                        <option value="kepala-sekolah">Kepala Sekolah</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="userPassword">Password</label>
                    <input type="password" id="userPassword" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
            </form>
        </div>
    </div>

    <!-- Modal Edit Pengguna -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Pengguna</h2>
            <form method="POST">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" id="editUserId" name="id">
                <div class="form-group">
                    <label for="editUserName">Nama Lengkap</label>
                    <input type="text" id="editUserName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editUserEmail">Email</label>
                    <input type="email" id="editUserEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="editUserRole">Peran</label>
                    <select id="editUserRole" name="role" required>
                        <option value="guru">Guru</option>
                        <option value="kepala-sekolah">Kepala Sekolah</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editUserPassword">Password (biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="password" id="editUserPassword" name="password">
                </div>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Pengguna -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Hapus Pengguna</h2>
            <p>Apakah Anda yakin ingin menghapus pengguna <span id="deleteUserName"></span>?</p>
            <form method="POST" style="display: inline-block;">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" id="deleteUserId" name="id">
                <input type="hidden" id="deleteUserNameInput" name="name">
                <button type="submit" class="btn btn-danger">Ya, Hapus</button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="closeModal(document.getElementById('deleteUserModal'))">Batal</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Data performa dari PHP
        const performanceData = <?= json_encode($performanceData) ?>;

        // Render grafik performa admin
        function renderAdminPerformanceChart() {
            const ctx = document.getElementById('adminPerformanceChart').getContext('2d');
            
            // Siapkan data untuk chart
            const labels = performanceData.map(item => {
                const [year, month] = item.month.split('-');
                return `${getMonthName(parseInt(month))} ${year}`;
            });
            
            const reports = performanceData.map(item => item.reports);
            const evaluations = performanceData.map(item => item.evaluations);
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Laporan',
                            data: reports,
                            backgroundColor: 'rgba(52, 152, 219, 0.2)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        },
                        {
                            label: 'Penilaian',
                            data: evaluations,
                            backgroundColor: 'rgba(46, 204, 113, 0.2)',
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Dapatkan nama bulan
        function getMonthName(month) {
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", 
                            "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            return months[month - 1] || '';
        }

        // Event listener untuk tombol tambah pengguna
        document.getElementById('btnAddUser').addEventListener('click', function() {
            openModal(document.getElementById('addUserModal'));
        });

        // Tambahkan event listener untuk tombol edit dan hapus
        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const role = this.getAttribute('data-role');
                
                document.getElementById('editUserId').value = id;
                document.getElementById('editUserName').value = name;
                document.getElementById('editUserEmail').value = email;
                document.getElementById('editUserRole').value = role;
                
                openModal(document.getElementById('editUserModal'));
            });
        });

        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('deleteUserId').value = id;
                document.getElementById('deleteUserName').textContent = name;
                document.getElementById('deleteUserNameInput').value = name;
                
                openModal(document.getElementById('deleteUserModal'));
            });
        });

        // Fungsi untuk membuka modal
        function openModal(modal) {
            modal.style.display = 'block';
        }

        // Fungsi untuk menutup modal
        function closeModal(modal) {
            modal.style.display = 'none';
        }

        // Inisialisasi event listener untuk modal
        function initModalListeners() {
            // Tutup modal ketika klik tombol close
            document.querySelectorAll('.modal .close').forEach(button => {
                button.addEventListener('click', function() {
                    closeModal(this.closest('.modal'));
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
        }

        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Hanya render chart jika di panel dashboard
            if (document.getElementById('adminPerformanceChart')) {
                renderAdminPerformanceChart();
            }
            initModalListeners();
        });
    </script>
</body>
</html>
