<?php
// customer/chat.php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$kitchens = [
    ['id' => 'khans', 'name' => 'Khans Kitchen', 'desc' => 'Traditional & Modern Cuisine'],
    ['id' => 'olympia', 'name' => 'Olympia Kitchen', 'desc' => 'Fast Food & Beverages'],
    ['id' => 'neptune', 'name' => 'Neptune Kitchen', 'desc' => 'Snacks & Quick Bites']
];

$selected_kitchen = isset($_GET['kitchen']) ? $_GET['kitchen'] : null;
$kitchen_name = ucfirst($selected_kitchen ?? 'Select a Kitchen');

foreach($kitchens as $k) {
    if ($k['id'] === $selected_kitchen) {
        $kitchen_name = $k['name'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?= htmlspecialchars($kitchen_name) ?> - Food Wave</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #F3F4F6; /* Light gray bg */
            color: #1F2937; /* Dark text */
            font-family: 'Outfit', sans-serif;
            overflow: hidden; 
        }
        
        .sidebar {
            background: white;
            border-right: 1px solid #E5E7EB;
        }

        .chat-area {
            background: #FFFFFF;
            background-image: radial-gradient(#F3F4F6 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .message-bubble {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 18px;
            position: relative;
            animation: fadeIn 0.3s ease-out;
            font-size: 0.95rem;
            line-height: 1.5;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .sent {
            background: #EF4444; /* Red */
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .received {
            background: #F3F4F6; /* Light gray */
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

        .kitchen-card {
            transition: all 0.2s ease;
        }
        .kitchen-card:hover {
            background: #F9FAFB;
        }
        .kitchen-card.active {
            background: #FEF2F2; /* Light red bg */
            border-right: 3px solid #EF4444;
        }
    </style>
</head>
<body class="h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 z-10 shrink-0 shadow-sm">
        <div class="flex items-center gap-4">
            <a href="navbar.php" class="p-2 rounded-full hover:bg-gray-100 transition text-gray-600" title="Back to Home">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    Food<span class="text-red-600">Wave</span> <span class="font-light text-gray-400">| Chat</span>
                </h1>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden sm:block text-right">
                <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($username) ?></div>
            </div>
            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold border border-red-200">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>
        </div>
    </header>

    <div class="flex flex-1 z-10 overflow-hidden w-full mx-auto">
        
        <!-- Sidebar (Kitchen List) -->
        <aside class="w-full md:w-80 lg:w-96 sidebar flex flex-col <?= $selected_kitchen ? 'hidden md:flex' : 'flex' ?>">
            <div class="p-5 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-800">Kitchens</h2>
                <p class="text-xs text-gray-500">Select a kitchen to message</p>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scroll">
                <?php foreach ($kitchens as $k): ?>
                    <a href="?kitchen=<?= $k['id'] ?>" class="kitchen-card block p-4 border-b border-gray-50 relative group <?= $selected_kitchen === $k['id'] ? 'active' : '' ?>">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-white border border-gray-200 flex items-center justify-center text-xl shadow-sm">
                                    <?php if($k['id'] == 'khans') echo '🍛'; elseif($k['id'] == 'olympia') echo '🍔'; else echo '☕'; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($k['name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= $k['desc'] ?></div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Chat Area -->
        <main class="flex-1 chat-area flex flex-col relative <?= !$selected_kitchen ? 'hidden md:flex' : 'flex' ?>">
            <?php if ($selected_kitchen): ?>
                
                <!-- Chat Header -->
                <div class="h-16 border-b border-gray-100 flex items-center px-6 bg-white shrink-0 shadow-sm z-20">
                    <a href="chat.php" class="md:hidden mr-4 p-2 -ml-2 text-gray-500 hover:bg-gray-100 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div class="flex items-center gap-3">
                         <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-lg border border-red-100 text-red-500">
                            <?php if($selected_kitchen == 'khans') echo '🍛'; elseif($selected_kitchen == 'olympia') echo '🍔'; else echo '☕'; ?>
                         </div>
                         <div>
                             <h2 class="font-bold text-gray-900 leading-tight"><?= htmlspecialchars($kitchen_name) ?></h2>
                             <div class="text-xs text-green-600 flex items-center gap-1 font-medium">
                                 <span class="w-2 h-2 rounded-full bg-green-500"></span> Online
                             </div>
                         </div>
                    </div>
                </div>

                <!-- Messages -->
                <div id="messagesList" class="flex-1 overflow-y-auto custom-scroll p-6 flex flex-col gap-2">
                    <div class="text-center text-gray-400 my-auto">Connect to kitchen chat...</div>
                </div>

                <!-- Input Area -->
                <div class="p-4 bg-white border-t border-gray-100 shrink-0 z-20">
                    <form id="chatForm" class="relative max-w-4xl mx-auto flex gap-3 items-end">
                        <input type="hidden" id="customer_id" value="<?= $user_id ?>">
                        <input type="hidden" id="kitchen" value="<?= $selected_kitchen ?>">
                        
                        <div class="flex-1 relative">
                            <input 
                                type="text" 
                                id="messageInput" 
                                class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-2xl px-5 py-3 pl-5 pr-12 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all placeholder-gray-400" 
                                placeholder="Type your message here..." 
                                autocomplete="off"
                            >
                        </div>
                        
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white rounded-xl p-3 shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                            </svg>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-gray-50">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-6 shadow-sm border border-gray-100">
                        <span class="text-4xl">💬</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Select a Conversation</h3>
                    <p class="text-gray-500 max-w-sm">Choose a kitchen from the sidebar to start ordering or asking questions in real-time.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

<script>
    const customerId = document.getElementById('customer_id')?.value;
    const kitchen = document.getElementById('kitchen')?.value;
    const messagesList = document.getElementById('messagesList');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    let lastMessageCount = 0;
    let isFetching = false;

    function scrollToBottom() {
        if(messagesList) messagesList.scrollTop = messagesList.scrollHeight;
    }

    async function fetchMessages() {
        if (!kitchen || isFetching) return;
        isFetching = true;
        
        try {
            const res = await fetch(`../api/chat.php?action=get_messages&customer_id=${customerId}&kitchen=${kitchen}`);
            const data = await res.json();
            
            if (data.status === 'success') {
                if (data.messages.length === 0) {
                     messagesList.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-full opacity-60">
                            <p class="text-gray-400">No messages yet.</p>
                        </div>`;
                } else {
                    if (data.messages.length !== lastMessageCount) {
                        let html = '';
                        let lastDate = null;
                        data.messages.forEach(msg => {
                            const isMe = msg.sender === 'customer';
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
            }
        } catch (e) {
            console.error(e);
        } finally {
            isFetching = false;
        }
    }

    if (chatForm) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (!text) return;
            const currentText = text;
            messageInput.value = '';

            const formData = new FormData();
            formData.append('customer_id', customerId);
            formData.append('kitchen', kitchen);
            formData.append('sender', 'customer');
            formData.append('message', currentText);

            try {
                const res = await fetch('../api/chat.php?action=send_message', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'success') {
                    fetchMessages();
                } else {
                    alert('Message failed to send');
                    messageInput.value = currentText;
                }
            } catch (e) {
                console.error(e);
                messageInput.value = currentText;
            }
        });

        fetchMessages();
        setInterval(fetchMessages, 3000);
        if(messageInput) messageInput.focus();
    }
</script>
</body>
</html>
