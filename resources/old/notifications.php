<?php
session_start();
require_once __DIR__ . '/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

$userId = $_SESSION['user_id'];

$isAccountRef = isset($_GET['ref']) && $_GET['ref'] === 'account';

// Fetch User Info
$stmt = $pdo->prepare("SELECT * FROM User WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM Notification WHERE userId = :uid ORDER BY createdAt DESC");
$stmt->execute(['uid' => $userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark as read immediately when page opens
$pdo->prepare("UPDATE Notification SET isRead = 1 WHERE userId = :uid")->execute(['uid' => $userId]);

// Fetch Unread Notifications Count (Should be 0 now, but keeping for layout parity)
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE userId = ? AND isRead = 0");
$notifStmt->execute([$userId]);
$unreadCount = $notifStmt->fetchColumn();

include 'Header.php';
?>

<div class="min-h-screen pt-24 md:pt-28 pb-28 md:pb-24 bg-slate-50 font-sans">
    <?php if ($isAccountRef): ?>
    <div class="max-w-[1200px] mx-auto px-6">
        <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-8">My Account</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
            <!-- Sidebar Navigation -->
            <div class="md:col-span-3 space-y-2">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 mb-6 text-center">
                    <div class="w-20 h-20 bg-[#003e86] text-white rounded-full flex items-center justify-center text-3xl font-black mx-auto mb-4 shadow-lg shadow-blue-900/20">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg leading-tight mb-1"><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p class="text-xs font-bold tracking-wider uppercase text-slate-400"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <!-- Mobile Icon Grid Menu -->
                <div class="grid grid-cols-3 gap-2 md:hidden bg-white rounded-[2rem] p-3 shadow-sm border border-slate-200">
                    <a href="account#dashboard" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        <span>Dashboard</span>
                    </a>
                    <a href="account#orders" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span>Orders</span>
                    </a>
                    <a href="wishlist?ref=account" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        <span>Wishlist</span>
                    </a>
                    <div class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl font-bold text-[11px] text-center transition-all bg-blue-50 text-[#003e86] relative">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        <span>Notifs</span>
                    </div>
                    <a href="account#settings" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Settings</span>
                    </a>
                    <a href="account?action=logout" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-rose-50 text-rose-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        <span>Logout</span>
                    </a>
                </div>

                <!-- Desktop App Menu List -->
                <div class="hidden md:flex bg-white rounded-[2rem] p-4 shadow-sm border border-slate-200 flex-col gap-2">
                    <a href="account#dashboard" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg> Dashboard</div>
                    </a>
                    <a href="account#orders" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> My Orders</div>
                    </a>
                    <a href="wishlist?ref=account" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg> My Wishlist</div>
                    </a>
                    <div class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm bg-blue-50 text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3 relative"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Notifications</div>
                    </div>
                    <a href="account#settings" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profile Settings</div>
                    </a>
                    <div class="my-1 border-t border-slate-100 mx-2"></div>
                    <a href="account?action=logout" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-rose-500 hover:bg-rose-50 transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Logout</div>
                    </a>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="md:col-span-9 space-y-4">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Your Notifications</h2>
                <?php if (empty($notifications)): ?>
                    <div class="p-10 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                        <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                        <p class="text-slate-500 font-medium">You don't have any notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                    <?php foreach ($notifications as $n): ?>
                        <div onclick="viewNotification(this)" data-type="<?php echo htmlspecialchars($n['type']); ?>" data-title="<?php echo htmlspecialchars($n['title']); ?>" data-message="<?php echo htmlspecialchars($n['message']); ?>" data-date="<?php echo date('M d, Y h:i A', strtotime($n['createdAt'])); ?>" class="cursor-pointer p-6 bg-white rounded-3xl border border-slate-200 shadow-sm flex gap-5 transition-all hover:shadow-md hover:border-blue-200 <?php echo !$n['isRead'] ? 'border-l-4 border-l-[#003e86]' : ''; ?>">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 <?php echo $n['type'] === 'order' ? 'bg-blue-100 text-[#003e86]' : 'bg-slate-100 text-slate-600'; ?>">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-slate-800 text-lg truncate"><?php echo htmlspecialchars($n['title']); ?></h3>
                                <p class="text-slate-600 text-sm mt-1 leading-relaxed line-clamp-2"><?php echo htmlspecialchars($n['message']); ?></p>
                                <span class="text-[11px] font-bold tracking-widest uppercase text-slate-400 mt-3 block"><?php echo date('M d, Y h:i A', strtotime($n['createdAt'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Standalone View -->
    <div class="max-w-[800px] mx-auto px-6">
        <h1 class="text-3xl font-black text-slate-900 mb-8 tracking-tight">Your Notifications</h1>
        <div class="space-y-4">
            <?php if (empty($notifications)): ?>
                <div class="p-10 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                    <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <p class="text-slate-500 font-medium">You don't have any notifications yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div onclick="viewNotification(this)" data-type="<?php echo htmlspecialchars($n['type']); ?>" data-title="<?php echo htmlspecialchars($n['title']); ?>" data-message="<?php echo htmlspecialchars($n['message']); ?>" data-date="<?php echo date('M d, Y h:i A', strtotime($n['createdAt'])); ?>" class="cursor-pointer p-6 bg-white rounded-3xl border border-slate-200 shadow-sm flex gap-5 transition-all hover:shadow-md hover:border-blue-200 <?php echo !$n['isRead'] ? 'border-l-4 border-l-[#003e86]' : ''; ?>">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 <?php echo $n['type'] === 'order' ? 'bg-blue-100 text-[#003e86]' : 'bg-slate-100 text-slate-600'; ?>">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-slate-800 text-lg truncate"><?php echo htmlspecialchars($n['title']); ?></h3>
                            <p class="text-slate-600 text-sm mt-1 leading-relaxed line-clamp-2"><?php echo htmlspecialchars($n['message']); ?></p>
                            <span class="text-[11px] font-bold tracking-widest uppercase text-slate-400 mt-3 block"><?php echo date('M d, Y h:i A', strtotime($n['createdAt'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Notification Details Modal -->
<div id="notification-modal" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-3xl overflow-hidden shadow-2xl animate-in zoom-in duration-200 flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 sticky top-0 z-10">
            <h2 class="font-bold text-slate-800 flex items-center gap-2">Notification Details</h2>
            <button type="button" onclick="closeNotificationModal()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 rounded-full transition-colors"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="overflow-y-auto p-6 flex-1 custom-scrollbar">
            <h3 class="font-black text-slate-800 text-xl mb-1.5" id="modal-notif-title"></h3>
            <p class="text-[10px] font-bold tracking-widest uppercase text-slate-400 mb-6 block" id="modal-notif-date"></p>
            <div class="text-slate-600 text-sm font-medium leading-relaxed whitespace-pre-wrap bg-slate-50 border border-slate-100 p-5 rounded-2xl" id="modal-notif-message"></div>
            <div id="modal-notif-extra"></div>
        </div>
    </div>
</div>

<script>
function viewNotification(element) {
    const title = element.getAttribute('data-title');
    const message = element.getAttribute('data-message');
    const date = element.getAttribute('data-date');
    const type = element.getAttribute('data-type') || 'info';

    document.getElementById('modal-notif-title').innerText = title;
    document.getElementById('modal-notif-message').innerText = message;
    document.getElementById('modal-notif-date').innerText = date;
    
    const extraContent = document.getElementById('modal-notif-extra');
    extraContent.innerHTML = '';

    const modal = document.getElementById('notification-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // If this is an order notification, dynamically fetch the ordered items!
    if (type === 'order') {
        const orderMatch = message.match(/#(\d+)/);
        if (orderMatch) {
            const orderId = orderMatch[1];
            extraContent.innerHTML = '<div class="flex justify-center py-6"><svg class="animate-spin h-6 w-6 text-[#003e86]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div>';
            
            fetch(`account.php?action=get_order_details&order_id=${orderId}`)
            .then(res => res.json())
            .then(result => {
                if (result.success && result.items) {
                    const escapeHtml = (unsafe) => {
                        return (unsafe || '').toString()
                            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                    };
                    let html = '<div class="mt-6 border-t border-slate-100 pt-6"><h4 class="text-xs font-black uppercase tracking-widest text-slate-800 mb-4">Ordered Items</h4><div class="space-y-3">';
                    result.items.forEach(item => {
                        let img = item.vImg ? item.vImg.split(',')[0] : (item.pImg ? item.pImg.split(',')[0] : 'https://placehold.co/100');
                        if (!img.startsWith('http')) img = (img.startsWith('/') ? '' : '/') + img;
                        html += `
                        <div class="flex items-center gap-3 p-3 bg-white border border-slate-100 rounded-xl shadow-sm">
                            <div class="w-12 h-12 rounded-lg bg-slate-50 border border-slate-100 overflow-hidden shrink-0">
                                <img src="${img}" class="w-full h-full object-cover mix-blend-multiply">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-slate-800 truncate text-xs">${escapeHtml(item.name)}</p>
                                <p class="text-[10px] font-bold text-slate-400 mt-0.5">${item.quantity} x ৳${Number(item.price).toLocaleString()}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-black text-slate-900 text-xs">৳${Number(item.price * item.quantity).toLocaleString()}</p>
                            </div>
                        </div>`;
                    });
                    html += '</div></div>';
                    html += `
                    <div class="mt-4 p-4 bg-slate-50 rounded-xl flex justify-between items-center text-sm border border-slate-100">
                        <span class="font-semibold text-slate-500">Order Total Amount</span>
                        <span class="font-black text-[#003e86]">৳${Number(result.order.total).toLocaleString()}</span>
                    </div>`;
                    extraContent.innerHTML = html;
                } else {
                    extraContent.innerHTML = '';
                }
            }).catch(() => { extraContent.innerHTML = ''; });
        }
    }
}

function closeNotificationModal() {
    const modal = document.getElementById('notification-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
<?php include 'Footer.php'; ?>