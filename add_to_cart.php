<?php
session_start();
require_once 'config/database.php';
require_once 'includes/cart_functions.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan.',
    'total_cart_items' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if (!$productId || $quantity <= 0) {
        $response['message'] = 'ID produk atau kuantitas tidak valid.';
        echo json_encode($response);
        exit;
    }

    $isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
    $userId = $_SESSION['user_id'] ?? null;

    try {
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
            $stmt = $pdo->prepare("SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {
                $newQuantity = $existingItem['quantity'] + $quantity;
                if ($newQuantity > $productStock) {
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia (' . $productStock . ').';
                    $response['success'] = false;
                } else {
                    $stmt = $pdo->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$newQuantity, $userId, $productId]);
                    $response['success'] = true;
                    $response['message'] = 'Kuantitas "' . htmlspecialchars($productName) . '" di keranjang diperbarui.';
                }
            } else {
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
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }

            if (isset($_SESSION['guest_cart'][$productId])) {
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
                if ($quantity > $productStock) {
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia (' . $productStock . ').';
                    $response['success'] = false;
                } else {
                    $_SESSION['guest_cart'][$productId] = [
                        'id' => $productId,
                        'name' => $productName,
                        'price' => $productPrice,
                        'quantity' => $quantity,
                        'stock' => $productStock
                    ];
                    $response['success'] = true;
                    $response['message'] = '"' . htmlspecialchars($productName) . '" berhasil ditambahkan ke keranjang.';
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in add_to_cart.php: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan database saat menambahkan produk.';
        $response['success'] = false;
    }
} else {
    $response['message'] = 'Metode request tidak valid.';
}

$response['total_cart_items'] = calculateTotalCartItems($pdo, $isLoggedIn, $userId);

echo json_encode($response);
?>
