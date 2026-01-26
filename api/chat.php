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
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function send_message($conn) {
    // Expect POST data: customer_id, kitchen, sender, message
    // sender should be 'customer' or 'kitchen'
    
    // Check authentication somewhat
    // Admins have user_id = null, so check usertype too
    if (!isset($_SESSION['user_id']) && (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin')) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in (Auth failed)']);
        return;
    }

    $customer_id = $_POST['customer_id'] ?? null;
    $kitchen = $_POST['kitchen'] ?? null;
    $sender = $_POST['sender'] ?? null;
    $message = trim($_POST['message'] ?? '');

    if (!$customer_id || !$kitchen || !$sender || !$message) {
        echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO chat_messages (customer_id, kitchen, sender, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $customer_id, $kitchen, $sender, $message);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
}

function get_messages($conn) {
    $customer_id = $_GET['customer_id'] ?? null;
    $kitchen = $_GET['kitchen'] ?? null;
    
    if (!$customer_id || !$kitchen) {
        echo json_encode(['status' => 'error', 'message' => 'Missing params']);
        return;
    }

    // Mark messages as read if receiver is requesting (logic can be refined but simple for now)
    // If sender is customer, kitchen is fetching, so mark customer->kitchen messages as read
    // If sender is kitchen, customer is fetching, mark kitchen->customer messages as read
    
    // Fetch messages
    $sql = "SELECT * FROM chat_messages WHERE customer_id = ? AND kitchen = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $customer_id, $kitchen);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode(['status' => 'success', 'messages' => $messages]);
}

function get_admin_conversations($conn) {
    // For a specific kitchen, get list of customers they have chatted with
    $kitchen = $_GET['kitchen'] ?? null;
    
    if (!$kitchen) {
        echo json_encode(['status' => 'error', 'message' => 'Kitchen not specified']);
        return;
    }

    // Get distinct customers who have messages with this kitchen
    // Join with customer table to get names
    $sql = "SELECT DISTINCT c.customer_id, c.fullname, c.username 
            FROM chat_messages cm 
            JOIN customer c ON cm.customer_id = c.customer_id 
            WHERE cm.kitchen = ? 
            ORDER BY cm.created_at DESC";
            
    // Note: The ORDER BY might be tricky with DISTINCT in some SQL modes without aggregation, 
    // but let's try basic query first. To get truly 'latest active', we might need GROUP BY.
    
    $sql = "SELECT c.customer_id, c.fullname, c.username, MAX(cm.created_at) as last_msg_time
            FROM chat_messages cm
            JOIN customer c ON cm.customer_id = c.customer_id
            WHERE cm.kitchen = ?
            GROUP BY c.customer_id, c.fullname, c.username
            ORDER BY last_msg_time DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kitchen);
    $stmt->execute();
    $result = $stmt->get_result();

    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }

    echo json_encode(['status' => 'success', 'conversations' => $conversations]);
}
?>
