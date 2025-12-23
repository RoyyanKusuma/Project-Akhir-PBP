<?php
// ajax/check_order_status.php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'unauthorized']);
    exit();
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

if ($order_id > 0) {
    $query = "SELECT status FROM transaksi WHERE id = $order_id AND user_id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode([
            'status' => $row['status'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} else {
    echo json_encode(['status' => 'invalid']);
}
?>