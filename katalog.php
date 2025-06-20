<?php
session_start();
require_once 'config/database.php';
require_once 'includes/cart_functions.php'; // Pastikan ini di-include

$username = '';
$isLoggedIn = false;
$userId = null; // Inisialisasi userId

if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && $_SESSION['role'] === 'user') {
    $username = htmlspecialchars($_SESSION['username']);
    $isLoggedIn = true;
    $userId = $_SESSION['user_id'];
}

// Gunakan fungsi terpusat untuk menghitung jumlah keranjang
// Ini akan mengambil dari database jika login, dari sesi jika tamu.
$cart_count = calculateTotalCartItems($pdo, $isLoggedIn, $userId);


$sql_products = "SELECT id, name, image_url, price, description, rating, stock FROM products"; // Tambahkan stock
$params = [];
$where_clauses = [];

if (isset($_GET['category_name']) && !empty($_GET['category_name'])) {
    $category_name = urldecode($_GET['category_name']);
    try {
        $stmt_category = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt_category->execute([$category_name]);
        $category_info = $stmt_category->fetch(PDO::FETCH_ASSOC);

        if ($category_info) {
            $where_clauses[] = "category_id = ?";
            $params[] = $category_info['id'];
        } else {
            // Kategori tidak ditemukan, biarkan $where_clauses kosong agar menampilkan semua produk jika kategori tidak valid
        }
    } catch (PDOException $e) {
        error_log("Error fetching category ID: " . $e->getMessage());
        // Handle error, e.g., display a message
    }
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = '%' . $_GET['search'] . '%';
    $where_clauses[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = $search_query;
    $params[] = $search_query;
}

if (!empty($where_clauses)) {
    $sql_products .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_products .= " ORDER BY name ASC";

try {
    $stmt_products = $pdo->prepare($sql_products);
    $stmt_products->execute($params);
    $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
    error_log("Error fetching products for catalog: " . $e->getMessage());
    echo "<p class='error-message'>Maaf, terjadi kesalahan saat memuat produk.</p>";
}

try {
    $stmt_all_categories = $pdo->query("SELECT name FROM categories ORDER BY name ASC");
    $all_categories = $stmt_all_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_categories = [];
    error_log("Error fetching all categories: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Produk - Soncake</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="public/css/user.css">
    <style>
        .catalog-container {
            display: flex;
            gap: 30px;
            padding-top: 30px;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background-color: var(--cream-white);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar h3 {
            color: var(--secondary-brown);
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 10px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            margin-bottom: 10px;
        }

        .sidebar ul li a {
            display: block;
            padding: 8px 12px;
            border-radius: 5px;
            color: var(--dark-grey-text);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active-filter {
            background-color: var(--primary-pink);
            color: #fff;
        }

        .catalog-products {
            flex-grow: 1;
        }

        .product-grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .no-products-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: var(--light-grey-text);
        }

        .search-bar {
            margin-bottom: 30px;
            display: flex;
        }
        .search-bar input[type="text"] {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px 0 0 8px;
            font-size: 1em;
        }
        .search-bar button {
            background-color: var(--primary-pink);
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .search-bar button:hover {
            background-color: var(--hover-pink);
        }

        @media (max-width: 992px) {
            .catalog-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: static;
                margin-bottom: 30px;
            }
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
                    <li><a href="user_dashboard.php">Home</a></li>
                    <li><a href="katalog.php" class="active">Katalog</a></li>

                    <li>
                        <a href="keranjang.php" id="cartLink">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                            <span id="cart-count" class="cart-badge"><?php echo $cart_count; ?></span>
                        </a>
                    </li>

                    <?php if ($isLoggedIn):  ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?> <i class="fas fa-caret-down"></i></a>
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
        <section class="catalog-section section-padding bg-light-pink">
            <div class="container">
                <h2>Koleksi Kue Kami</h2>

                <div class="catalog-container">
                    <aside class="sidebar">
                        <h3>Kategori</h3>
                        <ul>
                            <li>
                                <a href="katalog.php" class="<?php echo (!isset($_GET['category_name']) || empty($_GET['category_name'])) ? 'active-filter' : ''; ?>">
                                    Semua Produk
                                </a>
                            </li>
                            <?php foreach ($all_categories as $cat): ?>
                                <li>
                                    <a href="katalog.php?category_name=<?php echo urlencode($cat['name']); ?>"
                                       class="<?php echo (isset($_GET['category_name']) && urldecode($_GET['category_name']) == $cat['name']) ? 'active-filter' : ''; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>

                    <div class="catalog-products">
                        <form class="search-bar" action="katalog.php" method="GET">
                            <input type="text" name="search" placeholder="Cari produk..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </form>

                        <?php if (empty($products)): ?>
                            <p class="no-products-message">Maaf, tidak ada produk yang ditemukan untuk kriteria ini.</p>
                        <?php else: ?>
                            <div class="product-grid">
                                <?php foreach ($products as $product): ?>
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
                                            <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?></p>
                                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn-secondary">Lihat Detail</a>
                                            <button type="button" class="btn-primary add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>" <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-cart-plus"></i> <?php echo ($product['stock'] <= 0) ? 'Stok Habis' : 'Tambah ke Keranjang'; ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

        const cartLink = document.getElementById('cartLink');
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

        // Inisialisasi bubble keranjang saat halaman dimuat
        if (cartCountSpan) {
            const initialCount = parseInt(cartCountSpan.textContent);
            updateCartCountDisplay(initialCount);
        }

        if (cartLink) {
            cartLink.addEventListener('click', function(event) {
                if (!isLoggedIn) {
                    event.preventDefault();
                    // Menggunakan modal kustom atau pop-up daripada alert()
                    alert("Anda harus login terlebih dahulu untuk mengakses keranjang.");
                    sessionStorage.setItem('intended_url', window.location.href); // Simpan URL saat ini
                    window.location.href = 'login.php'; // Redirect ke halaman login
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

        // Event listener untuk tombol "Tambah ke Keranjang" di halaman katalog
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
                const quantity = 1; // Default quantity 1

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
                        updateCartCountDisplay(data.total_cart_items); // Update bubble
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
