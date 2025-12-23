<?php
// index.php - Dashboard utama setelah login
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Query statistik berdasarkan role
if ($role === 'admin') {
    $total_menu = mysqli_query($conn, "SELECT COUNT(*) as total FROM menu")->fetch_assoc()['total'];
    $total_transaksi = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi")->fetch_assoc()['total'];
    $total_pendapatan = mysqli_query($conn, "SELECT SUM(total_harga) as total FROM transaksi WHERE status = 'selesai'")->fetch_assoc()['total'];
    $total_pengguna = mysqli_query($conn, "SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
} else {
    $total_menu = mysqli_query($conn, "SELECT COUNT(*) as total FROM menu WHERE stok > 0")->fetch_assoc()['total'];
    $total_transaksi = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE user_id = $user_id")->fetch_assoc()['total'];
    $total_pendapatan = 0; // Tidak relevan untuk non-admin
    $total_pengguna = 0; // Tidak relevan untuk non-admin
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #000000ff;
            --secondary-color: #DBE2EF;
        }
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: fixed;
            width: 250px;
            padding-top: 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="text-center mb-4">
            <h3 class="text-white">Resto Delight</h3>
            <p class="text-white-50 small">Sistem Manajemen Restoran</p>
        </div>
        
        <div class="text-center mb-4">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                <i class="fas fa-user-circle fa-3x text-primary"></i>
            </div>
            <h5 class="text-white mt-2"><?php echo $_SESSION['username']; ?></h5>
            <span class="badge bg-light text-primary"><?php echo ucfirst($role); ?></span>
        </div>
        
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link active">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            
            <?php if ($role === 'admin' || $role === 'kasir'): ?>
            <a href="menu.php" class="nav-link">
                <i class="fas fa-utensils me-2"></i>Kelola Menu
            </a>
            <a href="transaksi.php" class="nav-link">
                <i class="fas fa-shopping-cart me-2"></i>Transaksi
            </a>
            <?php endif; ?>
            
            <a href="pesanan.php" class="nav-link">
                <i class="fas fa-list-alt me-2"></i>Pesan Menu
            </a>
            <a href="riwayat.php" class="nav-link">
                <i class="fas fa-history me-2"></i>Riwayat
            </a>
            
            <?php if ($role === 'admin'): ?>
            <a href="laporan.php" class="nav-link">
                <i class="fas fa-chart-bar me-2"></i>Laporan
            </a>
            <a href="pengguna.php" class="nav-link">
                <i class="fas fa-users me-2"></i>Pengguna
            </a>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="welcome-banner">
            <h1 class="display-5">Selamat Datang, <?php echo $_SESSION['username']; ?>!</h1>
            <p class="lead">Akses sistem manajemen restoran dengan mudah</p>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge bg-light text-primary">Role: <?php echo $role; ?></span>
                <span class="badge bg-light text-primary">Login: <?php echo date('d/m/Y H:i', $_SESSION['login_time']); ?></span>
            </div>
        </div>
        
        <div class="row">
            <?php if ($role === 'admin'): ?>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Menu</h6>
                            <h3><?php echo $total_menu; ?></h3>
                        </div>
                        <i class="fas fa-utensils stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Transaksi</h6>
                            <h3><?php echo $total_transaksi; ?></h3>
                        </div>
                        <i class="fas fa-receipt stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Pendapatan</h6>
                            <h3>Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-money-bill-wave stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Pengguna</h6>
                            <h3><?php echo $total_pengguna; ?></h3>
                        </div>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Menu Tersedia</h6>
                            <h3><?php echo $total_menu; ?></h3>
                        </div>
                        <i class="fas fa-utensils stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Pesanan Saya</h6>
                            <h3><?php echo $total_transaksi; ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Aksi Cepat</h6>
                            <a href="pesanan.php" class="btn btn-primary btn-sm mt-2">Pesan Sekarang</a>
                        </div>
                        <i class="fas fa-bolt stat-icon"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Sistem</h5>
                    </div>
                    <div class="card-body">
                        <p>Selamat datang di sistem <strong>Resto Delight</strong>. Berikut adalah fitur yang tersedia:</p>
                        <ul>
                            <li><strong>Kelola Menu</strong> - Tambah, edit, dan hapus menu makanan/minuman</li>
                            <li><strong>Transaksi</strong> - Lakukan transaksi pemesanan</li>
                            <li><strong>Riwayat</strong> - Lihat riwayat transaksi Anda</li>
                            <?php if ($role === 'admin'): ?>
                            <li><strong>Laporan</strong> - Analisis penjualan dan statistik</li>
                            <li><strong>Pengguna</strong> - Kelola user dan hak akses</li>
                            <?php endif; ?>
                        </ul>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Tips:</strong> Gunakan menu sidebar untuk navigasi cepat ke semua fitur.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-bullhorn me-2"></i>Pengumuman</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <div class="list-group-item border-0">
                                <small class="text-muted">Hari ini</small>
                                <p class="mb-1">Sistem telah diperbarui dengan fitur pencarian menu</p>
                            </div>
                            <div class="list-group-item border-0">
                                <small class="text-muted">Kemarin</small>
                                <p class="mb-1">Menu baru: Nasi Goreng Spesial telah ditambahkan</p>
                            </div>
                            <div class="list-group-item border-0">
                                <small class="text-muted">Minggu ini</small>
                                <p class="mb-1">Promo khusus member: Diskon 10% untuk minuman</p>
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