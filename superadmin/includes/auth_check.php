<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../../auth/login.php');
    exit;
}

function log_activity($conn, $user_id, $full_name, $role, $action, $description = '') {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = mysqli_prepare($conn,
        "INSERT INTO activity_logs (user_id, full_name, role, action, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'isssss', $user_id, $full_name, $role, $action, $description, $ip);
    mysqli_stmt_execute($stmt);
}
