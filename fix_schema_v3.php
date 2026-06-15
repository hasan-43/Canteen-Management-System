<?php
// fix_schema_v3.php
$username = "root";
$password = "";
$db_name = "food_wave";
$host = "127.0.0.1";

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting database fix v3...\n";

// Alter columns from ENUM to VARCHAR(50)
$queries = [
    "ALTER TABLE cart_items MODIFY COLUMN kitchen VARCHAR(50) NOT NULL",
    "ALTER TABLE orders MODIFY COLUMN kitchen VARCHAR(50) NOT NULL",
    "ALTER TABLE product_reviews MODIFY COLUMN kitchen VARCHAR(50) NOT NULL"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Successfully executed: $q\n";
    } else {
        echo "Error executing: $q - " . $conn->error . "\n";
    }
}

echo "Database fix v3 complete.\n";
?>
