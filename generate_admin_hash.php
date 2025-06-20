<?php
$admin_password_baru = "admin123"; 

$hashed_password = password_hash($admin_password_baru, PASSWORD_DEFAULT);
echo "Salin hash ini untuk admin Anda: <br>";
echo "<strong>" . $hashed_password . "</strong>";
?>