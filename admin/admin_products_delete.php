<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

$message = '';
$message_type = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];

    try {
        $stmt_img = $pdo->prepare("SELECT image_url FROM products WHERE id = :id");
        $stmt_img->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt_img->execute();
        $product_to_delete = $stmt_img->fetch(PDO::FETCH_ASSOC);

        if ($product_to_delete) {
            $image_to_delete = $product_to_delete['image_url'];
            $upload_dir = '../img/'; 

            $stmt_delete = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt_delete->bindParam(':id', $product_id, PDO::PARAM_INT);

            if ($stmt_delete->execute()) {
                if ($image_to_delete && $image_to_delete !== 'default.png' && file_exists($upload_dir . $image_to_delete)) {
                    unlink($upload_dir . $image_to_delete);
                }
                $message = "Produk berhasil dihapus.";
                $message_type = "success";
            } else {
                $message = "Gagal menghapus produk dari database.";
                $message_type = "error";
            }
        } else {
            $message = "Produk tidak ditemukan.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        error_log("Error deleting product: " . $e->getMessage());
        $message = "Terjadi kesalahan saat menghapus produk: " . $e->getMessage();
        $message_type = "error";
    }
} else {
    $message = "ID produk tidak valid atau tidak diberikan.";
    $message_type = "error";
}

header("Location: admin_products.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>