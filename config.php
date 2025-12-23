<?php
// config.php
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'resto_delight';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Fungsi untuk mencegah SQL injection
function clean_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}
?>