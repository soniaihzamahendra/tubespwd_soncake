<?php
session_start();
require_once 'config/database.php';
// require_once 'includes/cart_functions.php'; // Mungkin tidak diperlukan langsung di sini karena kita akan query langsung

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

$user_id = $_SESSION['user_id'];

// --- Perubahan Penting: Mengambil item keranjang dari DATABASE, bukan sesi ---
$cart_items = [];
try {
    $stmt_cart = $pdo->prepare("
        SELECT
            c.product_id,
            p.name,
            p.price,
            c.quantity,
            p.stock
        FROM carts c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt_cart->execute([$user_id]);
    $db_cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

    // Reformat array untuk kemudahan akses (product_id sebagai key)
    foreach ($db_cart_items as $item) {
        $cart_items[$item['product_id']] = [
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'stock' => $item['stock'] // Penting untuk validasi stok
        ];
    }

} catch (PDOException $e) {
    error_log("Error fetching cart items from database in process_checkout.php: " . $e->getMessage());
    $_SESSION['message'] = "Terjadi kesalahan saat memuat keranjang Anda dari database. Silakan coba lagi.";
    $_SESSION['message_type'] = "error";
    header("Location: keranjang.php");
    exit();
}

// Cek apakah keranjang kosong SETELAH mencoba memuat dari database
if (empty($cart_items)) {
    $_SESSION['message'] = "Keranjang Anda kosong. Tidak dapat memproses pesanan.";
    $_SESSION['message_type'] = "warning";
    header("Location: katalog.php"); // Redirect ke katalog jika keranjang kosong
    exit();
}
// --- Akhir Perubahan Penting ---


$total_amount = 0;

// Mengambil data dari POST
$nama_lengkap = trim($_POST['address_line1'] ?? ''); // Menggunakan address_line1 sebagai nama lengkap jika form tidak memiliki field nama_lengkap terpisah
$email = ''; // Email tidak di-submit dari form checkout yang diberikan, perlu ditambahkan jika ada
$telepon = trim($_POST['phone_number'] ?? '');
$alamat_pengiriman = trim($_POST['address_line1'] . ', ' . $_POST['city'] . ', ' . $_POST['postal_code'] ?? '');
$catatan_pesanan = trim($_POST['notes'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');

// Ambil email user dari sesi atau database jika tidak di-submit di form
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
} else {
    // Fallback: ambil email dari database jika tidak ada di sesi
    try {
        $stmt_user_email = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt_user_email->execute([$user_id]);
        $user_email_data = $stmt_user_email->fetch(PDO::FETCH_ASSOC);
        if ($user_email_data) {
            $email = $user_email_data['email'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user email in process_checkout.php: " . $e->getMessage());
    }
}


$errors = [];
// Validasi input
if (empty($nama_lengkap)) { $errors[] = "Nama lengkap (dari alamat) wajib diisi."; }
if (empty($email)) { $errors[] = "Email wajib diisi."; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Format email tidak valid."; }
if (empty($telepon)) { $errors[] = "Nomor telepon wajib diisi."; }
if (!preg_match('/^[0-9\-\(\)\s]+$/', $telepon)) { $errors[] = "Nomor telepon hanya boleh berisi angka atau format telepon umum."; } // Perluas regex untuk format telepon
if (empty($alamat_pengiriman)) { $errors[] = "Alamat pengiriman lengkap wajib diisi."; } // Sudah digabung di atas
if (empty($payment_method)) { $errors[] = "Metode pembayaran wajib dipilih."; }
// Validasi metode pembayaran yang diizinkan (Bank Transfer dan COD)
if (!in_array($payment_method, ['Bank Transfer', 'COD'])) {
    $errors[] = "Metode pembayaran tidak valid.";
}


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
        $stock_available = isset($item['stock']) && is_numeric($item['stock']) ? (int)$item['stock'] : 0; // Menggunakan stock dari item yang sudah diambil

        if ($quantity_in_cart <= 0) {
            $pdo->rollBack();
            $_SESSION['message'] = "Kuantitas untuk '" . $product_name_in_cart . "' tidak valid. Harap periksa keranjang Anda.";
            $_SESSION['message_type'] = "error";
            header("Location: keranjang.php");
            exit();
        }

        // Cek stok langsung dari data yang sudah diambil dari DB (sudah ada lock FOR UPDATE jika diperlukan)
        if ($stock_available < $quantity_in_cart) {
            $pdo->rollBack();
            $_SESSION['message'] = "Maaf, stok untuk '" . $product_name_in_cart . "' hanya tersedia " . $stock_available . " unit. Harap sesuaikan kuantitas di keranjang Anda.";
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
        $nama_lengkap, // Menggunakan nama_lengkap dari form
        $email,
        $telepon,
        $alamat_pengiriman, // Sudah digabung di atas
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

    // --- Perubahan Penting: Menghapus item keranjang dari DATABASE ---
    $stmt_clear_cart = $pdo->prepare("DELETE FROM carts WHERE user_id = ?");
    $stmt_clear_cart->execute([$user_id]);
    // --- Akhir Perubahan Penting ---

    $pdo->commit();

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
