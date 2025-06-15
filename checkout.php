<?php
session_start();
require_once 'config/database.php'; 

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php'; 
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['message'] = "Keranjang Anda kosong. Silakan tambahkan produk terlebih dahulu.";
    $_SESSION['message_type'] = "warning";
    header("Location: keranjang.php");
    exit();
}

$cart_items = $_SESSION['cart'];
$total_price = 0;

$stock_validation_errors = [];

foreach ($cart_items as $product_id => $item) {
    $item_quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
    $item_price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;
    $item_name = htmlspecialchars($item['name'] ?? 'Produk Tidak Dikenal'); 

    if ($item_quantity <= 0) {
        unset($_SESSION['cart'][$product_id]);
        $stock_validation_errors[] = "Produk '" . $item_name . "' dihapus dari keranjang karena jumlahnya tidak valid.";
        continue; 
    }

    try {
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $db_product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$db_product) {
            unset($_SESSION['cart'][$product_id]);
            $stock_validation_errors[] = "Produk '" . $item_name . "' tidak ditemukan di katalog dan telah dihapus dari keranjang Anda.";
        } elseif ($db_product['stock'] < $item_quantity) {

            $_SESSION['cart'][$product_id]['quantity'] = $db_product['stock']; 
            $total_price += $item_price * $db_product['stock']; 
            
            $stock_validation_errors[] = "Stok untuk '" . $item_name . "' tidak mencukupi. Kuantitas disesuaikan dari " . $item_quantity . " menjadi " . $db_product['stock'] . ".";
        } else {
            $_SESSION['cart'][$product_id]['stock'] = $db_product['stock']; 
            $total_price += $item_price * $item_quantity;
        }

    } catch (PDOException $e) {
        error_log("Error validating cart item stock: " . $e->getMessage());
        $stock_validation_errors[] = "Terjadi masalah saat memvalidasi stok untuk '" . $item_name . "'. Harap coba lagi.";
        unset($_SESSION['cart'][$product_id]); 
    }
}

if (!empty($stock_validation_errors)) {
    $_SESSION['message'] = implode("<br>", $stock_validation_errors);
    $_SESSION['message_type'] = "error";
    header("Location: keranjang.php");
    exit();
}

