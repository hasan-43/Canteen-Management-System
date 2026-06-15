<?php
// fix_phone_number_column.php
$username = "root";
$password = "";
$db_name = "food_wave";
$host = "127.0.0.1";

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Adding phone_number column to orders table...\n";

$check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'phone_number'");
if ($check_column->num_rows == 0) {
    $sql = "ALTER TABLE orders ADD COLUMN phone_number VARCHAR(20) NOT NULL AFTER kitchen";
    if ($conn->query($sql)) {
        echo "Successfully added phone_number column.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "phone_number column already exists.\n";
}

echo "Process complete.\n";
?>
