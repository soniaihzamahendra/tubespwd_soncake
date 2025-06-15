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
    $category_id = $_GET['id'];

    try {
        $pdo->beginTransaction();
     
        $stmt_update_products = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = :category_id");
        $stmt_update_products->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt_update_products->execute();

        $stmt_delete = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt_delete->bindParam(':id', $category_id, PDO::PARAM_INT);

        if ($stmt_delete->execute()) {
            $pdo->commit(); 
            $message = "Kategori berhasil dihapus. Produk terkait telah diatur ulang kategorinya.";
            $message_type = "success";
        } else {
            $pdo->rollBack(); 
            $message = "Gagal menghapus kategori dari database.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $pdo->rollBack(); 
        error_log("Error deleting category: " . $e->getMessage());
        $message = "Terjadi kesalahan saat menghapus kategori: " . $e->getMessage();
        $message_type = "error";
    }
} else {
    $message = "ID kategori tidak valid atau tidak diberikan.";
    $message_type = "error";
}

header("Location: admin_categories.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>