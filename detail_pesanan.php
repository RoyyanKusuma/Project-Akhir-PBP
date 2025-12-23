<?php
// detail_pesanan.php - Halaman detail pesanan dengan QR Code nyata
require_once 'config.php';
require_once 'qrcode.php'; // Include QR Code generator

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$transaksi_id = isset($_GET['id']) ? intval($_GET['id']) : 
                (isset($_SESSION['last_order']) ? $_SESSION['last_order'] : 0);

if ($transaksi_id === 0) {
    header('Location: pesanan.php');
    exit();
}

// Ambil data transaksi
$query = "SELECT t.*, u.username, u.email 
          FROM transaksi t 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE t.id = $transaksi_id AND t.user_id = $user_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    die('Pesanan tidak ditemukan atau Anda tidak memiliki akses!');
}

$transaksi = mysqli_fetch_assoc($result);

// Ambil detail transaksi
$query_detail = "SELECT dt.*, m.nama_menu, m.kategori, m.gambar 
                FROM detail_transaksi dt 
                JOIN menu m ON dt.menu_id = m.id 
                WHERE dt.transaksi_id = $transaksi_id";
$result_detail = mysqli_query($conn, $query_detail);

// Status mapping
$status_info = [
    'pending' => ['icon' => 'clock', 'color' => 'warning', 'text' => 'Menunggu Konfirmasi', 'progress' => 25],
    'diproses' => ['icon' => 'cogs', 'color' => 'info', 'text' => 'Sedang Diproses', 'progress' => 50],
    'selesai' => ['icon' => 'check-circle', 'color' => 'success', 'text' => 'Selesai', 'progress' => 100],
    'dibatalkan' => ['icon' => 'times-circle', 'color' => 'danger', 'text' => 'Dibatalkan', 'progress' => 0]
];

// Generate QR Code Data
$qr_data = json_encode([
    'order_id' => $transaksi['id'],
    'order_code' => $transaksi['kode_transaksi'],
    'customer' => $transaksi['username'],
    'total' => $transaksi['total_harga'],
    'status' => $transaksi['status'],
    'timestamp' => $transaksi['created_at']
]);

// Generate QR Code URL
$qr_code_url = QRCode::generate($qr_data, 180);

// Generate data untuk verifikasi
$verification_code = substr(md5($transaksi['kode_transaksi'] . $transaksi['created_at']), 0, 8);

