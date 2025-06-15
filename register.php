<?php
require_once 'config/database.php'; 

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password']; 
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    
    $role = 'user'; 

    if (empty($username) || empty($password)) {
        $message = "Username dan Password tidak boleh kosong.";
        $message_type = "error";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Username sudah terdaftar. Silakan gunakan username lain.";
                $message_type = "error";
            } else {
                $sql = "INSERT INTO users (username, password_hash, email, phone_number, role)
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                $email_to_insert = !empty($email) ? $email : NULL;
                $phone_number_to_insert = !empty($phone_number) ? $phone_number : NULL;

                $stmt->execute([$username, $password_hash, $email_to_insert, $phone_number_to_insert, $role]);

                $message = "Registrasi berhasil! Silakan login.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'for key \'username\'') !== false) {
                    $message = "Username sudah terdaftar.";
                } elseif (strpos($e->getMessage(), 'for key \'email\'') !== false) {
                    $message = "Email sudah terdaftar.";
                } elseif (strpos($e->getMessage(), 'for key \'phone_number\'') !== false) {
                    $message = "Nomor telepon sudah terdaftar.";
                } else {
                    $message = "Terjadi kesalahan duplikasi data.";
                }
            } else {
                $message = "Terjadi kesalahan saat registrasi: " . $e->getMessage();
            }
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Cake Shop</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container"> 
        <h2>Daftar Akun Baru</h2>
        <?php if ($message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="post">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email">

            <label for="phone_number">Nomor HP:</label>
            <input type="text" id="phone_number" name="phone_number">

            <button type="submit">Daftar</button>
        </form>
        <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div> 
</body>
</html>