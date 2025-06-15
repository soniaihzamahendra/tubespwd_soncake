<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied"); 
    exit();
}

$total_products = 0;
$total_orders = 0;
$pending_orders = 0;

try {
    $stmt_products = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt_products->fetchColumn();

    $stmt_orders = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt_orders->fetchColumn();

    $stmt_pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'");
    $pending_orders = $stmt_pending->fetchColumn();

} catch (PDOException $e) {
    error_log("Error fetching admin dashboard stats: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Soncake - Admin</h1>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="admin_products.php">Produk</a></li>
                <li><a href="admin_orders.php">Pesanan</a></li>
                <li><a href="admin_categories.php">Kategori</a></li>
                <li class="admin-dropdown">
                    <a href="#" class="dropbtn"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-caret-down"></i></a>
                    <div class="admin-dropdown-content">
                        <a href="admin_profile.php">Profil Admin</a>
                        <a href="../logout.php">Logout</a> </div>
                </li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>

        <div class="dashboard-cards">
            <div class="card">
                <i class="fas fa-box card-icon"></i>
                <h3><?php echo number_format($total_products); ?></h3>
                <p>Total Produk</p>
            </div>
            <div class="card">
                <i class="fas fa-receipt card-icon"></i>
                <h3><?php echo number_format($total_orders); ?></h3>
                <p>Total Pesanan</p>
            </div>
            <div class="card">
                <i class="fas fa-hourglass-half card-icon"></i>
                <h3><?php echo number_format($pending_orders); ?></h3>
                <p>Pesanan Pending</p>
            </div>
            </div>

        <div class="admin-sections">
            <h3 class="section-header">Aksi Cepat</h3>
            <div class="section-links">
                <a href="admin_products.php"><i class="fas fa-cube"></i> Kelola Produk</a>
                <a href="admin_products_add.php"><i class="fas fa-plus-square"></i> Tambah Produk Baru</a>
                <a href="admin_orders.php"><i class="fas fa-shopping-basket"></i> Kelola Pesanan</a>
                <a href="admin_categories.php"><i class="fas fa-sitemap"></i> Kelola Kategori</a>
                </div>
        </div>
    </main>

    <footer class="admin-footer">
        <p>&copy; <?php echo date("Y"); ?> Soncake Admin. All rights reserved.</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const adminDropdown = document.querySelector('.admin-dropdown');
        if (adminDropdown) {
            const dropbtn = adminDropdown.querySelector('.dropbtn');
            const dropdownContent = adminDropdown.querySelector('.admin-dropdown-content');

            dropbtn.addEventListener('click', function(event) {
                event.preventDefault();
                dropdownContent.classList.toggle('show');
            });

            document.addEventListener('click', function(event) {
                if (!adminDropdown.contains(event.target)) {
                    dropdownContent.classList.remove('show');
                }
            });
        }
    });
    </script>
</body>
</html>