// Hitung total dan jumlah item
$total = 0;
$item_count = 0;
$detail_items = [];
while ($detail = mysqli_fetch_assoc($result_detail)) {
    $subtotal = $detail['harga_satuan'] * $detail['quantity'];
    $total += $subtotal;
    $item_count += $detail['quantity'];
    $detail_items[] = $detail;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #000000ff;
            --secondary: #DBE2EF;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .order-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 60px 0 40px;
            border-radius: 0 0 30px 30px;
            margin-bottom: 40px;
        }
        
        .order-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
        }
        
        .status-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 40px 0;
        }
        
        .status-tracker::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 10%;
            right: 10%;
            height: 8px;
            background: #e9ecef;
            z-index: 1;
            border-radius: 4px;
        }
        
        .status-step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .status-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 4px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .status-step.active .status-icon {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .status-step.completed .status-icon {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .status-step.completed::before {
            background: var(--primary) !important;
        }
        
        .progress-tracker {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 30px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 4px;
            transition: width 1s ease;
        }
        
        .order-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
            transition: background-color 0.3s;
        }
        
        .order-item:hover {
            background-color: #f8f9fa;
        }
        
        .order-total {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .action-buttons {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px;
            border-top: 1px solid #eee;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .status-badge {
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .action-buttons {
                display: none !important;
            }
            
            .order-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
        .qr-code-container {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin: 20px 0;
            border: 2px dashed #ddd;
        }
        
        .qr-code {
            width: 180px;
            height: 180px;
            margin: 0 auto;
            padding: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
            border-radius: 5px;
        }
        
        .verification-info {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #4caf50;
        }
        
        .order-meta {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
        }
        
        .order-meta-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .order-meta-item:last-child {
            border-bottom: none;
        }
        
        .menu-image-sm {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 15px;
        }
        
        .countdown-timer {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 10px;
            margin: 15px 0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .info-card {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        
        .scan-instruction {
            text-align: center;
            padding: 15px;
            background: #fff3cd;
            border-radius: 10px;
            margin-top: 15px;
            border: 1px solid #ffc107;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .badge-pill {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .whatsapp-btn {
            background: #25D366;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .whatsapp-btn:hover {
            background: #128C7E;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.4);
        }
        
        .copy-btn {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            transform: scale(1.1);
        }
        
        .order-complete {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #000000ff, #DBE2EF);
            color: white;
            border-radius: 20px;
            margin: 30px 0;
        }
        
        .delivery-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
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
                <a href="pesanan.php" class="btn btn-outline-primary me-2 no-print">
                    <i class="fas fa-shopping-cart me-1"></i>Pesan Lagi
                </a>
                <a href="riwayat.php" class="btn btn-outline-secondary me-2 no-print">
                    <i class="fas fa-history me-1"></i>Riwayat
                </a>
                <button onclick="window.print()" class="btn btn-outline-success no-print">
                    <i class="fas fa-print me-1"></i>Cetak
                </button>
            </div>
        </div>
    </nav>
    
    <!-- Header -->
    <div class="order-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-3">Detail Pesanan</h1>
                    <div class="d-flex align-items-center flex-wrap gap-3 mb-3">
                        <span class="badge bg-light text-primary fs-6 p-3">
                            <i class="fas fa-receipt me-2"></i>
                            <?php echo $transaksi['kode_transaksi']; ?>
                        </span>
                        <span class="badge bg-light text-dark fs-6 p-3">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('d F Y, H:i', strtotime($transaksi['created_at'])); ?>
                        </span>
                        <span class="badge bg-light text-success fs-6 p-3">
                            <i class="fas fa-user me-2"></i>
                            <?php echo $transaksi['username']; ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="qr-code-container animate__animated animate__pulse animate__infinite" style="animation-iteration-count: 3;">
                        <div class="qr-code">
                            <img src="<?php echo $qr_code_url; ?>" alt="QR Code Pesanan">
                        </div>
                        <small class="text-white mt-2">Scan QR Code untuk verifikasi</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Status Tracker -->
        <div class="order-card">
            <h4 class="mb-4"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Status Pesanan</h4>
            
            <!-- Progress Bar -->
            <div class="progress-tracker">
                <div class="progress-bar" id="progressBar" 
                     style="width: <?php echo $status_info[$transaksi['status']]['progress']; ?>%">
                </div>
            </div>
            
            <!-- Status Steps -->
            <div class="status-tracker">
                <?php 
                $steps = ['pending', 'diproses', 'selesai'];
                $current_step = array_search($transaksi['status'], $steps);
                $current_status = $transaksi['status'];
                
                foreach ($steps as $index => $step): 
                    $is_active = $index === $current_step;
                    $is_completed = $index < $current_step || ($current_status === 'selesai' && $index <= $current_step);
                    $is_cancelled = $current_status === 'dibatalkan';
                    
                    $step_info = $status_info[$step];
                ?>
                <div class="status-step <?php echo $is_completed ? 'completed' : ($is_active ? 'active' : ''); ?> 
                     <?php echo $is_active ? 'animate__animated animate__pulse' : ''; ?>">
                    <div class="status-icon">
                        <i class="fas fa-<?php echo $step_info['icon']; ?>"></i>
                    </div>
                    <h6 class="mb-1"><?php echo $step_info['text']; ?></h6>
                    <small class="text-muted">
                        <?php if ($is_completed): ?>
                        <i class="fas fa-check text-success"></i> Selesai
                        <?php elseif ($is_active): ?>
                        <span class="text-primary fw-bold">Sedang Berlangsung</span>
                        <?php else: ?>
                        Menunggu
                        <?php endif; ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($current_status === 'pending'): ?>
            <!-- Countdown Timer untuk Pesanan Pending -->
            <div class="countdown-timer">
                <i class="fas fa-clock me-2"></i>
                Pesanan akan diproses dalam: 
                <span id="countdown">15:00</span> menit
            </div>
            <?php endif; ?>
            
            <?php if ($current_status === 'diproses'): ?>
            <!-- Delivery Info -->
            <div class="delivery-info">
                <div>
                    <i class="fas fa-motorcycle fa-2x text-primary me-3"></i>
                    <div>
                        <h6 class="mb-1">Pesanan sedang diproses</h6>
                        <small class="text-muted">Estimasi selesai: 20-30 menit</small>
                    </div>
                </div>
                <div class="text-end">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($current_status === 'selesai'): ?>
            <!-- Order Complete -->
            <div class="order-complete animate__animated animate__fadeIn">
                <i class="fas fa-check-circle fa-4x mb-3"></i>
                <h4>Pesanan Telah Selesai!</h4>
                <p class="mb-0">Terima kasih telah memesan di Resto Delight</p>
            </div>
            <?php endif; ?>
            
            <?php if ($is_cancelled): ?>
            <!-- Cancelled Order -->
            <div class="alert alert-danger text-center animate__animated animate__shakeX">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h5>Pesanan Dibatalkan</h5>
                <p class="mb-0">Pesanan ini telah dibatalkan.</p>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <div class="status-badge bg-<?php echo $status_info[$current_status]['color']; ?> text-white d-inline-block">
                    <i class="fas fa-<?php echo $status_info[$current_status]['icon']; ?> me-2"></i>
                    <?php echo $status_info[$current_status]['text']; ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Order Details -->
            <div class="col-lg-8">
                <div class="order-card">
                    <h4 class="mb-4"><i class="fas fa-list-alt me-2 text-primary"></i>Detail Pesanan</h4>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">Gambar</th>
                                    <th>Menu</th>
                                    <th class="text-center">Kategori</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-center">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detail_items as $detail): 
                                    $subtotal = $detail['harga_satuan'] * $detail['quantity'];
                                ?>
                                <tr class="order-item">
                                    <td>
                                        <?php if ($detail['gambar']): ?>
                                        <img src="<?php echo $detail['gambar']; ?>" class="menu-image-sm" alt="<?php echo $detail['nama_menu']; ?>">
                                        <?php else: ?>
                                        <div class="menu-image-sm d-flex align-items-center justify-content-center bg-light">
                                            <i class="fas fa-utensils text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $detail['nama_menu']; ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $detail['kategori'] === 'makanan' ? 'primary' : 
                                                               ($detail['kategori'] === 'minuman' ? 'info' : 'warning'); ?> badge-pill">
                                            <?php echo ucfirst($detail['kategori']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $detail['quantity']; ?></span>
                                    </td>
                                    <td class="text-center">Rp <?php echo number_format($detail['harga_satuan'], 0, ',', '.'); ?></td>
                                    <td class="text-end fw-bold">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="order-total">
                        <div class="row">
                            <div class="col-6">
                                <h5 class="mb-0">Total Pesanan</h5>
                                <small><?php echo $item_count; ?> item</small>
                            </div>
                            <div class="col-6 text-end">
                                <h2 class="mb-0">Rp <?php echo number_format($total, 0, ',', '.'); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Information & QR Code -->
            <div class="col-lg-4">
                <!-- QR Code Verification -->
                <div class="order-card">
                    <h4 class="mb-4"><i class="fas fa-qrcode me-2 text-primary"></i>Verifikasi QR Code</h4>
                    
                    <div class="qr-code-container">
                        <div class="qr-code pulse-animation">
                            <img src="<?php echo $qr_code_url; ?>" alt="QR Code Pesanan" id="qrcodeImage">
                        </div>
                        
                        <div class="scan-instruction mt-3">
                            <h6><i class="fas fa-info-circle me-2"></i>Cara Verifikasi:</h6>
                            <p class="small mb-2">1. Scan QR Code dengan smartphone</p>
                            <p class="small mb-2">2. Tunjukkan ke kasir/staf</p>
                            <p class="small mb-0">3. Tunggu konfirmasi</p>
                        </div>
                        
                        <div class="verification-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Kode Verifikasi</small>
                                    <strong id="verificationCode"><?php echo $verification_code; ?></strong>
                                </div>
                                <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyVerificationCode()">
                                    <i class="fas fa-copy"></i> Salin
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-shield-alt me-2"></i>Keamanan QR Code</h6>
                        <p class="small mb-0">QR Code ini berisi data pesanan Anda yang telah dienkripsi. Hanya dapat dibaca oleh sistem Resto Delight.</p>
                    </div>
                </div>
                
                <!-- Order Information -->
                <div class="order-card">
                    <h4 class="mb-4"><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Pesanan</h4>
                    
                    <div class="order-meta">
                        <div class="order-meta-item">
                            <span>Kode Pesanan</span>
                            <strong><?php echo $transaksi['kode_transaksi']; ?></strong>
                        </div>
                        
                        <div class="order-meta-item">
                            <span>Tanggal Pesanan</span>
                            <strong><?php echo date('d/m/Y H:i', strtotime($transaksi['created_at'])); ?></strong>
                        </div>
                        
                        <div class="order-meta-item">
                            <span>Metode Pembayaran</span>
                            <strong class="text-capitalize">
                                <i class="fas fa-<?php echo $transaksi['metode_pembayaran'] === 'tunai' ? 'money-bill-wave' : 
                                                 ($transaksi['metode_pembayaran'] === 'kartu' ? 'credit-card' : 'qrcode'); ?> me-1"></i>
                                <?php echo $transaksi['metode_pembayaran']; ?>
                            </strong>
                        </div>
                        
                        <div class="order-meta-item">
                            <span>Total Item</span>
                            <strong><?php echo $item_count; ?> item</strong>
                        </div>
                        
                        <div class="order-meta-item">
                            <span>Status</span>
                            <strong class="text-<?php echo $status_info[$current_status]['color']; ?>">
                                <?php echo $status_info[$current_status]['text']; ?>
                            </strong>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Support -->
                <div class="order-card">
                    <h4 class="mb-3"><i class="fas fa-headset me-2 text-primary"></i>Butuh Bantuan?</h4>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-phone me-2"></i>Telepon</h6>
                        <p class="mb-2">(021) 9079-3769</p>
                        <small class="text-muted">Senin-Minggu, 08:00-22:00</small>
                    </div>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-envelope me-2"></i>Email</h6>
                        <p class="mb-0">royyandwinandakusuma08@gmail.com</p>
                    </div>
                    
                    <div class="d-grid mt-3">
                        <button class="btn whatsapp-btn" onclick="contactWhatsApp()">
                            <i class="fab fa-whatsapp me-2"></i>Hubungi via WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <a href="pesanan.php" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-2"></i>Pesan Menu Lain
                            </a>
                            <a href="riwayat.php" class="btn btn-outline-secondary">
                                <i class="fas fa-history me-2"></i>Lihat Riwayat
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <?php if ($current_status === 'pending'): ?>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                <i class="fas fa-times me-2"></i>Batalkan Pesanan
                            </button>
                            <?php endif; ?>
                            <button onclick="shareOrder()" class="btn btn-info">
                                <i class="fas fa-share-alt me-2"></i>Bagikan Pesanan
                            </button>
                            <button onclick="window.print()" class="btn btn-success">
                                <i class="fas fa-print me-2"></i>Cetak Nota
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Batalkan Pesanan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="cancel_order.php">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?php echo $transaksi_id; ?>">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Perhatian:</strong> Pesanan yang sudah dibatalkan tidak dapat dikembalikan.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alasan Pembatalan</label>
                            <textarea name="alasan" class="form-control" rows="3" required 
                                      placeholder="Mengapa Anda membatalkan pesanan ini?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Batalkan Pesanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Share Modal -->
    <div class="modal fade" id="shareModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-share-alt me-2"></i>Bagikan Pesanan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="qr-code text-center mb-3">
                        <img src="<?php echo $qr_code_url; ?>" alt="QR Code" style="width: 150px; height: 150px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link Berbagi</label>
                        <div class="input-group">
                            <?php 
                            $current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                            ?>
                            <input type="text" class="form-control" id="shareLink" 
                                   value="<?php echo $current_url; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-center">
                        <button class="btn whatsapp-btn me-2" onclick="shareViaWhatsApp()">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                        <button class="btn btn-primary" onclick="shareViaEmail()">
                            <i class="fas fa-envelope"></i> Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light py-4 mt-5 border-top">
        <div class="container text-center">
            <p class="text-muted mb-0">
                <i class="fas fa-utensils me-2"></i>Resto Delight &copy; 2025 | 
                <a href="tel:02112345678" class="text-decoration-none text-muted">
                    <i class="fas fa-phone me-1"></i>(021) 9079-3769
                </a>
            </p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        // Countdown Timer
        <?php if ($current_status === 'pending'): ?>
        let countdownTime = 15 * 60; // 15 menit dalam detik
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            const minutes = Math.floor(countdownTime / 60);
            const seconds = countdownTime % 60;
            
            countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (countdownTime <= 0) {
                clearInterval(countdownInterval);
                countdownElement.textContent = "Waktu habis!";
                countdownElement.classList.add('text-danger');
            }
            
            countdownTime--;
        }
        
        const countdownInterval = setInterval(updateCountdown, 1000);
        updateCountdown();
        <?php endif; ?>
        
        // Copy Verification Code
        function copyVerificationCode() {
            const code = document.getElementById('verificationCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showToast('Kode verifikasi berhasil disalin!', 'success');
            });
        }
        
        // Copy Share Link
        function copyShareLink() {
            const link = document.getElementById('shareLink').value;
            navigator.clipboard.writeText(link).then(() => {
                showToast('Link berhasil disalin!', 'success');
            });
        }
        
        // Contact WhatsApp
        function contactWhatsApp() {
            const phone = "6281290793769"; // Ganti dengan nomor WhatsApp bisnis
            const message = `Halo Resto Delight, saya ingin bertanya tentang pesanan saya: <?php echo $transaksi['kode_transaksi']; ?>`;
            const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        }
        
        // Share Order
        function shareOrder() {
            const shareModal = new bootstrap.Modal(document.getElementById('shareModal'));
            shareModal.show();
        }
        
        // Share via WhatsApp
        function shareViaWhatsApp() {
            const message = `Lihat detail pesanan saya di Resto Delight:\nKode: <?php echo $transaksi['kode_transaksi']; ?>\nTotal: Rp <?php echo number_format($total, 0, ',', '.'); ?>\nLink: <?php echo $current_url; ?>`;
            const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        }
        
        // Share via Email
        function shareViaEmail() {
            const subject = `Detail Pesanan <?php echo $transaksi['kode_transaksi']; ?> - Resto Delight`;
            const body = `Detail Pesanan Resto Delight:

        Kode Pesanan: <?php echo $transaksi['kode_transaksi']; ?>
        Tanggal: <?php echo date('d F Y H:i', strtotime($transaksi['created_at'])); ?>
        Status: <?php echo $status_info[$current_status]['text']; ?>
        Total: Rp <?php echo number_format($total, 0, ',', '.'); ?>

        Untuk melihat detail lengkap, kunjungi link berikut:
        <?php echo $current_url; ?>

        Terima kasih telah memesan di Resto Delight!
            `;
            
            const mailtoLink = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            window.location.href = mailtoLink;
        }
        
        // Toast Notification
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', function () {
                this.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
            return container;
        }
        
        // Auto refresh status setiap 30 detik jika masih pending/diproses
        <?php if (in_array($current_status, ['pending', 'diproses'])): ?>
        function updateOrderStatus() {
            fetch(`ajax/check_order_status.php?id=<?php echo $transaksi_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.status !== '<?php echo $current_status; ?>') {
                        showToast('Status pesanan diperbarui!', 'info');
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        setInterval(updateOrderStatus, 30000);
        <?php endif; ?>
        
        // Animasi progress bar saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.getElementById('progressBar');
            const targetWidth = progressBar.style.width;
            progressBar.style.width = '0%';
            
            setTimeout(() => {
                progressBar.style.width = targetWidth;
            }, 500);
            
            // Jika status selesai, trigger confetti
            <?php if ($current_status === 'selesai'): ?>
            setTimeout(() => {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 }
                });
            }, 1000);
            <?php endif; ?>
            
            // Auto print jika parameter print=true
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === 'true') {
                setTimeout(() => window.print(), 1000);
            }
        });
        
        // Scan QR Code Animation
        const qrCode = document.getElementById('qrcodeImage');
        if (qrCode) {
            qrCode.addEventListener('click', function() {
                this.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => {
                    this.classList.remove('animate__animated', 'animate__pulse');
                }, 1000);
                
                showToast('QR Code dapat discan untuk verifikasi pesanan', 'info');
            });
        }
        
        // Simulasi scan dengan kamera
        function simulateQRScan() {
            const modal = new bootstrap.Modal(document.getElementById('scanModal'));
            modal.show();
        }
    </script>
    
    <!-- Scan Modal (Simulasi) -->
    <div class="modal fade" id="scanModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-camera me-2"></i>Scan QR Code</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="qr-code mb-3">
                        <img src="<?php echo $qr_code_url; ?>" alt="QR Code" style="width: 200px; height: 200px;">
                    </div>
                    <div class="scan-animation">
                        <div class="laser" style="
                            width: 100%;
                            height: 2px;
                            background: red;
                            position: relative;
                            animation: scan 2s linear infinite;
                            margin: 10px 0;
                        "></div>
                    </div>
                    <p class="mb-0">Arahkan kamera ke QR Code</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        @keyframes scan {
            0% { top: 0; }
            50% { top: 200px; }
            100% { top: 0; }
        }
        
        .laser {
            box-shadow: 0 0 10px red;
        }
    </style>
</body>
</html>