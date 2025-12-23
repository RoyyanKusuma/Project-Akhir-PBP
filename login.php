<?php
// login.php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE username = '$username' OR email = '$username'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            header('Location: index.php');
            exit();
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username/email tidak ditemukan!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-left {
            background: linear-gradient(135deg, #000000ff 0%, #DBE2EF 100%);
            color: white;
            padding: 40px;
        }
        .login-right {
            padding: 40px;
        }
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="login-card">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <div class="login-left d-flex flex-column justify-content-center h-100">
                                <h1 class="display-4 fw-bold">Resto Delight</h1>
                                <p class="lead">Selamat datang di sistem manajemen restoran kami</p>
                                <div class="mt-4">
                                    <i class="fas fa-utensils fa-4x mb-3"></i>
                                    <p>Login untuk mengakses semua fitur</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-right">
                                <div class="text-center mb-4">
                                    <div class="logo">RD</div>
                                    <h2>Login ke Akun</h2>
                                    <p class="text-muted">Masukkan kredensial Anda</p>
                                </div>
                                
                                <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username atau Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username" name="username" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-sign-in-alt me-2"></i>Login
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-4">
                                    <p class="mb-0">Belum punya akun? 
                                        <a href="register.php" class="text-decoration-none">Daftar disini</a>
                                    </p>
                                    <p class="mt-2">
                                        <small>
                                            Gunakan:<br>
                                            Admin: admin / password<br>
                                            Kasir: kasir / password<br>
                                            Password default: password
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>