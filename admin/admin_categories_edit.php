<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

$message = '';
$message_type = '';
$category = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT id, name, icon FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            $message = "Kategori tidak ditemukan.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        error_log("Error fetching category for edit: " . $e->getMessage());
        $message = "Terjadi kesalahan saat mengambil data kategori.";
        $message_type = "error";
    }
} else {
    $message = "ID kategori tidak valid atau tidak diberikan.";
    $message_type = "error";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $category) {
    $name = trim($_POST['name']);
    $icon = trim($_POST['icon']);
    
    if (empty($name)) {
        $message = "Nama kategori tidak boleh kosong.";
        $message_type = "error";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
            $stmt_check->execute([$name, $category['id']]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = "Kategori dengan nama tersebut sudah ada.";
                $message_type = "error";
            } else {
                $sql = "UPDATE categories SET name = ?, icon = ?, updated_at = NOW() WHERE id = ?";
                $stmt_update = $pdo->prepare($sql);
                $stmt_update->execute([$name, $icon, $category['id']]);

                $category['name'] = $name;
                $category['icon'] = $icon;

                header("Location: admin_categories.php?message=Kategori+berhasil+diperbarui&type=success");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Error updating category: " . $e->getMessage());
            $message = "Terjadi kesalahan saat memperbarui kategori: " . $e->getMessage();
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
    <title>Edit Kategori - Admin Soncake</title>
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
    margin-top: 20px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%; 
}

.category-form-container button[type="submit"]:hover {
    background-color: #C45A7A !important; 
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.category-form-container button[type="submit"] i {
    font-size: 1.2em;
}
        
        .category-form-container .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--admin-text-light);
            text-decoration: none;
            font-weight: 500;
        }
        .category-form-container .back-link:hover {
            color: var(--admin-text-dark);
            text-decoration: underline;
        }
        
        .message {
            padding: 12px;
            border-radius: 8櫃px;
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
        
        .current-icon-display {
            font-size: 2em; 
            color: var(--admin-primary-rose);
            text-align: center;
            margin-bottom: 15px;
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
            <h2>Edit Kategori</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($category): ?>
            <form action="" method="post">
                <label for="name">Nama Kategori:</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $category['name']); ?>">

                <label for="icon">Class Icon (Font Awesome):</label>
                <input type="text" id="icon" name="icon" placeholder="e.g., fas fa-birthday-cake" value="<?php echo htmlspecialchars($_POST['icon'] ?? $category['icon']); ?>">
                <small>Temukan ikon di <a href="https://fontawesome.com/v5/search?m=free" target="_blank">Font Awesome v5 Free</a> (contoh: fas fa-birthday-cake)</small>
                
                <?php if (!empty($category['icon'])): ?>
                    <label>Preview Icon:</label>
                    <div class="current-icon-display">
                        <i class="<?php echo htmlspecialchars($_POST['icon'] ?? $category['icon']); ?>"></i>
                    </div>
                <?php endif; ?>

                <button type="submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </form>
            <?php else: ?>
                <p style="text-align: center; color: var(--admin-text-light);">Kategori tidak ditemukan atau ID tidak valid.</p>
            <?php endif; ?>
            <a href="admin_categories.php" class="back-link"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Daftar Kategori</a>
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