<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=access_denied'); 
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username']; 
$admin_email = ''; 

$error_message = '';
$success_message = '';

try {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin_data) {
        $admin_username = htmlspecialchars($admin_data['username']);
        $admin_email = htmlspecialchars($admin_data['email']);
    } else {
        $error_message = "Data admin tidak ditemukan.";
        session_unset();
        session_destroy();
        header('Location: ../login.php?error=data_mismatch');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error fetching admin profile: " . $e->getMessage());
    $error_message = "Terjadi kesalahan database saat memuat profil.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? ''; 
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_username) || empty($new_email)) {
        $error_message = "Username dan Email tidak boleh kosong.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt_check->execute([$new_username, $new_email, $admin_id]);
            if ($stmt_check->fetch()) {
                $error_message = "Username atau email sudah digunakan oleh pengguna lain.";
            } else {
                $update_query = "UPDATE users SET username = ?, email = ?";
                $params = [$new_username, $new_email];

                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        $error_message = "Konfirmasi password tidak cocok.";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_query .= ", password = ?";
                        $params[] = $hashed_password;
                    }
                }

                if (empty($error_message)) { 
                    $update_query .= " WHERE id = ? AND role = 'admin'";
                    $params[] = $admin_id;

                    $stmt_update = $pdo->prepare($update_query);
                    if ($stmt_update->execute($params)) {
                        $success_message = "Profil berhasil diperbarui!";
                        $_SESSION['username'] = $new_username;
                        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? AND role = 'admin'");
                        $stmt->execute([$admin_id]);
                        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($admin_data) {
                            $admin_username = htmlspecialchars($admin_data['username']);
                            $admin_email = htmlspecialchars($admin_data['email']);
                        }
                    } else {
                        $error_message = "Gagal memperbarui profil.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Database error updating admin profile: " . $e->getMessage());
            $error_message = "Terjadi kesalahan database saat memperbarui profil.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profil - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../public/css/admin.css"> <style>
        .profile-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 40px auto;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-container h2 {
            text-align: center;
            color: #5e3a45;
            margin-bottom: 30px;
            font-size: 2.2em;
        }

        .profile-info p {
            font-size: 1.1em;
            margin-bottom: 15px;
            color: #333;
        }

        .profile-info strong {
            color: #8c6b75; 
            display: inline-block;
            width: 100px; 
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #5e3a45;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box; 
        }

        .btn-update-profile {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #b36e7c; 
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-update-profile:hover {
            background-color: #a05c6d;
        }

        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
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

        @media (max-width: 768px) {
            .profile-container {
                margin: 20px;
                padding: 20px;
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
                <li><a href="admin_products.php">Produk</a></li>
                <li><a href="admin_orders.php">Pesanan</a></li>
                <li><a href="admin_categories.php">Kategori</a></li>
                <li class="admin-dropdown">
                    <a href="#" class="dropbtn active"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-caret-down"></i></a>
                    <div class="admin-dropdown-content">
                        <a href="admin_profile.php">Profil Admin</a>
                        <a href="../logout.php">Logout</a>
                    </div>
                </li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <h2>Profil Admin</h2>
        
        <div class="profile-container">
            <?php if ($error_message): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <h2>Detail Profil</h2>
            <div class="profile-info">
                <p><strong>Username:</strong> <?php echo $admin_username; ?></p>
                <p><strong>Email:</strong> <?php echo $admin_email; ?></p>
                <p><strong>Role:</strong> Admin</p>
            </div>

            <hr style="margin: 30px 0; border-top: 1px solid #eee;">

            <h2>Ubah Profil</h2>
            <form action="admin_profile.php" method="POST">
                <div class="form-group">
                    <label for="username">Username Baru:</label>
                    <input type="text" id="username" name="username" value="<?php echo $admin_username; ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Baru:</label>
                    <input type="email" id="email" name="email" value="<?php echo $admin_email; ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password Baru (kosongkan jika tidak ingin mengubah):</label>
                    <input type="password" id="password" name="password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password Baru:</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
                <button type="submit" name="update_profile" class="btn-update-profile">Perbarui Profil</button>
            </form>
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