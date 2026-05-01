<?php
// ─────────────────────────────────────────
// PawPal — Database Configuration
// ─────────────────────────────────────────

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // default XAMPP username
define('DB_PASS', '');            // default XAMPP password (empty)
define('DB_NAME', 'pawpal_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#c62828;">
            <strong>Database Connection Failed:</strong> ' . mysqli_connect_error() . '
         </div>');
}

mysqli_set_charset($conn, 'utf8mb4');
?>
