<?php
require_once 'config/database.php';

$email    = 'superadmin@pawpal.com';
$password = 'password';

$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, password, role FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

if (!$user) {
    echo "❌ No user found with that email.";
} elseif (!password_verify($password, $user['password'])) {
    echo "❌ Password is WRONG. Hash in DB: " . $user['password'];
} else {
    echo "✅ Login works! Role: " . $user['role'];
}
?>