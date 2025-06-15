<?php
session_start();
require_once 'config/database.php'; 

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'user_profile.php';
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = [];
$success_message = '';
$error_message = '';

try {
    $stmt = $pdo->prepare("SELECT username, email, phone_number FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        session_destroy(); 
        header("Location: login.php?error=User_not_found");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat mengambil data profil Anda.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_email = trim($_POST['email'] ?? '');
    $new_phone_number = trim($_POST['phone_number'] ?? '');

    if (empty($new_email) || empty($new_phone_number)) {
        $error_message = "Email dan Nomor Telepon wajib diisi.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        try {
            $stmt_check_email = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check_email->execute([$new_email, $user_id]);
            if ($stmt_check_email->fetch()) {
                $error_message = "Email ini sudah digunakan oleh akun lain.";
            } else {
                $stmt_update = $pdo->prepare("UPDATE users SET email = ?, phone_number = ?, updated_at = NOW() WHERE id = ?");
                $stmt_update->execute([$new_email, $new_phone_number, $user_id]);

                $user_data['email'] = $new_email;
                $user_data['phone_number'] = $new_phone_number;
                
                $_SESSION['username'] = $user_data['username']; 
                
                $success_message = "Profil berhasil diperbarui!";
            }
        } catch (PDOException $e) {
            error_log("Error updating user profile: " . $e->getMessage());
            $error_message = "Terjadi kesalahan saat menyimpan perubahan profil Anda.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .profile-container {
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            padding: 30px;
            max-width: 600px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary-brown);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-grey-text);
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            background-color: #fcfcfc;
            color: var(--dark-grey-text);
        }
        
        .form-group input:read-only {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .btn-update-profile {
            background-color: var(--primary-pink);
            color: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        .btn-update-profile:hover {
            background-color: var(--darker-pink);
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .profile-links {
            text-align: center;
            margin-top: 30px;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding-top: 20px;
        }
        .profile-links a {
            color: var(--primary-pink);
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        .profile-links a:hover {
            color: var(--darker-pink);
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .profile-container {
                margin: 20px auto;
                padding: 15px;
            }
            .btn-update-profile {
                font-size: 1em;
                padding: 10px 20px;
            }
            .profile-links a {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="user_dashboard.php">Soncake</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn active"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-caret-down"></i></a>
                            <div class="dropdown-content">
                                <a href="history_pesanan.php">Pesanan Saya</a>
                                <a href="user_profile.php" class="active">Profil</a>
                                <a href="logout.php">Logout</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="section-padding">
            <div class="container">
                <h2 class="profile-header">Profil Pengguna</h2>

                <div class="profile-container">
                    <?php if ($success_message): ?>
                        <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <form action="user_profile.php" method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" readonly>
                            <small style="color: #666;">Username tidak dapat diubah.</small>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Nomor Telepon</label>
                            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn-update-profile">Simpan Perubahan</button>
                    </form>

                    <div class="profile-links">
                        <a href="change_password.php">Ganti Password</a>
                        </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-section about">
                <h3>Tentang Soncake</h3>
                <p>Kami menyajikan kue-kue premium dengan bahan terbaik untuk setiap momen spesial Anda.</p>
            </div>
            <div class="footer-section links">
                <h3>Tautan Cepat</h3>
                <ul>
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <li><a href="#">Kontak Kami</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                </ul>
            </div>
            <div class="footer-section contact">
                <h3>Hubungi Kami</h3>
                <p><i class="fas fa-map-marker-alt"></i> Jl. Raya Kue No. 123, Kota Rasa</p>
                <p><i class="fas fa-phone"></i> (021) 123-4567</p>
                <p><i class="fas fa-envelope"></i> info@soncake.com</p>
            </div>
            <div class="footer-section social">
                <h3>Ikuti Kami</h3>
                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Soncake. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>