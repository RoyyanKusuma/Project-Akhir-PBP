<?php
// menu.php - Fitur utama: Kelola Menu (CRUD)
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hanya admin dan kasir yang bisa akses
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'kasir') {
    header('Location: index.php');
    exit();
}

$message = '';
$message_type = '';

// Handle tambah menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_menu'])) {
    $nama_menu = clean_input($_POST['nama_menu']);
    $deskripsi = clean_input($_POST['deskripsi']);
    $harga = clean_input($_POST['harga']);
    $kategori = clean_input($_POST['kategori']);
    $stok = clean_input($_POST['stok']);
    
    $query = "INSERT INTO menu (nama_menu, deskripsi, harga, kategori, stok) 
              VALUES ('$nama_menu', '$deskripsi', '$harga', '$kategori', '$stok')";
    
    if (mysqli_query($conn, $query)) {
        $message = 'Menu berhasil ditambahkan!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Handle update menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_menu'])) {
    $id = clean_input($_POST['menu_id']);
    $nama_menu = clean_input($_POST['nama_menu']);
    $deskripsi = clean_input($_POST['deskripsi']);
    $harga = clean_input($_POST['harga']);
    $kategori = clean_input($_POST['kategori']);
    $stok = clean_input($_POST['stok']);
    
    $query = "UPDATE menu SET 
              nama_menu = '$nama_menu',
              deskripsi = '$deskripsi',
              harga = '$harga',
              kategori = '$kategori',
              stok = '$stok'
              WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        $message = 'Menu berhasil diperbarui!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Handle delete menu
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "DELETE FROM menu WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        $message = 'Menu berhasil dihapus!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Ambil data menu
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$query = "SELECT * FROM menu 
          WHERE nama_menu LIKE '%$search%' OR deskripsi LIKE '%$search%'
          ORDER BY kategori, nama_menu";
$result = mysqli_query($conn, $query);

// Ambil data untuk edit modal
$edit_menu = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $edit_result = mysqli_query($conn, "SELECT * FROM menu WHERE id = $id");
    $edit_menu = mysqli_fetch_assoc($edit_result);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Resto Delight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            width: 250px;
            padding-top: 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .menu-card {
            transition: transform 0.3s;
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .menu-card:hover {
            transform: translateY(-5px);
        }
        .menu-img {
            height: 200px;
            object-fit: cover;
        }
        .badge-makanan {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .badge-minuman {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }
        .badge-snack {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Include sidebar dari file terpisah jika ada -->
    <?php include 'sidebar.php' ?? ''; ?>
    
    <div class="sidebar">
        <div class="text-center mb-4">
            <h3 class="text-white">Resto Delight</h3>
            <p class="text-white-50 small">Kelola Menu</p>
        </div>
        
        <div class="text-center mb-4">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                <i class="fas fa-user-circle fa-3x text-primary"></i>
            </div>
            <h5 class="text-white mt-2"><?php echo $_SESSION['username']; ?></h5>
            <span class="badge bg-light text-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
        </div>
        
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            <a href="menu.php" class="nav-link active">
                <i class="fas fa-utensils me-2"></i>Kelola Menu
            </a>
            <a href="transaksi.php" class="nav-link">
                <i class="fas fa-shopping-cart me-2"></i>Transaksi
            </a>
            <a href="riwayat.php" class="nav-link">
                <i class="fas fa-history me-2"></i>Riwayat
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="laporan.php" class="nav-link">
                <i class="fas fa-chart-bar me-2"></i>Laporan
            </a>
            <?php endif; ?>
            <div class="mt-4">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-utensils me-2"></i>Kelola Menu</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahMenuModal">
                <i class="fas fa-plus me-2"></i>Tambah Menu Baru
            </button>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Form Pencarian -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Cari menu berdasarkan nama atau deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Cari</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Daftar Menu -->
        <div class="row">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($menu = mysqli_fetch_assoc($result)): ?>
                <div class="col-md-4 mb-4">
                    <div class="card menu-card h-100">
                        <?php if ($menu['gambar']): ?>
                        <img src="<?php echo $menu['gambar']; ?>" class="card-img-top menu-img" alt="<?php echo $menu['nama_menu']; ?>">
                        <?php else: ?>
                        <div class="card-img-top menu-img d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-utensils fa-3x text-muted"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title"><?php echo $menu['nama_menu']; ?></h5>
                                <span class="badge 
                                    <?php echo $menu['kategori'] === 'makanan' ? 'badge-makanan' : 
                                           ($menu['kategori'] === 'minuman' ? 'badge-minuman' : 'badge-snack'); ?>">
                                    <?php echo ucfirst($menu['kategori']); ?>
                                </span>
                            </div>
                            <p class="card-text text-muted"><?php echo $menu['deskripsi']; ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="text-primary mb-0">Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?></h4>
                                <span class="badge bg-<?php echo $menu['stok'] > 0 ? 'success' : 'danger'; ?>">
                                    Stok: <?php echo $menu['stok']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-white border-0">
                            <div class="d-flex justify-content-between">
                                <a href="?edit=<?php echo $menu['id']; ?>" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editMenuModal">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <a href="?delete=<?php echo $menu['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus menu ini?')">
                                    <i class="fas fa-trash me-1"></i>Hapus
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Tidak ada menu ditemukan. Silakan tambah menu baru.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Tambah Menu -->
    <div class="modal fade" id="tambahMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Menu Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nama_menu" class="form-label">Nama Menu</label>
                            <input type="text" class="form-control" id="nama_menu" name="nama_menu" required>
                        </div>
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="harga" class="form-label">Harga (Rp)</label>
                                <input type="number" class="form-control" id="harga" name="harga" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stok" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="stok" name="stok" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori</label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="makanan">Makanan</option>
                                <option value="minuman">Minuman</option>
                                <option value="snack">Snack</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_menu" class="btn btn-primary">Simpan Menu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Menu -->
    <?php if ($edit_menu): ?>
    <div class="modal fade" id="editMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="menu_id" value="<?php echo $edit_menu['id']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_nama_menu" class="form-label">Nama Menu</label>
                            <input type="text" class="form-control" id="edit_nama_menu" name="nama_menu" value="<?php echo $edit_menu['nama_menu']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="edit_deskripsi" name="deskripsi" rows="3" required><?php echo $edit_menu['deskripsi']; ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_harga" class="form-label">Harga (Rp)</label>
                                <input type="number" class="form-control" id="edit_harga" name="harga" value="<?php echo $edit_menu['harga']; ?>" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_stok" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="edit_stok" name="stok" value="<?php echo $edit_menu['stok']; ?>" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_kategori" class="form-label">Kategori</label>
                            <select class="form-select" id="edit_kategori" name="kategori" required>
                                <option value="makanan" <?php echo $edit_menu['kategori'] === 'makanan' ? 'selected' : ''; ?>>Makanan</option>
                                <option value="minuman" <?php echo $edit_menu['kategori'] === 'minuman' ? 'selected' : ''; ?>>Minuman</option>
                                <option value="snack" <?php echo $edit_menu['kategori'] === 'snack' ? 'selected' : ''; ?>>Snack</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_menu" class="btn btn-warning">Update Menu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var editModal = new bootstrap.Modal(document.getElementById('editMenuModal'));
            editModal.show();
        });
    </script>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>