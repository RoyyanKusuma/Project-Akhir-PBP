<?php
// ajax/check_notifications.php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$user_id = $_SESSION['user_id'];

// Hitung notifikasi belum dibaca
$query_count = "SELECT COUNT(*) as count FROM notifications 
                WHERE (user_id = $user_id OR user_id IS NULL) 
                AND is_read = FALSE";
$result_count = mysqli_query($conn, $query_count);
$count = mysqli_fetch_assoc($result_count)['count'];

// Ambil notifikasi terbaru
$query_latest = "SELECT * FROM notifications 
                WHERE (user_id = $user_id OR user_id IS NULL) 
                AND is_read = FALSE 
                ORDER BY created_at DESC 
                LIMIT 1";
$result_latest = mysqli_query($conn, $query_latest);
$latest = mysqli_num_rows($result_latest) > 0 ? mysqli_fetch_assoc($result_latest) : null;

// Jika ada notifikasi baru, tandai sebagai sedang dibaca
if ($latest) {
    $query_mark = "UPDATE notifications SET is_read = TRUE WHERE id = {$latest['id']}";
    mysqli_query($conn, $query_mark);
}

header('Content-Type: application/json');
echo json_encode([
    'count' => $count,
    'latest' => $latest
]);
?>