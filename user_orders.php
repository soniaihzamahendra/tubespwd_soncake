<?php
session_start();
require_once 'config/database.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=not_logged_in");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$orders = [];

if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            total_amount, 
            status,       -- Menggunakan 'status' sesuai database Anda
            order_date    -- Menggunakan 'order_date' sesuai database Anda
        FROM 
            orders
        WHERE 
            user_id = :user_id
        ORDER BY 
            order_date DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching user orders: " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat riwayat pesanan Anda.";
    $message_type = "error";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan Saya - Sweet Delights</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css"> <style>
        .order-history-container {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 900px;
            margin: 40px auto;
            overflow-x: auto;
        }

        .order-history-container h2 {
            color: var(--primary-pink);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2.2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .order-history-container table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .order-history-container th, .order-history-container td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: #555;
        }

        .order-history-container th {
            background-color: var(--light-pink);
            font-weight: 600;
            color: var(--primary-pink);
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .order-history-container tr:hover {
            background-color: #f9f9f9;
        }

        .order-history-container .view-detail-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            background-color: var(--primary-pink);
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        .order-history-container .view-detail-btn:hover {
            background-color: var(--darker-pink);
        }
        .order-history-container .view-detail-btn i {
            margin-right: 5px;
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
        <div class="order-history-container">
            <h2>Riwayat Pesanan Saya</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <p style="text-align: center; color: #777;">Anda belum memiliki riwayat pesanan.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Tanggal Pesan</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></td>
                                <td>Rp<?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($order['status'])); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="user_order_detail.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="view-detail-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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