<?php
// db.php - reusable database connection

// Database credentials
$username = "root";      // default MySQL username in XAMPP
$password = "";          // default password in XAMPP is empty
$db_name = "food_wave";
$host = "localhost";

// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set charset to utf8
$conn->set_charset("utf8");

// Now $conn can be used in your other PHP files
?>
