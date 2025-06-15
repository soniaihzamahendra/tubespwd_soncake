<?php
session_start();
require_once 'config/database.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'cart_count' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
        $response['message'] = 'Anda harus login untuk menambahkan produk ke keranjang.';
        echo json_encode($response);
        exit();
    }

    $product_id = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? null;
    if (empty($product_id) || !is_numeric($product_id) || empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
        $response['message'] = 'ID produk atau kuantitas tidak valid.';
        echo json_encode($response);
        exit();
    }

    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    try {
        $stmt = $pdo->prepare("SELECT id, name, price, stock, image_url FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $response['message'] = 'Produk tidak ditemukan.';
            echo json_encode($response);
            exit();
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $current_cart_quantity = $_SESSION['cart'][$product_id]['quantity'] ?? 0;

        $new_total_quantity_in_cart = $current_cart_quantity + $quantity;

        if ($new_total_quantity_in_cart > $product['stock']) {
            $response['message'] = 'Penambahan gagal. Jumlah total di keranjang akan melebihi stok yang tersedia. Stok: ' . $product['stock'];
            echo json_encode($response);
            exit();
        }

        $_SESSION['cart'][$product_id] = [
            'id'        => $product['id'],
            'name'      => $product['name'],
            'price'     => $product['price'],
            'image_url' => $product['image_url'],
            'quantity'  => $new_total_quantity_in_cart,
            'stock'     => $product['stock'] 
        ];

        $response['success'] = true;
        $response['message'] = "Produk '" . htmlspecialchars($product['name']) . "' berhasil ditambahkan ke keranjang!";

        $total_cart_items = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_cart_items += $item['quantity'];
        }
        $response['cart_count'] = $total_cart_items;


    } catch (PDOException $e) {
        error_log("Database error in add_to_cart.php: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan database saat menambahkan produk.';
    }

    echo json_encode($response);
    exit();

} else {
    $response['message'] = "Aksi tidak valid.";
    echo json_encode($response); 
    exit();
}
?>