<?php
/* 
 * Database Connection Configuration Template
 * 
 * IMPORTANT: 
 * 1. Copy this file to 'connectDB.php' in the root directory
 * 2. Update the values below with your actual database credentials
 * 3. Never commit 'connectDB.php' with real credentials to version control
 */

/* Database connection settings */
$servername = "localhost";          // Database host (usually "localhost")
$username = "root";                 // Your phpMyAdmin username (default: "root")
$password = "";                     // Your phpMyAdmin password (default: empty or "root")
$dbname = "rfidattendance";        // Database name

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for full Unicode support
mysqli_set_charset($conn, "utf8mb4");

?>
