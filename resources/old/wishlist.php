<?php
session_start();
require_once __DIR__ . '/db.php';

$isAccountRef = isset($_GET['ref']) && $_GET['ref'] === 'account';
$userId = $_SESSION['user_id'] ?? null;

if ($isAccountRef && !$userId) {
    header("Location: login");
    exit;
}

if ($userId) {
    $stmt = $pdo->prepare("SELECT * FROM User WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE userId = ? AND isRead = 0");
    $notifStmt->execute([$userId]);
    $unreadCount = $notifStmt->fetchColumn();
}

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
                    <div class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl font-bold text-[11px] text-center transition-all bg-blue-50 text-[#003e86]">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        <span>Wishlist</span>
                    </div>
                    <a href="notifications?ref=account" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all relative">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        <span>Notifs</span>
                    </a>
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
                    <div class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm bg-blue-50 text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg> My Wishlist</div>
                    </div>
                    <a href="notifications?ref=account" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3 relative"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Notifications</div>
                        <?php if(isset($unreadCount) && $unreadCount > 0): ?>
                        <span class="bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
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
            <div class="md:col-span-9">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Your Wishlist</h2>
                <div id="wishlist-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                    <!-- JS Injected items go here -->
                </div>
                
                <div id="empty-wishlist" class="hidden p-12 text-center bg-white rounded-3xl border border-slate-200 shadow-sm mt-4">
                    <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Your wishlist is empty</h3>
                    <p class="text-sm text-slate-500 mb-6">Save your favorite items here and find them later.</p>
                    <a href="./" class="bg-rose-500 text-white px-6 py-3 rounded-xl font-bold inline-block hover:bg-rose-600 transition-colors shadow-lg shadow-rose-500/20">Explore Products</a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Standalone View -->
    <div class="max-w-[1200px] mx-auto px-6">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                My Wishlist
            </h1>
        </div>
        
        <div id="wishlist-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-6">
            <!-- JS Injected items go here -->
        </div>
        
        <div id="empty-wishlist" class="hidden p-12 text-center bg-white rounded-3xl border border-slate-200 shadow-sm max-w-2xl mx-auto mt-10">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Your wishlist is empty</h3>
            <p class="text-sm text-slate-500 mb-6">Save your favorite items here and find them later.</p>
            <a href="./" class="bg-rose-500 text-white px-6 py-3 rounded-xl font-bold inline-block hover:bg-rose-600 transition-colors shadow-lg shadow-rose-500/20">Explore Products</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function renderWishlist() {
    let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
    const container = document.getElementById('wishlist-container');
    const emptyState = document.getElementById('empty-wishlist');
    let html = '';

    if (wishlist.length === 0) {
        container.innerHTML = '';
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
    } else {
        emptyState.classList.add('hidden');
        container.classList.remove('hidden');
        wishlist.forEach((item, index) => {
            html += `
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden hover:shadow-xl hover:shadow-slate-200/50 transition-all duration-300 group flex flex-col relative">
                <button onclick="removeWishlistItem(${index})" class="absolute top-3 right-3 z-10 bg-white/90 text-rose-500 p-2 rounded-full shadow-sm hover:bg-rose-500 hover:text-white transition-colors" title="Remove">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
                <a href="product?slug=${item.slug}" class="relative aspect-square overflow-hidden bg-slate-50 block p-6">
                    <img src="${item.image}" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500 mix-blend-multiply">
                </a>
                <div class="p-5 flex flex-col flex-1 border-t border-slate-50">
                    <a href="product?slug=${item.slug}" class="font-bold text-slate-800 text-sm mb-3 line-clamp-2 hover:text-[#003e86] transition-colors flex-1">${item.name}</a>
                    <div class="flex items-end justify-between mt-auto pt-2">
                        <span class="font-black text-lg text-[#003e86]">৳${item.price.toLocaleString()}</span>
                        <a href="product?slug=${item.slug}" class="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-[#003e86] hover:text-white transition-colors">View</a>
                    </div>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    }
}

function removeWishlistItem(index) {
    let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
    wishlist.splice(index, 1);
    localStorage.setItem('wishlist', JSON.stringify(wishlist));
    window.dispatchEvent(new Event('wishlistUpdated'));
    renderWishlist();
    showToast("Removed from Wishlist", "success");
}

document.addEventListener('DOMContentLoaded', renderWishlist);
</script>
<?php include 'Footer.php'; ?>