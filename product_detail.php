<?php
session_start();
require_once 'config/database.php';
require_once 'includes/cart_functions.php'; 

$product = null;
$error_message = '';

$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$cart_count = calculateTotalCartItems($pdo, $isLoggedIn, $userId);


if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.name,
                p.description,
                p.price,
                p.stock,
                p.image_url,
                p.rating,
                c.name AS category_name
            FROM
                products p
            JOIN
                categories c ON p.category_id = c.id
            WHERE
                p.id = :id
        ");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $error_message = "Produk tidak ditemukan.";
        }

    } catch (PDOException $e) {
        error_log("Error fetching product details: " . $e->getMessage());
        $error_message = "Terjadi kesalahan saat memuat detail produk.";
    }
} else {
    $error_message = "ID produk tidak valid atau tidak diberikan.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) . ' - Soncake' : 'Produk Tidak Ditemukan'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fdfaf6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .product-detail-container {
            flex: 1;
            max-width: 900px;
            margin: 40px auto;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            flex-wrap: wrap;
            padding: 30px;
            gap: 30px;
        }

        .product-detail-image {
            flex: 1;
            min-width: 300px;
            max-width: 45%;
            text-align: center;
        }

        .product-detail-image img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .product-detail-info {
            flex: 1;
            min-width: 300px;
            max-width: 50%;
        }

        .product-detail-info h1 {
            color: #5e3a45;
            font-size: 2.5em;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .product-detail-info .category {
            font-size: 1em;
            color: #8c6b75;
            margin-bottom: 15px;
        }

        .product-detail-info .rating {
            font-size: 1.1em;
            color: #e6a8b1;
            margin-bottom: 15px;
        }

        .product-detail-info .price {
            font-size: 2em;
            font-weight: 700;
            color: #b36e7c;
            margin-bottom: 20px;
        }

        .product-detail-info .stock {
            font-size: 1em;
            color: #777;
            margin-bottom: 20px;
        }

        .product-detail-info .description {
            color: #555;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .add-to-cart-form {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 20px;
        }

        .add-to-cart-form label {
            font-weight: 600;
            color: #5e3a45;
        }

        .add-to-cart-form input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            text-align: center;
        }

        .btn-add-to-cart {
            background-color: #e6a8b1;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-add-to-cart:hover {
            background-color: #d18f9e;
        }

        .btn-add-to-cart:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: #b36e7c;
            text-decoration: none;
            font-weight: 500;
            font-size: 1.1em;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        #addToCartMessage {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.5s ease-in-out, background-color 0.3s ease;
            max-height: 0;
            overflow: hidden;
        }
        #addToCartMessage.show {
            opacity: 1;
            max-height: 100px;
        }
        #addToCartMessage.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        #addToCartMessage.error {
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

        @media (max-width: 992px) {
            .main-header {
                flex-direction: column;
                padding: 10px 15px;
            }
            .main-header .main-nav ul {
                margin-top: 10px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .main-header .main-nav ul li {
                margin: 5px 10px;
            }
            .product-detail-container {
                flex-direction: column;
                padding: 20px;
                margin: 20px auto;
            }
            .product-detail-image,
            .product-detail-info {
                max-width: 100%;
                min-width: unset;
            }
            .add-to-cart-form {
                flex-direction: column;
                gap: 10px;
            }
            .add-to-cart-form input[type="number"] {
                width: 100%;
            }
            .btn-add-to-cart {
                width: 100%;
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
        <?php if ($product): ?>
            <div class="product-detail-container">
                <div class="product-detail-image">
                    <img src="img/<?php echo htmlspecialchars($product['image_url']); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             onerror="this.onerror=null;this.src='img/default.png';">
                </div>
                <div class="product-detail-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="category">Kategori: <?php echo htmlspecialchars($product['category_name']); ?></p>
                    <p class="rating">
                        <i class="fas fa-star"></i> <?php echo htmlspecialchars(number_format($product['rating'], 1)); ?> / 5.0
                    </p>
                    <p class="price">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                    <p class="stock">Stok Tersedia: <?php echo htmlspecialchars($product['stock']); ?></p>
                    <p class="description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                    <form class="add-to-cart-form" id="addToCartForm" action="add_to_cart.php" method="post">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                        <label for="quantity">Jumlah:</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['stock']); ?>" required>
                        <button type="submit" class="btn-add-to-cart" <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-cart-plus"></i> <?php echo ($product['stock'] <= 0) ? 'Stok Habis' : 'Tambah ke Keranjang'; ?>
                        </button>
                    </form>
                    <div id="addToCartMessage"></div>
                </div>
            </div>
            <a href="katalog.php" class="back-link"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Katalog</a>
        <?php else: ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <a href="katalog.php" class="back-link">Kembali ke Katalog</a>
        <?php endif; ?>
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

    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    const cartLink = document.getElementById('cartLink');
    const cartCountSpan = document.getElementById('cart-count');
    const addToCartForm = document.getElementById('addToCartForm');
    const quantityInput = document.getElementById('quantity');
    const addToCartMessageDiv = document.getElementById('addToCartMessage');

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

    if (cartCountSpan) {
        const initialCount = parseInt(cartCountSpan.textContent);
        updateCartCountDisplay(initialCount);
    }

    if (cartLink) {
        cartLink.addEventListener('click', function(event) {
            if (!isLoggedIn) {
                event.preventDefault();
                alert("Anda harus login terlebih dahulu untuk mengakses keranjang.");
                sessionStorage.setItem('intended_url', window.location.href);
                window.location.href = 'login.php';
            }
        });
    }

    if (addToCartForm) {
        addToCartForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const submitButton = this.querySelector('.btn-add-to-cart');

            addToCartMessageDiv.classList.remove('show', 'success', 'error');
            addToCartMessageDiv.textContent = '';

            if (submitButton && submitButton.disabled) {
                return;
            }

            if (!isLoggedIn) {
                sessionStorage.setItem('intended_url', window.location.href);
                alert('Anda harus login terlebih dahulu untuk menambahkan produk ke keranjang.');
                window.location.href = 'login.php';
                return;
            }

            const quantity = parseInt(quantityInput.value);
            const productStock = parseInt(quantityInput.max);

            if (isNaN(quantity) || quantity <= 0) {
                addToCartMessageDiv.textContent = 'Kuantitas harus angka positif.';
                addToCartMessageDiv.classList.add('show', 'error');
                return;
            }
            if (quantity > productStock) {
                addToCartMessageDiv.textContent = 'Jumlah melebihi stok yang tersedia (' + productStock + ').';
                addToCartMessageDiv.classList.add('show', 'error');
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Menambahkan...'; 
            }

            try {
                const formData = new FormData(this); 
                const response = await fetch('add_to_cart.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData) 
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText + ' - ' + errorText);
                }

                const data = await response.json();

                if (data.success) {
                    addToCartMessageDiv.textContent = data.message;
                    addToCartMessageDiv.classList.add('show', 'success');
                    updateCartCountDisplay(data.total_cart_items); 
                } else {
                    addToCartMessageDiv.textContent = data.message || 'Terjadi kesalahan saat menambahkan produk ke keranjang.';
                    addToCartMessageDiv.classList.add('show', 'error');
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                addToCartMessageDiv.textContent = 'Kesalahan jaringan atau server: ' + error.message;
                addToCartMessageDiv.classList.add('show', 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-cart-plus"></i> ' + (productStock <= 0 ? 'Stok Habis' : 'Tambah ke Keranjang');
                }
            }
        });
    }
});
    </script>
</body>
</html>
