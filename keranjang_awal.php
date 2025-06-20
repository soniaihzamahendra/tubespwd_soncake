<?php
session_start();
require_once 'config/database.php';

$cart_items = [];
$total_price = 0;
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
$cartItemCount = 0; 

if ($is_logged_in) {
    $userId = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.stock, p.image_url
                               FROM carts c JOIN products p ON c.product_id = p.id
                               WHERE c.user_id = ?");
        $stmt->execute([$userId]);
        $db_cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($db_cart_items as $item) {
            $cart_items[$item['product_id']] = [
                'id' => $item['product_id'],
                'name' => $item['name'],
                'price' => (float)$item['price'],
                'image_url' => $item['image_url'],
                'quantity' => (int)$item['quantity'],
                'stock' => (int)$item['stock']
            ];
            $total_price += (float)$item['price'] * (int)$item['quantity'];
            $cartItemCount += (int)$item['quantity']; 
        }
    } catch (PDOException $e) {
        error_log("Database error in keranjang.php (logged in cart fetch): " . $e->getMessage());
        $_SESSION['message'] = 'Terjadi kesalahan saat memuat keranjang Anda dari database.';
        $_SESSION['message_type'] = 'error';
    }
} else {
    if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = []; 
    }
    
    $temp_guest_cart = [];
    foreach ($_SESSION['guest_cart'] as $productId => $item) {
        try {
            $stmt = $pdo->prepare("SELECT stock, price, name, image_url FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $productDb = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($productDb) {
                $current_quantity = (int)($item['quantity'] ?? 0);
                $available_stock = (int)$productDb['stock'];

                if ($current_quantity > $available_stock) {
                    $current_quantity = $available_stock;
                    $_SESSION['message'] = 'Kuantitas untuk beberapa produk disesuaikan karena melebihi stok yang tersedia.';
                    $_SESSION['message_type'] = 'warning';
                }
                if ($current_quantity <= 0) {
                     continue;
                }

                $temp_guest_cart[$productId] = [
                    'id' => $productId,
                    'name' => $productDb['name'],
                    'price' => (float)$productDb['price'],
                    'image_url' => $productDb['image_url'],
                    'quantity' => $current_quantity,
                    'stock' => $available_stock
                ];
                $total_price += (float)$productDb['price'] * $current_quantity;
                $cartItemCount += $current_quantity; 
            } else {
                $_SESSION['message'] = 'Beberapa produk di keranjang Anda tidak lagi tersedia dan telah dihapus.';
                $_SESSION['message_type'] = 'warning';
            }
        } catch (PDOException $e) {
            error_log("Database error in keranjang.php (guest cart product fetch): " . $e->getMessage());
            $_SESSION['message'] = 'Terjadi kesalahan saat memuat detail produk di keranjang Anda.';
            $_SESSION['message_type'] = 'error';
        }
    }
    $_SESSION['guest_cart'] = $temp_guest_cart;
    $cart_items = $_SESSION['guest_cart'];

    if (!empty($cart_items)) {
        $_SESSION['message'] = $_SESSION['message'] ?? 'Anda belum login. Item di keranjang ini bersifat sementara. Silakan <a href="login.php">login</a> untuk menyimpan keranjang Anda secara permanen.';
        $_SESSION['message_type'] = $_SESSION['message_type'] ?? 'warning';
    } else {
        if (isset($_SESSION['message']) && strpos($_SESSION['message'], 'sementara') !== false) {
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
    }
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

        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
            justify-content: center; 
            align-items: center; 
            padding: 20px; 
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto; 
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 450px;
            text-align: center;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content h3 {
            color: var(--secondary-brown);
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .modal-content p {
            margin-bottom: 25px;
            line-height: 1.6;
            color: var(--dark-grey-text);
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .modal-buttons .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease, color 0.3s ease;
            text-decoration: none; 
            display: inline-block; 
        }

        .modal-buttons .btn-login {
            background-color: var(--primary-pink);
            color: white;
        }

        .modal-buttons .btn-login:hover {
            background-color: #e06d6d; 
        }

        .modal-buttons .btn-close {
            background-color: #ddd;
            color: var(--dark-grey-text);
        }

        .modal-buttons .btn-close:hover {
            background-color: #ccc;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }

        .modal.fade-out {
            animation: fadeOut 0.3s ease-out forwards;
        }

        .cart-icon-wrapper {
            position: relative;
            display: inline-block; 
        }

        .cart-bubble {
            position: absolute;
            top: -8px; 
            right: -8px; 
            background-color: var(--primary-pink); 
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75em;
            font-weight: bold;
            min-width: 20px; 
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            line-height: 1; 
            display: <?php echo ($cartItemCount > 0) ? 'block' : 'none'; ?>; 
        }

        .mobile-cart-count {
            display: <?php echo ($cartItemCount > 0) ? 'inline-block' : 'none'; ?>;
            background-color: var(--primary-pink);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75em;
            margin-left: 5px;
        }

    </style>
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="index.php">Soncake</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="katalogawal.php">Katalog</a></li>
                    <li>
                        <a href="keranjang.php" class="active cart-icon-wrapper">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                            <span class="cart-bubble" id="cart-item-count"><?php echo $cartItemCount; ?></span>
                        </a>
                    </li>
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
                    echo '<div id="server-message" class="message-box ' . htmlspecialchars($message_type) . '">' . $_SESSION['message'] . '</div>';
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                }
                ?>
                <div id="ajax-message-display" class="message-box" style="display: none;"></div>

                <div class="cart-container">
                    <?php if (empty($cart_items)): ?>
                        <p class="empty-cart-message">Keranjang Anda kosong. Yuk, <a href="katalogawal.php">belanja sekarang!</a></p>
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
                                            <button class="decrement-qty-btn" data-id="<?php echo $product_id; ?>" data-stock="<?php echo htmlspecialchars($item['stock']); ?>" <?php echo ($item['quantity'] <= 1) ? 'disabled' : ''; ?>>-</button>
                                            <input type="number" class="item-quantity-input" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" max="<?php echo htmlspecialchars($item['stock']); ?>" data-id="<?php echo $product_id; ?>" data-price="<?php echo $item['price']; ?>">
                                            <button class="increment-qty-btn" data-id="<?php echo $product_id; ?>" data-stock="<?php echo htmlspecialchars($item['stock']); ?>" <?php echo ($item['quantity'] >= $item['stock']) ? 'disabled' : ''; ?>>+</button>
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
                            <a href="checkout.php" class="btn-primary" id="checkout-btn">Lanjutkan ke Checkout <i class="fas fa-arrow-right"></i></a>
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
                    <li><a href="index.php">Home</a></li>
                    <li><a href="katalogawal.php">Katalog</a></li>
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

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <h3>Login Diperlukan</h3>
            <p>Untuk melanjutkan ke proses checkout, Anda harus login terlebih dahulu.</p>
            <div class="modal-buttons">
                <a href="login.php" class="btn btn-login">Login Sekarang</a>
                <button type="button" class="btn btn-close" id="closeModalBtn">Nanti Saja</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ajaxMessageDisplay = document.getElementById('ajax-message-display');
            const checkoutBtn = document.getElementById('checkout-btn');
            const loginModal = document.getElementById('loginModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const isLoggedIn = <?php echo json_encode($is_logged_in); ?>; 
            const cartItemCountBubble = document.getElementById('cart-item-count');

            function formatRupiah(amount) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(amount);
            }

            function showMessage(message, type) {
                ajaxMessageDisplay.textContent = ''; 
                if (typeof message === 'string') {
                    ajaxMessageDisplay.innerHTML = message;
                } else {
                    ajaxMessageDisplay.textContent = 'Error: Message is not a string.';
                }
                ajaxMessageDisplay.className = `message-box ${type}`;
                ajaxMessageDisplay.style.display = 'block';

                setTimeout(() => {
                    ajaxMessageDisplay.style.display = 'none';
                    ajaxMessageDisplay.textContent = '';
                    ajaxMessageDisplay.className = 'message-box';
                }, 5000);
            }

            function updateCartBubble(count) {
                const cartBubbles = document.querySelectorAll('.cart-bubble');
                cartBubbles.forEach(bubble => {
                    bubble.textContent = count;
                    bubble.style.display = count > 0 ? 'block' : 'none';
                });
                
                const mobileCartCount = document.querySelector('.mobile-cart-count');
                if (mobileCartCount) {
                    mobileCartCount.textContent = count;
                    mobileCartCount.style.display = count > 0 ? 'inline-block' : 'none';
                }
            }

            function updateCartDisplay(productId, newQuantity, subtotal, cartTotal, newCartItemCount) {
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
                
                updateCartBubble(newCartItemCount);
            }

            function removeItemFromDisplay(productId, cartTotal, newCartItemCount) {
                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    row.remove();
                    const totalElement = document.getElementById('cart-total-amount');
                    totalElement.textContent = formatRupiah(cartTotal);

                    const cartTableBody = document.querySelector('.cart-items tbody');
                    if (cartTableBody && cartTableBody.children.length === 0) {
                        const cartContainer = document.querySelector('.cart-container');
                        cartContainer.innerHTML = '<p class="empty-cart-message">Keranjang Anda kosong. Yuk, <a href="katalogawal.php">belanja sekarang!</a></p>';
                    }
                }
                
                updateCartBubble(newCartItemCount);
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
                        showMessage(data.message, data.message_type || 'success');
                        if (action === 'remove' || data.action_performed === 'remove') {
                            removeItemFromDisplay(productId, data.cart_total, data.cart_item_count);
                        } else { 
                            updateCartDisplay(productId, data.new_quantity || quantity, data.subtotal, data.cart_total, data.cart_item_count);
                        }
                    } else {
                        showMessage(data.message || 'Terjadi kesalahan pada server.', data.message_type || 'error');
                        if (action === 'update' && data.current_quantity !== undefined) {
                            const quantityInput = document.querySelector(`tr[data-product-id="${productId}"] .item-quantity-input`);
                            if (quantityInput) {
                                quantityInput.value = data.current_quantity;
                                const itemPrice = parseFloat(quantityInput.dataset.price);
                                const currentSubtotal = itemPrice * data.current_quantity;
                                updateCartDisplay(productId, data.current_quantity, currentSubtotal, data.cart_total, data.cart_item_count);
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

            checkoutBtn.addEventListener('click', function(e) {
                if (!isLoggedIn) {
                    e.preventDefault(); 
                    loginModal.style.display = 'flex'; 
                }
            });

            closeModalBtn.addEventListener('click', function() {
                loginModal.classList.add('fade-out');
                loginModal.addEventListener('animationend', function handler() {
                    loginModal.style.display = 'none';
                    loginModal.classList.remove('fade-out');
                    loginModal.removeEventListener('animationend', handler);
                });
            });

            window.addEventListener('click', function(e) {
                if (e.target == loginModal) {
                    loginModal.classList.add('fade-out');
                    loginModal.addEventListener('animationend', function handler() {
                        loginModal.style.display = 'none';
                        loginModal.classList.remove('fade-out');
                        loginModal.removeEventListener('animationend', handler);
                    });
                }
            });
        });
    </script>
</body>
</html>