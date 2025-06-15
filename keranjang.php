<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    $_SESSION['message'] = 'Anda harus login terlebih dahulu untuk mengakses keranjang belanja.';
    $_SESSION['message_type'] = 'error';
    header("Location: login.php"); 
    exit(); 
}

require_once 'config/database.php'; 

$cart_items = $_SESSION['cart'] ?? [];
$total_price = 0;

foreach ($cart_items as $item) {
    $price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;
    $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
    $total_price += $price * $quantity;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .cart-container {
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            padding: 30px;
            max-width: 900px;
        }

        .cart-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary-brown);
        }

        .cart-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .cart-items th, .cart-items td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .cart-items th {
            background-color: var(--light-pink-bg);
            color: var(--secondary-brown);
            font-weight: 600;
        }

        .cart-item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .cart-item-info img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--dark-grey-text);
        }

        .cart-item-price {
            color: var(--primary-pink);
            font-weight: 500;
        }

        .quantity-control-cart {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 5px;
            overflow: hidden;
            width: fit-content;
        }

        .quantity-control-cart button {
            background-color: #f0f0f0;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9em;
            color: #555;
            transition: background-color 0.2s ease;
        }
        .quantity-control-cart button:hover {
            background-color: #e0e0e0;
        }
        .quantity-control-cart input {
            width: 40px;
            text-align: center;
            border: none;
            outline: none;
            font-size: 1em;
            padding: 8px 0;
            -moz-appearance: textfield;
        }
        .quantity-control-cart input::-webkit-outer-spin-button,
        .quantity-control-cart input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .quantity-control-cart button:disabled {
            background-color: #ddd;
            cursor: not-allowed;
            color: #aaa;
        }

        .remove-item-btn {
            background-color: #ff4d4d;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.9em;
        }

        .remove-item-btn:hover {
            background-color: #e60000;
        }

        .cart-summary {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .cart-total {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--secondary-brown);
        }

        .cart-total span {
            color: var(--primary-pink);
            margin-left: 10px;
        }

        .checkout-btn-container {
            text-align: right;
            margin-top: 30px;
        }

        .empty-cart-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: var(--light-grey-text);
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
            .cart-container {
                margin: 20px auto;
                padding: 15px;
            }
            .cart-items {
                font-size: 0.9em;
            }
            .cart-items th, .cart-items td {
                padding: 10px;
            }
            .cart-item-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .cart-item-info img {
                width: 60px;
                height: 60px;
            }
            .cart-summary {
                flex-direction: column;
                align-items: flex-end;
                gap: 10px;
            }
            .cart-total {
                font-size: 1.2em;
            }
            .checkout-btn-container {
                text-align: center;
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
                    <li><a href="keranjang.php" class="active"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
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
                <h2 class="cart-header">Keranjang Belanja Anda</h2>

                <?php
                if (isset($_SESSION['message'])) {
                    $message_type = $_SESSION['message_type'] ?? 'info';
                    echo '<div id="server-message" class="message-box ' . htmlspecialchars($message_type) . '">' . htmlspecialchars($_SESSION['message']) . '</div>';
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                }
                ?>
                <div id="ajax-message-display" class="message-box" style="display: none;"></div>

                <div class="cart-container">
                    <?php if (empty($cart_items)): ?>
                        <p class="empty-cart-message">Keranjang Anda kosong. Yuk, <a href="katalog.php">belanja sekarang!</a></p>
                    <?php else: ?>
                        <table class="cart-items">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Harga</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $product_id => $item): ?>
                                <tr data-product-id="<?php echo $product_id; ?>">
                                    <td>
                                        <div class="cart-item-info">
                                            <img src="img/<?php echo htmlspecialchars($item['image_url']); ?>"
                                            alt="<?php echo htmlspecialchars($item['name']); ?>"
                                            onerror="this.onerror=null;this.src='img/default.png';">
                                            <span class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="cart-item-price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></span></td>
                                    <td>
                                        <div class="quantity-control-cart">
                                            <button class="decrement-qty-btn" data-id="<?php echo $product_id; ?>" data-stock="<?php echo $item['stock']; ?>" <?php echo ($item['quantity'] <= 1) ? 'disabled' : ''; ?>>-</button>
                                            <input type="number" class="item-quantity-input" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" max="<?php echo htmlspecialchars($item['stock']); ?>" data-id="<?php echo $product_id; ?>" data-price="<?php echo $item['price']; ?>">
                                            <button class="increment-qty-btn" data-id="<?php echo $product_id; ?>" data-stock="<?php echo $item['stock']; ?>" <?php echo ($item['quantity'] >= $item['stock']) ? 'disabled' : ''; ?>>+</button>
                                        </div>
                                    </td>
                                    <td class="subtotal-cell">Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                    <td>
                                        <button class="remove-item-btn" data-id="<?php echo $product_id; ?>"><i class="fas fa-trash"></i> Hapus</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="cart-summary">
                            <div class="cart-total">
                                Total: <span id="cart-total-amount">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></span>
                            </div>
                        </div>

                        <div class="checkout-btn-container">
                            <a href="checkout.php" class="btn-primary">Lanjutkan ke Checkout <i class="fas fa-arrow-right"></i></a>
                        </div>
                    <?php endif; ?>
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
            const ajaxMessageDisplay = document.getElementById('ajax-message-display');

            function formatRupiah(amount) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(amount);
            }

            function showMessage(message, type) {
                ajaxMessageDisplay.textContent = message;
                ajaxMessageDisplay.className = `message-box ${type}`;
                ajaxMessageDisplay.style.display = 'block';

                setTimeout(() => {
                    ajaxMessageDisplay.style.display = 'none';
                    ajaxMessageDisplay.textContent = '';
                    ajaxMessageDisplay.className = 'message-box';
                }, 5000);
            }

            function updateCartDisplay(productId, newQuantity, subtotal, cartTotal) {
                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    const quantityInput = row.querySelector('.item-quantity-input');
                    quantityInput.value = newQuantity;
                    
                    const subtotalCell = row.querySelector('.subtotal-cell');
                    subtotalCell.textContent = formatRupiah(subtotal);
                    
                    const totalElement = document.getElementById('cart-total-amount');
                    totalElement.textContent = formatRupiah(cartTotal);
                    
                    const decrementBtn = row.querySelector('.decrement-qty-btn');
                    const incrementBtn = row.querySelector('.increment-qty-btn');
                    const stock = parseInt(quantityInput.max);

                    decrementBtn.disabled = newQuantity <= 1;
                    incrementBtn.disabled = newQuantity >= stock;
                }
            }

            function removeItemFromDisplay(productId, cartTotal) {
                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    row.remove();
                    const totalElement = document.getElementById('cart-total-amount');
                    totalElement.textContent = formatRupiah(cartTotal);

                    const cartTableBody = document.querySelector('.cart-items tbody');
                    if (cartTableBody && cartTableBody.children.length === 0) {
                        const cartContainer = document.querySelector('.cart-container');
                        cartContainer.innerHTML = '<p class="empty-cart-message">Keranjang Anda kosong. Yuk, <a href="katalog.php">belanja sekarang!</a></p>';
                    }
                }
            }

            async function sendUpdateCartRequest(productId, quantity, action) {
                const dataToSend = {
                    product_id: productId,
                    quantity: quantity,
                    action: action
                };

                try {
                    const response = await fetch('update_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(dataToSend)
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText} - ${errorText}`);
                    }
                    
                    const data = await response.json();

                    if (data.success) {
                        showMessage(data.message, 'success');
                        if (action === 'remove') {
                            removeItemFromDisplay(productId, data.cart_total);
                        } else { 
                            updateCartDisplay(productId, data.new_quantity || quantity, data.subtotal, data.cart_total);
                        }
                    } else {
                        showMessage(data.message || 'Terjadi kesalahan pada server.', 'error');
                        if (action === 'update' && data.current_quantity) {
                            const quantityInput = document.querySelector(`tr[data-product-id="${productId}"] .item-quantity-input`);
                            if (quantityInput) {
                                quantityInput.value = data.current_quantity;
                                updateCartDisplay(productId, data.current_quantity, data.subtotal, data.cart_total);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Fetch Error:', error);
                    showMessage('Kesalahan jaringan atau server: ' + error.message, 'error');
                }
            }


            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item-btn') || e.target.closest('.remove-item-btn')) {
                    const btn = e.target.closest('.remove-item-btn');
                    const productId = btn.dataset.id;
                    if (confirm('Yakin ingin menghapus produk ini dari keranjang?')) {
                        sendUpdateCartRequest(productId, 0, 'remove');
                    }
                }
                
                if (e.target.classList.contains('increment-qty-btn')) {
                    const productId = e.target.dataset.id;
                    const inputElement = e.target.previousElementSibling;
                    let currentQty = parseInt(inputElement.value);
                    const maxStock = parseInt(inputElement.max);

                    if (currentQty < maxStock) {
                        currentQty++;
                        sendUpdateCartRequest(productId, currentQty, 'update');
                    } else {
                        showMessage('Jumlah maksimum (stok) telah tercapai!', 'warning');
                    }
                }
                
                if (e.target.classList.contains('decrement-qty-btn')) {
                    const productId = e.target.dataset.id;
                    const inputElement = e.target.nextElementSibling;
                    let currentQty = parseInt(inputElement.value);

                    if (currentQty > 1) {
                        currentQty--;
                        sendUpdateCartRequest(productId, currentQty, 'update');
                    } else {
                        showMessage('Jumlah minimum adalah 1.', 'warning');
                    }
                }
            });

            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('item-quantity-input')) {
                    const productId = e.target.dataset.id;
                    let newQty = parseInt(e.target.value);
                    const maxStock = parseInt(e.target.max);

                    if (isNaN(newQty) || newQty < 1) {
                        newQty = 1;
                        e.target.value = 1;
                        showMessage('Kuantitas harus angka positif minimal 1.', 'error');
                    } else if (newQty > maxStock) {
                        newQty = maxStock;
                        e.target.value = maxStock;
                        showMessage('Jumlah melebihi stok yang tersedia (' + maxStock + ')!', 'warning');
                    }
                    sendUpdateCartRequest(productId, newQty, 'update');
                }
            });
        });
    </script>
</body>
</html>