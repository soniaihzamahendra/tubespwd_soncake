<?php
session_start();
// Pastikan path ini benar ke file koneksi database Anda
require_once 'config/database.php';
// Include fungsi-fungsi keranjang yang baru
require_once 'includes/cart_functions.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan.',
    'total_cart_items' => 0 // Inisialisasi total item keranjang
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gunakan filter_input untuk keamanan
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    // Validasi input
    if (!$productId || $quantity <= 0) {
        $response['message'] = 'ID produk atau kuantitas tidak valid.';
        echo json_encode($response);
        exit;
    }

    // Tentukan apakah pengguna login atau tamu
    $isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
    $userId = $_SESSION['user_id'] ?? null; // user_id hanya akan ada jika login

    try {
        // Ambil detail produk dari database untuk memeriksa stok dan harga
        $stmt = $pdo->prepare("SELECT name, price, stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $response['message'] = 'Produk tidak ditemukan.';
            echo json_encode($response);
            exit;
        }

        $productName = $product['name'];
        $productPrice = $product['price'];
        $productStock = $product['stock'];

        if ($isLoggedIn) {
            // Pengguna yang sudah login: Perbarui/Masukkan ke database
            $stmt = $pdo->prepare("SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {
                // Jika produk sudah ada di keranjang, perbarui kuantitas
                $newQuantity = $existingItem['quantity'] + $quantity;
                if ($newQuantity > $productStock) {
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia (' . $productStock . ').';
                    $response['success'] = false; // Gagal menambahkan penuh
                } else {
                    $stmt = $pdo->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$newQuantity, $userId, $productId]);
                    $response['success'] = true;
                    $response['message'] = 'Kuantitas "' . htmlspecialchars($productName) . '" di keranjang diperbarui.';
                }
            } else {
                // Jika produk belum ada, masukkan sebagai item baru
                if ($quantity > $productStock) {
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia (' . $productStock . ').';
                    $response['success'] = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $productId, $quantity]);
                    $response['success'] = true;
                    $response['message'] = '"' . htmlspecialchars($productName) . '" berhasil ditambahkan ke keranjang.';
                }
            }
        } else {
            // Pengguna tamu: Perbarui/Masukkan ke sesi
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }

            if (isset($_SESSION['guest_cart'][$productId])) {
                // Jika produk sudah ada di sesi, perbarui kuantitas
                $newQuantity = $_SESSION['guest_cart'][$productId]['quantity'] + $quantity;
                if ($newQuantity > $productStock) {
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia (' . $productStock . ').';
                    $response['success'] = false;
                } else {
                    $_SESSION['guest_cart'][$productId]['quantity'] = $newQuantity;
                    $response['success'] = true;
                    $response['message'] = 'Kuantitas "' . htmlspecialchars($productName) . '" di keranjang diperbarui.';
                }
            } else {
                // Jika produk belum ada di sesi, tambahkan sebagai item baru
                if ($quantity > $productStock) {
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia (' . $productStock . ').';
                    $response['success'] = false;
                } else {
                    $_SESSION['guest_cart'][$productId] = [
                        'id' => $productId,
                        'name' => $productName,
                        'price' => $productPrice,
                        'quantity' => $quantity,
                        'stock' => $productStock // Simpan stok untuk pemeriksaan sisi klien jika diperlukan
                    ];
                    $response['success'] = true;
                    $response['message'] = '"' . htmlspecialchars($productName) . '" berhasil ditambahkan ke keranjang.';
                }
            }
        }
    } catch (PDOException $e) {
        // Tangani kesalahan database
        error_log("Database error in add_to_cart.php: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan database saat menambahkan produk.';
        $response['success'] = false;
    }
} else {
    $response['message'] = 'Metode request tidak valid.';
}

// Selalu hitung dan kembalikan total item keranjang yang diperbarui
$response['total_cart_items'] = calculateTotalCartItems($pdo, $isLoggedIn, $userId);

echo json_encode($response);
?>
