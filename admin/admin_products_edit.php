<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

$message = '';
$message_type = '';
$product = null;
$categories = [];

try {
    $stmt_cat = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories for edit: " . $e->getMessage());
    $message = "Terjadi kesalahan saat mengambil daftar kategori.";
    $message_type = "error";
}
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $message = "Produk tidak ditemukan.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        error_log("Error fetching product for edit: " . $e->getMessage());
        $message = "Terjadi kesalahan saat mengambil data produk.";
        $message_type = "error";
    }
} else {
    $message = "ID produk tidak valid atau tidak diberikan.";
    $message_type = "error";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $product) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    $rating = $_POST['rating'] ?? 0.0;

    $current_image_url = $product['image_url']; 

    if (empty($name) || empty($description) || empty($price) || empty($stock) || empty($category_id)) {
        $message = "Semua kolom wajib diisi (kecuali Gambar).";
        $message_type = "error";
    } elseif (!is_numeric($price) || $price < 0) {
        $message = "Harga harus angka positif.";
        $message_type = "error";
    } elseif (!is_numeric($stock) || $stock < 0 || !filter_var($stock, FILTER_VALIDATE_INT)) {
        $message = "Stok harus angka bulat positif.";
        $message_type = "error";
    } elseif (!is_numeric($rating) || $rating < 0 || $rating > 5) {
        $message = "Rating harus angka antara 0 dan 5.";
        $message_type = "error";
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['image']['name'];
            $file_tmp_name = $_FILES['image']['tmp_name'];
            $file_size = $_FILES['image']['size'];
            $file_type = $_FILES['image']['type'];

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_ext, $allowed_ext)) {
                $message = "Format gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.";
                $message_type = "error";
            } elseif ($file_size > 5 * 1024 * 1024) { 
                $message = "Ukuran gambar terlalu besar. Maksimal 5MB.";
                $message_type = "error";
            } else {
                $new_image_url = uniqid('product_', true) . '.' . $file_ext;
                $upload_dir = '../img/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (!move_uploaded_file($file_tmp_name, $upload_dir . $new_image_url)) {
                    $message = "Gagal mengunggah gambar baru.";
                    $message_type = "error";
                } else {
                    if ($current_image_url && $current_image_url !== 'default.png' && file_exists($upload_dir . $current_image_url)) {
                        unlink($upload_dir . $current_image_url);
                    }
                    $current_image_url = $new_image_url;
                }
            }
        }
        if (empty($message)) {
            try {
                $sql = "UPDATE products SET
                                name = ?,
                                description = ?,
                                price = ?,
                                stock = ?,
                                category_id = ?,
                                image_url = ?,
                                rating = ?,
                                updated_at = NOW()
                            WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $description, $price, $stock, $category_id, $current_image_url, $rating, $product_id]);

                header("Location: admin_products.php?message=Produk+berhasil+diperbarui&type=success");
                exit();

            } catch (PDOException $e) {
                error_log("Error updating product: " . $e->getMessage());
                $message = "Terjadi kesalahan saat memperbarui produk: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk - Admin Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        .product-form-container {
            background-color: var(--admin-card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 700px;
            margin: 40px auto;
        }

        .product-form-container h2 {
            color: var(--admin-text-dark);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .product-form-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .product-form-container label {
            font-weight: 600;
            color: var(--admin-text-dark);
            margin-bottom: 5px;
            display: block;
        }

        .product-form-container input[type="text"],
        .product-form-container input[type="number"],
        .product-form-container input[type="file"],
        .product-form-container textarea,
        .product-form-container select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .product-form-container input[type="text"]:focus,
        .product-form-container input[type="number"]:focus,
        .product-form-container input[type="file"]:focus,
        .product-form-container textarea:focus,
        .product-form-container select:focus {
            border-color: var(--admin-primary-rose);
            outline: none;
        }

        .product-form-container textarea {
            resize: vertical;
            min-height: 100px;
        }

        .product-form-container button[type="submit"] {
            background-color: var(--primary-pink);
            color: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
        }
        .product-form-container button[type="submit"]:hover {
            background-color: var(--darker-pink);
            transform: translateY(-2px);
        }

        .product-form-container .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--admin-text-light);
            text-decoration: none;
            font-weight: 500;
        }
        .product-form-container .back-link:hover {
            color: var(--admin-text-dark);
            text-decoration: underline;
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

        .current-image {
            text-align: center;
            margin-bottom: 15px;
        }
        .current-image img {
            max-width: 150px;
            height: auto;
            border-radius: 8px;
            border: 1px solid #eee;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
     
.product-form-container button[type="submit"] {
    background-color: #ff6b81;
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.1em;
    font-weight: 600;
    transition: background-color 0.3s ease, transform 0.2s ease;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.product-form-container button[type="submit"]:hover {
    background-color: #e8455c;
    transform: translateY(-2px);
}

.product-form-container button[type="submit"] i {
    font-size: 1em;
}

    </style>
</head>
<body>

    <header class="admin-header">
        <h1>Soncake - Admin</h1>
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
        <div class="product-form-container">
            <h2>Edit Produk</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($product): ?>
            <form action="" method="post" enctype="multipart/form-data">
                <label for="name">Nama Produk:</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>">

                <label for="description">Deskripsi:</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($_POST['description'] ?? $product['description']); ?></textarea>

                <label for="price">Harga (Rp):</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>">

                <label for="stock">Stok:</label>
                <input type="number" id="stock" name="stock" min="0" required value="<?php echo htmlspecialchars($_POST['stock'] ?? $product['stock']); ?>">

                <label for="category_id">Kategori:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>"
                                <?php
                                    if (isset($_POST['category_id'])) {
                                        echo ($_POST['category_id'] == $category['id']) ? 'selected' : '';
                                    } else {
                                        echo ($product['category_id'] == $category['id']) ? 'selected' : '';
                                    }
                                ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Gambar Saat Ini:</label>
                <div class="current-image">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="../img/<?php echo htmlspecialchars($product['image_url']); ?>"
                             alt="Current Image"
                             onerror="this.onerror=null;this.src='../img/default.png';">
                    <?php else: ?>
                        <p>Tidak ada gambar.</p>
                    <?php endif; ?>
                </div>

                <label for="image">Ganti Gambar Produk (Opsional, Max 5MB, JPG/PNG/GIF):</label>
                <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif">

                <label for="rating">Rating (0-5, opsional):</label>
                <input type="number" id="rating" name="rating" step="0.1" min="0" max="5" value="<?php echo htmlspecialchars($_POST['rating'] ?? $product['rating']); ?>">

                <button type="submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </form>
            <?php endif; ?>
            <a href="admin_products.php" class="back-link"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Daftar Produk</a>
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