<?php
// pesanan.php - Halaman untuk pelanggan memesan menu
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$cart = isset($_SESSION['cart_customer']) ? $_SESSION['cart_customer'] : [];

// Handle pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? clean_input($_GET['kategori']) : '';

// Handle tambah ke keranjang
if (isset($_GET['add_to_cart'])) {
    $menu_id = intval($_GET['add_to_cart']);
    $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
    
    $query = "SELECT * FROM menu WHERE id = $menu_id AND stok > 0";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $menu = mysqli_fetch_assoc($result);
        
        if ($quantity > $menu['stok']) {
            $message = 'Stok tidak mencukupi! Stok tersedia: ' . $menu['stok'];
            $message_type = 'danger';
        } else {
            if (isset($cart[$menu_id])) {
                $cart[$menu_id]['quantity'] += $quantity;
            } else {
                $cart[$menu_id] = [
                    'id' => $menu['id'],
                    'nama' => $menu['nama_menu'],
                    'deskripsi' => $menu['deskripsi'],
                    'harga' => $menu['harga'],
                    'quantity' => $quantity,
                    'stok' => $menu['stok'],
                    'kategori' => $menu['kategori']
                ];
            }
            
            $_SESSION['cart_customer'] = $cart;
            $message = 'Menu "' . $menu['nama_menu'] . '" ditambahkan ke keranjang!';
            $message_type = 'success';
        }
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
            $message = 'Quantity melebihi stok yang tersedia untuk ' . $cart[$menu_id]['nama'] . '!';
            $message_type = 'danger';
        }
    }
    $_SESSION['cart_customer'] = $cart;
}

// Hapus item dari keranjang
if (isset($_GET['remove_from_cart'])) {
    $menu_id = intval($_GET['remove_from_cart']);
    if (isset($cart[$menu_id])) {
        $menu_name = $cart[$menu_id]['nama'];
        unset($cart[$menu_id]);
        $_SESSION['cart_customer'] = $cart;
        $message = '"' . $menu_name . '" dihapus dari keranjang!';
        $message_type = 'success';
    }
}

// Kosongkan keranjang
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart_customer'] = [];
    $cart = [];
    $message = 'Keranjang dikosongkan!';
    $message_type = 'success';
}

