<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

$message = '';
$message_type = '';

if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}

$orders = [];

try {
    $stmt = $pdo->query("
        SELECT
            o.id,
            u.username AS customer_name,
            o.total_amount,
            o.status,      -- Menggunakan 'status' sesuai database Anda
            o.order_date,  -- Menggunakan 'order_date' sesuai database Anda
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
        ORDER BY
            o.order_date DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat daftar pesanan.";
    $message_type = "error";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Admin Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        .table-container {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 1000px;
            margin: 40px auto;
            overflow-x: auto; 
        }

        .table-container h2 {
            color: var(--admin-text-dark);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table-container th, .table-container td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: var(--admin-text-dark);
        }

        .table-container th {
            background-color: var(--admin-primary-rose-light);
            font-weight: 600;
            color: var(--admin-text-dark);
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .table-container tr:hover {
            background-color: var(--admin-hover-bg);
        }

        .order-actions .btn-action {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease;
            margin-right: 5px;
        }

        .order-actions .view-btn {
            background-color: #007bff; 
        }
        .order-actions .view-btn:hover {
            background-color: #0056b3;
        }

        .order-actions .status-btn {
            background-color: #ffc107; 
            color: #333;
        }
        .order-actions .status-btn:hover {
            background-color: #e0a800;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
            color: #fff;
        }
        .status-Pending { background-color: #ffc107; color: #333; }
        .status-Processing { background-color: #17a2b8; }
        .status-Shipped { background-color: #28a745; } 
        .status-Completed { background-color: #6f42c1; } 
        .status-Cancelled { background-color: #dc3545; }
        .status-Refunded { background-color: #6c757d; } 
        .status-WaitingforPayment { background-color: #ff9800; }
        .status-Waiting_for_Payment { background-color: #ff9800; }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
        <div class="table-container">
            <h2>Daftar Pesanan</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <p style="text-align: center; color: var(--admin-text-light);">Belum ada pesanan yang masuk.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Tanggal Pesan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td>Rp<?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($order['status'])); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></td>
                                <td class="order-actions">
                                    <a href="admin_order_detail.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="btn-action view-btn" title="Lihat Detail Pesanan"><i class="fas fa-eye"></i> Detail</a>
                                    <a href="admin_order_status.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="btn-action status-btn" title="Ubah Status Pesanan"><i class="fas fa-sync-alt"></i> Status</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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