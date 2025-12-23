<?php
// verifikasi_qr.php - Halaman untuk staff memverifikasi QR Code
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya admin dan kasir yang bisa akses
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'kasir') {
    header('Location: index.php');
    exit();
}

$message = '';
$message_type = '';
$order_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_data'])) {
    $scan_data = $_POST['scan_data'];
    $data = json_decode($scan_data, true);
    
    if ($data && isset($data['order_code'])) {
        $order_code = clean_input($data['order_code']);
        
        $query = "SELECT t.*, u.username, u.email 
                  FROM transaksi t 
                  LEFT JOIN users u ON t.user_id = u.id 
                  WHERE t.kode_transaksi = '$order_code'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            $order_data = mysqli_fetch_assoc($result);
            
            // Update status jika masih pending
            if ($order_data['status'] === 'pending') {
                $update_query = "UPDATE transaksi SET status = 'diproses' WHERE kode_transaksi = '$order_code'";
                mysqli_query($conn, $update_query);
                
                // Notifikasi untuk customer
                $notif_message = "Pesanan #$order_code sedang diproses oleh restoran.";
                $query_notif = "INSERT INTO notifications (user_id, type, title, message) 
                               VALUES ({$order_data['user_id']}, 'info', 'Pesanan Diproses', '$notif_message')";
                mysqli_query($conn, $query_notif);
                
                $message = 'Pesanan berhasil diverifikasi dan sedang diproses!';
                $message_type = 'success';
            }
        } else {
            $message = 'Pesanan tidak ditemukan!';
            $message_type = 'danger';
        }
    } else {
        $message = 'Data QR Code tidak valid!';
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi QR Code - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 500px;
        }
        
        .scanner-container {
            width: 300px;
            height: 300px;
            margin: 0 auto 20px;
            border: 3px dashed #667eea;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .scanner-laser {
            position: absolute;
            width: 100%;
            height: 2px;
            background: red;
            animation: scan 2s linear infinite;
            box-shadow: 0 0 10px red;
        }
        
        @keyframes scan {
            0% { top: 0; }
            50% { top: 300px; }
            100% { top: 0; }
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .btn-scan {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-scan:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="verification-card">
        <div class="text-center mb-4">
            <i class="fas fa-qrcode fa-4x text-primary mb-3"></i>
            <h2>Verifikasi QR Code</h2>
            <p class="text-muted">Scan QR Code dari customer untuk verifikasi pesanan</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Scanner Simulation -->
        <div class="scanner-container">
            <div class="scanner-laser"></div>
            <i class="fas fa-camera fa-4x text-muted"></i>
        </div>
        
        <!-- Manual Input -->
        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label class="form-label">Masukkan Data QR Code (JSON)</label>
                <textarea class="form-control" name="scan_data" rows="4" 
                          placeholder='{"order_code": "ORD-20241222-123456", ...}'></textarea>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-scan">
                    <i class="fas fa-check-circle me-2"></i>Verifikasi Pesanan
                </button>
            </div>
        </form>
        
        <!-- Order Info (jika ada) -->
        <?php if ($order_data): ?>
        <div class="order-info">
            <h5><i class="fas fa-receipt me-2"></i>Detail Pesanan</h5>
            <div class="row">
                <div class="col-6">
                    <small class="text-muted">Kode Pesanan</small>
                    <p class="fw-bold"><?php echo $order_data['kode_transaksi']; ?></p>
                </div>
                <div class="col-6">
                    <small class="text-muted">Customer</small>
                    <p class="fw-bold"><?php echo $order_data['username']; ?></p>
                </div>
                <div class="col-6">
                    <small class="text-muted">Total</small>
                    <p class="fw-bold">Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></p>
                </div>
                <div class="col-6">
                    <small class="text-muted">Status</small>
                    <p class="fw-bold text-success"><?php echo ucfirst($order_data['status']); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="transaksi.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Transaksi
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simulasi kamera scanner
        const scanner = document.querySelector('.scanner-container');
        scanner.addEventListener('click', function() {
            // Untuk demo, kita akan generate data QR code random
            const demoData = JSON.stringify({
                order_code: 'ORD-20241222-' + Math.floor(Math.random() * 1000000),
                customer: 'Customer Demo',
                total: Math.floor(Math.random() * 100000) + 50000,
                status: 'pending'
            });
            
            document.querySelector('textarea[name="scan_data"]').value = demoData;
            showToast('QR Code berhasil discan!', 'success');
        });
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>