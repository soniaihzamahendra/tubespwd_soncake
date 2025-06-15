<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=not_logged_in");
    exit();
}

$user_id = $_SESSION['user_id'];
$order = null;
$order_items = [];
$message = '';
$message_type = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = $_GET['id'];

    try {
        $stmt_order = $pdo->prepare("
            SELECT
                o.id,
                o.user_id,
                o.total_amount,
                o.status,
                o.order_date,
                o.shipping_name,
                o.shipping_email,
                o.shipping_phone,
                o.shipping_address,
                o.notes,
                o.payment_method
            FROM
                orders o
            WHERE
                o.id = :order_id AND o.user_id = :user_id
        ");
        $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt_order->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_order->execute();
        $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $message = "Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.";
            $message_type = "error";
        } else {
            $stmt_items = $pdo->prepare("
                SELECT
                    oi.quantity,
                    oi.price_at_purchase,
                    p.name AS product_name,
                    p.image_url
                FROM
                    order_items oi
                JOIN
                    products p ON oi.product_id = p.id
                WHERE
                    oi.order_id = :order_id
            ");
            $stmt_items->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $stmt_items->execute();
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        error_log("Error fetching user order details: " . $e->getMessage());
        $message = "Terjadi kesalahan saat memuat detail pesanan Anda.";
        $message_type = "error";
    }
} else {
    $message = "ID Pesanan tidak valid atau tidak diberikan.";
    $message_type = "error";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo htmlspecialchars($order_id ?? ''); ?> - Sweet Delights</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css"> <style>
        .order-detail-container {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 900px;
            margin: 40px auto;
        }

        .order-detail-container h2 {
            color: var(--primary-pink);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2.2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .order-info, .shipping-info, .payment-info, .order-items-table {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            background-color: #fcfcfc;
        }

        .order-info p, .shipping-info p, .payment-info p {
            margin: 8px 0;
            color: #555;
            line-height: 1.6;
        }
        .order-info p strong, .shipping-info p strong, .payment-info p strong {
            color: #333;
            display: inline-block;
            width: 180px;
        }
        .order-info h3, .shipping-info h3, .payment-info h3 {
            color: var(--primary-pink);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.4em;
            border-bottom: 1px dashed #eee;
            padding-bottom: 10px;
        }

        .order-items-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-items-table th, .order-items-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .order-items-table th {
            background-color: var(--light-pink);
            font-weight: 600;
            color: var(--primary-pink);
            text-transform: uppercase;
            font-size: 0.9em;
        }
        .order-items-table tr:last-child td {
            border-bottom: none;
        }
        .product-thumb-detail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            vertical-align: middle;
            margin-right: 10px;
            border: 1px solid #ddd;
        }

        .order-summary {
            text-align: right;
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary-pink);
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid var(--light-pink);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 1.1em;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: var(--primary-pink);
            text-decoration: underline;
        }
        .back-link i {
            margin-right: 8px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: 600;
            text-transform: capitalize;
            color: #fff;
            margin-left: 10px;
            vertical-align: middle;
        }
        .status-Pending { background-color: #ffc107; color: #333; }
        .status-Processing { background-color: #17a2b8; }
        .status-Shipped { background-color: #28a745; }
        .status-Completed { background-color: #6f42c1; }
        .status-Cancelled { background-color: #dc3545; }
        .status-Refunded { background-color: #6c757d; }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container">
            <div class="logo">
                <a href="index.php">Sweet Delights</a>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li><a href="admin/admin_dashboard.php">Admin Panel</a></li>
                        <?php else: ?>
                            <li><a href="user_orders.php" class="active">Pesanan Saya</a></li>
                            <li><a href="profile.php">Profil</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="order-detail-container">
            <h2>Detail Pesanan #<?php echo htmlspecialchars($order['id'] ?? 'N/A'); ?></h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="order-info">
                    <h3>Informasi Pesanan</h3>
                    <p><strong>ID Pesanan:</strong> #<?php echo htmlspecialchars($order['id']); ?></p>
                    <p><strong>Status:</strong>
                        <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($order['status'])); ?>">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </p>
                    <p><strong>Tanggal Pesan:</strong> <?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></p>
                    <p><strong>Total Pembayaran:</strong> Rp<?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
                    <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                    <?php if (!empty($order['notes'])): ?>
                        <p><strong>Catatan Pembeli:</strong> <?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                    <?php endif; ?>
                </div>

                <div class="shipping-info">
                    <h3>Informasi Pengiriman</h3>
                    <p><strong>Nama Penerima:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                    <p><strong>Email Pengiriman:</strong> <?php echo htmlspecialchars($order['shipping_email']); ?></p>
                    <p><strong>Telepon Pengiriman:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                    <p><strong>Alamat Pengiriman:</strong> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>

                <div class="order-items-table">
                    <h3>Produk dalam Pesanan</h3>
                    <?php if (empty($order_items)): ?>
                        <p style="text-align: center;">Tidak ada produk dalam pesanan ini.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Harga Satuan</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <img src="img/<?php echo htmlspecialchars($item['image_url']); ?>"
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 onerror="this.onerror=null;this.src='img/default.png';"
                                                 class="product-thumb-detail">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </td>
                                        <td>Rp<?php echo number_format($item['price_at_purchase'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td>Rp<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #777;">Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.</p>
            <?php endif; ?>

            <a href="user_orders.php" class="back-link"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Riwayat Pesanan</a>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Sweet Delights. All rights reserved.</p>
        </div>
    </footer>

    <script src="public/js/script.js"></script>
</body>
</html>