<?php
// setup.php - Setup database otomatis
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'resto_delight';

$conn = mysqli_connect($host, $username, $password);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Buat database jika belum ada
$sql = "CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (mysqli_query($conn, $sql)) {
    echo "Database berhasil dibuat/ada.<br>";
} else {
    die("Gagal membuat database: " . mysqli_error($conn));
}

// Pilih database
mysqli_select_db($conn, $database);

// Tabel users
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'kasir', 'pelanggan') DEFAULT 'pelanggan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $sql);

// Tabel menu
$sql = "CREATE TABLE IF NOT EXISTS menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_menu VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    harga DECIMAL(10,2) NOT NULL,
    kategori ENUM('makanan', 'minuman', 'snack') NOT NULL,
    gambar VARCHAR(255),
    stok INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $sql);

// Tabel transaksi
$sql = "CREATE TABLE IF NOT EXISTS transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_transaksi VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    total_harga DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'pending',
    metode_pembayaran ENUM('tunai', 'kartu', 'qris') DEFAULT 'tunai',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";
mysqli_query($conn, $sql);

// Tabel detail_transaksi
$sql = "CREATE TABLE IF NOT EXISTS detail_transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT,
    menu_id INT,
    quantity INT NOT NULL,
    harga_satuan DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE
)";
mysqli_query($conn, $sql);

// Tabel notifications
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    title VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $sql);

// Insert data default
// Cek apakah data sudah ada
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$row = mysqli_fetch_assoc($check);

if ($row['count'] == 0) {
    // Insert admin user
    $hashed_password = password_hash('password', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role) VALUES 
            ('admin', 'admin@restodelight.com', '$hashed_password', 'admin'),
            ('kasir', 'kasir@restodelight.com', '$hashed_password', 'kasir')";
    mysqli_query($conn, $sql);
    echo "User default berhasil ditambahkan.<br>";
}

// Insert menu default
$check_menu = mysqli_query($conn, "SELECT COUNT(*) as count FROM menu");
$row_menu = mysqli_fetch_assoc($check_menu);

if ($row_menu['count'] == 0) {
    $sql = "INSERT INTO menu (nama_menu, deskripsi, harga, kategori, stok) VALUES
            ('Nasi Goreng Spesial', 'Nasi goreng dengan telur, ayam, dan sayuran', 35000, 'makanan', 20),
            ('Mie Goreng Jawa', 'Mie goreng dengan bumbu khas Jawa', 30000, 'makanan', 15),
            ('Ayam Bakar Madu', 'Ayam bakar dengan bumbu madu spesial', 45000, 'makanan', 10),
            ('Es Teh Manis', 'Es teh dengan gula pasir', 8000, 'minuman', 50),
            ('Jus Alpukat', 'Jus alpukat dengan susu kental manis', 20000, 'minuman', 25),
            ('Kentang Goreng', 'Kentang goreng renyah dengan saus', 25000, 'snack', 30)";
    mysqli_query($conn, $sql);
    echo "Menu default berhasil ditambahkan.<br>";
}

// Hapus trigger lama jika ada
mysqli_query($conn, "DROP TRIGGER IF EXISTS after_transaksi_insert");
mysqli_query($conn, "DROP TRIGGER IF EXISTS after_transaksi_update_status");

// Buat trigger sederhana
$sql = "CREATE TRIGGER after_transaksi_insert 
        AFTER INSERT ON transaksi 
        FOR EACH ROW 
        INSERT INTO notifications (user_id, type, title, message)
        VALUES (
            NEW.user_id, 
            'success', 
            'Transaksi Baru',
            CONCAT('Transaksi #', NEW.kode_transaksi, ' berhasil dibuat. Total: Rp ', 
                   FORMAT(NEW.total_harga, 0))
        )";
mysqli_query($conn, $sql);

$sql = "CREATE TRIGGER after_transaksi_update_status 
        AFTER UPDATE ON transaksi 
        FOR EACH ROW 
        INSERT INTO notifications (user_id, type, title, message)
        SELECT 
            NEW.user_id,
            'info',
            'Status Diubah',
            CONCAT('Status transaksi #', NEW.kode_transaksi, 
                   ' berubah dari ', OLD.status, ' menjadi ', NEW.status)
        WHERE OLD.status != NEW.status";
mysqli_query($conn, $sql);

echo "<h2>Setup database berhasil!</h2>";
echo "<p>Database telah diatur dengan benar.</p>";
echo "<p>Login dengan:</p>";
echo "<ul>";
echo "<li>Username: admin | Password: password (Role: Admin)</li>";
echo "<li>Username: kasir | Password: password (Role: Kasir)</li>";
echo "</ul>";
echo "<a href='login.php'>Klik di sini untuk login</a>";

mysqli_close($conn);
?>