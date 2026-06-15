<?php
// api/notifications.php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connection.php';

$action = $_GET['action'] ?? '';

if ($action === 'check_updates') {
    if (!isset($_SESSION['usertype'])) {
        echo json_encode(['status' => 'success', 'role' => 'guest', 'unread_messages' => 0, 'latest_order_id' => 0]);
        exit;
    }

    $usertype = $_SESSION['usertype'];
    $unread_messages = 0;
    $latest_order_id = 0;

    // Check Unread Messages
    if ($usertype === 'customer' && isset($_SESSION['user_id'])) {
        $customerId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_messages WHERE customer_id = ? AND sender <> 'customer' AND is_read = 0");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $unread_messages = (int)($res['cnt'] ?? 0);
        $stmt->close();
    } elseif ($usertype === 'admin' && isset($_SESSION['shop_table'])) {
        $kitchen = $_SESSION['shop_table'];
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_messages WHERE kitchen = ? AND sender <> 'kitchen' AND is_read = 0");
        $stmt->bind_param("s", $kitchen);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $unread_messages = (int)($res['cnt'] ?? 0);
        $stmt->close();

        // Also check latest order ID for this kitchen
        $stmt2 = $conn->prepare("SELECT MAX(order_id) AS max_id FROM orders WHERE LOWER(kitchen) = ?");
        $stmt2->bind_param("s", $kitchen);
        $stmt2->execute();
        $res2 = $stmt2->get_result()->fetch_assoc();
        $latest_order_id = (int)($res2['max_id'] ?? 0);
        $stmt2->close();
    } elseif ($usertype === 'delivery' && isset($_SESSION['delivery_id'])) {
        $riderId = (int)$_SESSION['delivery_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_messages WHERE rider_id = ? AND sender <> 'rider' AND is_read = 0");
        $stmt->bind_param("i", $riderId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $unread_messages = (int)($res['cnt'] ?? 0);
        $stmt->close();

        // Also check latest order ID available to delivery (confirmed / out_for_delivery / picked_up)
        $res2 = $conn->query("SELECT MAX(order_id) AS max_id FROM orders WHERE order_status IN ('confirmed','out_for_delivery','picked_up')");
        $row2 = $res2->fetch_assoc();
        $latest_order_id = (int)($row2['max_id'] ?? 0);
    }

    echo json_encode([
        'status' => 'success',
        'role' => $usertype,
        'unread_messages' => $unread_messages,
        'latest_order_id' => $latest_order_id
    ]);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
?>
