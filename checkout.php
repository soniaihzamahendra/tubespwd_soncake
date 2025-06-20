<?php
session_start();
require_once 'config/database.php';
require_once 'includes/cart_functions.php'; 

$username = '';
$isLoggedIn = false;
$cart_items = []; 
$total_price = 0;
$userId = null; 

if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && $_SESSION['role'] === 'user') {
    $username = htmlspecialchars($_SESSION['username']);
    $isLoggedIn = true;
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT
                c.product_id AS id,
                p.name,
                p.price,
                p.image_url,
                c.quantity,
                p.stock
            FROM carts c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$userId]);
        $db_cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($db_cart_items as $item) {
            $cart_items[$item['id']] = $item;
        }

    } catch (PDOException $e) {
        error_log("Error fetching cart items for logged-in user in checkout.php: " . $e->getMessage());
        $_SESSION['message'] = 'Terjadi kesalahan saat memuat keranjang Anda. Silakan coba lagi.';
        $_SESSION['message_type'] = 'error';
        header("Location: keranjang.php");
        exit();
    }

} else {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    $_SESSION['message'] = 'Anda harus login terlebih dahulu untuk melanjutkan checkout.';
    $_SESSION['message_type'] = 'error';
    header("Location: login.php");
    exit();
}

if (empty($cart_items)) {
    $_SESSION['message'] = 'Keranjang Anda kosong. Silakan tambahkan produk terlebih dahulu.';
    $_SESSION['message_type'] = 'warning';
    header("Location: katalog.php"); 
    exit();
}

foreach ($cart_items as $item) {
    $price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;
    $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
    $total_price += $price * $quantity;
}

$cart_count = calculateTotalCartItems($pdo, $isLoggedIn, $userId);

$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .checkout-container {
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            padding: 30px;
            max-width: 900px;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary-brown);
        }

        .checkout-summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .checkout-summary-table th, .checkout-summary-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .checkout-summary-table th {
            background-color: var(--light-pink-bg);
            color: var(--secondary-brown);
            font-weight: 600;
        }

        .checkout-summary-table .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .checkout-summary-table .item-info img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .checkout-summary-table .item-name {
            font-weight: 600;
            color: var(--dark-grey-text);
        }

        .checkout-total-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .checkout-total {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--secondary-brown);
        }

        .checkout-total span {
            color: var(--primary-pink);
            margin-left: 15px;
        }

        .shipping-address-section,
        .payment-method-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }

        .shipping-address-section h3,
        .payment-method-section h3 {
            color: var(--secondary-brown);
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 10px;
        }

        .shipping-address-section label,
        .payment-method-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-grey-text);
        }

        .shipping-address-section input[type="text"],
        .shipping-address-section textarea,
        .payment-method-section select {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
        }

        .payment-method-section .payment-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .payment-method-section .payment-option input[type="radio"] {
            margin-right: 10px;
        }

        .place-order-btn-container {
            text-align: center;
            margin-top: 30px;
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
            .checkout-container {
                margin: 20px auto;
                padding: 15px;
            }
            .checkout-summary-table {
                font-size: 0.9em;
            }
            .checkout-summary-table th, .checkout-summary-table td {
                padding: 10px;
            }
            .checkout-summary-table .item-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .checkout-summary-table .item-info img {
                width: 40px;
                height: 40px;
            }
            .checkout-total-section {
                flex-direction: column;
                align-items: flex-end;
                gap: 10px;
            }
            .checkout-total {
                font-size: 1.5em;
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
                    <li>
                        <a href="keranjang.php" id="cartLink">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                            <span id="cart-count" class="cart-badge"><?php echo $cart_count; ?></span>
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn"><i class="fas fa-user-circle"></i> <?php echo $username; ?> <i class="fas fa-caret-down"></i></a>
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
                <h2 class="checkout-header">Ringkasan Pesanan</h2>

                <?php if ($message): ?>
                    <div class="message-box <?php echo htmlspecialchars($message_type); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="checkout-container">
                    <table class="checkout-summary-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga Satuan</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $product_id => $item): ?>
                                <tr>
                                    <td>
                                        <div class="item-info">
                                            <img src="img/<?php echo htmlspecialchars($item['image_url']); ?>"
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 onerror="this.onerror=null;this.src='img/default.png';">
                                            <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                        </div>
                                    </td>
                                    <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="checkout-total-section">
                        <div class="checkout-total">
                            Total Pembayaran: <span id="checkout-total-amount">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <form action="process_checkout.php" method="POST" class="checkout-form">
                        <div class="shipping-address-section">
                            <h3>Alamat Pengiriman</h3>
                            <label for="address_line1">Alamat Lengkap:</label>
                            <input type="text" id="address_line1" name="address_line1" placeholder="Contoh: Jl. Merdeka No. 10" required>

                            <label for="city">Kota:</label>
                            <input type="text" id="city" name="city" placeholder="Contoh: Jakarta" required>

                            <label for="postal_code">Kode Pos:</label>
                            <input type="text" id="postal_code" name="postal_code" placeholder="Contoh: 12345" required>

                            <label for="phone_number">Nomor Telepon:</label>
                            <input type="text" id="phone_number" name="phone_number" placeholder="Contoh: 081234567890" required>

                            <label for="notes">Catatan Tambahan (Opsional):</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Contoh: Tanpa kacang, pesan untuk hadiah."></textarea>
                        </div>

                        <div class="payment-method-section">
                            <h3>Metode Pembayaran</h3>
                            <div class="payment-option">
                                <input type="radio" id="bank_transfer" name="payment_method" value="Bank Transfer" checked>
                                <label for="bank_transfer">Transfer Bank</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="cod" name="payment_method" value="COD">
                                <label for="cod">Cash On Delivery (COD)</label>
                            </div>
                        </div>

                        <div class="place-order-btn-container">
                            <button type="submit" class="btn-primary">Konfirmasi Pesanan <i class="fas fa-check-circle"></i></button>
                        </div>
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
            const cartCountSpan = document.getElementById('cart-count');
            function updateCartCountDisplay(count) {
                if (cartCountSpan) {
                    cartCountSpan.textContent = count;
                    if (count > 0) {
                        cartCountSpan.classList.remove('hidden');
                    } else {
                        cartCountSpan.classList.add('hidden');
                    }
                }
            }

            const initialCartCount = <?php echo $cart_count; ?>;
            updateCartCountDisplay(initialCartCount);

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

            const checkoutForm = document.querySelector('.checkout-form');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(event) {
                    const totalAmountElement = document.getElementById('checkout-total-amount');
                    const totalText = totalAmountElement.textContent;
                    const totalValue = parseFloat(totalText.replace('Rp', '').replace(/\./g, '').replace(',', '.'));

                    if (isNaN(totalValue) || totalValue <= 0) {
                        event.preventDefault();
                        alert('Keranjang Anda kosong. Silakan tambahkan produk terlebih dahulu sebelum checkout.');
                        window.location.href = 'katalog.php';
                    }
                });
            }
        });
    </script>
</body>
</html>
