<?php
session_start();
require_once 'config/database.php';

$username = '';
$isLoggedIn = false;
$cartItemCount = 0; // Initialize cart item count

if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && $_SESSION['role'] === 'user') {
    $username = htmlspecialchars($_SESSION['username']);
    $isLoggedIn = true;

    // For logged-in users, get cart count from database
    try {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT SUM(quantity) AS total_items FROM carts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartItemCount = (int)($result['total_items'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error fetching cart count for logged-in user: " . $e->getMessage());
        $cartItemCount = 0; // Default to 0 on error
    }

} else {
    // For guest users, get cart count from session (guest_cart)
    if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $item) {
            $cartItemCount += (int)($item['quantity'] ?? 0);
        }
    }
}


try {
    $stmt_products = $pdo->query("SELECT id, name, image_url, price, rating, description FROM products ORDER BY rating DESC LIMIT 4");
    $featured_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featured_products = [];
    error_log("Error fetching featured products: " . $e->getMessage());
}

try {
    $stmt_categories = $pdo->query("SELECT name, icon FROM categories ORDER BY name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

$testimonials = [
    ['quote' => 'Kue Red Velvet-nya super enak, lembut dan tidak terlalu manis. Pesan lagi pasti!', 'author' => 'Sarah M.'],
    ['quote' => 'Pelayanan cepat dan ramah. Kue ulang tahun sesuai pesanan dan rasanya juara!', 'author' => 'Budi A.'],
    ['quote' => 'Selalu puas dengan Soncake. Varian kuenya banyak dan semuanya lezat.', 'author' => 'Citra D.'],
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang di Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .hero-section {
            background: url('img/hero-background.jpg') no-repeat center center/cover;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        /* Add this for the cart count bubble */
        .cart-link {
            position: relative;
            display: inline-block;
        }
        .cart-count {
            background-color: var(--primary-pink); /* Or any color you prefer */
            color: white;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 0.75em;
            position: absolute;
            top: -8px; /* Adjust as needed */
            right: -10px; /* Adjust as needed */
            min-width: 20px; /* Ensures roundness for single digits */
            text-align: center;
            line-height: 1.2;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            font-weight: 600;
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
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="katalogawal.php">Katalog</a></li>

                    <li>
                        <a href="keranjang_awal.php" id="cartLink" class="cart-link">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                            <span id="cart-item-count" class="cart-count">
                                <?php echo $cartItemCount; ?>
                            </span>
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
        <section class="hero-section">
            <div class="container">
                <h1>Rayakan Setiap Momen dengan Kue Pilihan Kami</h1>
                <p>Dari ulang tahun hingga pernikahan, Soncake sajikan kebahagiaan dalam setiap gigitan.</p>
                <a href="katalogawal.php" class="btn-primary">Lihat Koleksi Kue</a>
            </div>
        </section>

        <section class="categories-section section-padding">
            <div class="container">
                <h2>Kategori</h2>
                <div class="category-grid">
                    <?php if (empty($categories)): ?>
                        <p>Tidak ada kategori yang tersedia saat ini.</p>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <a href="katalogawal.php?category_name=<?php echo urlencode($category['name']); ?>" class="category-item">
                                <i class="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-box'); ?>"></i>
                                <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="products-section section-padding bg-light-pink">
            <div class="container">
                <h2>Pilihan Kue Unggulan</h2>
                <div class="product-grid">
                    <?php if (empty($featured_products)): ?>
                        <p>Tidak ada produk unggulan yang tersedia saat ini.</p>
                    <?php else: ?>
                        <?php foreach ($featured_products as $product): ?>
                            <div class="product-card">
                                <img src="img/<?php echo htmlspecialchars($product['image_url']); ?>"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.onerror=null;this.src='img/default.png';">
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="product-price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                                    <div class="product-rating">
                                        <i class="fas fa-star"></i> <?php echo htmlspecialchars($product['rating']); ?>
                                    </div>
                                    <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                    <a href="product_detail_awal.php?id=<?php echo $product['id']; ?>" class="btn-secondary">Lihat Detail</a>
                                    <button type="button" class="btn-primary add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">Tambah ke Keranjang</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="testimonials-section section-padding">
            <div class="container">
                <h2>Kata Pelanggan Kami</h2>
                <div class="testimonial-grid">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="testimonial-card">
                            <p class="quote">"<?php echo htmlspecialchars($testimonial['quote']); ?>"</p>
                            <p class="author">- <?php echo htmlspecialchars($testimonial['author']); ?></p>
                        </div>
                    <?php endforeach; ?>
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
        <div class="footer-bottom" style="color: #FFF !important; background-color: #5a2d1e !important; padding: 15px 0;">
            <p>&copy; <?php echo date("Y"); ?> Soncake. All rights reserved.</p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown menu logic (existing)
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

        // Add to Cart and Update Cart Count Logic
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        const cartItemCountSpan = document.getElementById('cart-item-count');

        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                const productId = this.dataset.productId;
                
                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'product_id=' + productId + '&quantity=1'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message); // Menampilkan pesan dari backend
                        // Update cart count in the header
                        if (cartItemCountSpan && data.total_cart_items !== undefined) {
                            cartItemCountSpan.textContent = data.total_cart_items;
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menambahkan produk ke keranjang.');
                });
            });
        });
    });
    </script>
</body>
</html>