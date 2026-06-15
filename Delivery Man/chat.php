<?php
// Delivery Man/chat.php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'delivery') {
    header('Location: ../login.php');
    exit;
}

$rider_id = $_SESSION['delivery_id'];
$displayName = $_SESSION['delivery_name'] ?? 'Rider';

// Fetch active customers for this rider
$customers = [];
$sql_cust = "SELECT DISTINCT c.customer_id, c.fullname, c.username 
             FROM orders o 
             JOIN customer c ON o.customer_id = c.customer_id 
             WHERE o.delivery_man_id = ? AND o.order_status NOT IN ('delivered', 'cancelled')";
$stmt = $conn->prepare($sql_cust);
if ($stmt) {
    $stmt->bind_param("i", $rider_id);
    $stmt->execute();
    $res_cust = $stmt->get_result();
    while ($c = $res_cust->fetch_assoc()) {
        $customers[] = $c;
    }
}

// Fetch active kitchens for this rider
$kitchens = [];
$sql_kit = "SELECT DISTINCT o.kitchen
            FROM orders o 
            WHERE o.delivery_man_id = ? AND o.order_status NOT IN ('delivered', 'cancelled')";
$stmt = $conn->prepare($sql_kit);
if ($stmt) {
    $stmt->bind_param("i", $rider_id);
    $stmt->execute();
    $res_kit = $stmt->get_result();
    while ($k = $res_kit->fetch_assoc()) { 
        $kid = $k['kitchen'];
        $name = ucwords(str_replace('_', ' ', $kid)) . ' Kitchen';
        $kitchens[] = ['id' => $kid, 'name' => $name]; 
    }
}

$selected_customer = $_GET['customer_id'] ?? null;
$selected_kitchen = $_GET['kitchen'] ?? null;
$chat_name = "Select a Chat";
$chat_icon = "💬";

