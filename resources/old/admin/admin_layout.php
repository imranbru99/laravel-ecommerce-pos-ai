<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}

// PHP logic to determine active page
$current_page = $_SERVER['REQUEST_URI'];
$current_path = parse_url($current_page, PHP_URL_PATH);
$current_file = basename($current_path);
$current_file = str_replace('.php', '', $current_file);

// New Order Notification Logic
$newOrderToastMessage = null;
$newOrderToastLink = null;
if (isset($pdo)) {
    try {
        $lastCheckedOrderId = $_SESSION['last_checked_order_id'] ?? null;

        // Get the current max order ID
        $maxIdStmt = $pdo->query("SELECT MAX(id) FROM `Order`");
        $currentMaxOrderId = $maxIdStmt ? (int)$maxIdStmt->fetchColumn() : 0;

        if ($lastCheckedOrderId === null) {
            // First time in session, just set the current max ID
            $_SESSION['last_checked_order_id'] = $currentMaxOrderId;
        } elseif ($currentMaxOrderId > $lastCheckedOrderId) {
            // There are new orders
            $newOrderCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `Order` WHERE id > ?");
            $newOrderCountStmt->execute([$lastCheckedOrderId]);
            $newOrderCount = (int)$newOrderCountStmt->fetchColumn();

            if ($newOrderCount > 0) {
                if ($newOrderCount === 1) {
                    $newOrderStmt = $pdo->prepare("SELECT id FROM `Order` WHERE id > ? ORDER BY id DESC LIMIT 1");
                    $newOrderStmt->execute([$lastCheckedOrderId]);
                    $latestNewOrderId = $newOrderStmt->fetchColumn();
                    $newOrderToastMessage = "A new order #" . str_pad($latestNewOrderId, 6, '0', STR_PAD_LEFT) . " has just arrived!";
                    $newOrderToastLink = "order-details?id=" . $latestNewOrderId;
                } else {
                    $newOrderToastMessage = $newOrderCount . " new orders have arrived!";
                    $newOrderToastLink = "orders";
                }
            }
            $_SESSION['last_checked_order_id'] = $currentMaxOrderId;
        }
    } catch (Exception $e) {} // Fail silently if Order table doesn't exist
}

if (empty($current_file) || $current_file === 'admin') {
    $current_file = 'dashboard';
}
$page_title = "Dashboard"; 

$navItems = [
    ["name" => "Dashboard", "href" => "dashboard", "icon" => "layout-grid"],
    ["name" => "POS", "href" => "pos", "icon" => "monitor-speaker"],
    ["name" => "Flash Deals", "href" => "flash-deals", "icon" => "zap"],
    ["name" => "Banners", "href" => "banners", "icon" => "image"],
    ["name" => "Coupons", "href" => "coupons", "icon" => "ticket"],
    ["name" => "Categories", "href" => "categories", "icon" => "layers"],
    ["name" => "Products", "href" => "products", "icon" => "package"],
    ["name" => "Orders", "href" => "orders", "icon" => "shopping-cart"],
    ["name" => "Customers", "href" => "customers", "icon" => "users"],
    ["name" => "AI & Automation", "href" => "ai-settings", "icon" => "bot"],
    ["name" => "Settings", "icon" => "settings", "subItems" => [
        ["name" => "General Setting", "href" => "settings"],
        ["name" => "Payment Methods", "href" => "payment-settings"],
        ["name" => "Shipping Methods", "href" => "shipping-settings"],
        ["name" => "Social Channels", "href" => "social-settings"],
        ["name" => "Notifications", "href" => "notification-settings"],
        ["name" => "API Configurations", "href" => "api-settings"],
        ["name" => "System Update", "href" => "system-update"]
    ]]
];

foreach ($navItems as $item) {
    if (isset($item['href']) && $current_file === $item['href']) {
        $page_title = $item['name'];
    } elseif (isset($item['subItems'])) {
        foreach ($item['subItems'] as $subItem) {
            if ($current_file === $subItem['href']) {
                $page_title = $subItem['name'];
            }
        }
    }
}

