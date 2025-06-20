<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

$message = '';
$message_type = '';
$order_id = null;
$current_status = '';
$possible_statuses = ['Pending', 'Processing', 'Shipped', 'Completed', 'Cancelled', 'Refunded'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id");
        $stmt->bindParam(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $message = "Pesanan tidak ditemukan.";
            $message_type = "error";
            $order_id = null; 
        } else {
            $current_status = $order['status'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching order status: " . $e->getMessage());
        $message = "Terjadi kesalahan saat mengambil status pesanan.";
        $message_type = "error";
        $order_id = null;
    }
} else {
    $message = "ID Pesanan tidak valid atau tidak diberikan.";
    $message_type = "error";
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && $order_id !== null) {
    $new_status = trim($_POST['status']);

    if (!in_array($new_status, $possible_statuses)) {
        $message = "Status yang dipilih tidak valid.";
        $message_type = "error";
    } else {
        try {
            $sql = "UPDATE orders SET status = :new_status WHERE id = :id";
            $stmt_update = $pdo->prepare($sql);
            $stmt_update->bindParam(':new_status', $new_status);
            $stmt_update->bindParam(':id', $order_id, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                header("Location: admin_orders.php?message=" . urlencode("Status pesanan #{$order_id} berhasil diubah menjadi '{$new_status}'.") . "&type=success");
                exit();
            } else {
                $message = "Gagal memperbarui status pesanan.";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            error_log("Error updating order status: " . $e->getMessage());
            $message = "Terjadi kesalahan saat memperbarui status pesanan: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Status Pesanan #<?php echo htmlspecialchars($order_id ?? ''); ?> - Admin Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        .status-form-container {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 500px;
            margin: 40px auto;
            text-align: center;
        }

        .status-form-container h2 {
            color: var(--admin-text-dark);
            margin-bottom: 25px;
            font-size: 2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .status-form-container p {
            font-size: 1.1em;
            color: var(--admin-text-light);
            margin-bottom: 20px;
        }
        .status-form-container p strong {
            color: var(--admin-text-dark);
        }

        .status-form-container label {
            font-weight: 600;
            color: var(--admin-text-dark);
            margin-bottom: 10px;
            display: block;
            text-align: left;
        }

        .status-form-container select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20256%20512%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M208.3%20192h-160c-11.4%200-17%2013.7-9.7%2022.4l80%2080c4.7%204.7%2012.3%204.7%2017%200l80-80c7.3-8.7%201.7-22.4-9.7-22.4z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            margin-bottom: 20px; 
        }

        .status-form-container select:focus {
            border-color: var(--admin-primary-rose);
            outline: none;
        }
        .status-form-container button[type="submit"] {
            background-color: #E56B92; 
            color: #FFFFFF !important; 
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            display: block;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .status-form-container button[type="submit"]:hover {
            background-color: #C45A7A; 
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .status-form-container button[type="submit"] i {
            margin-right: 8px;
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
        <div class="status-form-container">
            <h2>Ubah Status Pesanan</h2>

            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($order_id !== null): ?>
                <p>Pesanan ID: <strong>#<?php echo htmlspecialchars($order_id); ?></strong></p>
                <p>Status Saat Ini:
                    <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($current_status)); ?>">
                        <?php echo htmlspecialchars($current_status); ?>
                    </span>
                </p>

                <form action="" method="post">
                    <label for="status">Pilih Status Baru:</label>
                    <select id="status" name="status" required>
                        <?php foreach ($possible_statuses as $status_option): ?>
                            <option value="<?php echo htmlspecialchars($status_option); ?>"
                                <?php echo ($status_option == $current_status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i class="fas fa-save"></i> Perbarui Status</button>
                </form>
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