if ($selected_customer) {
    $stmt = $conn->prepare("SELECT fullname FROM customer WHERE customer_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $selected_customer);
        $stmt->execute();
        $c_res = $stmt->get_result()->fetch_assoc();
        if ($c_res) { $chat_name = $c_res['fullname']; $chat_icon = "👤"; }
    }
} elseif ($selected_kitchen) {
    $chat_name = ucwords(str_replace('_', ' ', $selected_kitchen)) . ' Kitchen';
    $chat_icon = "🏠";
}
?>
<!DOCTYPE html>
<html lang="en">
<HEAD>
    <script src="../resources/js/theme.js"></script>
    <script src="../resources/js/theme.js"></script>
    <meta charset="UTF-8">
    <title>Rider Chat - Campus Cravings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; color: #1F2937; font-family: 'Outfit', sans-serif; overflow: hidden; }
        .sidebar { background: white; border-right: 1px solid #E5E7EB; }
        .chat-area { background: #FFFFFF; background-image: radial-gradient(#F3F4F6 1px, transparent 1px); background-size: 20px 20px; }
        .message-bubble { max-width: 75%; padding: 12px 18px; border-radius: 18px; position: relative; animation: fadeIn 0.3s ease-out; font-size: 0.95rem; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .sent { background: #EF4444; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .received { background: #F3F4F6; color: #1F2937; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #E5E7EB; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        .item-card:hover { background: #F9FAFB; }
        .item-card.active { background: #FEF2F2; border-right: 3px solid #EF4444; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="h-screen flex flex-col">

    <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 z-20 shrink-0 shadow-sm">
        <div class="flex items-center gap-4">
            <a href="navbar.php" class="p-2 rounded-full hover:bg-gray-100 transition text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">Food<span class="text-red-600">Wave</span> <span class="font-light text-gray-400">| Rider Chat</span></h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="hidden sm:inline text-sm font-bold text-gray-800"><?= htmlspecialchars($displayName) ?></span>
            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold">R</div>
        </div>
    </header>

    <div class="flex flex-1 z-10 overflow-hidden">
        <aside class="w-full md:w-80 sidebar flex flex-col <?= ($selected_customer || $selected_kitchen) ? 'hidden md:flex' : 'flex' ?>">
            <div class="p-5 border-b border-gray-100"><h2 class="text-lg font-bold">Conversations</h2></div>
            <div class="flex-1 overflow-y-auto custom-scroll">
                
                <div class="px-5 py-3 bg-gray-50 text-[10px] uppercase font-bold text-gray-400">Active Customers</div>
                <?php foreach ($customers as $c): ?>
                    <a href="?customer_id=<?= $c['customer_id'] ?>" class="item-card block p-4 border-b border-gray-50 <?= $selected_customer == $c['customer_id'] ? 'active' : '' ?>">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-red-50 border flex items-center justify-center text-xl">👤</div>
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($c['fullname']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>

                <div class="px-5 py-3 bg-gray-50 text-[10px] uppercase font-bold text-gray-400 mt-4">Kitchens Support</div>
                <?php foreach ($kitchens as $k): ?>
                    <a href="?kitchen=<?= $k['id'] ?>" class="item-card block p-4 border-b border-gray-50 <?= $selected_kitchen == $k['id'] ? 'active' : '' ?>">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-blue-50 border flex items-center justify-center text-xl">🏠</div>
                            <div class="font-bold text-gray-800"><?= $k['name'] ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="flex-1 chat-area flex flex-col relative <?= !($selected_customer || $selected_kitchen) ? 'hidden md:flex' : 'flex' ?>">
            <?php if ($selected_customer || $selected_kitchen): ?>
                <div class="h-16 border-b flex items-center px-6 bg-white shrink-0 z-20">
                    <a href="chat.php" class="md:hidden mr-4 text-gray-500"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15 19l-7-7 7-7" /></svg></a>
                    <div class="flex items-center gap-3">
                         <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-lg"><?= $chat_icon ?></div>
                         <h2 class="font-bold text-gray-900"><?= htmlspecialchars($chat_name) ?></h2>
                    </div>
                </div>
                <div id="messagesList" class="flex-1 overflow-y-auto custom-scroll p-6 flex flex-col gap-2"></div>
                <div class="p-4 bg-white border-t z-20">
                    <form id="chatForm" class="flex gap-3 max-w-4xl mx-auto items-end">
                        <input type="text" id="messageInput" class="flex-1 bg-gray-50 border rounded-2xl px-5 py-3 focus:outline-none focus:border-red-500" placeholder="Type a message..." autocomplete="off">
                        <button type="submit" class="bg-red-600 text-white rounded-xl p-3"><svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" /></svg></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="flex-1 flex flex-col items-center justify-center bg-gray-50"><div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-6 shadow-sm">💬</div><h3 class="text-2xl font-bold text-gray-900">Rider Messaging</h3><p class="text-gray-500">Stay in touch with customers and kitchens for active orders.</p></div>
            <?php endif; ?>
        </main>
    </div>

<script>
    const riderId = "<?= $rider_id ?>";
    const selectedCustomer = "<?= $selected_customer ?>";
    const selectedKitchen = "<?= $selected_kitchen ?>";
    const messagesList = document.getElementById('messagesList');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    let lastMessageCount = 0;
    let isFetching = false;

    function scrollToBottom() { if(messagesList) messagesList.scrollTop = messagesList.scrollHeight; }

    async function fetchMessages() {
        if (!(selectedCustomer || selectedKitchen) || isFetching) return;
        isFetching = true;
        const url = `../api/chat.php?action=get_messages&rider_id=${riderId}` + (selectedCustomer ? `&customer_id=${selectedCustomer}` : `&kitchen=${selectedKitchen}`);
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.status === 'success' && data.messages.length !== lastMessageCount) {
                messagesList.innerHTML = data.messages.map(msg => {
                    const isMe = msg.sender === 'rider';
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    return `<div class="message-bubble ${isMe ? 'sent' : 'received'}"><div class="break-words font-medium">${msg.message}</div><div class="text-[10px] ${isMe ? 'text-white/80' : 'text-gray-400'} mt-1 text-right font-medium">${time}</div></div>`;
                }).join('');
                scrollToBottom();
                lastMessageCount = data.messages.length;
            }
        } catch (e) { console.error(e); } finally { isFetching = false; }
    }

    if (chatForm) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (!text) return;
            messageInput.value = '';
            const formData = new FormData();
            formData.append('rider_id', riderId);
            if(selectedCustomer) formData.append('customer_id', selectedCustomer);
            if(selectedKitchen) formData.append('kitchen', selectedKitchen);
            formData.append('sender', 'rider');
            formData.append('message', text);
            try {
                const res = await fetch('../api/chat.php?action=send_message', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') fetchMessages();
                else alert('Failed to send');
            } catch (e) { console.error(e); }
        });
        fetchMessages();
        setInterval(fetchMessages, 3000);
        messageInput.focus();
    }
</script>
</body>
</html>
