<?php
session_start();
require_once 'config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Metode permintaan tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: checkout.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Anda harus login untuk menyelesaikan pesanan.";
    $_SESSION['message_type'] = "error";
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['message'] = "Keranjang Anda kosong. Tidak dapat memproses pesanan.";
    $_SESSION['message_type'] = "warning";
    header("Location: keranjang.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_items = $_SESSION['cart'];
$total_amount = 0;

$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$email = trim($_POST['email'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$alamat_pengiriman = trim($_POST['alamat_pengiriman'] ?? '');
$catatan_pesanan = trim($_POST['catatan_pesanan'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');

$errors = [];
if (empty($nama_lengkap)) { $errors[] = "Nama lengkap wajib diisi."; }
if (empty($email)) { $errors[] = "Email wajib diisi."; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Format email tidak valid."; }
if (empty($telepon)) { $errors[] = "Nomor telepon wajib diisi."; }
if (!preg_match('/^[0-9]+$/', $telepon)) { $errors[] = "Nomor telepon hanya boleh berisi angka."; }
if (empty($alamat_pengiriman)) { $errors[] = "Alamat pengiriman wajib diisi."; }
if (empty($payment_method)) { $errors[] = "Metode pembayaran wajib dipilih."; }

if (!empty($errors)) {
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "error";
    header("Location: checkout.php");
    exit();
}

$pdo->beginTransaction();

try {
    foreach ($cart_items as $product_id => $item) {
        $product_name_in_cart = htmlspecialchars($item['name'] ?? 'Produk Tak Dikenal');
        $quantity_in_cart = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
        $price_in_cart = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;

        if ($quantity_in_cart <= 0) {
            $pdo->rollBack();
            $_SESSION['message'] = "Kuantitas untuk '" . $product_name_in_cart . "' tidak valid. Harap periksa keranjang Anda.";
            $_SESSION['message_type'] = "error";
            header("Location: keranjang.php");
            exit();
        }

        $stmt_stock = $pdo->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
        $stmt_stock->execute([$product_id]);
        $product_info = $stmt_stock->fetch(PDO::FETCH_ASSOC);

        if (!$product_info) {
            $pdo->rollBack();
            $_SESSION['message'] = "Maaf, produk '" . $product_name_in_cart . "' tidak ditemukan di katalog dan telah dihapus dari keranjang Anda.";
            $_SESSION['message_type'] = "error";
            header("Location: keranjang.php");
            exit();
        } elseif ($product_info['stock'] < $quantity_in_cart) {
            $pdo->rollBack();
            $_SESSION['message'] = "Maaf, stok untuk '" . $product_name_in_cart . "' hanya tersedia " . $product_info['stock'] . " unit. Harap sesuaikan kuantitas di keranjang Anda.";
            $_SESSION['message_type'] = "error";
            header("Location: keranjang.php");
            exit();
        }
        $total_amount += $price_in_cart * $quantity_in_cart;
    }

    if ($total_amount <= 0) {
        $pdo->rollBack();
        $_SESSION['message'] = "Total pembayaran tidak valid. Keranjang mungkin kosong atau harga produk tidak valid.";
        $_SESSION['message_type'] = "error";
        header("Location: keranjang.php");
        exit();
    }

    $order_status = ($payment_method == 'COD') ? 'Pending' : 'Waiting for Payment';

    $stmt_order = $pdo->prepare("INSERT INTO orders (user_id, total_amount, order_date, status, shipping_name, shipping_email, shipping_phone, shipping_address, notes, payment_method)
                                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
    $stmt_order->execute([
        $user_id,
        $total_amount,
        $order_status,
        $nama_lengkap,
        $email,
        $telepon,
        $alamat_pengiriman,
        $catatan_pesanan,
        $payment_method
    ]);

    $order_id = $pdo->lastInsertId();

    foreach ($cart_items as $product_id => $item) {
        $quantity_to_deduct = isset($item['quantity']) && is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
        $price_at_purchase = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : 0;

        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase)
                                     VALUES (?, ?, ?, ?)");
        $stmt_item->execute([
            $order_id,
            $product_id,
            $quantity_to_deduct,
            $price_at_purchase
        ]);

        $stmt_stock_update = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt_stock_update->execute([$quantity_to_deduct, $product_id]);
    }

    $pdo->commit();

    unset($_SESSION['cart']);

    $_SESSION['message'] = "Pesanan Anda dengan ID #" . $order_id . " berhasil ditempatkan! Cek detail di halaman Riwayat Pesanan.";
    $_SESSION['message_type'] = "success";
    header("Location: order_confirmation.php?order_id=" . $order_id);
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Checkout PDO error: " . $e->getMessage());
    $_SESSION['message'] = "Terjadi kesalahan database saat memproses pesanan Anda. Silakan coba lagi. Debug: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: checkout.php");
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("General checkout error: " . $e->getMessage());
    $_SESSION['message'] = "Terjadi kesalahan yang tidak terduga saat memproses pesanan. Silakan coba lagi. Debug: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: checkout.php");
    exit();
}
?>