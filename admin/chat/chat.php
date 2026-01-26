<?php
// admin/chat/chat.php (Multi-role support)
session_start();
require_once '../../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || !isset($_SESSION['shop'])) {
    header('Location: ../../login.php');
    exit;
}

$shopName = $_SESSION['shop'];
$kitchenId = (stripos($shopName, 'khans') !== false) ? 'khans' : ((stripos($shopName, 'neptune') !== false) ? 'neptune' : 'olympia');

$customer_id = $_GET['customer_id'] ?? null;
$rider_id = $_GET['rider_id'] ?? null;

if (!$customer_id && !$rider_id) {
    header("Location: index.php");
    exit;
}

$target_name = "";
$target_username = "";
$target_icon = "👤";

if ($customer_id) {
    $stmt = $conn->prepare("SELECT fullname, username FROM customer WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
} else {
    $stmt = $conn->prepare("SELECT fullname, username FROM delivery_man WHERE id = ?");
    $stmt->bind_param("i", $rider_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $target_icon = "🚴";
}

if (!$target) die("Recipient not found");
$target_name = $target['fullname'];
$target_username = $target['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?= htmlspecialchars($target_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; color: #1F2937; font-family: 'Outfit', sans-serif; overflow: hidden; }
        .message-bubble { max-width: 75%; padding: 12px 18px; border-radius: 18px; position: relative; animation: fadeIn 0.3s ease-out; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-size: 0.95rem; line-height: 1.5; }
        .sent { background: #EF4444; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .received { background: white; color: #1F2937; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #E5E7EB; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="h-screen flex flex-col">
    <header class="bg-white h-16 flex items-center px-6 justify-between z-10 shrink-0 shadow-sm">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 rounded-full hover:bg-gray-100 text-gray-500 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg></a>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-xl border border-gray-100"><?= $target_icon ?></div>
                <h1 class="font-bold text-gray-900"><?= htmlspecialchars($target_name) ?> <span class="hidden sm:inline font-normal text-gray-400 text-sm ml-2">@<?= htmlspecialchars($target_username) ?></span></h1>
            </div>
        </div>
        <div class="text-xs text-red-600 font-bold border border-red-200 px-3 py-1 rounded bg-red-50 uppercase tracking-widest"><?= $rider_id ? 'Rider Chat' : 'Customer Chat' ?></div>
    </header>

    <div class="flex-1 overflow-hidden flex flex-col w-full mx-auto md:max-w-5xl md:my-6 md:rounded-2xl md:bg-white md:shadow-xl md:border md:border-gray-200">
        <div id="messagesList" class="flex-1 overflow-y-auto custom-scroll p-6 flex flex-col gap-2"></div>
        <div class="p-4 bg-white border-t border-gray-100 shrink-0 z-20">
            <form id="chatForm" class="flex gap-3 items-end">
                <input type="text" id="messageInput" class="flex-1 bg-gray-50 border border-gray-200 text-gray-900 rounded-2xl px-5 py-4 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all placeholder-gray-400" placeholder="Type a message..." autocomplete="off">
                <button type="submit" class="bg-red-600 text-white rounded-xl p-4 shadow-md transition-all"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" /></svg></button>
            </form>
        </div>
    </div>

<script>
    const customerId = "<?= $customer_id ?>";
    const riderId = "<?= $rider_id ?>";
    const kitchen = "<?= $kitchenId ?>";
    const messagesList = document.getElementById('messagesList');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    let lastMessageCount = 0;

    function scrollToBottom() { messagesList.scrollTop = messagesList.scrollHeight; }

    async function fetchMessages() {
        const url = `../../api/chat.php?action=get_messages&kitchen=${kitchen}` + (customerId ? `&customer_id=${customerId}` : `&rider_id=${riderId}`);
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.status === 'success' && data.messages.length !== lastMessageCount) {
                messagesList.innerHTML = data.messages.map(msg => {
                    const isMe = msg.sender === 'kitchen';
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    return `<div class="message-bubble ${isMe ? 'sent' : 'received'}"><div class="break-words font-medium">${msg.message}</div><div class="text-[10px] ${isMe ? 'text-white/80' : 'text-gray-400'} mt-1 text-right">${time}</div></div>`;
                }).join('');
                scrollToBottom();
                lastMessageCount = data.messages.length;
            }
        } catch (e) { console.error(e); }
    }

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text) return;
        messageInput.value = '';
        const formData = new FormData();
        if(customerId) formData.append('customer_id', customerId);
        if(riderId) formData.append('rider_id', riderId);
        formData.append('kitchen', kitchen);
        formData.append('sender', 'kitchen');
        formData.append('message', text);
        try {
            const res = await fetch('../../api/chat.php?action=send_message', { method: 'POST', body: formData });
            if ((await res.json()).status === 'success') fetchMessages();
            else alert('Failed to send');
        } catch (e) { console.error(e); }
    });

    fetchMessages();
    setInterval(fetchMessages, 3000);
    messageInput.focus();
</script>
</body>
</html>
