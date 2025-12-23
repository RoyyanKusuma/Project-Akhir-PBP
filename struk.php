<?php
// struk.php - Halaman untuk mencetak struk
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$transaksi_id = isset($_GET['id']) ? intval($_GET['id']) : 
                (isset($_SESSION['last_transaction']) ? $_SESSION['last_transaction'] : 0);

$query = "SELECT t.*, u.username, u.email 
          FROM transaksi t 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE t.id = $transaksi_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    die('Transaksi tidak ditemukan!');
}

$transaksi = mysqli_fetch_assoc($result);

// Ambil detail transaksi
$query_detail = "SELECT dt.*, m.nama_menu 
                FROM detail_transaksi dt 
                JOIN menu m ON dt.menu_id = m.id 
                WHERE dt.transaksi_id = $transaksi_id";
$result_detail = mysqli_query($conn, $query_detail);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?php echo $transaksi['kode_transaksi']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 20px;
                font-size: 14px;
            }
            .struk-container {
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
        }
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .struk-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .struk-header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .struk-item {
            border-bottom: 1px dashed #eee;
            padding: 10px 0;
        }
        .struk-footer {
            border-top: 2px dashed #ccc;
            padding-top: 20px;
            margin-top: 20px;
            text-align: center;
        }
        .barcode {
            font-family: monospace;
            letter-spacing: 3px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="struk-container">
        <div class="struk-header">
            <h2 class="mb-1">Resto Delight</h2>
            <p class="text-muted mb-2">Jl. Resto No. 123, Kota Delight</p>
            <p class="text-muted mb-0">Telp: (021) 9079-3769</p>
            <hr class="my-3">
            <h4 class="mb-0">STRUK PEMBAYARAN</h4>
            <p class="text-muted mb-0"><?php echo $transaksi['kode_transaksi']; ?></p>
        </div>
        
        <div class="mb-4">
            <div class="row mb-2">
                <div class="col-6">
                    <small class="text-muted">Tanggal</small>
                    <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($transaksi['created_at'])); ?></p>
                </div>
                <div class="col-6 text-end">
                    <small class="text-muted">Kasir</small>
                    <p class="mb-0"><?php echo $_SESSION['username']; ?></p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <small class="text-muted">Customer</small>
                    <p class="mb-0"><?php echo $transaksi['username'] ?? 'Guest'; ?></p>
                </div>
            </div>
        </div>
        
        <div class="mb-4">
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
                    <?php 
                    $total = 0;
                    while ($detail = mysqli_fetch_assoc($result_detail)): 
                        $total += $detail['subtotal'];
                    ?>
                    <tr>
                        <td><?php echo $detail['nama_menu']; ?></td>
                        <td class="text-center"><?php echo $detail['quantity']; ?></td>
                        <td class="text-end">Rp <?php echo number_format($detail['harga_satuan'], 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Total</td>
                        <td class="text-end fw-bold">Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end">Metode Bayar</td>
                        <td class="text-end"><?php echo ucfirst($transaksi['metode_pembayaran']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="barcode text-center">
            <div style="font-size: 24px; letter-spacing: 8px;"><?php echo str_replace('-', '', $transaksi['kode_transaksi']); ?></div>
            <small class="text-muted">SCAN CODE</small>
        </div>
        
        <div class="struk-footer">
            <p class="text-muted mb-2">Terima kasih atas kunjungan Anda!</p>
            <p class="text-muted mb-0">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
            <hr class="my-3">
            <p class="small text-muted mb-0">Struk ini sah sebagai bukti pembayaran</p>
        </div>
    </div>
    
    <div class="text-center mt-3 no-print">
        <button onclick="window.print()" class="btn btn-primary me-2">
            <i class="fas fa-print me-2"></i>Cetak Struk
        </button>
        <a href="transaksi.php" class="btn btn-success">
            <i class="fas fa-shopping-cart me-2"></i>Transaksi Baru
        </a>
    </div>
    
    <script>
        // Auto print saat halaman dimuat
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>