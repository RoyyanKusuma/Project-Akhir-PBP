<?php
// laporan.php - Fitur tambahan: Laporan dan Analytics (Admin only)
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya admin yang bisa akses laporan
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Set default date range (bulan ini)
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-d');

// Query untuk statistik utama
$query_total_transaksi = "SELECT COUNT(*) as total FROM transaksi 
                         WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
$total_transaksi = mysqli_fetch_assoc(mysqli_query($conn, $query_total_transaksi))['total'];

$query_total_pendapatan = "SELECT COALESCE(SUM(total_harga), 0) as total FROM transaksi 
                          WHERE status = 'selesai' 
                          AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
$total_pendapatan = mysqli_fetch_assoc(mysqli_query($conn, $query_total_pendapatan))['total'];

$query_total_menu_terjual = "SELECT COALESCE(SUM(dt.quantity), 0) as total 
                            FROM detail_transaksi dt 
                            JOIN transaksi t ON dt.transaksi_id = t.id 
                            WHERE t.status = 'selesai' 
                            AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
$total_menu_terjual = mysqli_fetch_assoc(mysqli_query($conn, $query_total_menu_terjual))['total'];

$query_rata_transaksi = "SELECT COALESCE(AVG(total_harga), 0) as rata FROM transaksi 
                        WHERE status = 'selesai' 
                        AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
$rata_transaksi = mysqli_fetch_assoc(mysqli_query($conn, $query_rata_transaksi))['rata'];

// Data untuk chart pendapatan per hari
$query_pendapatan_harian = "SELECT DATE(created_at) as tanggal, 
                           COALESCE(SUM(total_harga), 0) as total 
                           FROM transaksi 
                           WHERE status = 'selesai' 
                           AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                           GROUP BY DATE(created_at) 
                           ORDER BY tanggal";
$result_pendapatan_harian = mysqli_query($conn, $query_pendapatan_harian);

// Data untuk chart menu terlaris
$query_menu_terlaris = "SELECT m.nama_menu, SUM(dt.quantity) as jumlah_terjual, 
                       SUM(dt.subtotal) as total_pendapatan 
                       FROM detail_transaksi dt 
                       JOIN menu m ON dt.menu_id = m.id 
                       JOIN transaksi t ON dt.transaksi_id = t.id 
                       WHERE t.status = 'selesai' 
                       AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
                       GROUP BY m.id 
                       ORDER BY jumlah_terjual DESC 
                       LIMIT 10";
$result_menu_terlaris = mysqli_query($conn, $query_menu_terlaris);

// Data untuk chart status transaksi
$query_status_transaksi = "SELECT status, COUNT(*) as jumlah 
                          FROM transaksi 
                          WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                          GROUP BY status";
