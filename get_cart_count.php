<?php
// ajax/get_cart_count.php
session_start();

$cart = isset($_SESSION['cart_customer']) ? $_SESSION['cart_customer'] : [];
$count = count($cart);

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?>