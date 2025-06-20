<?php
function calculateTotalCartItems($pdo, $isLoggedIn, $userId) {
    $total_items = 0;
    if ($isLoggedIn) {
        try {
            $stmt = $pdo->prepare("SELECT SUM(quantity) AS total FROM carts WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_items = (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error calculating total cart items (DB): " . $e->getMessage());
        }
    } else {
        if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $item) {
                $total_items += (int)($item['quantity'] ?? 0);
            }
        }
    }
    return $total_items;
}

?>
