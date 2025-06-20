<?php
session_start();
require_once 'config/database.php';
require_once 'includes/cart_functions.php';

$username = '';
$isLoggedIn = false;
$cart_count = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && $_SESSION['role'] === 'user') {
    $username = htmlspecialchars($_SESSION['username']);
    $isLoggedIn = true;
    $cart_count = calculateTotalCartItems($pdo, true, $_SESSION['user_id']);
} else {
    $cart_count = calculateTotalCartItems($pdo, false, null);
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
                    <li><a href="user_dashboard.php" class="active">Home</a></li>
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

    <section class="hero-section">
        <div class="container">
            <h1>Rayakan Setiap Momen dengan Kue Pilihan Kami</h1>
            <p>Dari ulang tahun hingga pernikahan, kami sajikan kebahagiaan dalam setiap gigitan.</p>
            <a href="katalog.php" class="btn-primary">Lihat Koleksi Kue</a>
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
                        <a href="katalog.php?category_name=<?php echo urlencode($category['name']); ?>" class="category-item">
                            <i class="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-box'); ?>"></i> <h3><?php echo htmlspecialchars($category['name']); ?></h3>
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
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn-secondary">Lihat Detail</a>
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
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
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

        if (cartCountSpan) {
            const initialCount = parseInt(cartCountSpan.textContent);
            updateCartCountDisplay(initialCount);
        }

        const cartLink = document.getElementById('cartLink');
        if (cartLink) {
            cartLink.addEventListener('click', function(event) {
                if (!isLoggedIn) {
                    event.preventDefault(); 
                    alert("Anda harus login terlebih dahulu untuk mengakses keranjang.");
                    window.location.href = 'login.php'; 
                }
            });
        }

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

        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault(); 

                if (!isLoggedIn) {
                    sessionStorage.setItem('intended_url', window.location.href);
                    alert('Anda harus login terlebih dahulu untuk menambahkan produk ke keranjang.');
                    window.location.href = 'login.php';
                    return; 
                }

                const productId = this.dataset.productId; 
                const quantity = 1; 

                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ 
                        product_id: productId,
                        quantity: quantity
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        updateCartCountDisplay(data.total_cart_items);
                    } else {
                        alert('Gagal menambahkan ke keranjang: ' + data.message); 
                    }
                })
                .catch(error => {
                    console.error('Ada masalah dengan operasi fetch:', error);
                    alert('Terjadi kesalahan saat menambahkan produk ke keranjang. Silakan coba lagi.');
                });
            });
        });
    });
    </script>
</body>
</html>
