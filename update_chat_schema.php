<?php
require_once 'config/db_connection.php';

// 1. Modify sender_role to include 'delivery'
$sql1 = "ALTER TABLE chat_messages MODIFY COLUMN sender_role ENUM('customer', 'admin', 'delivery') NOT NULL";

// 2. Add receiver_role if not exists (simplest way is to try adding it, or check first)
// We'll just try to add it. If it exists, it might fail, but that's okay for this one-off.
// We'll use a safer approach: check if column exists.
$check = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'receiver_role'");
if ($check->num_rows == 0) {
    $sql2 = "ALTER TABLE chat_messages ADD COLUMN receiver_role ENUM('customer', 'admin', 'delivery') NOT NULL DEFAULT 'customer' AFTER sender_role";
} else {
    $sql2 = "ALTER TABLE chat_messages MODIFY COLUMN receiver_role ENUM('customer', 'admin', 'delivery') NOT NULL";
}

// Execute
try {
    if ($conn->query($sql1) === TRUE) {
        echo "Sender role updated successfully.\n";
    } else {
        echo "Error updating sender role: " . $conn->error . "\n";
    }

    if ($conn->query($sql2) === TRUE) {
        echo "Receiver role updated/added successfully.\n";
    } else {
        echo "Error updating receiver role: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>
