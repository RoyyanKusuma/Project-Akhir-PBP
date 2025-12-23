<?php
// cancel_order.php - Handle pembatalan pesanan
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $alasan = clean_input($_POST['alasan']);
    $user_id = $_SESSION['user_id'];
    
    // Cek apakah pesanan milik user dan masih pending
    $query = "SELECT * FROM transaksi WHERE id = $order_id AND user_id = $user_id AND status = 'pending'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) === 1) {
        // Update status ke dibatalkan
        $update_query = "UPDATE transaksi SET status = 'dibatalkan' WHERE id = $order_id";
        
        if (mysqli_query($conn, $update_query)) {
            // Simpan notifikasi
            $query_transaksi = "SELECT kode_transaksi FROM transaksi WHERE id = $order_id";
            $result_transaksi = mysqli_query($conn, $query_transaksi);
            $transaksi = mysqli_fetch_assoc($result_transaksi);
            
            $notif_message = "Pesanan #" . $transaksi['kode_transaksi'] . " telah dibatalkan. Alasan: " . $alasan;
            $query_notif = "INSERT INTO notifications (user_id, type, title, message) 
                           VALUES ($user_id, 'danger', 'Pesanan Dibatalkan', '$notif_message')";
            mysqli_query($conn, $query_notif);
            
            // Notifikasi untuk admin
            $query_admin_notif = "INSERT INTO notifications (user_id, type, title, message)
                                SELECT id, 'warning', 'Pesanan Dibatalkan',
                                CONCAT('Pesanan #', '{$transaksi['kode_transaksi']}', ' telah dibatalkan oleh customer.')
                                FROM users WHERE role IN ('admin', 'kasir')";
            mysqli_query($conn, $query_admin_notif);
            
            $_SESSION['message'] = 'Pesanan berhasil dibatalkan!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Gagal membatalkan pesanan: ' . mysqli_error($conn);
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = 'Pesanan tidak ditemukan atau tidak dapat dibatalkan!';
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: riwayat.php');
    exit();
}
?>