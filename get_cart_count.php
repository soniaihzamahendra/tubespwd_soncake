<?php
session_start();
require_once 'config/database.php';
require_once 'includes/cart_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'cart_count' => 0, 'message' => ''];

$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$totalCartItems = calculateTotalCartItems($pdo, $isLoggedIn, $userId);

$response['success'] = true;
$response['cart_count'] = $totalCartItems;

echo json_encode($response);
exit();
?>
