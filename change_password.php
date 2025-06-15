<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'change_password.php';
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $error_message = "Semua field harus diisi.";
    } elseif ($new_password !== $confirm_new_password) {
        $error_message = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) { 
        $error_message = "Password baru minimal 6 karakter.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                session_destroy();
                header("Location: login.php?error=User_data_corrupted");
                exit();
            }
            if (!password_verify($current_password, $user['password_hash'])) {
                $error_message = "Password saat ini salah.";
            } else {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt_update = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt_update->execute([$new_password_hash, $user_id]);

                $success_message = "Password berhasil diperbarui!";
            }

        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            $error_message = "Terjadi kesalahan saat memperbarui password. Silakan coba lagi.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .password-change-container {
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            padding: 30px;
            max-width: 500px;
        }

        .password-change-header {
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

        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            background-color: #fcfcfc;
            color: var(--dark-grey-text);
        }

        .btn-change-password {
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
        .btn-change-password:hover {
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

        @media (max-width: 768px) {
            .password-change-container {
                margin: 20px auto;
                padding: 15px;
            }
            .btn-change-password {
                font-size: 1em;
                padding: 10px 20px;
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
                                <a href="user_profile.php">Profil</a>
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
                <h2 class="password-change-header">Ganti Password</h2>

                <div class="password-change-container">
                    <?php if ($success_message): ?>
                        <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <form action="change_password.php" method="POST">
                        <div class="form-group">
                            <label for="current_password">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                        </div>
                        
                        <button type="submit" class="btn-change-password">Ubah Password</button>
                    </form>
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