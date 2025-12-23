<?php
// sidebar.php - Komponen sidebar untuk semua halaman
if (!isset($_SESSION['user_id'])) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="text-center mb-4">
        <h3 class="text-white">Resto Delight</h3>
        <p class="text-white-50 small">Sistem Restoran</p>
    </div>
    
    <div class="text-center mb-4">
        <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
            <i class="fas fa-user-circle fa-3x text-primary"></i>
        </div>
        <h5 class="text-white mt-2"><?php echo $_SESSION['username']; ?></h5>
        <span class="badge bg-light text-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
    </div>
    
    <nav class="nav flex-column">
        <a href="index.php" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'kasir'): ?>
        <a href="menu.php" class="nav-link <?php echo $current_page === 'menu.php' ? 'active' : ''; ?>">
            <i class="fas fa-utensils me-2"></i>Kelola Menu
        </a>
        <a href="transaksi.php" class="nav-link <?php echo $current_page === 'transaksi.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart me-2"></i>Transaksi
        </a>
        <?php endif; ?>
        
        <a href="pesanan.php" class="nav-link <?php echo $current_page === 'pesanan.php' ? 'active' : ''; ?>">
            <i class="fas fa-list-alt me-2"></i>Pesan Menu
        </a>
        <a href="riwayat.php" class="nav-link <?php echo $current_page === 'riwayat.php' ? 'active' : ''; ?>">
            <i class="fas fa-history me-2"></i>Riwayat
        </a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="laporan.php" class="nav-link <?php echo $current_page === 'laporan.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar me-2"></i>Laporan
        </a>
        <a href="pengguna.php" class="nav-link <?php echo $current_page === 'pengguna.php' ? 'active' : ''; ?>">
            <i class="fas fa-users me-2"></i>Pengguna
        </a>
        <?php endif; ?>
        
        <!-- Notification Bell -->
        <div class="notification-badge mt-3 ms-3">
            <?php 
            require_once 'config.php';
            $user_id = $_SESSION['user_id'];
            $query_unread = "SELECT COUNT(*) as count FROM notifications 
                            WHERE (user_id = $user_id OR user_id IS NULL) 
                            AND is_read = FALSE";
            $unread_count = mysqli_fetch_assoc(mysqli_query($conn, $query_unread))['count'];
            ?>
            <a href="notifications.php" class="nav-link text-warning">
                <i class="fas fa-bell me-2"></i>Notifikasi
                <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="mt-4">
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </nav>
</div>