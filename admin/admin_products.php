<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

$products = [];
$error_message = '';

try {
    $stmt = $pdo->query("
        SELECT
            p.id,
            p.name,
            p.description,
            p.price,
            p.stock,
            p.image_url,
            p.created_at,
            p.updated_at,
            c.name AS category_name
        FROM
            products p
        JOIN
            categories c ON p.category_id = c.id
        ORDER BY
            p.created_at DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching products for admin: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat mengambil data produk.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        .product-management {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
        }

        .product-management h2 {
            color: var(--admin-text-dark);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .add-product-btn {
            display: inline-block;
            background-color: var(--primary-pink);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
            transition: background-color 0.3s ease;
        }
        .add-product-btn:hover {
            background-color: var(--darker-pink);
        }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }

        .product-table th, .product-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        .product-table th {
            background-color: var(--admin-primary-rose); 
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
        }

        .product-table tbody tr:nth-child(even) {
            background-color: var(--admin-light-bg); 
        }

        .product-table tbody tr:hover {
            background-color: #f0e6e9; 
        }

        .product-table td img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

        .product-actions a {
            color: var(--admin-primary-rose);
            text-decoration: none;
            margin-right: 10px;
            transition: color 0.3s ease;
        }
        .product-actions a:hover {
            color: var(--admin-dark-rose);
            text-decoration: underline;
        }
        .product-actions .delete-btn {
            color: #e74c3c; 
        }
        .product-actions .delete-btn:hover {
            color: #c0392b;
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

        /* Responsive table */
        @media (max-width: 992px) {
            .product-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .product-table thead, .product-table tbody, .product-table th, .product-table td, .product-table tr {
                display: block; 
            }
            .product-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .product-table tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                border-radius: 8px;
                overflow: hidden;
            }
            .product-table td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%; 
                text-align: right; 
            }
            .product-table td:before {
                content: attr(data-label); 
                position: absolute;
                left: 10px;
                width: 45%; 
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: var(--admin-text-dark);
            }
            .product-table td:last-child {
                border-bottom: none;
            }
            .product-actions {
                text-align: center;
                padding-top: 10px;
            }
        }
    </style>
</head>
<body>

    <header class="admin-header">
        <h1>Soncake - Admin </h1>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_products.php" class="active">Produk</a></li>
                <li><a href="admin_orders.php">Pesanan</a></li>
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
        <div class="product-management">
            <h2>Kelola Produk</h2>

            <?php if ($error_message): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['message'])): ?>
                <div class="message <?php echo htmlspecialchars($_GET['type'] ?? 'success'); ?>">
                    <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <a href="admin_products_add.php" style="background-color: #E56B92; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus-circle"></i> Tambah Produk Baru
            </a>

            <?php if (count($products) > 0): ?>
                <div class="table-responsive"> <table class="product-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Gambar</th>
                                <th>Nama</th>
                                <th>Deskripsi</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Kategori</th>
                                <th>Dibuat</th>
                                <th>Diperbarui</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td data-label="Gambar">
                                        <img src="../img/<?php echo htmlspecialchars($product['image_url']); ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                                            onerror="this.onerror=null;this.src='../img/default.png';" />
                                    </td>
                                    <td data-label="Nama"><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td data-label="Deskripsi"><?php echo htmlspecialchars(substr($product['description'], 0, 70)) . (strlen($product['description']) > 70 ? '...' : ''); ?></td>
                                    <td data-label="Harga">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                    <td data-label="Stok"><?php echo htmlspecialchars($product['stock']); ?></td>
                                    <td data-label="Kategori"><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td data-label="Dibuat"><?php echo date('d M Y H:i', strtotime($product['created_at'])); ?></td>
                                    <td data-label="Diperbarui"><?php echo date('d M Y H:i', strtotime($product['updated_at'])); ?></td>
                                    <td data-label="Aksi" class="product-actions">
                                        <a href="admin_products_edit.php?id=<?php echo htmlspecialchars($product['id']); ?>" title="Edit Produk"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="admin_products_delete.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?')" title="Hapus Produk"><i class="fas fa-trash-alt"></i> Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Belum ada produk yang ditambahkan. <a href="admin_products_add.php">Tambahkan sekarang!</a></p>
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