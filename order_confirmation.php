<?php
session_start();
require_once 'config/database.php';

$order = null;
$order_items = [];
$display_message = '';

if (isset($_SESSION['message'])) {
    $display_message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];

    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "Anda harus login untuk melihat detail pesanan.";
        $_SESSION['message_type'] = "error";
        $_SESSION['redirect_after_login'] = 'order_confirmation.php?order_id=' . $order_id;
        header("Location: login.php");
        exit();
    }

    try {
        $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt_order->execute([$order_id, $_SESSION['user_id']]);
        $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $display_message = "Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.";
            $message_type = "error";
        } else {
            $stmt_items = $pdo->prepare("SELECT oi.*, p.name AS product_name, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $stmt_items->execute([$order_id]);
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            if (empty($display_message)) {
                $display_message = "Pesanan Anda berhasil dikonfirmasi! ID Pesanan: #" . htmlspecialchars($order['id']);
                $message_type = "success";
            }
        }

    } catch (PDOException $e) {
        error_log("Error fetching order confirmation: " . $e->getMessage());
        $display_message = "Terjadi kesalahan saat mengambil detail pesanan. Silakan coba lagi.";
        $message_type = "error";
    }
} else {
    if (empty($display_message)) {
        $display_message = "ID Pesanan tidak valid atau tidak ditemukan. Mohon coba lagi atau lihat riwayat pesanan Anda.";
        $message_type = "error";
    }
}

if (!isset($message_type)) {
    $message_type = 'info';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - Sweet Delights</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        :root {
            --primary-pink: #FF6B81;
            --secondary-brown: #8B4513;
            --cream-white: #FFF8E1;
            --light-pink-bg: #FFEBEF;
            --dark-grey-text: #333;
            --light-grey-text: #666;
            --success-green: #28a745;
            --warning-orange: #ffc107;
            --error-red: #dc3545;
            --info-blue: #17a2b8;
        }
        .confirmation-container {
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            padding: 40px;
            max-width: 800px;
            text-align: center;
        }

        .confirmation-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        .confirmation-icon.success { color: var(--success-green); }
        .confirmation-icon.error { color: var(--error-red); }
        .confirmation-icon.warning { color: var(--warning-orange); }
        .confirmation-icon.info { color: var(--info-blue); }


        .confirmation-title {
            color: var(--secondary-brown);
            font-size: 2.2em;
            margin-bottom: 15px;
        }

        .confirmation-message {
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .confirmation-message.success { color: var(--success-green); }
        .confirmation-message.error { color: var(--error-red); }
        .confirmation-message.warning { color: var(--warning-orange); }
        .confirmation-message.info { color: var(--info-blue); }

        .order-details-summary {
            text-align: left;
            margin-top: 30px;
            border-top: 1px solid rgba(0,0,0,0.1);
            padding-top: 20px;
        }

        .order-details-summary h4 {
            color: var(--secondary-brown);
            margin-bottom: 15px;
        }

        .order-meta p {
            margin-bottom: 8px;
            color: var(--dark-grey-text);
        }
        .order-meta strong {
            color: var(--primary-pink);
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .order-items-table th, .order-items-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            text-align: left;
        }
        .order-items-table th {
            background-color: var(--light-pink-bg);
            color: var(--secondary-brown);
        }
        .order-items-table .item-name {
            font-weight: 500;
        }
        .order-items-table .item-total {
            font-weight: 600;
            color: var(--primary-pink);
        }

        .order-total-footer {
            text-align: right;
            font-size: 1.4em;
            font-weight: 700;
            color: var(--secondary-brown);
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        .order-total-footer span {
            color: var(--primary-pink);
            margin-left: 10px;
        }

        .action-buttons {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .action-buttons .btn-primary, .action-buttons .btn-secondary {
            padding: 12px 25px;
            font-size: 1em;
        }
        .btn-secondary {
            background-color: var(--light-grey-bg);
            color: var(--dark-grey-text);
            border: 1px solid var(--light-grey-text);
        }
        .btn-secondary:hover {
            background-color: var(--light-grey-text);
            color: #fff;
        }
        .message-box {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }

        .message-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-box.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .message-box.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="user_dashboard.php">Sweet Delights</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-caret-down"></i></a>
                            <div class="dropdown-content">
                                <a href="history_pesanan.php">Pesanan Saya</a>
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
                <div class="confirmation-container">
                    <?php
                    if (!empty($display_message)) {
                        $icon_class = 'fas ';
                        switch ($message_type) {
                            case 'success':
                                $icon_class .= 'fa-check-circle';
                                break;
                            case 'error':
                                $icon_class .= 'fa-times-circle';
                                break;
                            case 'warning':
                                $icon_class .= 'fa-exclamation-triangle';
                                break;
                            case 'info':
                            default:
                                $icon_class .= 'fa-info-circle';
                                break;
                        }
                        echo '<i class="' . $icon_class . ' confirmation-icon ' . htmlspecialchars($message_type) . '"></i>';
                        echo '<h2 class="confirmation-title">' . ($message_type === 'success' ? 'Pesanan Berhasil!' : 'Pemberitahuan') . '</h2>';
                        echo '<p class="confirmation-message ' . htmlspecialchars($message_type) . '">' . htmlspecialchars($display_message) . '</p>';
                    }
                    ?>

                    <?php if ($order): ?>
                        <div class="order-details-summary">
                            <h4>Detail Pesanan Anda</h4>
                            <div class="order-meta">
                                <p><strong>ID Pesanan:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
                                <p><strong>Tanggal Pesanan:</strong> <?php echo date('d M Y, H:i', strtotime($order['order_date'])); ?></p>
                                <p><strong>Status:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
                                <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                                <p><strong>Dikirim ke:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?>, <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                <p><strong>Telepon:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                                <?php if (!empty($order['notes'])): ?>
                                    <p><strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($order_items)): ?>
                                <table class="order-items-table">
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Qty</th>
                                            <th>Harga Satuan</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <img src="img/<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                                        <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                <td>Rp <?php echo number_format($item['price_at_purchase'], 0, ',', '.'); ?></td>
                                                <td class="item-total">Rp <?php echo number_format($item['price_at_purchase'] * $item['quantity'], 0, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="order-total-footer">
                                    Total Pembayaran: <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--light-grey-text); margin-top: 20px;">Tidak ada item yang ditemukan untuk pesanan ini.</p>
                            <?php endif; ?>

                        </div>

                        <div class="action-buttons">
                            <a href="user_dashboard.php" class="btn-primary"><i class="fas fa-home"></i> Kembali ke Home</a>
                            <a href="history_pesanan.php" class="btn-secondary"><i class="fas fa-receipt"></i> Lihat Riwayat Pesanan</a>
                        </div>

                    <?php else: ?>
                        <div class="action-buttons">
                            <a href="katalog.php" class="btn-primary">Kembali ke Katalog</a>
                            <a href="keranjang.php" class="btn-secondary">Periksa Keranjang</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-section about">
                <h3>Tentang Sweet Delights</h3>
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
                <p><i class="fas fa-envelope"></i> info@sweetdelights.com</p>
            </div>
            <div class="footer-section social">
                <h3>Ikuti Kami</h3>
                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Sweet Delights. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>