<?php
// profile.php - Halaman profil customer
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Ambil data user
$query = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi username unik
    $check_username = "SELECT id FROM users WHERE username = '$username' AND id != $user_id";
    $result_check = mysqli_query($conn, $check_username);
    
    if (mysqli_num_rows($result_check) > 0) {
        $message = 'Username sudah digunakan!';
        $message_type = 'danger';
    } else {
        $update_fields = [];
        
        // Update username jika berubah
        if ($username !== $user['username']) {
            $update_fields[] = "username = '$username'";
            $_SESSION['username'] = $username;
        }
        
        // Update email jika berubah
        if ($email !== $user['email']) {
            $update_fields[] = "email = '$email'";
        }
        
        // Update password jika diisi
        if (!empty($new_password)) {
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_fields[] = "password = '$hashed_password'";
                } else {
                    $message = 'Password baru tidak cocok!';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Password saat ini salah!';
                $message_type = 'danger';
            }
        }
        
        if (empty($message) && !empty($update_fields)) {
            $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = $user_id";
            
            if (mysqli_query($conn, $update_query)) {
                $message = 'Profil berhasil diperbarui!';
                $message_type = 'success';
                
                // Refresh user data
                $result = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
                $user = mysqli_fetch_assoc($result);
            } else {
                $message = 'Gagal memperbarui profil: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        } elseif (empty($message)) {
            $message = 'Tidak ada perubahan yang dilakukan.';
            $message_type = 'info';
        }
    }
}

// Hitung statistik user
$query_stats = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_orders,
    COALESCE(SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END), 0) as total_spent
    FROM transaksi WHERE user_id = $user_id";
$result_stats = mysqli_query($conn, $query_stats);
$stats = mysqli_fetch_assoc($result_stats);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #000000ff 0%, #DBE2EF 100%);
            color: white;
            padding: 60px 0 40px;
            border-radius: 0 0 30px 30px;
            margin-bottom: 40px;
        }
        
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .avatar-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #424874, #DBE2EF);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #000000ff;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-second" href="index.php">
                <i class="fas fa-utensils me-2"></i>Resto Delight
            </a>
            <div class="d-flex align-items-center">
                <a href="pesanan.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-shopping-cart me-1"></i>Pesan Menu
                </a>
                <a href="riwayat.php" class="btn btn-outline-secondary">
                    <i class="fas fa-history me-1"></i>Riwayat
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="avatar-lg mx-auto mb-3">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="col-md-9">
                    <h1 class="display-6 fw-bold mb-2"><?php echo $user['username']; ?></h1>
                    <p class="lead mb-3"><?php echo $user['email']; ?></p>
                    <div class="d-flex flex-wrap gap-3">
                        <span class="badge bg-light text-primary p-2">
                            <i class="fas fa-user-tag me-1"></i>
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <span class="badge bg-light text-primary p-2">
                            <i class="fas fa-calendar me-1"></i>
                            Bergabung: <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['total_orders']; ?></h3>
                    <p class="text-muted mb-0">Total Pesanan</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['completed_orders']; ?></h3>
                    <p class="text-muted mb-0">Pesanan Selesai</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="mb-1">Rp <?php echo number_format($stats['total_spent'], 0, ',', '.'); ?></h3>
                    <p class="text-muted mb-0">Total Belanja</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Edit Profile -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <h4 class="mb-4"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Profil</h4>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Ubah Password</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Kosongkan jika tidak ingin mengubah password.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Password Saat Ini</label>
                                <input type="password" class="form-control" name="current_password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Password Baru</label>
                                <input type="password" class="form-control" name="new_password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="update_profile" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <h4 class="mb-4"><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Akun</h4>
                    
                    <div class="mb-4">
                        <small class="text-muted d-block">ID Pengguna</small>
                        <strong><?php echo $user['id']; ?></strong>
                    </div>
                    
                    <div class="mb-4">
                        <small class="text-muted d-block">Role</small>
                        <strong class="badge bg-primary"><?php echo ucfirst($user['role']); ?></strong>
                    </div>
                    
                    <div class="mb-4">
                        <small class="text-muted d-block">Bergabung Sejak</small>
                        <strong><?php echo date('d F Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                    
                    <div class="mb-4">
                        <small class="text-muted d-block">Status Akun</small>
                        <strong class="text-success">Aktif</strong>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-grid gap-2">
                        <a href="riwayat.php" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>Lihat Riwayat Pesanan
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Keluar
                        </a>
                    </div>
                </div>
                
                <!-- Tips -->
                <div class="alert alert-warning">
                    <h6><i class="fas fa-lightbulb me-2"></i>Tips Keamanan</h6>
                    <ul class="mb-0 small">
                        <li>Gunakan password yang kuat</li>
                        <li>Jangan bagikan kredensial login</li>
                        <li>Selalu logout setelah selesai</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light py-4 mt-5 border-top">
        <div class="container text-center">
            <p class="text-muted mb-0">
                <i class="fas fa-utensils me-2"></i>Resto Delight &copy; 2025
            </p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>