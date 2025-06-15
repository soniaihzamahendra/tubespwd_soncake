<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$productId = $data['product_id'] ?? null;
$quantity = $data['quantity'] ?? null;
$action = $data['action'] ?? null;

if ($productId === null || $action === null) {
    $response['message'] = 'Invalid request parameters. Product ID or action missing.';
    echo json_encode($response);
    exit;
}

if ($action === 'remove') {
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
        $response['success'] = true;
        $response['message'] = 'Produk berhasil dihapus dari keranjang.';
    } else {
        $response['message'] = 'Produk tidak ditemukan di keranjang.';
    }
} elseif ($action === 'update') {
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false ? (int)$quantity : 1;

    if (isset($_SESSION['cart'][$productId])) {
        try {
            $stmt = $pdo->prepare("SELECT stock, price FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $productDb = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($productDb) {
                $stock = (int)$productDb['stock'];
                $priceFromDb = (float)$productDb['price'];

                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$productId]);
                    $response['success'] = true;
                    $response['message'] = 'Produk berhasil dihapus dari keranjang karena kuantitas 0.';
                    $response['action_performed'] = 'remove';
                } elseif ($quantity > $stock) {
                    $_SESSION['cart'][$productId]['quantity'] = $stock;
                    $_SESSION['cart'][$productId]['price'] = $priceFromDb;
                    $response['success'] = true;
                    $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia. Kuantitas disesuaikan dengan stok (' . $stock . ').';
                    $response['message_type'] = 'warning';
                    $response['new_quantity'] = $stock;
                    $response['subtotal'] = $priceFromDb * $stock;
                } else {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                    $_SESSION['cart'][$productId]['price'] = $priceFromDb;
                    $response['success'] = true;
                    $response['message'] = 'Kuantitas berhasil diperbarui.';
                    $response['new_quantity'] = $quantity;
                    $response['subtotal'] = $priceFromDb * $quantity;
                }
            } else {
                unset($_SESSION['cart'][$productId]);
                $response['success'] = false;
                $response['message'] = 'Produk tidak ditemukan di database. Dihapus dari keranjang.';
                $response['action_performed'] = 'remove';
            }
        } catch (PDOException $e) {
            error_log("Database error in update_cart.php: " . $e->getMessage());
            $response['message'] = 'Terjadi kesalahan database saat memperbarui keranjang.';
        }
    } else {
        $response['message'] = 'Produk tidak ditemukan di keranjang.';
    }
} else {
    $response['message'] = 'Aksi tidak valid.';
}

$total_price = 0;
foreach ($_SESSION['cart'] as $item) {
    $price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;
    $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
    $total_price += $price * $quantity;
}
$response['cart_total'] = $total_price;

echo json_encode($response);
?>