if (empty($_SESSION['cart'])) {
    $_SESSION['message'] = "Keranjang Anda kosong setelah validasi stok. Silakan tambahkan produk kembali.";
    $_SESSION['message_type'] = "warning";
    header("Location: katalog.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = '';
$user_email = '';
$user_phone = '';
$user_address = '';

try {
    $stmt_user = $pdo->prepare("SELECT name, email, phone, address FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_name = $user_data['name'];
        $user_email = $user_data['email'];
        $user_phone = $user_data['phone'];
        $user_address = $user_data['address'];
    }
} catch (PDOException $e) {
    error_log("Error fetching user data for checkout: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sweet Delights</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        :root {
            --primary-pink: #FF6B81;
            --secondary-brown: #8B4513;
            --cream-white: #FFF8E1;
            --light-pink-bg: #FFEAEF;
            --dark-grey-text: #333;
            --light-grey-text: #777;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--cream-white);
            color: var(--dark-grey-text);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-header {
            background-color: var(--primary-pink);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo a {
            color: #fff;
            font-size: 1.8em;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .logo a:hover {
            color: var(--cream-white);
        }

        .main-nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .main-nav ul li {
            margin-left: 25px;
            position: relative;
        }

        .main-nav ul li a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            font-size: 1.05em;
            padding: 5px 0;
            transition: color 0.3s ease, border-bottom 0.3s ease;
        }

        .main-nav ul li a:hover,
        .main-nav ul li a.active {
            color: var(--cream-white);
            border-bottom: 2px solid var(--cream-white);
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            background-color: transparent;
            color: white;
            padding: 5px 0;
            font-size: 1.05em;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .dropbtn i {
            margin-right: 8px;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
            overflow: hidden;
            right: 0;
            top: 100%;
        }

        .dropdown-content a {
            color: var(--dark-grey-text);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
            font-weight: 400;
            border-bottom: 1px solid #eee;
        }

        .dropdown-content a:hover {
            background-color: var(--light-pink-bg);
            color: var(--primary-pink);
            border-bottom: 1px solid var(--primary-pink);
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }


        .section-padding {
            padding: 60px 0;
        }

        .cart-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary-brown);
        }

        .checkout-container {
            display: flex;
            flex-wrap: wrap; 
            gap: 40px;
            padding: 40px;
            background-color: #fff; 
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            max-width: 1000px;
        }

        .checkout-form, .order-summary {
            flex: 1 1 45%;
            min-width: 300px; 
        }

        .checkout-form h3, .order-summary h3 {
            color: var(--secondary-brown);
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 10px;
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
        .form-group input[type="tel"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box; 
            background-color: #fcfcfc;
            color: var(--dark-grey-text);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input[type="radio"] {
            margin-right: 10px;
        }
        .payment-methods label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .order-summary .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed rgba(0,0,0,0.05);
        }
        .order-summary .summary-item:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .order-summary .summary-item span:first-child {
            color: var(--dark-grey-text);
        }
        .order-summary .summary-item span:last-child {
            font-weight: 500;
            color: var(--primary-pink);
        }

        .order-summary .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--light-pink-bg);
            font-size: 1.3em;
            font-weight: 700;
            color: var(--secondary-brown);
        }
        .order-summary .summary-total span:last-child {
            color: var(--primary-pink);
        }

        .checkout-button-container {
            text-align: right;
            margin-top: 30px;
            width: 100%; 
        }
        .checkout-button-container .btn-primary {
            width: auto;
            padding: 15px 30px;
            font-size: 1.1em;
            background-color: var(--primary-pink);
            color: #fff;
            border: 2px solid var(--primary-pink);
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .checkout-button-container .btn-primary:hover {
            background-color: #e05c70;
            border-color: #e05c70;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 107, 129, 0.4);
        }

        .validation-error {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
        input.is-invalid, textarea.is-invalid, select.is-invalid {
            border-color: red !important;
        }

        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }

        .message-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-box.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @media (max-width: 768px) {
            .checkout-container {
                flex-direction: column;
                padding: 20px;
                margin: 20px auto;
                gap: 20px;
            }
            .checkout-form, .order-summary {
                flex: 1 1 100%;
                min-width: unset;
            }
            .checkout-button-container {
                text-align: center;
            }
            .checkout-button-container .btn-primary {
                width: 100%;
            }
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .main-nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .main-nav ul li {
                margin: 0 10px 10px 10px;
            }
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="user_dashboard.php">Sweet Delights</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php">Katalog</a></li>
                    <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-caret-down"></i></a>
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
                <h2 class="cart-header">Proses Checkout</h2>

                <?php
                if (isset($_SESSION['message'])) {
                    $message_type = $_SESSION['message_type'] ?? 'info';
                    echo '<div class="message-box ' . htmlspecialchars($message_type) . '">' . $_SESSION['message'] . '</div>';
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                }
                ?>

                <div class="checkout-container">
                    <div class="checkout-form">
                        <h3>Informasi Pengiriman</h3>
                        <form action="process_checkout.php" method="POST" id="checkoutForm">
                            <div class="form-group">
                                <label for="nama_lengkap">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                <div class="validation-error" id="nama_lengkap_error"></div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                                <div class="validation-error" id="email_error"></div>
                            </div>
                            <div class="form-group">
                                <label for="telepon">Nomor Telepon</label>
                                <input type="tel" id="telepon" name="telepon" value="<?php echo htmlspecialchars($user_phone); ?>" required>
                                <div class="validation-error" id="telepon_error"></div>
                            </div>
                            <div class="form-group">
                                <label for="alamat_pengiriman">Alamat Pengiriman Lengkap</label>
                                <textarea id="alamat_pengiriman" name="alamat_pengiriman" required><?php echo htmlspecialchars($user_address); ?></textarea>
                                <div class="validation-error" id="alamat_pengiriman_error"></div>
                            </div>
                            <div class="form-group">
                                <label for="catatan_pesanan">Catatan Pesanan (opsional)</label>
                                <textarea id="catatan_pesanan" name="catatan_pesanan"></textarea>
                            </div>

                            <h3>Metode Pembayaran</h3>
                            <div class="form-group payment-methods">
                                <label>
                                    <input type="radio" name="payment_method" value="COD" required checked>
                                    Bayar di Tempat (Cash On Delivery)
                                </label>
                                <label>
                                    <input type="radio" name="payment_method" value="Bank Transfer" required>
                                    Transfer Bank (Konfirmasi Manual)
                                </label>
                                <div class="validation-error" id="payment_method_error"></div>
                            </div>

                            <div class="checkout-button-container">
                                <button type="submit" class="btn-primary">Konfirmasi Pesanan <i class="fas fa-check-circle"></i></button>
                            </div>
                        </form>
                    </div>

                    <div class="order-summary">
                        <h3>Ringkasan Pesanan</h3>
                        <?php 
                        foreach ($_SESSION['cart'] as $product_id => $item): 
                            $display_item_price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;
                            $display_item_quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
                        ?>
                            <div class="summary-item">
                                <span><?php echo htmlspecialchars($item['name'] ?? 'Produk Tidak Dikenal'); ?> (x<?php echo htmlspecialchars($display_item_quantity); ?>)</span>
                                <span>Rp <?php echo number_format($display_item_price * $display_item_quantity, 0, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="summary-total">
                            <span>Total Pembayaran:</span>
                            <span>Rp <?php echo number_format($total_price, 0, ',', '.'); ?></span>
                        </div>
                        <p style="text-align: center; font-size: 0.9em; color: var(--light-grey-text); margin-top: 20px;">
                            Dengan mengklik "Konfirmasi Pesanan", Anda menyetujui syarat & ketentuan kami.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-section about">
                <h3>Tentang Sweet Delights</h3>
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
                <p><i class="fas fa-envelope"></i> info@sweetdelights.com</p>
            </div>
            <div class="footer-section social">
                <h3>Ikuti Kami</h3>
                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Sweet Delights. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkoutForm = document.getElementById('checkoutForm');
            const inputs = checkoutForm.querySelectorAll('input[required], textarea[required], select[required]');

            checkoutForm.addEventListener('submit', function(event) {
                let isValid = true;

                inputs.forEach(input => {
                    const errorElement = document.getElementById(input.id + '_error');
                    if (errorElement) {
                        errorElement.textContent = ''; 
                    }

                    if (!input.checkValidity()) {
                        isValid = false;
                        if (errorElement) {
                            errorElement.textContent = input.validationMessage || 'Bidang ini wajib diisi.';
                        }
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
                const paymentMethodError = document.getElementById('payment_method_error');
                let paymentSelected = false;
                paymentMethodRadios.forEach(radio => {
                    if (radio.checked) {
                        paymentSelected = true;
                    }
                });

                if (!paymentSelected) {
                    isValid = false;
                    paymentMethodError.textContent = 'Pilih metode pembayaran.';
                } else {
                    paymentMethodError.textContent = '';
                }


                if (!isValid) {
                    event.preventDefault(); 
                    alert('Mohon lengkapi semua data yang diperlukan dengan benar.');
                }
            });

            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const errorElement = document.getElementById(input.id + '_error');
                    if (errorElement && input.checkValidity()) {
                        errorElement.textContent = '';
                        input.classList.remove('is-invalid');
                    }
                });
            });
        });
    </script>
</body>
</html>