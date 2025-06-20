<?php
session_start();
require_once 'config/database.php';
// Include fungsi-fungsi keranjang yang baru
require_once 'includes/cart_functions.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'cart_total' => 0,
    'new_quantity' => 0,
    'subtotal' => 0,
    'current_quantity' => 0,
    'cart_item_count' => 0 // Total item di keranjang untuk bubble
];

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$productId = $data['product_id'] ?? null;
$quantity = $data['quantity'] ?? null;
$action = $data['action'] ?? null;

if ($productId === null || $action === null) {
    $response['message'] = 'Parameter request tidak valid. ID Produk atau aksi hilang.';
    echo json_encode($response);
    exit;
}

$productId = (int)$productId;
// Kuantitas bisa kosong jika aksi 'remove', jadi hanya konversi jika bukan null
if ($quantity !== null) {
    $quantity = (int)$quantity;
}

$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
$userId = $is_logged_in ? $_SESSION['user_id'] : null;

try {
    // 1. Dapatkan stok produk dan harga dari database
    $stmt = $pdo->prepare("SELECT stock, price, name, image_url FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $productDb = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$productDb) {
        $response['message'] = 'Produk tidak ditemukan di database.';
        // Jika produk tidak ditemukan, hapus dari keranjang (jika ada)
        if ($is_logged_in) {
            $stmt_delete_db = $pdo->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
            $stmt_delete_db->execute([$userId, $productId]);
        } else {
            unset($_SESSION['guest_cart'][$productId]);
        }
        $response['success'] = false;
        $response['action_performed'] = 'remove'; // Informasikan bahwa item dihapus
        echo json_encode($response);
        exit;
    }

    $stock = (int)$productDb['stock'];
    $priceFromDb = (float)$productDb['price'];
    $productName = $productDb['name'];
    $imageUrl = $productDb['image_url'];

    // Tentukan kuantitas saat ini di keranjang
    $current_cart_quantity = 0;
    if ($is_logged_in) {
        $stmt_current_qty = $pdo->prepare("SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?");
        $stmt_current_qty->execute([$userId, $productId]);
        $current_cart_quantity = $stmt_current_qty->fetchColumn() ?: 0;
    } else {
        $current_cart_quantity = $_SESSION['guest_cart'][$productId]['quantity'] ?? 0;
    }
    $response['current_quantity'] = $current_cart_quantity;

    // --- Tangani Aksi ---
    if ($action === 'remove') {
        if ($is_logged_in) {
            $stmt_delete = $pdo->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
            $stmt_delete->execute([$userId, $productId]);
        } else {
            unset($_SESSION['guest_cart'][$productId]);
        }
        $response['success'] = true;
        $response['message'] = 'Produk "' . htmlspecialchars($productName) . '" berhasil dihapus dari keranjang.';
        $response['action_performed'] = 'remove';
    } elseif ($action === 'update') {
        if ($quantity < 0) {
            $quantity = 1; // Kuantitas tidak boleh negatif
            $response['message'] = 'Kuantitas tidak valid. Disetel ke 1.';
            $response['message_type'] = 'error';
        }

        if ($quantity > $stock) {
            $quantity = $stock; // Kuantitas tidak boleh melebihi stok
            $response['message'] = 'Jumlah yang diminta melebihi stok yang tersedia. Kuantitas disesuaikan dengan stok (' . $stock . ').';
            $response['message_type'] = 'warning';
        }

        if ($is_logged_in) {
            if ($current_cart_quantity > 0) {
                // Perbarui kuantitas jika produk sudah ada
                $stmt_update = $pdo->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt_update->execute([$quantity, $userId, $productId]);
            } else {
                // Jika produk tidak ada, masukkan sebagai item baru (mungkin terjadi jika halaman direfresh setelah dihapus secara manual)
                $stmt_insert = $pdo->prepare("INSERT INTO carts (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                $stmt_insert->execute([$userId, $productId, $quantity]);
            }
        } else {
            // Perbarui sesi keranjang
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }
            $_SESSION['guest_cart'][$productId] = [
                'id' => $productId,
                'name' => $productName,
                'price' => $priceFromDb,
                'image_url' => $imageUrl,
                'quantity' => $quantity,
                'stock' => $stock
            ];
        }
        $response['success'] = true;
        // Jika tidak ada pesan error/warning sebelumnya, set pesan sukses
        $response['message'] = $response['message'] ?: 'Kuantitas berhasil diperbarui.';
        $response['new_quantity'] = $quantity;
        $response['subtotal'] = $priceFromDb * $quantity;
    } else {
        $response['message'] = 'Aksi tidak valid.';
        echo json_encode($response);
        exit();
    }

    // --- Hitung Ulang Total Harga Keranjang dan Jumlah Item ---
    $total_cart_price = 0;
    $total_cart_items_count = 0;

    if ($is_logged_in) {
        // Ambil total harga dan total item dari database
        $stmt_total = $pdo->prepare("SELECT SUM(c.quantity * p.price) AS total_price, SUM(c.quantity) AS total_items
                                     FROM carts c
                                     JOIN products p ON c.product_id = p.id
                                     WHERE c.user_id = ?");
        $stmt_total->execute([$userId]);
        $totals = $stmt_total->fetch(PDO::FETCH_ASSOC);
        $total_cart_price = (float)($totals['total_price'] ?? 0);
        $total_cart_items_count = (int)($totals['total_items'] ?? 0);
    } else {
        // Ambil total harga dan total item dari sesi
        if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $item) {
                $total_cart_price += (float)$item['price'] * (int)$item['quantity'];
                $total_cart_items_count += (int)$item['quantity'];
            }
        }
    }

    $response['cart_total'] = $total_cart_price;
    $response['cart_item_count'] = $total_cart_items_count; // Kirim jumlah item ke frontend

} catch (PDOException $e) {
    // Tangani kesalahan database
    error_log("Database error in update_cart.php: " . $e->getMessage());
    $response['message'] = 'Terjadi kesalahan database saat memproses keranjang.';
    $response['success'] = false;
}

echo json_encode($response);
?>
