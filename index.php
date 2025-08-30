<?php
session_start();
// Jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['user'])) {
    switch($_SESSION['user']['role']) {
        case 'admin': header('Location: admin.php'); break;
        case 'guru': header('Location: guru.php'); break;
        case 'kepala-sekolah': header('Location: kepala-sekolah.php'); break;
    }
    exit;
}

// Proses login jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config.php';
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            // Redirect ke dashboard sesuai role
            switch($user['role']) {
                case 'admin': header('Location: admin.php'); break;
                case 'guru': header('Location: guru.php'); break;
                case 'kepala-sekolah': header('Location: kepala-sekolah.php'); break;
            }
            exit;
        } else {
            $error = "Email atau password salah";
        }
    } else {
        $error = "Email dan password harus diisi";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kinerja Guru - Login</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2ecc71;
            --danger: #e74c3c;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --gray: #95a5a6;
        }
        * {margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .login-header {text-align: center; margin-bottom: 30px;}
        .login-header h1 {color: var(--primary); margin-bottom: 10px;}
        .login-header p {color: var(--gray);}
        .form-group {margin-bottom: 20px;}
        .form-group label {display: block; margin-bottom: 8px; font-weight: 500;}
        .form-group input {
            width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; transition: border 0.3s;
        }
        .form-group input:focus {border-color: var(--primary); outline: none;}
        .btn {
            width: 100%; padding: 12px; background-color: var(--primary); color: white;
            border: none; border-radius: 5px; font-size: 16px; font-weight: 500; cursor: pointer; transition: background-color 0.3s;
        }
        .btn:hover {background-color: #2980b9;}
        .error-message {color: var(--danger); margin-top: 10px; text-align: center;}
        .success-message {color: var(--secondary); margin-top: 10px; text-align: center;}
        .user-accounts {
            margin-top: 25px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .user-accounts h3 {
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 15px;
            text-align: center;
        }
        .account-list {
            display: grid;
            gap: 10px;
        }
        .account-item {
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid var(--primary);
        }
        .account-item.guru {border-left-color: var(--secondary);}
        .account-item.kepala-sekolah {border-left-color: #9b59b6;}
        .account-item.admin {border-left-color: var(--danger);}
        .account-item strong {display: block; margin-bottom: 5px;}
        .account-item span {font-size: 14px; color: var(--gray);}
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Sistem Kinerja Guru</h1>
            <p>Silakan masuk dengan akun Anda</p>
        </div>
        <?php if (isset($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="Masukkan email Anda">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Masukkan password Anda">
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>

        <div class="user-accounts">
            <h3>Akun Demo untuk Testing</h3>
            <div class="account-list">
                <div class="account-item guru">
                    <strong>Guru</strong>
                    <span>Email: guru@sipeka.com</span><br>
                    <span>Password: password</span>
                </div>
                <div class="account-item kepala-sekolah">
                    <strong>Kepala Sekolah</strong>
                    <span>Email: kepsek@sipeka.com</span><br>
                    <span>Password: password</span>
                </div>
                <div class="account-item admin">
                    <strong>Admin</strong>
                    <span>Email: admin@sipeka.com</span><br>
                    <span>Password: password</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
