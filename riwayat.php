<?php
// riwayat.php - Fitur utama: Riwayat Pemesanan
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter berdasarkan role
if ($role === 'admin' || $role === 'kasir') {
    $where_clause = "1=1";
    $title = "Semua Riwayat Transaksi";
} else {
    $where_clause = "t.user_id = $user_id";
    $title = "Riwayat Pesanan Saya";
}

// Filter tanggal
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-d');

// Query untuk mendapatkan riwayat transaksi
$query = "SELECT t.*, u.username, 
          (SELECT COUNT(*) FROM detail_transaksi dt WHERE dt.transaksi_id = t.id) as jumlah_item
          FROM transaksi t
          LEFT JOIN users u ON t.user_id = u.id
          WHERE $where_clause 
          AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
          ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #000000ff 0%, #DBE2EF 100%);
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
        .history-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
            border: none;
        }
        .history-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-diproses { background-color: #17a2b8; color: #fff; }
        .status-selesai { background-color: #28a745; color: #fff; }
        .status-dibatalkan { background-color: #dc3545; color: #fff; }
        .detail-row {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .filter-card {
            background: linear-gradient(135deg, #000000ff, #DBE2EF);
            color: white;
            border-radius: 15px;
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
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h3 class="text-white">Resto Delight</h3>
            <p class="text-white-50 small">Riwayat</p>
        </div>
        
        <div class="text-center mb-4">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                <i class="fas fa-history fa-3x text-primary"></i>
            </div>
            <h5 class="text-white mt-2"><?php echo $_SESSION['username']; ?></h5>
            <span class="badge bg-light text-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
        </div>
        
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            <a href="menu.php" class="nav-link">
                <i class="fas fa-utensils me-2"></i>Kelola Menu
            </a>
            <a href="transaksi.php" class="nav-link">
                <i class="fas fa-shopping-cart me-2"></i>Transaksi
            </a>
            <a href="riwayat.php" class="nav-link active">
                <i class="fas fa-history me-2"></i>Riwayat
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="laporan.php" class="nav-link">
                <i class="fas fa-chart-bar me-2"></i>Laporan
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-history me-2"></i><?php echo $title; ?></h1>
            <button class="btn btn-primary" onclick="printRiwayat()">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
        
        <!-- Filter -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label text-white">Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-white">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua</option>
                            <option value="pending">Pending</option>
                            <option value="diproses">Diproses</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-light w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Daftar Riwayat -->
        <div id="riwayat-content">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($transaksi = mysqli_fetch_assoc($result)): 
                    $status_class = 'status-' . $transaksi['status'];
                ?>
                <div class="card history-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title"><?php echo $transaksi['kode_transaksi']; ?></h5>
                                        <p class="card-text text-muted">
                                            <i class="fas fa-user me-1"></i><?php echo $transaksi['username'] ?? 'Guest'; ?> |
                                            <i class="fas fa-calendar me-1 ms-2"></i><?php echo date('d/m/Y H:i', strtotime($transaksi['created_at'])); ?>
                                        </p>
                                    </div>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($transaksi['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <small class="text-muted">Total</small>
                                        <h4>Rp <?php echo number_format($transaksi['total_harga'], 0, ',', '.'); ?></h4>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Metode Bayar</small>
                                        <p class="mb-0"><?php echo ucfirst($transaksi['metode_pembayaran']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Jumlah Item</small>
                                        <p class="mb-0"><?php echo $transaksi['jumlah_item']; ?> item</p>
                                    </div>
                                </div>
                                
                                <!-- Detail Items -->
                                <?php 
                                $detail_query = "SELECT dt.*, m.nama_menu 
                                               FROM detail_transaksi dt 
                                               JOIN menu m ON dt.menu_id = m.id 
                                               WHERE dt.transaksi_id = {$transaksi['id']}";
                                $detail_result = mysqli_query($conn, $detail_query);
                                ?>
                                <div class="mt-3">
                                    <h6>Detail Pesanan:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Menu</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Harga</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($detail = mysqli_fetch_assoc($detail_result)): ?>
                                                <tr>
                                                    <td><?php echo $detail['nama_menu']; ?></td>
                                                    <td class="text-center"><?php echo $detail['quantity']; ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($detail['harga_satuan'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="border-start ps-4">
                                    <h6 class="mb-3">Aksi</h6>
                                    <div class="d-grid gap-2">
                                        <a href="struk.php?id=<?php echo $transaksi['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-receipt me-2"></i>Lihat Struk
                                        </a>
                                        
                                        <?php if (($role === 'admin' || $role === 'kasir') && $transaksi['status'] === 'pending'): ?>
                                        <form method="POST" action="update_status.php" class="d-grid">
                                            <input type="hidden" name="transaksi_id" value="<?php echo $transaksi['id']; ?>">
                                            <button type="submit" name="status" value="diproses" class="btn btn-warning">
                                                <i class="fas fa-play me-2"></i>Proses
                                            </button>
                                            <button type="submit" name="status" value="selesai" class="btn btn-success mt-2">
                                                <i class="fas fa-check me-2"></i>Selesaikan
                                            </button>
                                            <button type="submit" name="status" value="dibatalkan" class="btn btn-danger mt-2">
                                                <i class="fas fa-times me-2"></i>Batalkan
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($transaksi['status'] === 'selesai'): ?>
                                    <div class="mt-3 text-center">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                        <p class="text-success mt-2 mb-0">Transaksi Selesai</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-history fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada riwayat transaksi</h5>
                        <p class="text-muted">Transaksi akan muncul di sini setelah Anda melakukan pemesanan.</p>
                        <a href="transaksi.php" class="btn btn-primary mt-3">
                            <i class="fas fa-shopping-cart me-2"></i>Buat Transaksi
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php 
        $total_rows = mysqli_num_rows($result);
        $rows_per_page = 10;
        $total_pages = ceil($total_rows / $rows_per_page);
        
        if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($i == 1) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item">
                    <a class="page-link" href="#">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printRiwayat() {
            var content = document.getElementById('riwayat-content').innerHTML;
            var printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Cetak Riwayat - Resto Delight</title>');
            printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<style>body{padding:20px;}</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h2 class="text-center mb-4"><?php echo $title; ?></h2>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
        
        // Auto refresh riwayat setiap 30 detik
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>