$result_status_transaksi = mysqli_query($conn, $query_status_transaksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            opacity: 0.8;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .filter-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .export-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
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
            .export-buttons {
                position: static;
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h3 class="text-white">Resto Delight</h3>
            <p class="text-white-50 small">Laporan</p>
        </div>
        
        <div class="text-center mb-4">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                <i class="fas fa-chart-bar fa-3x text-primary"></i>
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
            <a href="riwayat.php" class="nav-link">
                <i class="fas fa-history me-2"></i>Riwayat
            </a>
            <a href="laporan.php" class="nav-link active">
                <i class="fas fa-chart-bar me-2"></i>Laporan
            </a>
            <a href="pengguna.php" class="nav-link">
                <i class="fas fa-users me-2"></i>Pengguna
            </a>
            <div class="mt-4">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </nav>
    </div>
    
    <div class="main-content">
        <h1 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Laporan & Analytics</h1>
        
        <!-- Filter -->
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label text-white">Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-white">Tipe Laporan</label>
                        <select name="report_type" class="form-select">
                            <option value="harian">Harian</option>
                            <option value="mingguan">Mingguan</option>
                            <option value="bulanan" selected>Bulanan</option>
                            <option value="tahunan">Tahunan</option>
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
        
        <!-- Statistik Utama -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Transaksi</h6>
                            <h3><?php echo number_format($total_transaksi); ?></h3>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>Bulan ini
                            </small>
                        </div>
                        <i class="fas fa-receipt stat-icon text-primary"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Pendapatan</h6>
                            <h3>Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>Bulan ini
                            </small>
                        </div>
                        <i class="fas fa-money-bill-wave stat-icon text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Menu Terjual</h6>
                            <h3><?php echo number_format($total_menu_terjual); ?></h3>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>Bulan ini
                            </small>
                        </div>
                        <i class="fas fa-utensils stat-icon text-warning"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Rata-rata Transaksi</h6>
                            <h3>Rp <?php echo number_format($rata_transaksi, 0, ',', '.'); ?></h3>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>Bulan ini
                            </small>
                        </div>
                        <i class="fas fa-chart-line stat-icon text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5 class="mb-4"><i class="fas fa-chart-line me-2"></i>Pendapatan Harian</h5>
                    <canvas id="pendapatanChart" height="250"></canvas>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Status Transaksi</h5>
                    <canvas id="statusChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-4"><i class="fas fa-star me-2"></i>10 Menu Terlaris</h5>
                    <canvas id="menuChart" height="150"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tabel Detail Laporan -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-table me-2"></i>Detail Transaksi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Tanggal</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Metode Bayar</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query_detail = "SELECT t.*, u.username 
                                           FROM transaksi t 
                                           LEFT JOIN users u ON t.user_id = u.id 
                                           WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
                                           ORDER BY t.created_at DESC 
                                           LIMIT 20";
                            $result_detail = mysqli_query($conn, $query_detail);
                            
                            while ($row = mysqli_fetch_assoc($result_detail)): 
                            ?>
                            <tr>
                                <td><?php echo $row['kode_transaksi']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                <td><?php echo $row['username'] ?? 'Guest'; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $row['status'] === 'selesai' ? 'success' : 
                                             ($row['status'] === 'pending' ? 'warning' : 
                                             ($row['status'] === 'diproses' ? 'info' : 'danger')); 
                                    ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst($row['metode_pembayaran']); ?></td>
                                <td class="text-end">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <td colspan="5" class="text-end fw-bold">Total Pendapatan:</td>
                                <td class="text-end fw-bold">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Export Buttons -->
        <div class="export-buttons">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </button>
                <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </button>
                <button type="button" class="btn btn-info" onclick="printLaporan()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Data untuk chart pendapatan harian
        <?php
        $labels_pendapatan = [];
        $data_pendapatan = [];
        
        while ($row = mysqli_fetch_assoc($result_pendapatan_harian)) {
            $labels_pendapatan[] = date('d M', strtotime($row['tanggal']));
            $data_pendapatan[] = $row['total'];
        }
        
        mysqli_data_seek($result_pendapatan_harian, 0);
        ?>
        
        // Data untuk chart status transaksi
        <?php
        $labels_status = [];
        $data_status = [];
        $colors_status = [];
        
        while ($row = mysqli_fetch_assoc($result_status_transaksi)) {
            $labels_status[] = ucfirst($row['status']);
            $data_status[] = $row['jumlah'];
            
            switch($row['status']) {
                case 'pending': $colors_status.push('#ffc107'); break;
                case 'diproses': $colors_status.push('#17a2b8'); break;
                case 'selesai': $colors_status.push('#28a745'); break;
                case 'dibatalkan': $colors_status.push('#dc3545'); break;
            }
        }
        
        mysqli_data_seek($result_status_transaksi, 0);
        ?>
        
        // Data untuk chart menu terlaris
        <?php
        $labels_menu = [];
        $data_menu = [];
        
        while ($row = mysqli_fetch_assoc($result_menu_terlaris)) {
            $labels_menu[] = substr($row['nama_menu'], 0, 20) . '...';
            $data_menu[] = $row['jumlah_terjual'];
        }
        
        mysqli_data_seek($result_menu_terlaris, 0);
        ?>
        
        // Chart Pendapatan Harian
        const ctxPendapatan = document.getElementById('pendapatanChart').getContext('2d');
        const pendapatanChart = new Chart(ctxPendapatan, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels_pendapatan); ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?php echo json_encode($data_pendapatan); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Chart Status Transaksi
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($labels_status); ?>,
                datasets: [{
                    data: <?php echo json_encode($data_status); ?>,
                    backgroundColor: <?php echo json_encode($colors_status); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Chart Menu Terlaris
        const ctxMenu = document.getElementById('menuChart').getContext('2d');
        const menuChart = new Chart(ctxMenu, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_menu); ?>,
                datasets: [{
                    label: 'Jumlah Terjual',
                    data: <?php echo json_encode($data_menu); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Fungsi Export
        function exportToExcel() {
            alert('Fitur export Excel akan diimplementasikan!');
            // Implementasi export Excel bisa menggunakan library seperti SheetJS
        }
        
        function exportToPDF() {
            alert('Fitur export PDF akan diimplementasikan!');
            // Implementasi export PDF bisa menggunakan library seperti jsPDF
        }
        
        function printLaporan() {
            window.print();
        }
        
        // Auto refresh chart data setiap 1 menit
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>