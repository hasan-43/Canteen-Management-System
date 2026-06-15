<?php
require_once __DIR__ . '/../config/db_connection.php';

$result = $conn->query("SHOW COLUMNS FROM orders");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Query failed: " . $conn->error;
}
?>
