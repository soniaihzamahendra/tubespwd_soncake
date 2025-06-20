<?php
session_start();
// Pastikan path ini benar ke file koneksi database Anda
require_once 'config/database.php';
// Include fungsi-fungsi keranjang yang baru
require_once 'includes/cart_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'cart_count' => 0, 'message' => ''];

$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Gunakan fungsi yang sudah terpusat untuk menghitung total item keranjang
$totalCartItems = calculateTotalCartItems($pdo, $isLoggedIn, $userId);

$response['success'] = true;
$response['cart_count'] = $totalCartItems;

echo json_encode($response);
exit();
?>
