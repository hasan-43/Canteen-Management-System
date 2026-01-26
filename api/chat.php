<?php
// api/chat.php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connection.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'send_message':
        send_message($conn);
        break;
    case 'get_messages':
        get_messages($conn);
        break;
    case 'get_admin_conversations':
        get_admin_conversations($conn);
        break;
    case 'get_customer_riders':
        get_customer_riders($conn);
        break;
    case 'get_rider_conversations':
        get_rider_conversations($conn);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function send_message($conn) {
    if (!isset($_SESSION['usertype'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        return;
    }

    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $kitchen = !empty($_POST['kitchen']) ? $_POST['kitchen'] : null;
    $rider_id = !empty($_POST['rider_id']) ? (int)$_POST['rider_id'] : null;
    $sender = $_POST['sender']; // 'customer', 'kitchen', 'rider'
    $message = trim($_POST['message'] ?? '');

    if (!$message || !$sender) {
        echo json_encode(['status' => 'error', 'message' => 'Missing message or sender']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO chat_messages (customer_id, kitchen, rider_id, sender, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $customer_id, $kitchen, $rider_id, $sender, $message);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
}

function get_messages($conn) {
    $customer_id = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
    $kitchen = !empty($_GET['kitchen']) ? $_GET['kitchen'] : null;
    $rider_id = !empty($_GET['rider_id']) ? (int)$_GET['rider_id'] : null;

    $params = [];
    $types = "";
    $where = [];

    if ($customer_id && $kitchen) {
        $where = "customer_id = ? AND kitchen = ? AND rider_id IS NULL";
        array_push($params, $customer_id, $kitchen);
        $types = "is";
    } elseif ($customer_id && $rider_id) {
        $where = "customer_id = ? AND rider_id = ? AND kitchen IS NULL";
        array_push($params, $customer_id, $rider_id);
        $types = "ii";
    } elseif ($kitchen && $rider_id) {
        $where = "kitchen = ? AND rider_id = ? AND customer_id IS NULL";
        array_push($params, $kitchen, $rider_id);
        $types = "si";
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid chat pair']);
        return;
    }

    $sql = "SELECT * FROM chat_messages WHERE $where ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode(['status' => 'success', 'messages' => $messages]);
}

function get_customer_riders($conn) {
    if (!isset($_GET['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing customer_id']);
        return;
    }
    $customerId = (int)$_GET['customer_id'];

    // Get riders assigned to current active orders
    $sql = "SELECT DISTINCT d.id, d.fullname, d.username 
            FROM orders o 
            JOIN delivery_man d ON o.delivery_man_id = d.id 
            WHERE o.customer_id = ? AND o.order_status NOT IN ('delivered', 'cancelled')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $riders = [];
    while ($row = $result->fetch_assoc()) {
        $riders[] = $row;
    }

    echo json_encode(['status' => 'success', 'riders' => $riders]);
}

function get_admin_conversations($conn) {
    $kitchen = $_GET['kitchen'] ?? null;
    if (!$kitchen) {
        echo json_encode(['status' => 'error', 'message' => 'Kitchen not specified']);
        return;
    }

    $res = ["customers" => [], "riders" => []];
    
    // Customers
    $sql_cust = "SELECT c.customer_id as id, c.fullname, c.username, MAX(cm.created_at) as last_msg_time
            FROM chat_messages cm
            JOIN customer c ON cm.customer_id = c.customer_id
            WHERE cm.kitchen = ? AND cm.rider_id IS NULL
            GROUP BY c.customer_id, c.fullname, c.username
            ORDER BY last_msg_time DESC";
    $stmt = $conn->prepare($sql_cust);
    $stmt->bind_param("s", $kitchen);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $res["customers"][] = $row;

    // Riders (Chat history)
    $sql_riders = "SELECT d.id, d.fullname, d.username, MAX(cm.created_at) as last_msg_time
            FROM chat_messages cm
            JOIN delivery_man d ON cm.rider_id = d.id
            WHERE cm.kitchen = ? AND cm.customer_id IS NULL
            GROUP BY d.id, d.fullname, d.username
            ORDER BY last_msg_time DESC";
    $stmt = $conn->prepare($sql_riders);
    $stmt->bind_param("s", $kitchen);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $res["riders"][] = $row;

    // Active Riders (Not in chat yet)
    $sql_active = "SELECT DISTINCT d.id, d.fullname, d.username, NULL as last_msg_time
                   FROM orders o
                   JOIN delivery_man d ON o.delivery_man_id = d.id
                   WHERE o.kitchen = ? AND o.order_status NOT IN ('delivered', 'cancelled')";
    $stmt = $conn->prepare($sql_active);
    $stmt->bind_param("s", $kitchen);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exists = false;
        foreach ($res["riders"] as $r) if ($r['id'] == $row['id']) $exists = true;
        if (!$exists) $res["riders"][] = $row;
    }

    echo json_encode(['status' => 'success', 'conversations' => $res]);
}

function get_rider_conversations($conn) {
    $rider_id = (int)($_GET['rider_id'] ?? 0);
    if (!$rider_id) {
        echo json_encode(['status' => 'error', 'message' => 'Rider ID missing']);
        return;
    }

    $res = ["customers" => [], "kitchens" => []];
    
    // Active customers
    $sql_cust = "SELECT DISTINCT c.customer_id as id, c.fullname, c.username
                 FROM orders o
                 JOIN customer c ON o.customer_id = c.customer_id
                 WHERE o.delivery_man_id = ? AND o.order_status NOT IN ('delivered', 'cancelled')";
    $stmt = $conn->prepare($sql_cust);
    $stmt->bind_param("i", $rider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $res["customers"][] = $row;

    // Active kitchens
    $sql_kit = "SELECT DISTINCT kitchen as id
                FROM orders 
                WHERE delivery_man_id = ? AND order_status NOT IN ('delivered', 'cancelled')";
    $stmt = $conn->prepare($sql_kit);
    $stmt->bind_param("i", $rider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $kid = $row['id'];
        $name = ($kid == 'khans') ? 'Khans Kitchen' : (($kid == 'neptune') ? 'Neptune Kitchen' : 'Olympia Kitchen');
        $res["kitchens"][] = ["id" => $kid, "name" => $name];
    }

    echo json_encode(['status' => 'success', 'conversations' => $res]);
}
