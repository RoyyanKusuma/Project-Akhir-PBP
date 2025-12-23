<?php
// transaksi.php - Fitur utama: Transaksi Pemesanan
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya admin dan kasir yang bisa akses transaksi
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'kasir') {
    header('Location: pesanan.php');
    exit();
}

$message = '';
$message_type = '';
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Tambah ke keranjang
if (isset($_GET['add_to_cart'])) {
    $menu_id = intval($_GET['add_to_cart']);
    $query = "SELECT * FROM menu WHERE id = $menu_id AND stok > 0";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $menu = mysqli_fetch_assoc($result);
        
        if (isset($cart[$menu_id])) {
            $cart[$menu_id]['quantity'] += 1;
        } else {
            $cart[$menu_id] = [
                'id' => $menu['id'],
                'nama' => $menu['nama_menu'],
                'harga' => $menu['harga'],
                'quantity' => 1,
                'stok' => $menu['stok']
            ];
        }
        
        $_SESSION['cart'] = $cart;
        $message = 'Menu ditambahkan ke keranjang!';
        $message_type = 'success';
    } else {
        $message = 'Menu tidak tersedia atau stok habis!';
        $message_type = 'danger';
    }
}

// Update quantity
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $menu_id => $quantity) {
        $quantity = intval($quantity);
        if ($quantity > 0 && $quantity <= $cart[$menu_id]['stok']) {
            $cart[$menu_id]['quantity'] = $quantity;
        } elseif ($quantity > $cart[$menu_id]['stok']) {
            $message = 'Quantity melebihi stok yang tersedia!';
            $message_type = 'danger';
        }
    }
    $_SESSION['cart'] = $cart;
}

// Hapus item dari keranjang
if (isset($_GET['remove_from_cart'])) {
    $menu_id = intval($_GET['remove_from_cart']);
    if (isset($cart[$menu_id])) {
        unset($cart[$menu_id]);
        $_SESSION['cart'] = $cart;
        $message = 'Item dihapus dari keranjang!';
        $message_type = 'success';
    }
}

// Kosongkan keranjang
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = [];
    $cart = [];
    $message = 'Keranjang dikosongkan!';
    $message_type = 'success';
}

