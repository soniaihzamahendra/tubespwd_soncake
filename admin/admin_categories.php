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

$categories = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['name']);
    $category_icon = trim($_POST['icon']); 

    if (empty($category_name)) {
        $message = "Nama kategori tidak boleh kosong.";
        $message_type = "error";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $stmt_check->execute([$category_name]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = "Kategori dengan nama tersebut sudah ada.";
                $message_type = "error";
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
                $stmt_insert->execute([$category_name, $category_icon]);
                $message = "Kategori '{$category_name}' berhasil ditambahkan.";
                $message_type = "success";
                $_POST['name'] = ''; 
                $_POST['icon'] = '';
            }
        } catch (PDOException $e) {
            error_log("Error adding category: " . $e->getMessage());
            $message = "Terjadi kesalahan saat menambahkan kategori: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

try {
    $stmt = $pdo->query("SELECT id, name, icon FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat daftar kategori.";
    if ($message_type !== "success") {
        $message_type = "error";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Admin Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        .category-form-container {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 600px;
            margin: 40px auto;
            margin-bottom: 20px;
        }

        .category-form-container h2 {
            color: var(--admin-text-dark);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .category-form-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .category-form-container label {
            font-weight: 600;
            color: var(--admin-text-dark);
            margin-bottom: 5px;
            display: block;
        }

        .category-form-container input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .category-form-container input[type="text"]:focus {
            border-color: var(--admin-primary-rose);
            outline: none;
        }

        .category-form-container button[type="submit"] {
            background-color: #E56B92 !important; 
            color: #FFFFFF !important;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .category-form-container button[type="submit"]:hover {
            background-color: #C45A7A !important; 
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .category-form-container button[type="submit"] i {
            font-size: 1.2em;
        }
        .table-container {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 900px;
            margin: 20px auto;
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

        .category-icon-display {
            font-size: 1.2em;
            color: var(--admin-text-light);
            margin-right: 5px;
        }

        .category-actions .btn-action {
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

        .category-actions .edit-btn {
            background-color: #5cb85c; 
        }
        .category-actions .edit-btn:hover {
            background-color: #4cae4c;
        }

        .category-actions .delete-btn {
            background-color: #d9534f; 
        }
        .category-actions .delete-btn:hover {
            background-color: #c9302c;
        }

        .category-actions .btn-action i {
            margin-right: 5px;
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
    </style>
</head>
<body>

    <header class="admin-header">
        <h1>Soncake - Admin</h1>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_products.php">Produk</a></li>
                <li><a href="admin_orders.php">Pesanan</a></li>
                <li><a href="admin_categories.php" class="active">Kategori</a></li>
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
        <div class="category-form-container">
            <h2>Tambah Kategori Baru</h2>

            <?php if ($message && ($message_type == 'error' || ($message_type == 'success' && isset($_POST['add_category'])))): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="" method="post">
                <input type="hidden" name="add_category" value="1">
                <label for="name">Nama Kategori:</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">

                <label for="icon">Class Icon (Font Awesome):</label>
                <input type="text" id="icon" name="icon" placeholder="e.g., fas fa-birthday-cake" value="<?php echo htmlspecialchars($_POST['icon'] ?? ''); ?>">
                <small>Temukan ikon di <a href="https://fontawesome.com/v5/search?m=free" target="_blank">Font Awesome v5 Free</a> (contoh: fas fa-birthday-cake)</small>

                <button type="submit"><i class="fas fa-plus-circle"></i> Tambah Kategori</button>
            </form>
        </div>

        <div class="table-container">
            <h2>Daftar Kategori</h2>

            <?php if ($message && $message_type == 'success' && !isset($_POST['add_category'])):?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($categories)): ?>
                <p style="text-align: center; color: var(--admin-text-light);">Belum ada kategori yang ditambahkan.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Kategori</th>
                            <th>Icon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['id']); ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <?php if (!empty($category['icon'])): ?>
                                        <i class="<?php echo htmlspecialchars($category['icon']); ?> category-icon-display"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($category['icon']); ?>
                                </td>
                                <td class="category-actions">
                                    <a href="admin_categories_edit.php?id=<?php echo htmlspecialchars($category['id']); ?>" class="btn-action edit-btn" title="Edit Kategori"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="admin_categories_delete.php?id=<?php echo htmlspecialchars($category['id']); ?>" class="btn-action delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini? SEMUA PRODUK DALAM KATEGORI INI AKAN KEHILANGAN KATEGORINYA (disarankan ubah kategori produk terlebih dahulu).')" title="Hapus Kategori"><i class="fas fa-trash-alt"></i> Hapus</a>
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