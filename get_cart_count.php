<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'cart_count' => 0, 'message' => ''];

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $total_cart_items = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_cart_items += $item['quantity'];
        }
        $response['success'] = true;
        $response['cart_count'] = $total_cart_items;
    } else {
        $response['success'] = true;
        $response['cart_count'] = 0;
    }
} else {
    $response['success'] = true;
    $response['cart_count'] = 0;
}

echo json_encode($response);
exit();
?>