$storeName = '';
$faviconUrl = null;
// Fetch Admin Notifications
$unreadCount = 0;
$adminNotifications = [];
if (isset($pdo)) {
    try {
        $setStmt = $pdo->query("SELECT storeName, faviconUrl FROM SettingGeneral LIMIT 1");
        $setRow = $setStmt ? $setStmt->fetch(PDO::FETCH_ASSOC) : [];
        if (!empty($setRow['storeName'])) $storeName = $setRow['storeName'];
        if (!empty($setRow['faviconUrl'])) $faviconUrl = $setRow['faviconUrl'];

        $unreadStmt = $pdo->query("SELECT COUNT(*) FROM Notification WHERE userId IS NULL AND isRead = FALSE");
        $unreadCount = $unreadStmt ? $unreadStmt->fetchColumn() : 0;
        $notifStmt = $pdo->query("SELECT * FROM Notification WHERE userId IS NULL ORDER BY createdAt DESC LIMIT 20");
        $adminNotifications = $notifStmt ? $notifStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        // Fallback if missing tables/columns
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Admin Panel</title>
    <?php if ($faviconUrl): ?>
    <link rel="icon" href="../<?php echo htmlspecialchars(ltrim($faviconUrl, '/')); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .sidebar-active { background: #0f172a; color: white !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900 overflow-hidden h-screen flex">

    <!-- 1. SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 transform -translate-x-full md:relative md:translate-x-0 transition-all duration-300 ease-in-out border-r border-slate-200 bg-white flex flex-col">
        
        <!-- Branding -->
        <div class="h-20 flex items-center px-8 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
                    <i data-lucide="box" class="text-white w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="font-extrabold text-xl tracking-tight text-slate-900"><?php echo htmlspecialchars($storeName); ?></h1>
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.2em] leading-none">Console</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-4">Menu</p>
            
            <?php foreach ($navItems as $item): ?>
                <?php if (isset($item['subItems'])): ?>
                <?php 
                $isDropdownActive = false;
                foreach ($item['subItems'] as $sub) {
                    if ($current_file === $sub['href']) {
                        $isDropdownActive = true;
                        break;
                    }
                }
                ?>
                    <!-- Dropdown Item -->
                    <div class="flex flex-col">
                    <button onclick="toggleDropdown('<?php echo $item['name']; ?>')" class="flex items-center justify-between group px-4 py-3 rounded-xl transition-all <?php echo $isDropdownActive ? 'sidebar-active' : 'hover:bg-slate-50 text-slate-600'; ?>">
                            <div class="flex items-center gap-3.5">
                            <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5 <?php echo $isDropdownActive ? '' : 'group-hover:text-indigo-600 transition-colors'; ?>"></i>
                                <span class="text-sm font-semibold"><?php echo $item['name']; ?></span>
                            </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-300 <?php echo $isDropdownActive ? 'rotate-180' : ''; ?>" id="icon-<?php echo $item['name']; ?>"></i>
                        </button>
                    <div id="dropdown-<?php echo $item['name']; ?>" class="<?php echo $isDropdownActive ? 'flex' : 'hidden'; ?> flex-col gap-1 mt-1 px-4 ml-4 border-l-2 border-slate-100 pb-2">
                            <?php foreach ($item['subItems'] as $sub): ?>
                            <?php $isSubActive = ($current_file === $sub['href']); ?>
                            <a href="<?php echo $sub['href']; ?>" class="pl-6 pr-4 py-2 rounded-lg text-xs font-semibold transition-all <?php echo $isSubActive ? 'text-indigo-600 bg-indigo-50' : 'text-slate-500 hover:text-indigo-600 hover:bg-indigo-50'; ?>">
                                    <?php echo $sub['name']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Standard Link -->
                <?php $isActive = ($current_file === $item['href']); ?>
                    <a href="<?php echo $item['href']; ?>" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all <?php echo $isActive ? 'sidebar-active' : 'hover:bg-slate-50 text-slate-600 hover:text-indigo-600'; ?>">
                        <div class="flex items-center gap-3.5">
                            <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
                            <span class="text-sm font-semibold"><?php echo $item['name']; ?></span>
                        </div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- User Card -->
        <div class="p-4 mt-auto">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between group hover:bg-white hover:shadow-md transition-all">
                <a href="profile" class="flex items-center gap-3 flex-1 min-w-0 transition-colors">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center font-bold text-white shadow-inner">
                            <?php echo isset($_SESSION['admin_name']) ? strtoupper(substr($_SESSION['admin_name'], 0, 1)) : 'A'; ?>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-900 truncate"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin User'); ?></p>
                        <p class="text-[10px] font-medium text-slate-500 uppercase">Manager</p>
                    </div>
                </a>
                <a href="logout" title="Logout" class="block p-2 shrink-0">
                    <i data-lucide="log-out" class="w-4 h-4 text-slate-400 hover:text-red-500 transition-colors"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- 2. MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto relative">
        
        <!-- Header -->
        <header class="h-20 sm:h-24 shrink-0 px-6 sm:px-8 flex items-center justify-between bg-white/80 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-40 shadow-sm shadow-slate-100/50">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div class="flex flex-col">
                    <h3 class="text-lg font-bold text-slate-800 leading-tight"><?php echo $page_title; ?></h3>
                    <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        <span>Admin</span> <i data-lucide="chevron-right" class="w-3 h-3"></i> <span class="text-indigo-600"><?php echo $page_title; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-2 relative">
                <!-- Notification Toggle Button -->
                <button onclick="toggleNotifications()" id="notification-btn" class="p-3 rounded-2xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-indigo-600 transition-all relative shadow-sm">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="absolute top-3 right-3 w-2 h-2 bg-rose-500 rounded-full border border-white animate-pulse"></span>
                    <?php endif; ?>
                </button>

                <!-- Notification Dropdown Panel (Hide/Show) -->
                <div id="notification-dropdown" class="hidden absolute top-[calc(100%+1rem)] right-0 w-80 bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden z-50">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h4 class="font-bold text-slate-800">Notifications</h4>
                        <?php if ($unreadCount > 0): ?>
                            <span class="text-[10px] font-black bg-indigo-100 text-indigo-600 px-2.5 py-1 rounded-lg uppercase tracking-wider"><?php echo $unreadCount; ?> New</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-80 overflow-y-auto custom-scrollbar p-3 space-y-1">
                        <?php if (empty($adminNotifications)): ?>
                            <div class="p-4 text-center text-sm font-medium text-slate-500">No new notifications</div>
                        <?php else: ?>
                            <?php foreach ($adminNotifications as $notif): 
                                $link = '#';
                                if (preg_match('/(?:Order|order)\s*#(\d+)/', $notif['message'], $matches) || preg_match('/(?:Order|order)\s*#(\d+)/', $notif['title'], $matches)) {
                                    $link = 'order-details?id=' . $matches[1];
                                }
                                $isAlert = ($notif['type'] === 'alert' || $notif['type'] === 'error');
                            ?>
                                <a href="<?php echo htmlspecialchars($link); ?>" class="flex gap-4 p-3 hover:bg-slate-50 rounded-2xl transition-colors group <?php echo $notif['isRead'] ? 'opacity-60' : ''; ?>">
                                    <div class="w-10 h-10 rounded-xl <?php echo $isAlert ? 'bg-rose-100 text-rose-600' : 'bg-blue-100 text-blue-600'; ?> flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm">
                                        <i data-lucide="<?php echo $isAlert ? 'alert-triangle' : 'shopping-bag'; ?>" class="w-5 h-5"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-[11px] font-medium text-slate-500 mt-0.5 leading-snug line-clamp-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider"><?php echo date('M d, h:i A', strtotime($notif['createdAt'])); ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 border-t border-slate-100 text-center bg-slate-50/50 hover:bg-slate-50 transition-colors">
                        <button onclick="markAllRead()" class="text-xs font-bold text-indigo-600 uppercase tracking-widest hover:text-indigo-800">Mark All as Read</button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main View -->
        <main class="p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
                <div class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <?php if(isset($content)) echo $content; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Overlay -->
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/20 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

    <script>
        lucide.createIcons();

        // Global Toast Notification
        window.showToast = function(message, type = 'success', link = null) {
            let container = document.getElementById('global-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'global-toast-container';
                container.className = 'fixed bottom-8 right-8 z-[100] space-y-4 flex flex-col items-end pointer-events-none';
                document.body.appendChild(container);
            }
            
            const bgColor = type === 'success' ? 'bg-slate-900' : (type === 'error' ? 'bg-rose-600' : 'bg-blue-600');
            const icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'alert-circle' : 'info');
            const iconColor = type === 'success' ? 'text-emerald-400' : 'text-white';
            
            const toast = document.createElement('div');
            let cursorClass = link ? 'cursor-pointer hover:scale-105 active:scale-95' : '';
            toast.className = `flex items-center gap-4 px-6 py-4 rounded-2xl shadow-2xl text-white transition-all duration-500 animate-in slide-in-from-right pointer-events-auto ${bgColor} ${cursorClass}`;
            
            if (link) {
                toast.onclick = () => window.location.href = link;
                toast.innerHTML = `
                    <i data-lucide="${icon}" class="w-5 h-5 ${iconColor}"></i>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold tracking-tight">${message}</span>
                        <span class="text-[10px] text-white/70 uppercase tracking-widest mt-0.5 font-semibold flex items-center gap-1">Click to view details <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
                    </div>
                `;
            } else {
                toast.innerHTML = `
                    <i data-lucide="${icon}" class="w-5 h-5 ${iconColor}"></i>
                    <span class="text-sm font-bold tracking-tight">${message}</span>
                `;
            }
            
            container.appendChild(toast);
            lucide.createIcons();
            
            const duration = link ? 7000 : 3000;
            setTimeout(() => { 
                toast.classList.add('opacity-0', 'translate-x-8'); 
                setTimeout(() => toast.remove(), 300); 
            }, duration);
        };

        // Global Confirmation Modal
        window.customConfirm = function(message, onConfirm, onCancel = () => {}) {
            let modal = document.getElementById('global-confirm-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'global-confirm-modal';
                modal.className = 'fixed inset-0 z-[110] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4';
                modal.innerHTML = `<div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200"><div class="p-6 text-center mt-4"><div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="alert-triangle" class="w-8 h-8"></i></div><h3 class="text-xl font-bold text-slate-800 mb-2">Are you sure?</h3><p id="global-confirm-message" class="text-sm text-slate-500 font-medium"></p></div><div class="p-4 bg-slate-50 flex gap-3 border-t border-slate-100 mt-2"><button id="global-confirm-cancel" class="flex-1 py-3 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button><button id="global-confirm-ok" class="flex-1 py-3 text-sm font-bold text-white bg-rose-600 rounded-xl shadow-md hover:bg-rose-700 transition-colors">Yes, Proceed</button></div></div>`;
                document.body.appendChild(modal);
            }
            document.getElementById('global-confirm-message').innerText = message;
            modal.style.display = 'flex';
            lucide.createIcons();

            const btnOk = document.getElementById('global-confirm-ok');
            const btnCancel = document.getElementById('global-confirm-cancel');
            const newBtnOk = btnOk.cloneNode(true); const newBtnCancel = btnCancel.cloneNode(true);
            btnOk.parentNode.replaceChild(newBtnOk, btnOk); btnCancel.parentNode.replaceChild(newBtnCancel, btnCancel);

            newBtnOk.addEventListener('click', () => { modal.style.display = 'none'; if(onConfirm) onConfirm(); });
            newBtnCancel.addEventListener('click', () => { modal.style.display = 'none'; if(onCancel) onCancel(); });
        };

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function toggleDropdown(name) {
            const dropdown = document.getElementById('dropdown-' + name);
            const icon = document.getElementById('icon-' + name);
            
            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                dropdown.classList.add('flex');
                icon.style.transform = 'rotate(180deg)';
            } else {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
                icon.style.transform = 'rotate(0deg)';
            }
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notification-dropdown');
            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                dropdown.classList.add('animate-in', 'fade-in', 'zoom-in-95', 'duration-200');
            } else {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('animate-in', 'fade-in', 'zoom-in-95', 'duration-200');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notification-dropdown');
            const toggleBtn = document.getElementById('notification-btn');
            if (dropdown && !dropdown.contains(event.target) && toggleBtn && !toggleBtn.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        async function markAllRead() {
            try {
                await fetch('../api/read_admin_notifications');
                const badge = document.querySelector('.bg-rose-500.animate-pulse');
                if (badge) badge.remove();
                location.reload();
            } catch(e) { 
                console.error(e); 
                showToast('Failed to mark notifications as read.', 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            <?php if (!empty($newOrderToastMessage)): ?>
            showToast('<?php echo addslashes($newOrderToastMessage); ?>', 'info', '<?php echo addslashes($newOrderToastLink ?? ''); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>