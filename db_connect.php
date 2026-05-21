<?php
// api/v1/db_connect.php

// --- ADD THIS LINE ---
// This forces mysqli to throw exceptions on errors, which our catch blocks can handle.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Database Credentials ---
// Replace with your actual database details from Hostinger
define('DB_HOST', 'mysql-ipx-db');
define('DB_USER', 'root');
define('DB_PASS', 'G1uIUVxqE5LsR3LlbjZo9aKtmfUsI9AxAwa9nOiublQgn16PabiqQnN0LJboJhK3'); // Default password is empty
define('DB_NAME', 'ipx');


// --- Create a new database connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Check for connection errors ---
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error.
    // In a production environment, you might want to log this error instead of displaying it.
    die("Connection failed: " . $conn->connect_error);
}

// --- Set the character set to utf8mb4 for full Unicode support ---
$conn->set_charset("utf8mb4");

// The $conn object is now ready to be used by other scripts.
?>