<?php
$admin_password = 'shafee123'; // <--- REPLACE THIS with your desired plaintext password
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
echo "Your hashed password: " . $hashed_password;
?>