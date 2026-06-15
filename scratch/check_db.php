<?php
require_once __DIR__ . '/../config/db_connection.php';

function print_table_schema($conn, $table) {
    echo "=== SCHEMA FOR TABLE: $table ===\n";
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        echo "Table does not exist!\n\n";
        return;
    }
    $res = $conn->query("DESCRIBE `$table`");
    while ($row = $res->fetch_assoc()) {
        printf("%-20s %-20s %-10s %-10s %-20s %s\n", 
            $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'], $row['Extra']);
    }
    echo "\n";
}

print_table_schema($conn, 'chat_messages');
print_table_schema($conn, 'delivery');
print_table_schema($conn, 'delivery_man');
print_table_schema($conn, 'orders');
?>