// Proses transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $user_id = $_SESSION['user_id'];
    $metode_pembayaran = clean_input($_POST['metode_pembayaran']);
    $total = 0;
    
    // Hitung total
    foreach ($cart as $item) {
        $total += $item['harga'] * $item['quantity'];
    }
    
    // Generate kode transaksi unik
    $kode_transaksi = 'TRX-' . date('YmdHis') . '-' . rand(100, 999);
    
    // Mulai transaksi database
    mysqli_begin_transaction($conn);
    
    try {
        // Insert transaksi
        $query_transaksi = "INSERT INTO transaksi (kode_transaksi, user_id, total_harga, metode_pembayaran, status) 
                           VALUES ('$kode_transaksi', $user_id, $total, '$metode_pembayaran', 'pending')";
        
        if (!mysqli_query($conn, $query_transaksi)) {
            throw new Exception("Gagal menyimpan transaksi: " . mysqli_error($conn));
        }
        
        $transaksi_id = mysqli_insert_id($conn);
        
        // Insert detail transaksi dan update stok
        foreach ($cart as $menu_id => $item) {
            $quantity = $item['quantity'];
            $harga_satuan = $item['harga'];
            $subtotal = $harga_satuan * $quantity;
            
            $query_detail = "INSERT INTO detail_transaksi (transaksi_id, menu_id, quantity, harga_satuan, subtotal) 
                            VALUES ($transaksi_id, $menu_id, $quantity, $harga_satuan, $subtotal)";
            
            if (!mysqli_query($conn, $query_detail)) {
                throw new Exception("Gagal menyimpan detail transaksi: " . mysqli_error($conn));
            }
            
            // Update stok
            $query_update_stok = "UPDATE menu SET stok = stok - $quantity WHERE id = $menu_id";
            if (!mysqli_query($conn, $query_update_stok)) {
                throw new Exception("Gagal update stok: " . mysqli_error($conn));
            }
        }
        
        // Commit transaksi
        mysqli_commit($conn);
        
        // Simpan notifikasi
        $notif_message = "Transaksi #$kode_transaksi berhasil dibuat dengan total Rp " . number_format($total, 0, ',', '.');
        $query_notif = "INSERT INTO notifications (user_id, type, message) 
                       VALUES ($user_id, 'success', '$notif_message')";
        mysqli_query($conn, $query_notif);
        
        // Clear cart dan redirect ke struk
        $_SESSION['cart'] = [];
        $_SESSION['last_transaction'] = $transaksi_id;
        
        $message = 'Transaksi berhasil! Total: Rp ' . number_format($total, 0, ',', '.');
        $message_type = 'success';
        
        // Redirect ke struk setelah 2 detik
        header("Refresh: 2; url=struk.php?id=$transaksi_id");
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = 'Transaksi gagal: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Ambil data menu untuk ditampilkan
$query_menu = "SELECT * FROM menu WHERE stok > 0 ORDER BY kategori, nama_menu";
$result_menu = mysqli_query($conn, $query_menu);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .menu-card {
            transition: transform 0.3s;
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .menu-card:hover {
            transform: translateY(-5px);
        }
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .cart-total {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 15px;
        }
        .quantity-input {
            width: 70px;
            text-align: center;
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
            <p class="text-white-50 small">Transaksi</p>
        </div>
        
        <div class="text-center mb-4">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                <i class="fas fa-cash-register fa-3x text-primary"></i>
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
            <a href="transaksi.php" class="nav-link active">
                <i class="fas fa-shopping-cart me-2"></i>Transaksi
            </a>
            <a href="riwayat.php" class="nav-link">
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
            <h1><i class="fas fa-shopping-cart me-2"></i>Transaksi</h1>
            <?php if (!empty($cart)): ?>
            <div>
                <a href="?clear_cart" class="btn btn-danger me-2" onclick="return confirm('Kosongkan keranjang?')">
                    <i class="fas fa-trash me-1"></i>Kosongkan
                </a>
                <span class="badge bg-primary p-2">
                    <i class="fas fa-shopping-basket me-1"></i>
                    <?php echo count($cart); ?> Item
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Daftar Menu -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-utensils me-2"></i>Daftar Menu Tersedia</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (mysqli_num_rows($result_menu) > 0): ?>
                                <?php while ($menu = mysqli_fetch_assoc($result_menu)): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card menu-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo $menu['nama_menu']; ?></h6>
                                                <span class="badge bg-success">Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?></span>
                                            </div>
                                            <p class="card-text small text-muted"><?php echo substr($menu['deskripsi'], 0, 50) . '...'; ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-info">Stok: <?php echo $menu['stok']; ?></span>
                                                <a href="?add_to_cart=<?php echo $menu['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-plus me-1"></i>Tambah
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>Tidak ada menu tersedia.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Keranjang Belanja -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-shopping-basket me-2"></i>Keranjang Belanja</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cart)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-basket fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Keranjang belanja kosong</p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <?php 
                                    $total = 0;
                                    foreach ($cart as $item): 
                                        $subtotal = $item['harga'] * $item['quantity'];
                                        $total += $subtotal;
                                    ?>
                                    <div class="cart-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo $item['nama']; ?></h6>
                                                <small class="text-muted">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?> / item</small>
                                            </div>
                                            <a href="?remove_from_cart=<?php echo $item['id']; ?>" class="text-danger">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <input type="number" name="quantity[<?php echo $item['id']; ?>]" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="<?php echo $item['stok']; ?>" 
                                                   class="form-control form-control-sm quantity-input">
                                            <span class="text-primary fw-bold">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="cart-total mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Total</h5>
                                        <h3 class="mb-0">Rp <?php echo number_format($total, 0, ',', '.'); ?></h3>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Metode Pembayaran</label>
                                    <select name="metode_pembayaran" class="form-select" required>
                                        <option value="tunai">Tunai</option>
                                        <option value="kartu">Kartu Debit/Kredit</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="update_cart" class="btn btn-warning">
                                        <i class="fas fa-sync me-2"></i>Update Keranjang
                                    </button>
                                    <button type="submit" name="checkout" class="btn btn-success btn-lg">
                                        <i class="fas fa-check-circle me-2"></i>Proses Pembayaran
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh cart count
        function updateCartCount() {
            fetch('ajax/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.badge.bg-primary');
                    if (badge) {
                        badge.innerHTML = `<i class="fas fa-shopping-basket me-1"></i>${data.count} Item`;
                    }
                });
        }
        
        // Update setiap 10 detik
        setInterval(updateCartCount, 10000);
    </script>
</body>
</html>