// Proses checkout/pemesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($cart)) {
        $message = 'Keranjang belanja kosong!';
        $message_type = 'danger';
    } else {
        $metode_pembayaran = clean_input($_POST['metode_pembayaran']);
        $catatan = clean_input($_POST['catatan'] ?? '');
        $total = 0;
        
        // Hitung total
        foreach ($cart as $item) {
            $total += $item['harga'] * $item['quantity'];
        }
        
        // Generate kode transaksi
        $kode_transaksi = 'ORD-' . date('YmdHis') . '-' . rand(100, 999);
        
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
            $notif_message = "Pesanan #$kode_transaksi berhasil dibuat. Total: Rp " . number_format($total, 0, ',', '.') . ". Status: Menunggu konfirmasi.";
            $query_notif = "INSERT INTO notifications (user_id, type, title, message) 
                           VALUES ($user_id, 'success', 'Pesanan Berhasil', '$notif_message')";
            mysqli_query($conn, $query_notif);
            
            // Notifikasi untuk admin/kasir
            $username = $_SESSION['username'];
            $query_admin_notif = "INSERT INTO notifications (user_id, type, title, message)
                                SELECT id, 'info', 'Pesanan Baru',
                                CONCAT('Pesanan baru #', '$kode_transaksi', ' oleh ', '$username',
                                       '. Total: Rp ', FORMAT($total, 0))
                                FROM users 
                                WHERE role IN ('admin', 'kasir')";
            mysqli_query($conn, $query_admin_notif);
            
            // Clear cart dan set session
            $_SESSION['cart_customer'] = [];
            $_SESSION['last_order'] = $transaksi_id;
            
            $message = 'Pesanan berhasil! Kode pesanan: ' . $kode_transaksi . '. Total: Rp ' . number_format($total, 0, ',', '.');
            $message_type = 'success';
            
            // Redirect ke detail pesanan setelah 3 detik
            header("Refresh: 3; url=detail_pesanan.php?id=$transaksi_id");
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = 'Pesanan gagal: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Query untuk menampilkan menu
$where_clause = "WHERE stok > 0";
if (!empty($search)) {
    $where_clause .= " AND (nama_menu LIKE '%$search%' OR deskripsi LIKE '%$search%')";
}
if (!empty($kategori) && $kategori !== 'semua') {
    $where_clause .= " AND kategori = '$kategori'";
}

$query_menu = "SELECT * FROM menu $where_clause ORDER BY kategori, nama_menu";
$result_menu = mysqli_query($conn, $query_menu);

// Hitung total keranjang
$total_keranjang = 0;
foreach ($cart as $item) {
    $total_keranjang += $item['harga'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Menu - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #000000ff;
            --secondary: #DBE2EF;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--primary) !important;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 80px 0 60px;
            margin-bottom: 40px;
            border-radius: 0 0 30px 30px;
        }
        
        .menu-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .menu-img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .menu-category {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .category-makanan {
            background: linear-gradient(135deg, #609966, #EDF1D6);
            color: white;
        }
        
        .category-minuman {
            background: linear-gradient(135deg, #FFC7C7, #F8E8EE);
            color: white;
        }
        
        .category-snack {
            background: linear-gradient(135deg, #AD8B73, #FFFBE9);
            color: white;
        }
        
        .cart-sidebar {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 25px;
            height: fit-content;
        }
        
        .cart-item {
            border-bottom: 1px dashed #eee;
            padding: 15px 0;
        }
        
        .cart-total {
            background: linear-gradient(135deg, var(--warning), var(--danger));
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .badge-cart {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .menu-price {
            color: var(--primary);
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .menu-stok {
            font-size: 0.9rem;
            color: #666;
        }
        
        .btn-order {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 45px;
            border-radius: 25px;
            border: 2px solid #eee;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0 40px;
            }
            
            .cart-sidebar {
                position: relative;
                top: 0;
                margin-top: 30px;
            }
        }
        
        .notification-bell {
            position: relative;
            margin-right: 15px;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 25px;
            margin: 0 5px;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .menu-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-utensils me-2"></i>Resto Delight
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="pesanan.php"><i class="fas fa-shopping-cart me-1"></i>Pesan Menu</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="riwayat.php"><i class="fas fa-history me-1"></i>Riwayat Pesanan</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <!-- Notification Bell -->
                    <div class="notification-bell me-3">
                        <a href="notifications.php" class="text-dark position-relative">
                            <i class="fas fa-bell fa-lg <?php echo $unread_count > 0 ? 'pulse' : ''; ?>"></i>
                            <?php 
                            $query_unread = "SELECT COUNT(*) as count FROM notifications 
                                            WHERE user_id = $user_id AND is_read = FALSE";
                            $result_unread = mysqli_query($conn, $query_unread);
                            $unread_count = mysqli_fetch_assoc($result_unread)['count'];
                            if ($unread_count > 0): 
                            ?>
                            <span class="badge-cart"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <!-- Cart Icon -->
                    <div class="position-relative me-3">
                        <a href="#cart-section" class="text-dark position-relative">
                            <i class="fas fa-shopping-basket fa-lg"></i>
                            <?php if (!empty($cart)): ?>
                            <span class="badge-cart"><?php echo count($cart); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <span class="ms-2"><?php echo $_SESSION['username']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="riwayat.php"><i class="fas fa-history me-2"></i>Riwayat Saya</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">Pesan Menu Favorit Anda</h1>
                    <p class="lead mb-4">Temukan berbagai pilihan makanan dan minuman lezat dari Resto Delight. Pesan sekarang, nikmati kenyamanan!</p>
                    
                    <!-- Search Box -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="form-control form-control-lg" name="search" 
                                           placeholder="Cari menu favorit..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="kategori" class="form-select form-select-lg">
                                    <option value="semua">Semua Kategori</option>
                                    <option value="makanan" <?php echo $kategori === 'makanan' ? 'selected' : ''; ?>>Makanan</option>
                                    <option value="minuman" <?php echo $kategori === 'minuman' ? 'selected' : ''; ?>>Minuman</option>
                                    <option value="snack" <?php echo $kategori === 'snack' ? 'selected' : ''; ?>>Snack</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-light btn-lg w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-primary p-2">
                            <i class="fas fa-utensils me-1"></i>
                            <?php echo mysqli_num_rows($result_menu); ?> Menu Tersedia
                        </span>
                        <?php if (!empty($cart)): ?>
                        <span class="badge bg-warning text-dark p-2">
                            <i class="fas fa-shopping-basket me-1"></i>
                            <?php echo count($cart); ?> Item dalam Keranjang
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-motorcycle fa-7x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2 fa-lg"></i>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Menu List -->
            <div class="col-lg-8">
                <!-- Category Tabs -->
                <div class="filter-card mb-4">
                    <ul class="nav nav-tabs justify-content-center border-0" id="categoryTabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo empty($kategori) || $kategori === 'semua' ? 'active' : ''; ?>" 
                               href="?kategori=semua">Semua</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $kategori === 'makanan' ? 'active' : ''; ?>" 
                               href="?kategori=makanan">Makanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $kategori === 'minuman' ? 'active' : ''; ?>" 
                               href="?kategori=minuman">Minuman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $kategori === 'snack' ? 'active' : ''; ?>" 
                               href="?kategori=snack">Snack</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Menu Grid -->
                <div class="row" id="menu-grid">
                    <?php if (mysqli_num_rows($result_menu) > 0): ?>
                        <?php while ($menu = mysqli_fetch_assoc($result_menu)): 
                            $category_class = 'category-' . $menu['kategori'];
                            $category_label = ucfirst($menu['kategori']);
                        ?>
                        <div class="col-md-6 col-lg-6 mb-4">
                            <div class="menu-card h-100">
                                <?php if ($menu['gambar']): ?>
                                <img src="<?php echo $menu['gambar']; ?>" class="menu-img" alt="<?php echo $menu['nama_menu']; ?>">
                                <?php else: ?>
                                <div class="menu-img d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-<?php echo $menu['kategori'] === 'makanan' ? 'hamburger' : 
                                                     ($menu['kategori'] === 'minuman' ? 'wine-glass' : 'cookie'); ?> fa-4x text-muted"></i>
                                </div>
                                <?php endif; ?>
                                
                                <span class="menu-category <?php echo $category_class; ?>">
                                    <?php echo $category_label; ?>
                                </span>
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo $menu['nama_menu']; ?></h5>
                                        <span class="menu-price">
                                            Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="menu-description"><?php echo $menu['deskripsi']; ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="menu-stok">
                                                <i class="fas fa-box me-1"></i>
                                                Stok: <?php echo $menu['stok']; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="quantity-control">
                                            <form method="GET" class="d-flex align-items-center">
                                                <input type="hidden" name="add_to_cart" value="<?php echo $menu['id']; ?>">
                                                <button type="button" class="quantity-btn minus" onclick="decreaseQuantity(<?php echo $menu['id']; ?>)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" id="quantity-<?php echo $menu['id']; ?>" 
                                                       name="quantity" value="1" min="1" max="<?php echo $menu['stok']; ?>"
                                                       class="quantity-input">
                                                <button type="button" class="quantity-btn plus" onclick="increaseQuantity(<?php echo $menu['id']; ?>, <?php echo $menu['stok']; ?>)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="submit" class="btn btn-order ms-3">
                                                    <i class="fas fa-cart-plus me-2"></i>Tambah
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-search fa-4x mb-3"></i>
                                <h4>Menu tidak ditemukan</h4>
                                <p class="text-muted">Coba kata kunci pencarian lain atau kategori berbeda.</p>
                                <a href="pesanan.php" class="btn btn-primary">
                                    <i class="fas fa-redo me-2"></i>Tampilkan Semua Menu
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cart Sidebar -->
            <div class="col-lg-4">
                <div class="cart-sidebar" id="cart-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">
                            <i class="fas fa-shopping-basket me-2 text-primary"></i>
                            Keranjang Saya
                        </h4>
                        <?php if (!empty($cart)): ?>
                        <a href="?clear_cart" class="btn btn-sm btn-outline-danger" onclick="return confirm('Kosongkan keranjang belanja?')">
                            <i class="fas fa-trash me-1"></i>Kosongkan
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($cart)): ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-shopping-basket fa-4x text-muted mb-3"></i>
                            <h5>Keranjang Kosong</h5>
                            <p class="text-muted">Tambahkan menu favorit Anda ke keranjang</p>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="cart-items mb-3">
                                <?php foreach ($cart as $item): ?>
                                <div class="cart-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="me-3">
                                            <h6 class="mb-1"><?php echo $item['nama']; ?></h6>
                                            <small class="text-muted">
                                                Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?> / item
                                            </small>
                                        </div>
                                        <a href="?remove_from_cart=<?php echo $item['id']; ?>" class="text-danger" 
                                           onclick="return confirm('Hapus <?php echo $item['nama']; ?> dari keranjang?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn minus" onclick="decreaseCartQuantity(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" name="quantity[<?php echo $item['id']; ?>]" 
                                                   id="cart-quantity-<?php echo $item['id']; ?>"
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="<?php echo $item['stok']; ?>"
                                                   class="quantity-input">
                                            <button type="button" class="quantity-btn plus" onclick="increaseCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['stok']; ?>)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <span class="fw-bold text-primary">
                                            Rp <?php echo number_format($item['harga'] * $item['quantity'], 0, ',', '.'); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="cart-total">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Total</h5>
                                    <h3 class="mb-0">Rp <?php echo number_format($total_keranjang, 0, ',', '.'); ?></h3>
                                </div>
                                
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" name="update_cart" class="btn btn-warning">
                                        <i class="fas fa-sync me-2"></i>Update Keranjang
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Metode Pembayaran</h6>
                                <div class="mb-3">
                                    <select name="metode_pembayaran" class="form-select" required>
                                        <option value="tunai" selected>Tunai</option>
                                        <option value="kartu">Kartu Debit/Kredit</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Catatan Pesanan (opsional)</label>
                                    <textarea name="catatan" class="form-control" rows="3" 
                                              placeholder="Contoh: Tidak pakai pedas, tambah sambal, dll."></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="checkout" class="btn btn-success btn-lg">
                                        <i class="fas fa-check-circle me-2"></i>Checkout Sekarang
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Informasi Tambahan -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center text-muted mb-2">
                            <i class="fas fa-clock me-2"></i>
                            <small>Estimasi penyiapan: 15-30 menit</small>
                        </div>
                        <div class="d-flex align-items-center text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Pesanan akan diproses setelah pembayaran</small>
                        </div>
                    </div>
                </div>
                
                <!-- Promo Banner -->
                <div class="card border-0 shadow-sm mt-4" style="background: linear-gradient(135deg, #FF2E63 0%, #B1B2FF 100%);">
                    <div class="card-body text-white text-center">
                        <h5><i class="fas fa-gift me-2"></i>Promo Spesial!</h5>
                        <p class="mb-2">Diskon 10% untuk pembelian minimal Rp 100.000</p>
                        <small>Berlaku hingga 31 Desember 2025</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-3"><i class="fas fa-utensils me-2"></i>Resto Delight</h4>
                    <p class="text-white-50">Menyajikan makanan dan minuman terbaik dengan kualitas premium dan pelayanan terbaik.</p>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-3">Menu</h5>
                    <ul class="list-unstyled">
                        <li><a href="pesanan.php" class="text-white-50 text-decoration-none">Pesan Online</a></li>
                        <li><a href="menu.php" class="text-white-50 text-decoration-none">Daftar Menu</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Promo</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-3">Kontak</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i>Jl. Resto No. 123, Jakarta</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i>(021) 9079-3769</li>
                        <li><i class="fas fa-envelope me-2"></i>info@restodelight.com</li>
                    </ul>
                </div>
                <div class="col-lg-3 mb-4">
                    <h5 class="mb-3">Jam Operasional</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">Senin - Jumat: 10:00 - 22:00</li>
                        <li class="mb-2">Sabtu - Minggu: 09:00 - 23:00</li>
                    </ul>
                </div>
            </div>
            <hr class="bg-white-50">
            <div class="text-center text-white-50">
                <p class="mb-0">&copy; 2025 Resto Delight. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk quantity control
        function increaseQuantity(menuId, maxStock) {
            const input = document.getElementById('quantity-' + menuId);
            let value = parseInt(input.value);
            if (value < maxStock) {
                input.value = value + 1;
            } else {
                alert('Stok tidak mencukupi! Stok tersedia: ' + maxStock);
            }
        }
        
        function decreaseQuantity(menuId) {
            const input = document.getElementById('quantity-' + menuId);
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
            }
        }
        
        function increaseCartQuantity(menuId, maxStock) {
            const input = document.getElementById('cart-quantity-' + menuId);
            let value = parseInt(input.value);
            if (value < maxStock) {
                input.value = value + 1;
            } else {
                alert('Stok tidak mencukupi! Stok tersedia: ' + maxStock);
            }
        }
        
        function decreaseCartQuantity(menuId) {
            const input = document.getElementById('cart-quantity-' + menuId);
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
            }
        }
        
        // Real-time cart update
        function updateCartBadge() {
            const cartCount = <?php echo count($cart); ?>;
            const badge = document.querySelector('.badge-cart');
            if (cartCount > 0) {
                if (!badge) {
                    const cartIcon = document.querySelector('.fa-shopping-basket').parentElement;
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge-cart';
                    newBadge.textContent = cartCount;
                    cartIcon.appendChild(newBadge);
                } else {
                    badge.textContent = cartCount;
                }
            } else if (badge) {
                badge.remove();
            }
        }
        
        // Auto refresh cart setiap 10 detik
        setInterval(() => {
            fetch('ajax/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.badge-cart');
                    if (data.count > 0) {
                        if (!badge) {
                            const cartIcon = document.querySelector('.fa-shopping-basket').parentElement;
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge-cart';
                            newBadge.textContent = data.count;
                            cartIcon.appendChild(newBadge);
                        } else {
                            badge.textContent = data.count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });
        }, 10000);
        
        // Smooth scroll untuk cart
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                if (this.getAttribute('href') === '#cart-section') {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Notifikasi toast
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
        
        // Inisialisasi
        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge();
            
            // Cek jika ada pesan sukses dari checkout
            <?php if ($message_type === 'success' && strpos($message, 'Pesanan berhasil') !== false): ?>
            showToast('<?php echo addslashes($message); ?>', 'success');
            <?php endif; ?>
        });
    </script>
</body>
</html>