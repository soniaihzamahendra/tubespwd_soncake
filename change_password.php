<?php
session_start();
// Pastikan jalur ini sesuai dengan struktur folder Anda.
// Asumsi: file ini berada di folder root proyek Anda, sama dengan 'config' dan 'includes'.
require_once 'config/database.php';
require_once 'includes/cart_functions.php';

// Menonaktifkan laporan error untuk lingkungan produksi.
// Aktifkan kembali (error_reporting(E_ALL); ini_set('display_errors', 1);) hanya jika debugging diperlukan.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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

    // Validasi input
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $error_message = "Semua field harus diisi.";
    } elseif ($new_password !== $confirm_new_password) {
        $error_message = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password baru minimal 6 karakter.";
    } else {
        try {
            // Ambil hash password pengguna saat ini dari database
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Jika data pengguna tidak ditemukan, artinya sesi mungkin rusak atau user_id tidak valid
                session_destroy(); // Hancurkan sesi untuk keamanan
                header("Location: login.php?error=User_data_corrupted");
                exit();
            }

            // Memverifikasi password saat ini
            if (password_verify($current_password, $user['password_hash'])) {
                // Hash password baru
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Perbarui password di database
                $stmt_update = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt_update->execute([$new_password_hash, $user_id]);

                // Periksa apakah ada baris yang terpengaruh (password berhasil diubah)
                if ($stmt_update->rowCount() > 0) {
                    $success_message = "Password berhasil diperbarui!";
                } else {
                    // Ini bisa terjadi jika password baru sama dengan yang lama, atau masalah lain yang tidak memicu exception
                    $error_message = "Tidak ada perubahan password terdeteksi. Mungkin password baru sama dengan password lama Anda.";
                    error_log("Password update resulted in 0 row count for user_id: " . $user_id); // Tetap log di server
                }
            } else {
                $error_message = "Password saat ini salah. Mohon periksa kembali.";
                error_log("Password verification failed for user_id: " . $user_id); // Tetap log di server
            }

        } catch (PDOException $e) {
            // Tangani kesalahan database
            error_log("Error changing password (PDOException): " . $e->getMessage() . " for user_id: " . $user_id);
            $error_message = "Terjadi kesalahan database saat memperbarui password. Silakan coba lagi.";
        } catch (Exception $e) {
            // Tangani kesalahan umum lainnya
            error_log("General error changing password: " . $e->getMessage() . " for user_id: " . $user_id);
            $error_message = "Terjadi kesalahan yang tidak terduga saat memperbarui password. Silakan coba lagi.";
        }
    }
}

// Data untuk header navigasi
$username_header = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
$isLoggedIn_header = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'; // Asumsi untuk header user
$cart_count_header = 0;
if ($isLoggedIn_header) {
    // Fungsi ini diasumsikan ada di includes/cart_functions.php
    $cart_count_header = calculateTotalCartItems($pdo, $isLoggedIn_header, $_SESSION['user_id']);
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
        .cart-badge {
            background-color: #ff0000;
            color: white;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 0.7em;
            position: relative;
            top: -8px;
            left: -5px;
            white-space: nowrap;
            vertical-align: super;
            min-width: 18px;
            text-align: center;
            display: inline-block;
        }
        .cart-badge.hidden {
            display: none;
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
                    <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang <span id="cart-count" class="cart-badge"><?php echo $cart_count_header; ?></span></a></li>
                    <?php if ($isLoggedIn_header): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn active"><i class="fas fa-user-circle"></i> <?php echo $username_header; ?> <i class="fas fa-caret-down"></i></a>
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

                    <?php
                    // Cek dan tampilkan pesan dari sesi (jika ada, mungkin dari skrip lain)
                    if (isset($_SESSION['message'])) {
                        $session_message = $_SESSION['message'];
                        $session_message_type = $_SESSION['message_type'] ?? 'error'; // Default ke error jika type tidak diset
                        echo '<div class="message ' . htmlspecialchars($session_message_type) . '-message">' . htmlspecialchars($session_message) . '</div>';
                        unset($_SESSION['message']); // Hapus pesan setelah ditampilkan
                        unset($_SESSION['message_type']); // Hapus tipe pesan
                    }
                    ?>

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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle dropdowns
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const dropbtn = dropdown.querySelector('.dropbtn');
                if (window.innerWidth <= 992) {
                    dropbtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        document.querySelectorAll('.dropdown').forEach(otherDropdown => {
                            if (otherDropdown !== dropdown && otherDropdown.classList.contains('active')) {
                                otherDropdown.classList.remove('active');
                            }
                        });
                        dropdown.classList.toggle('active');
                    });
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    document.querySelectorAll('.dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
            });

            // Optional: Hide messages after a few seconds
            const successMessage = document.querySelector('.success-message');
            const errorMessage = document.querySelector('.error-message');

            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }
        });
    </script>
</body>
</html>
