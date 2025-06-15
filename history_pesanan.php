<?php
session_start();
require_once 'config/database.php'; 

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'history_pesanan.php'; 
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$orders = [];
$message = '';

try {
    $stmt = $pdo->prepare("SELECT id, total_amount, order_date, status, payment_method FROM orders WHERE user_id = ? ORDER BY order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        $message = "Anda belum memiliki riwayat pesanan.";
    }

} catch (PDOException $e) {
    error_log("Error fetching order history: " . $e->getMessage());
    $message = "Terjadi kesalahan saat mengambil riwayat pesanan Anda. Silakan coba lagi.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .history-container {
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            padding: 30px;
            max-width: 900px;
        }

        .history-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary-brown);
        }

        .order-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .order-list-table th, .order-list-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .order-list-table th {
            background-color: var(--light-pink-bg);
            color: var(--secondary-brown);
            font-weight: 600;
        }

        .order-list-table tbody tr:hover {
            background-color: #fcf6f6;
        }

        .order-id {
            font-weight: 600;
            color: var(--primary-pink);
        }

        .order-status {
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            color: #fff;
            font-size: 0.9em;
        }
        .status-Pending { background-color: #ffc107; }
        .status-Processing { background-color: #17a2b8; } 
        .status-Completed { background-color: #28a745; } 
        .status-Cancelled { background-color: #dc3545; } 

        .view-details-btn {
            background-color: var(--primary-pink);
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }
        .view-details-btn:hover {
            background-color: var(--darker-pink);
        }

        .no-orders-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: var(--light-grey-text);
        }
        @media (max-width: 768px) {
            .history-container {
                margin: 20px auto;
                padding: 15px;
            }
            .order-list-table {
                font-size: 0.85em;
            }
            .order-list-table th, .order-list-table td {
                padding: 10px;
            }
            .order-id, .order-status, .view-details-btn {
                font-size: 0.8em;
            }
            .view-details-btn {
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="user_dashboard.php">Soncake</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn active"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-caret-down"></i></a>
                            <div class="dropdown-content">
                                <a href="history_pesanan.php" class="active">Pesanan Saya</a>
                                <a href="user_profile.php">Profil</a>
                                <a href="logout.php">Logout</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="section-padding">
            <div class="container">
                <h2 class="history-header">Riwayat Pesanan Anda</h2>

                <div class="history-container">
                    <?php if (!empty($message)): ?>
                        <p class="no-orders-message"><?php echo htmlspecialchars($message); ?></p>
                        <?php if (empty($orders)):  ?>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="katalog.php" class="btn-primary">Mulai Belanja Sekarang!</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <table class="order-list-table">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Tanggal</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Metode Pembayaran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><span class="order-id">#<?php echo htmlspecialchars($order['id']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                    <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                    <td><span class="order-status status-<?php echo str_replace(' ', '', htmlspecialchars($order['status'])); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                    <td>
                                        <a href="order_confirmation.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="view-details-btn">
                                            <i class="fas fa-info-circle"></i> Detail Pesanan
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-section about">
                <h3>Tentang Soncake</h3>
                <p>Kami menyajikan kue-kue premium dengan bahan terbaik untuk setiap momen spesial Anda.</p>
            </div>
            <div class="footer-section links">
                <h3>Tautan Cepat</h3>
                <ul>
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <li><a href="#">Kontak Kami</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                </ul>
            </div>
            <div class="footer-section contact">
                <h3>Hubungi Kami</h3>
                <p><i class="fas fa-map-marker-alt"></i> Jl. Raya Kue No. 123, Kota Rasa</p>
                <p><i class="fas fa-phone"></i> (021) 123-4567</p>
                <p><i class="fas fa-envelope"></i> info@soncake.com</p>
            </div>
            <div class="footer-section social">
                <h3>Ikuti Kami</h3>
                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Soncake. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>