<?php
// admin/chat/index.php
session_start();
require_once '../../config/db_connection.php';

// Check admin auth
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || !isset($_SESSION['shop'])) {
    header('Location: ../../login.php');
    exit;
}

$shopName = $_SESSION['shop'];
$kitchenId = '';
if (stripos($shopName, 'khans') !== false) $kitchenId = 'khans';
elseif (stripos($shopName, 'neptune') !== false) $kitchenId = 'neptune';
elseif (stripos($shopName, 'olympia') !== false) $kitchenId = 'olympia';

if (!$kitchenId) {
    die("Invalid shop session.");
}

$navbarLink = "../$kitchenId/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Conversations - <?= htmlspecialchars($shopName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #F9FAFB; 
            color: #1F2937;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
        }
        .card {
            background: white;
            border: 1px solid #E5E7EB;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #EF4444;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 h-20 flex items-center justify-between px-8 sticky top-0 z-50">
        <div class="flex items-center gap-6">
            <a href="<?= $navbarLink ?>" class="group flex items-center gap-2 text-gray-500 hover:text-red-600 transition">
                <div class="p-2 rounded-full group-hover:bg-red-50 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </div>
                <span class="font-medium hidden sm:inline">Back to Dashboard</span>
            </a>
            <div class="w-px h-8 bg-gray-200 hidden sm:block"></div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                Messages
            </h1>
        </div>
        <div class="flex items-center gap-4">
             <div class="text-right hidden sm:block">
                 <div class="text-xs text-gray-400">LOGGED IN AS</div>
                 <div class="font-bold text-gray-900"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
             </div>
             <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 border border-red-200 flex items-center justify-center font-bold">
                 <?= strtoupper(substr($_SESSION['shop'], 0, 1)) ?>
             </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto p-6 md:p-10">
        <div id="loading" class="flex flex-col items-center justify-center py-20">
            <div class="w-12 h-12 rounded-full border-4 border-gray-200 border-t-red-600 animate-spin mb-4"></div>
            <p class="text-gray-400">Loading messages...</p>
        </div>

        <div id="conversationList" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
    </main>

<script>
    const kitchenId = "<?= $kitchenId ?>";
    const listEl = document.getElementById('conversationList');
    const loadingEl = document.getElementById('loading');

    async function loadConversations() {
        try {
            const res = await fetch(`../../api/chat.php?action=get_admin_conversations&kitchen=${kitchenId}`);
            const data = await res.json();
            
            loadingEl.style.display = 'none';

            if (data.status === 'success') {
                if (data.conversations.length === 0) {
                    listEl.innerHTML = `
                        <div class="col-span-full flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-gray-300">
                            <span class="text-4xl mb-4 opacity-30">📭</span>
                            <h3 class="text-xl font-bold text-gray-900 mb-1">No Active Chats</h3>
                            <p class="text-gray-500">New messages will appear here.</p>
                        </div>
                    `;
                    return;
                }

                let html = '';
                data.conversations.forEach(c => {
                    const init = (c.fullname || c.username).substring(0,2).toUpperCase();
                    const dateObj = c.last_msg_time ? new Date(c.last_msg_time) : new Date();
                    const isToday = new Date().toDateString() === dateObj.toDateString();
                    const timeDisp = isToday 
                        ? dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                        : dateObj.toLocaleDateString();

                    html += `
                        <a href="chat.php?customer_id=${c.customer_id}" class="card rounded-2xl p-5 flex items-center gap-5 group cursor-pointer">
                            <div class="w-14 h-14 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-xl border border-gray-200 group-hover:bg-red-50 group-hover:text-red-600 transition">
                                ${init}
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <h2 class="text-lg font-bold text-gray-900 truncate">${c.fullname || c.username}</h2>
                                    <span class="text-xs font-mono text-gray-400 bg-gray-100 px-2 py-1 rounded">${timeDisp}</span>
                                </div>
                                <div class="text-sm text-gray-500 flex items-center gap-2">
                                    <span class="truncate">@${c.username}</span>
                                    <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                    <span class="text-red-500 text-xs font-bold tracking-wide group-hover:underline">OPEN</span>
                                </div>
                            </div>
                            
                            <div class="text-gray-300 group-hover:text-red-500 transform group-hover:translate-x-1 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    `;
                });
                listEl.innerHTML = html;
            } else {
                listEl.innerHTML = '<div class="text-red-500 text-center col-span-full">Failed to load conversations.</div>';
            }
        } catch (e) {
            console.error(e);
            loadingEl.textContent = 'Error loading data.';
        }
    }

    loadConversations();
    setInterval(loadConversations, 5000);
</script>
</body>
</html>
