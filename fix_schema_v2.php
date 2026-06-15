<?php
// fix_schema_v2.php
$username = "root";
$password = "";
$db_name = "food_wave";
$host = "127.0.0.1";

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting database fix v2...\n";

// 1. Add rider_id to chat_messages if missing
$check_rider_id = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'rider_id'");
if ($check_rider_id->num_rows == 0) {
    echo "Adding rider_id to chat_messages...\n";
    $sql = "ALTER TABLE chat_messages ADD COLUMN rider_id INT NULL AFTER kitchen, ADD INDEX (rider_id)";
    if ($conn->query($sql)) {
        echo "Successfully added rider_id.\n";
    } else {
        echo "Error adding rider_id: " . $conn->error . "\n";
    }
}

// 2. Fix delivery_man table to match IDs from delivery table
echo "Correcting delivery_man IDs...\n";
$check_delivery = $conn->query("SHOW TABLES LIKE 'delivery'");
if ($check_delivery->num_rows > 0) {
    // Empty the table and re-migrate with explicit IDs
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE delivery_man");
    $sql_migrate = "INSERT INTO delivery_man (id, username, fullname, email, password_hash, phone, status)
                    SELECT delivery_id, username, fullname, email, password, phone, status FROM delivery";
    if ($conn->query($sql_migrate)) {
        echo "Successfully re-migrated data with correct IDs.\n";
    } else {
        echo "Error re-migrating data: " . $conn->error . "\n";
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

echo "Database fix v2 complete.\n";
?>
