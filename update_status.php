<?php
// update_status.php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'kasir') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaksi_id']) && isset($_POST['status'])) {
    $transaksi_id = intval($_POST['transaksi_id']);
    $status = clean_input($_POST['status']);
    
    $query = "UPDATE transaksi SET status = '$status' WHERE id = $transaksi_id";
    
    if (mysqli_query($conn, $query)) {
        // Tambah notifikasi
        $user_id = $_SESSION['user_id'];
        $query_transaksi = "SELECT kode_transaksi FROM transaksi WHERE id = $transaksi_id";
        $result = mysqli_query($conn, $query_transaksi);
        $transaksi = mysqli_fetch_assoc($result);
        
        $messages = [
            'pending' => 'Transaksi #' . $transaksi['kode_transaksi'] . ' ditandai sebagai pending',
            'diproses' => 'Transaksi #' . $transaksi['kode_transaksi'] . ' sedang diproses',
            'selesai' => 'Transaksi #' . $transaksi['kode_transaksi'] . ' telah selesai',
            'dibatalkan' => 'Transaksi #' . $transaksi['kode_transaksi'] . ' dibatalkan'
        ];
        
        $message = $messages[$status] ?? 'Status transaksi diubah';
        $type = $status === 'selesai' ? 'success' : 
               ($status === 'dibatalkan' ? 'danger' : 'info');
        
        $query_notif = "INSERT INTO notifications (user_id, type, message) 
                       VALUES ($user_id, '$type', '$message')";
        mysqli_query($conn, $query_notif);
        
        $_SESSION['message'] = 'Status transaksi berhasil diubah!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error: ' . mysqli_error($conn);
        $_SESSION['message_type'] = 'danger';
    }
}

header('Location: riwayat.php');
exit();
?>