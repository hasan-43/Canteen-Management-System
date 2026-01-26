<?php
// admin/chat/chat.php
session_start();
require_once '../../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || !isset($_SESSION['shop'])) {
    header('Location: ../../login.php');
    exit;
}

$shopName = $_SESSION['shop'];
$kitchenId = '';
if (stripos($shopName, 'khans') !== false) $kitchenId = 'khans';
elseif (stripos($shopName, 'neptune') !== false) $kitchenId = 'neptune';
elseif (stripos($shopName, 'olympia') !== false) $kitchenId = 'olympia';

$customer_id = $_GET['customer_id'] ?? null;
if (!$customer_id) {
    header("Location: index.php");
    exit;
}

// Get customer details
$stmt = $conn->prepare("SELECT fullname, username FROM customer WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$res = $stmt->get_result();
$customer = $res->fetch_assoc();

if (!$customer) {
    die("Customer not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?= htmlspecialchars($customer['fullname']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #F3F4F6;
            color: #1F2937; 
            font-family: 'Outfit', sans-serif;
            overflow: hidden; 
        }
        .header-shadow {
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .message-bubble {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 18px;
            position: relative;
            animation: fadeIn 0.3s ease-out;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .sent {
            background: #EF4444;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .received {
            background: white;
            color: #1F2937;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            border: 1px solid #E5E7EB;
        }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white h-16 flex items-center px-6 justify-between z-10 shrink-0 header-shadow">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 rounded-full hover:bg-gray-100 text-gray-500 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600 border border-gray-200">
                    <?= strtoupper(substr($customer['fullname'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-900 leading-tight"><?= htmlspecialchars($customer['fullname']) ?></h1>
                    <div class="text-xs text-gray-500 font-mono">@<?= htmlspecialchars($customer['username']) ?></div>
                </div>
            </div>
        </div>
        <div class="hidden sm:flex items-center gap-2">
            <div class="text-xs text-red-600 font-bold tracking-widest uppercase border border-red-200 px-2 py-1 rounded bg-red-50">
                Admin Chat
            </div>
        </div>
    </header>

    <!-- Main Chat Area -->
    <div class="flex-1 overflow-hidden z-10 flex flex-col w-full mx-auto md:max-w-5xl md:my-6 md:rounded-2xl md:bg-white md:shadow-xl md:border md:border-gray-200">
        
        <!-- Messages -->
        <div id="messagesList" class="flex-1 overflow-y-auto custom-scroll p-6 flex flex-col gap-2">
            <div class="text-center text-gray-400 my-auto">Loading conversation history...</div>
        </div>

        <!-- Input Area -->
        <div class="p-4 bg-white border-t border-gray-100 shrink-0 z-20">
            <form id="chatForm" class="relative flex gap-3 items-end">
                <input type="hidden" id="customer_id" value="<?= $customer_id ?>">
                <input type="hidden" id="kitchen" value="<?= $kitchenId ?>">
                
                <div class="flex-1 relative">
                    <input 
                        type="text" 
                        id="messageInput" 
                        class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-2xl px-5 py-4 pl-5 pr-12 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all placeholder-gray-400" 
                        placeholder="Reply as <?= htmlspecialchars($shopName) ?>..." 
                        autocomplete="off"
                    >
                </div>
                
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white rounded-xl p-4 shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

<script>
    const customerId = document.getElementById('customer_id').value;
    const kitchen = document.getElementById('kitchen').value;
    const messagesList = document.getElementById('messagesList');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    let lastMessageCount = 0;

    function scrollToBottom() {
        messagesList.scrollTop = messagesList.scrollHeight;
    }

    async function fetchMessages() {
        try {
            const res = await fetch(`../../api/chat.php?action=get_messages&customer_id=${customerId}&kitchen=${kitchen}`);
            const data = await res.json();
            
            if (data.status === 'success') {
                if (data.messages.length === 0) {
                     messagesList.innerHTML = `<div class="flex flex-col items-center justify-center h-full opacity-60"><p class="text-gray-400">No conversation yet.</p><p class="text-sm text-gray-500">Send the first message!</p></div>`;
                     return;
                }
                
                if (data.messages.length !== lastMessageCount) {
                    let html = '';
                    let lastDate = null;
                    data.messages.forEach(msg => {
                         const isMe = msg.sender === 'kitchen'; 
                         const dateObj = new Date(msg.created_at);
                         const time = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                         const dateStr = dateObj.toLocaleDateString();

                         if (lastDate !== dateStr) {
                                html += `<div class="text-center text-xs text-gray-400 my-4 uppercase tracking-wider font-semibold bg-gray-100 py-1 px-3 rounded-full mx-auto w-fit">${dateStr}</div>`;
                                lastDate = dateStr;
                         }

                         html += `
                            <div class="message-bubble ${isMe ? 'sent' : 'received'}">
                                <div class="break-words font-medium">${msg.message}</div>
                                <div class="text-[10px] ${isMe ? 'text-white/80' : 'text-gray-400'} mt-1 text-right font-medium">${time}</div>
                            </div>
                        `;
                    });
                    messagesList.innerHTML = html;
                    scrollToBottom();
                    lastMessageCount = data.messages.length;
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text) return;
        const currentText = text;
        messageInput.value = '';

        const formData = new FormData();
        formData.append('customer_id', customerId);
        formData.append('kitchen', kitchen);
        formData.append('sender', 'kitchen'); 
        formData.append('message', currentText);

        try {
            const res = await fetch('../../api/chat.php?action=send_message', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                fetchMessages(); 
            } else {
                alert('Failed to send');
                messageInput.value = currentText;
            }
        } catch (e) {
            console.error(e);
            messageInput.value = currentText;
        }
    });

    fetchMessages();
    setInterval(fetchMessages, 3000);
    // Auto focus
    if(messageInput) messageInput.focus();
</script>
</body>
</html>
