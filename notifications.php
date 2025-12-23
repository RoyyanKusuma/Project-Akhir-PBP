<?php
// notifications.php - Versi aman dengan error handling
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Cek apakah tabel notifications ada
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

if (mysqli_num_rows($check_table) == 0) {
    // Tabel tidak ada, buat dulu
    $create_table = "CREATE TABLE IF NOT EXISTS notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        title VARCHAR(255),
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!mysqli_query($conn, $create_table)) {
        die("Gagal membuat tabel notifications. Silakan jalankan setup database terlebih dahulu.");
    }
}

// Lanjutkan dengan kode biasa...
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Create notifications table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    title VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_table);

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = $user_id";
    mysqli_query($conn, $query);
}

// Mark single as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $query = "UPDATE notifications SET is_read = TRUE WHERE id = $notif_id AND user_id = $user_id";
    mysqli_query($conn, $query);
}

// Delete notification
if (isset($_GET['delete_notif'])) {
    $notif_id = intval($_GET['delete_notif']);
    $query = "DELETE FROM notifications WHERE id = $notif_id AND user_id = $user_id";
    mysqli_query($conn, $query);
}

// Get notifications
$query = "SELECT * FROM notifications 
          WHERE user_id = $user_id 
          OR user_id IS NULL
          ORDER BY created_at DESC 
          LIMIT 50";
$result = mysqli_query($conn, $query);

// Count unread notifications
$query_unread = "SELECT COUNT(*) as count FROM notifications 
                WHERE (user_id = $user_id OR user_id IS NULL) 
                AND is_read = FALSE";
$unread_count = mysqli_fetch_assoc(mysqli_query($conn, $query_unread))['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .notification-badge {
            position: relative;
        }
        .notification-badge .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
            padding: 2px 5px;
        }
        .notification-item {
            border-left: 4px solid;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .notification-item.unread {
            background-color: #f8f9fa;
            border-left-width: 6px;
        }
        .notification-item.info { border-color: #17a2b8; }
        .notification-item.success { border-color: #28a745; }
        .notification-item.warning { border-color: #ffc107; }
        .notification-item.danger { border-color: #dc3545; }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .notification-item:hover .notification-actions {
            opacity: 1;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Notification Bell (bisa di include di semua halaman) -->
    <div class="notification-badge d-inline-block ms-3">
        <button class="btn btn-outline-primary position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#notificationOffcanvas">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count > 0): ?>
            <span class="badge bg-danger rounded-pill"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </button>
    </div>
    
    <!-- Offcanvas Notification Panel -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notificationOffcanvas" style="width: 400px;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">
                <i class="fas fa-bell me-2"></i>Notifikasi
                <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> baru</span>
                <?php endif; ?>
            </h5>
            <div>
                <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read" class="btn btn-sm btn-outline-primary me-2">
                    Tandai semua dibaca
                </a>
                <?php endif; ?>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
        </div>
        <div class="offcanvas-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="list-group">
                    <?php while ($notif = mysqli_fetch_assoc($result)): 
                        $class = $notif['type'] . ($notif['is_read'] ? '' : ' unread');
                        $icon = $notif['type'] === 'success' ? 'check-circle' : 
                               ($notif['type'] === 'warning' ? 'exclamation-triangle' : 
                               ($notif['type'] === 'danger' ? 'times-circle' : 'info-circle'));
                    ?>
                    <div class="notification-item <?php echo $class; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-3">
                                <i class="fas fa-<?php echo $icon; ?> text-<?php echo $notif['type']; ?> me-2"></i>
                                <strong><?php echo $notif['title'] ?? 'Notifikasi'; ?></strong>
                            </div>
                            <div class="notification-actions">
                                <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?php echo $notif['id']; ?>" class="text-success me-2" title="Tandai dibaca">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?delete_notif=<?php echo $notif['id']; ?>" class="text-danger" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <p class="mb-1 mt-2"><?php echo $notif['message']; ?></p>
                        <small class="notification-time">
                            <i class="far fa-clock me-1"></i>
                            <?php echo time_elapsed_string($notif['created_at']); ?>
                        </small>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Tidak ada notifikasi</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="offcanvas-footer p-3 border-top">
            <div class="d-grid">
                <a href="notifications.php" class="btn btn-outline-primary">
                    <i class="fas fa-list me-2"></i>Lihat Semua Notifikasi
                </a>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification Template -->
    <div id="toastTemplate" class="toast align-items-center text-white bg-success border-0" role="alert" style="display: none;">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-bell me-2"></i>
                <span class="toast-message"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk menampilkan toast notifikasi
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container') || createToastContainer();
            const toastTemplate = document.getElementById('toastTemplate');
            const newToast = toastTemplate.cloneNode(true);
            
            newToast.id = 'toast-' + Date.now();
            newToast.style.display = '';
            newToast.classList.remove('bg-success');
            newToast.classList.add('bg-' + type);
            newToast.querySelector('.toast-message').textContent = message;
            
            toastContainer.appendChild(newToast);
            const bsToast = new bootstrap.Toast(newToast);
            bsToast.show();
            
            // Hapus toast setelah ditampilkan
            newToast.addEventListener('hidden.bs.toast', function () {
                this.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }
        
        // Polling untuk notifikasi baru (setiap 30 detik)
        function checkNewNotifications() {
            fetch('ajax/check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        // Update badge
                        const badge = document.querySelector('.notification-badge .badge');
                        if (badge) {
                            badge.textContent = data.count;
                            badge.classList.remove('d-none');
                        } else {
                            const bell = document.querySelector('.notification-badge .btn');
                            if (bell) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'badge bg-danger rounded-pill';
                                newBadge.textContent = data.count;
                                newBadge.style.position = 'absolute';
                                newBadge.style.top = '-5px';
                                newBadge.style.right = '-5px';
                                newBadge.style.fontSize = '0.7rem';
                                newBadge.style.padding = '2px 5px';
                                bell.appendChild(newBadge);
                            }
                        }
                        
                        // Tampilkan toast untuk notifikasi baru
                        if (data.latest) {
                            showToast(data.latest.message, data.latest.type);
                        }
                    }
                });
        }
        
        // Jalankan polling setiap 30 detik
        setInterval(checkNewNotifications, 30000);
        
        // Jalankan sekali saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            checkNewNotifications();
        });
    </script>
</body>
</html>

<?php
// Fungsi helper untuk format waktu
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
?>