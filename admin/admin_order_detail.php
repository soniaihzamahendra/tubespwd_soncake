<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

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
                u.username AS customer_username,
                u.email AS customer_email,
                u.phone_number AS customer_phone,
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
            JOIN
                users u ON o.user_id = u.id
            WHERE
                o.id = :order_id
        ");
        $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt_order->execute();
        $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $message = "Pesanan tidak ditemukan.";
            $message_type = "error";
        } else {
            $stmt_items = $pdo->prepare("
                SELECT
                    oi.quantity,
                    oi.price_at_purchase, -- Menggunakan 'price_at_purchase' sesuai database Anda
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
        error_log("Error fetching order details: " . $e->getMessage());
        $message = "Terjadi kesalahan saat memuat detail pesanan.";
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
    <title>Detail Pesanan #<?php echo htmlspecialchars($order_id ?? ''); ?> - Admin Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        .order-detail-container {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 900px;
            margin: 40px auto;
        }

        .order-detail-container h2 {
            color: var(--admin-text-dark);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .order-info, .customer-info, .shipping-info, .payment-info, .order-items-table {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            background-color: #fcfcfc;
        }

        .order-info p, .customer-info p, .shipping-info p, .payment-info p {
            margin: 8px 0;
            color: var(--admin-text-light);
            line-height: 1.6;
        }
        .order-info p strong, .customer-info p strong, .shipping-info p strong, .payment-info p strong {
            color: var(--admin-text-dark);
            display: inline-block;
            width: 150px;
        }
        .order-info h3, .customer-info h3, .shipping-info h3, .payment-info h3 {
            color: var(--admin-primary-rose);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.3em;
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
            background-color: var(--admin-primary-rose-light);
            font-weight: 600;
            color: var(--admin-text-dark);
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
        }

        .order-summary {
            text-align: right;
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary-pink);
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid var(--admin-primary-rose-light);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: var(--admin-text-light);
            text-decoration: none;
            font-weight: 500;
            font-size: 1.1em;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: var(--admin-text-dark);
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

    <header class="admin-header">
        <h1>Soncake - Admin </h1>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_products.php">Produk</a></li>
                <li><a href="admin_orders.php" class="active">Pesanan</a></li>
                <li><a href="admin_categories.php">Kategori</a></li>
                <li class="admin-dropdown">
                    <a href="#" class="dropbtn"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-caret-down"></i></a>
                    <div class="admin-dropdown-content">
                        <a href="admin_profile.php">Profil Admin</a>
                        <a href="../logout.php">Logout</a>
                    </div>
                </li>
            </ul>
        </nav>
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

                <div class="customer-info">
                    <h3>Informasi Pelanggan</h3>
                    <p><strong>Nama Pelanggan:</strong> <?php echo htmlspecialchars($order['customer_username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                    <p><strong>Telepon:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
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
                                            <img src="../img/<?php echo htmlspecialchars($item['image_url']); ?>"
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 onerror="this.onerror=null;this.src='../img/default.png';"
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
                <p style="text-align: center; color: var(--admin-text-light);">Pesanan tidak ditemukan atau ID tidak valid.</p>
            <?php endif; ?>

            <a href="admin_orders.php" class="back-link"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Daftar Pesanan</a>
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