<?php
// File: includes/cart_functions.php
// Fungsi ini akan digunakan di beberapa file untuk menghitung total item di keranjang

/**
 * Menghitung total jumlah item di keranjang.
 * Untuk pengguna yang login, data diambil dari tabel 'carts' di database.
 * Untuk pengguna tamu, data diambil dari sesi PHP ($_SESSION['guest_cart']).
 *
 * @param PDO $pdo Objek koneksi PDO database.
 * @param bool $isLoggedIn Status login pengguna (true jika login, false jika tamu).
 * @param int|null $userId ID pengguna jika sudah login (null jika tamu).
 * @return int Total jumlah item di keranjang.
 */
function calculateTotalCartItems($pdo, $isLoggedIn, $userId) {
    $total_items = 0;
    if ($isLoggedIn) {
        // Jika pengguna login, ambil dari database
        try {
            $stmt = $pdo->prepare("SELECT SUM(quantity) AS total FROM carts WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_items = (int)($result['total'] ?? 0); // Pastikan hasilnya integer, default 0
        } catch (PDOException $e) {
            // Log error jika ada masalah database
            error_log("Error calculating total cart items (DB): " . $e->getMessage());
            // Tetap kembalikan 0 agar aplikasi tidak crash dan bubble tetap 0
        }
    } else {
        // Jika pengguna tamu, ambil dari sesi
        if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $item) {
                $total_items += (int)($item['quantity'] ?? 0); // Pastikan hasilnya integer, default 0
            }
        }
    }
    return $total_items;
}

?>