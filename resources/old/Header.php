<?php
/**
 * Header layout
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Placeholder for user login check. In a real app, this would be in a helper/bootstrap file.
if (!function_exists('isUserLoggedIn')) {
    function isUserLoggedIn() {
        // To test, you can return true. For a real app, check session variables.
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM SettingGeneral LIMIT 1");
    $storeSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $storeSettings = [];
}

$storeName = $storeSettings['storeName'] ?? "";

// Default (Global) meta from settings
$globalMetaTitle = !empty($storeSettings['metaTitle']) ? $storeSettings['metaTitle'] : $storeName;
$globalMetaDescription = !empty($storeSettings['metaDescription']) ? $storeSettings['metaDescription'] : "";

$logoUrl = !empty($storeSettings['logoUrl']) ? ltrim($storeSettings['logoUrl'], '/') : null;
$faviconUrl = !empty($storeSettings['faviconUrl']) ? $storeSettings['faviconUrl'] : null;

// Page-specific overrides (e.g., from product.php)
$finalMetaTitle = isset($pageMetaTitle) && !empty($pageMetaTitle) ? $pageMetaTitle : $globalMetaTitle;
$finalMetaDescription = isset($pageMetaDescription) && !empty($pageMetaDescription) ? $pageMetaDescription : $globalMetaDescription;

$fbPixelId = !empty($storeSettings['fbPixelId']) ? $storeSettings['fbPixelId'] : null;
$gaId = !empty($storeSettings['googleAnalyticsId']) ? $storeSettings['googleAnalyticsId'] : null;

try {
    $mobileCatStmt = $pdo->query("SELECT name, slug, imageUrl FROM Category WHERE status = 1 AND parentId IS NULL ORDER BY name ASC");
    $mobileCategories = $mobileCatStmt ? $mobileCatStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $mobileCategories = [];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . ($dir === '/' ? '/' : $dir . '/');
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $globalMetaImage = !empty($storeSettings['metaImage']) ? $baseUrl . ltrim($storeSettings['metaImage'], '/') : ($logoUrl ? $baseUrl . ltrim($logoUrl, '/') : '');
    $finalMetaImage = isset($pageMetaImage) && !empty($pageMetaImage) ? $baseUrl . ltrim($pageMetaImage, '/') : $globalMetaImage;
    ?>
    <base href="<?php echo $baseUrl; ?>">
    <title><?php echo htmlspecialchars($finalMetaTitle); ?></title>
    <?php if ($finalMetaDescription): ?>
    <meta name="description" content="<?php echo htmlspecialchars($finalMetaDescription); ?>">
    <?php endif; ?>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($finalMetaTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($finalMetaDescription); ?>">
    <?php if ($finalMetaImage): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($finalMetaImage); ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($currentUrl); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($finalMetaTitle); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($finalMetaDescription); ?>">
    <?php if ($finalMetaImage): ?>
    <meta property="twitter:image" content="<?php echo htmlspecialchars($finalMetaImage); ?>">
    <?php endif; ?>
    
    <?php if ($faviconUrl): ?>
    <link rel="icon" href="<?php echo htmlspecialchars(ltrim($faviconUrl, '/')); ?>">
    <?php endif; ?>

        <?php if ($fbPixelId): ?>
        <!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo htmlspecialchars($fbPixelId); ?>');
        fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($fbPixelId); ?>&ev=PageView&noscript=1"/></noscript>
        <!-- End Meta Pixel Code -->
        <?php endif; ?>

        <?php if ($gaId): ?>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($gaId); ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '<?php echo htmlspecialchars($gaId); ?>');
        </script>
        <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { -webkit-font-smoothing: antialiased; }
        
        @keyframes icon-pop {
            0% { transform: scale(1); }
            40% { transform: scale(1.25) rotate(-10deg); }
            75% { transform: scale(1.1) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        .animate-pop { animation: icon-pop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        /* Custom Scrollbars */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .product-scroll::-webkit-scrollbar { height: 6px; }
        .product-scroll::-webkit-scrollbar-track { background: transparent; }
        .product-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .product-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        @media (max-width: 768px) {
            .product-scroll::-webkit-scrollbar { display: none; }
            .product-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        }
    </style>
</head>
<body class="bg-[#f8f9fa] text-[#191c1d] font-sans">

<header class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-xl border-b border-slate-100 shadow-sm transition-all">
    <div class="flex items-center justify-between px-4 sm:px-6 py-3 max-w-[1200px] mx-auto gap-4">
        
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- 1. Brand Logo -->
            <a href="./" class="flex-shrink-0 block">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                         alt="<?php echo htmlspecialchars($storeName); ?>" 
                         class="h-8 sm:h-9 w-auto object-contain" />
                <?php else: ?>
                    <span class="text-2xl font-black tracking-tighter text-slate-900 uppercase">
                        <?php echo htmlspecialchars($storeName); ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>

        <!-- 2. Central Search Bar (Desktop) & Nav Links -->
        <div class="hidden lg:flex flex-1 items-center justify-center gap-8">
            <form action="shop" method="GET" class="flex-1 max-w-lg relative">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="w-full bg-slate-100 border-none rounded-full pl-11 pr-5 py-2.5 focus:ring-2 focus:ring-[#003e86] outline-none text-sm transition-all" />
            </form>
            <!-- 3. Desktop Navigation Links -->
            <nav class="flex items-center gap-6 text-sm font-bold text-slate-600">
            <a href="shop" class="flex items-center gap-2 hover:text-[#003e86] hover:bg-blue-50 px-3 py-1.5 rounded-xl transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                Products
            </a>
            <a href="flash-deals" class="flex items-center gap-1.5 hover:text-amber-700 hover:bg-amber-50 px-3 py-1.5 rounded-xl transition-all">
                <span class="text-amber-500 animate-pulse">⚡</span> Flash Deals
            </a>
            <a href="track-order" class="flex items-center gap-2 hover:text-[#003e86] hover:bg-blue-50 px-3 py-1.5 rounded-xl transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Track Order
            </a>
            </nav>
        </div>

        <!-- Search Bar for Mobile/Tablet -->
        <div class="flex lg:hidden flex-1 justify-center px-4">
            <form action="shop" method="GET" class="w-full max-w-xs relative">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="w-full bg-slate-100 border-none rounded-full pl-10 pr-4 py-2 focus:ring-2 focus:ring-[#003e86] outline-none text-sm transition-all" />
            </form>
        </div>

        <!-- 4. Icons Group (Hidden on Mobile, visible on md and up) -->
        <div class="hidden md:flex items-center gap-0 sm:gap-1">
            <!-- Wishlist -->
            <a href="wishlist" class="relative p-2.5 rounded-full text-slate-600 hover:text-[#003e86] hover:bg-blue-50 transition-all duration-300" title="Wishlist">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                <span id="header-wishlist-count" class="absolute top-1 right-0 bg-rose-500 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full border-2 border-white shadow-sm hidden">0</span>
            </a>
            <!-- Profile / Login -->
            <a href="<?php echo isUserLoggedIn() ? 'account' : 'login'; ?>" class="p-2.5 rounded-full text-slate-600 hover:text-[#003e86] hover:bg-blue-50 transition-all duration-300" title="Account">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <!-- Cart -->
            <a href="cart" class="relative p-2.5 rounded-full text-slate-600 hover:text-[#003e86] hover:bg-blue-50 transition-all duration-300" title="Cart">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                <span id="header-cart-count" class="absolute top-1 right-0 bg-[#003e86] text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full border-2 border-white shadow-sm hidden">0</span>
            </a>
            <!-- Notifications (Conditional & hidden on mobile) -->
            <?php if (isUserLoggedIn()): ?>
            <a href="notifications" class="relative p-2.5 rounded-full text-slate-600 hover:text-[#003e86] hover:bg-blue-50 transition-all duration-300 hidden sm:flex" title="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                <?php if (isset($userUnreadNotifs) && $userUnreadNotifs > 0): ?>
                <span class="absolute top-2 right-2.5 bg-[#ba1a1a] w-2 h-2 rounded-full border border-white"></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>

    </div>

</header>

<!-- Mobile Drawer Overlay -->
<div id="mobile-drawer-overlay" onclick="toggleMobileMenu()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[90] hidden transition-opacity lg:hidden"></div>

<!-- Mobile Drawer Menu -->
<div id="mobile-drawer" class="fixed top-0 left-0 h-full w-72 bg-white z-[100] transform -translate-x-full transition-transform duration-300 flex flex-col lg:hidden shadow-2xl">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <h2 class="font-black text-slate-800 text-lg uppercase tracking-wider">Menu</h2>
        <button onclick="toggleMobileMenu()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-800 rounded-full transition-colors">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto custom-scrollbar p-5 space-y-6">
        <div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Quick Links</h3>
            <ul class="space-y-2 mb-6 border-b border-slate-100 pb-6">
                <li>
                    <a href="shop" class="flex items-center gap-3 text-sm font-bold text-slate-700 hover:text-[#003e86] p-2 rounded-xl hover:bg-blue-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#003e86] flex items-center justify-center shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                        </div>
                        All Products
                    </a>
                </li>
                <li>
                    <a href="flash-deals" class="flex items-center gap-3 text-sm font-bold text-slate-700 hover:text-amber-600 p-2 rounded-xl hover:bg-amber-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </div>
                        Flash Deals
                    </a>
                </li>
                <li>
                    <a href="track-order" class="flex items-center gap-3 text-sm font-bold text-slate-700 hover:text-emerald-600 p-2 rounded-xl hover:bg-emerald-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        Track Order
                    </a>
                </li>
            </ul>
        </div>
        <div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Categories</h3>
            <ul class="space-y-2">
                <?php foreach($mobileCategories as $mCat): ?>
                <li>
                    <div class="flex items-center justify-between p-2 rounded-xl hover:bg-blue-50 transition-colors group">
                        <a href="shop?category=<?php echo htmlspecialchars($mCat['slug']); ?>" class="flex items-center gap-3 text-sm font-bold text-slate-700 group-hover:text-[#003e86] flex-1">
                            <div class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden shrink-0">
                                <?php if(!empty($mCat['imageUrl'])): ?>
                                    <img src="<?php echo htmlspecialchars(ltrim($mCat['imageUrl'], '/')); ?>" class="w-full h-full object-cover mix-blend-multiply">
                                <?php else: ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <?php endif; ?>
                            </div>
                            <?php echo htmlspecialchars($mCat['name']); ?>
                        </a>
                        <?php if(!empty($mCat['subCategories'])): ?>
                        <button type="button" onclick="document.getElementById('mob-sub-<?php echo $mCat['id']; ?>').classList.toggle('hidden'); this.querySelector('svg').classList.toggle('rotate-180');" class="p-2 text-slate-400 hover:text-[#003e86]">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="transition-transform duration-300"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if(!empty($mCat['subCategories'])): ?>
                    <ul id="mob-sub-<?php echo $mCat['id']; ?>" class="hidden mt-1 mb-2 space-y-2 border-l-2 border-slate-100 ml-6 pl-4">
                        <?php foreach($mCat['subCategories'] as $mSub): ?>
                        <li>
                            <a href="shop?category=<?php echo htmlspecialchars($mSub['slug']); ?>" class="block text-sm font-semibold text-slate-500 hover:text-[#003e86] py-1 transition-colors">
                                <?php echo htmlspecialchars($mSub['name']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php if(empty($mobileCategories)): ?>
                <li class="text-sm text-slate-400">No categories found</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<script>
// Global Cart & Wishlist Counter Script
function updateGlobalBadges() {
    // Cart Badge
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartCount = cart.reduce((acc, item) => acc + item.quantity, 0);
    const cartBadge = document.getElementById('header-cart-count');
    if (cartBadge) {
        cartBadge.innerText = cartCount;
        if (cartCount > 0) cartBadge.classList.remove('hidden');
        else cartBadge.classList.add('hidden');
    }

    const mobileCartBadge = document.getElementById('mobile-bottom-cart-count');
    if (mobileCartBadge) {
        mobileCartBadge.innerText = cartCount;
        if (cartCount > 0) mobileCartBadge.classList.remove('hidden');
        else mobileCartBadge.classList.add('hidden');
    }

    // Wishlist Badge
    const wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
    const wishlistBadge = document.getElementById('header-wishlist-count');
    if (wishlistBadge) {
        wishlistBadge.innerText = wishlist.length;
        if (wishlist.length > 0) wishlistBadge.classList.remove('hidden');
        else wishlistBadge.classList.add('hidden');
    }

    const mobileWishlistBadge = document.getElementById('mobile-bottom-wishlist-count');
    if (mobileWishlistBadge) {
        mobileWishlistBadge.innerText = wishlist.length;
        if (wishlist.length > 0) mobileWishlistBadge.classList.remove('hidden');
        else mobileWishlistBadge.classList.add('hidden');
    }
}

window.toggleMobileMenu = function() {
    const menu = document.getElementById('mobile-drawer');
    const overlay = document.getElementById('mobile-drawer-overlay');
    if (menu) menu.classList.toggle('-translate-x-full');
    if (overlay) overlay.classList.toggle('hidden');
};

window.flyToIcon = function(imageSrc, event, targetSelector) {
    if (!event) return;
    
    const targetIcons = document.querySelectorAll(targetSelector);
    let targetIcon = null;
    targetIcons.forEach(icon => {
        const rect = icon.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            targetIcon = icon; // Selects the visible icon based on device screen
        }
    });
    
    if (!targetIcon) return;
    
    const flyingImg = document.createElement('img');
    flyingImg.src = imageSrc;
    flyingImg.style.position = 'fixed';
    flyingImg.style.zIndex = '9999';
    flyingImg.style.width = '60px';
    flyingImg.style.height = '60px';
    flyingImg.style.borderRadius = '50%';
    flyingImg.style.objectFit = 'cover';
    flyingImg.style.left = (event.clientX - 30) + 'px';
    flyingImg.style.top = (event.clientY - 30) + 'px';
    
    // Next.js/Framer Motion style ultra-smooth slow deceleration curve
    flyingImg.style.transition = 'all 1.5s cubic-bezier(0.16, 1, 0.3, 1), opacity 1.5s ease-out';
    flyingImg.style.boxShadow = '0 25px 50px -12px rgba(0,0,0,0.5)';
    flyingImg.style.pointerEvents = 'none';
    flyingImg.style.transform = 'scale(1) rotate(0deg)';
    flyingImg.style.opacity = '1';
    
    document.body.appendChild(flyingImg);
    
    // Force reflow
    flyingImg.offsetHeight;
    
    const targetRect = targetIcon.getBoundingClientRect();
    
    flyingImg.style.left = (targetRect.left + targetRect.width / 2 - 10) + 'px';
    flyingImg.style.top = (targetRect.top + targetRect.height / 2 - 10) + 'px';
    flyingImg.style.width = '20px';
    flyingImg.style.height = '20px';
    flyingImg.style.transform = 'scale(0.3) rotate(15deg)';
    flyingImg.style.opacity = '0';
    
    setTimeout(() => {
        flyingImg.remove();
        
        // Add Pop Animation to the Icon
        targetIcon.classList.add('animate-pop');
        setTimeout(() => {
            targetIcon.classList.remove('animate-pop');
        }, 400);
    }, 1500);
};

window.flyToCart = function(imageSrc, event) {
    window.flyToIcon(imageSrc, event, 'a[href="cart"]');
};

window.flyToWishlist = function(imageSrc, event) {
    window.flyToIcon(imageSrc, event, 'a[href="wishlist"]');
};

window.quickAddToCart = function(id, name, price, image, slug, event) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    const existingIndex = cart.findIndex(item => 
        (item.productId == id || item.id == id) && 
        (!item.size || item.size === 'Standard') && 
        (!item.color || item.color === 'Default')
    );

    if (existingIndex >= 0) {
        return;
    } else {
        cart.push({
            productId: id,
            name: name,
            price: price,
            image: image,
            size: 'Standard',
            color: 'Default',
            quantity: 1,
            slug: slug
        });
        
        localStorage.setItem('cart', JSON.stringify(cart));
        window.dispatchEvent(new Event('cartUpdated'));
        
        if (event && window.flyToCart) {
            flyToCart(image, event);
        }
    }
};

    window.toggleWishlist = function(id, name, image, price, slug, event) {
        let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        const index = wishlist.findIndex(item => item.id === id);
        
        if (index >= 0) {
            wishlist.splice(index, 1);
            if (window.showToast) showToast("Removed from Wishlist", "info");
        } else {
            wishlist.push({ id, name, image, price, slug });
            if (event && window.flyToWishlist) {
                flyToWishlist(image, event);
            }
            if (window.showToast) showToast("Added to Wishlist", "success");
        }
        
        localStorage.setItem('wishlist', JSON.stringify(wishlist));
        window.dispatchEvent(new Event('wishlistUpdated'));
    };
    
    // Auto update Wishlist buttons to red color when added
    window.updateAllWishlistButtons = function() {
        const wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        const buttons = document.querySelectorAll('.wishlist-btn-global');
        
        buttons.forEach(btn => {
            const pid = btn.getAttribute('data-product-id');
            const exists = wishlist.some(item => (item.id == pid));
            
            if (exists) {
                btn.classList.add('text-rose-500');
                btn.classList.remove('text-slate-400');
                btn.querySelector('svg').setAttribute('fill', 'currentColor');
            } else {
                btn.classList.add('text-slate-400');
                btn.classList.remove('text-rose-500');
                btn.querySelector('svg').setAttribute('fill', 'none');
            }
        });
    };

document.addEventListener('DOMContentLoaded', updateGlobalBadges);
    document.addEventListener('DOMContentLoaded', window.updateAllWishlistButtons);
window.addEventListener('cartUpdated', updateGlobalBadges);
window.addEventListener('wishlistUpdated', updateGlobalBadges);
    window.addEventListener('wishlistUpdated', window.updateAllWishlistButtons);

// Make sure Add To Cart buttons change everywhere dynamically!
window.updateAllCartButtons = function() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const buttons = document.querySelectorAll('.quick-add-to-cart-btn');
    
    buttons.forEach(btn => {
        const pid = btn.getAttribute('data-product-id');
        const exists = cart.some(item => (item.productId == pid || item.id == pid));
        
        if (exists) {
            btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>`;
            btn.classList.remove('bg-blue-50', 'text-[#003e86]', 'hover:bg-[#003e86]', 'hover:text-white');
            btn.classList.add('bg-emerald-500', 'text-white', 'hover:bg-emerald-600');
            btn.title = "Added to Cart";
        } else {
            btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`;
            btn.classList.remove('bg-emerald-500', 'text-white', 'hover:bg-emerald-600');
            btn.classList.add('bg-blue-50', 'text-[#003e86]', 'hover:bg-[#003e86]', 'hover:text-white');
            btn.title = "Add to Cart";
        }
    });
};
document.addEventListener('DOMContentLoaded', window.updateAllCartButtons);
window.addEventListener('cartUpdated', window.updateAllCartButtons);

// Global Toast Notification (if not defined by page)
window.showToast = window.showToast || function(message, type = 'success') {
    if (window.innerWidth < 768 && type !== 'error') return; // Hide non-error toasts on mobile

    let container = document.getElementById('global-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'global-toast-container';
        container.className = 'fixed bottom-24 md:bottom-8 left-4 right-4 md:left-auto md:right-8 z-[100] space-y-2 md:space-y-4 flex flex-col items-center md:items-end pointer-events-none';
        document.body.appendChild(container);
    }
    const bg = type === 'success' ? 'bg-[#003e86]' : (type === 'error' ? 'bg-rose-600' : 'bg-amber-500');
    const toast = document.createElement('div');
    toast.className = `flex items-center justify-center gap-2 md:gap-3 px-4 md:px-6 py-3 md:py-4 rounded-xl md:rounded-2xl shadow-xl text-white transition-all duration-300 animate-in slide-in-from-bottom-2 md:slide-in-from-right pointer-events-auto ${bg} max-w-sm w-full md:w-auto text-center md:text-left`;
    toast.innerHTML = `<span class="text-xs md:text-sm font-bold tracking-tight">${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(1rem)'; setTimeout(() => toast.remove(), 300); }, 3000);
};

// Global Confirmation Modal
window.customConfirm = window.customConfirm || function(message, onConfirm, onCancel = () => {}) {
    let modal = document.getElementById('global-confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'global-confirm-modal';
        modal.className = 'fixed inset-0 z-[110] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4';
        modal.innerHTML = `<div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200"><div class="p-6 text-center mt-4"><div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></div><h3 class="text-xl font-bold text-slate-800 mb-2">Are you sure?</h3><p id="global-confirm-message" class="text-sm text-slate-500 font-medium"></p></div><div class="p-4 bg-slate-50 flex gap-3 border-t border-slate-100 mt-2"><button id="global-confirm-cancel" class="flex-1 py-3 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button><button id="global-confirm-ok" class="flex-1 py-3 text-sm font-bold text-white bg-rose-600 rounded-xl shadow-md hover:bg-rose-700 transition-colors">Yes, Proceed</button></div></div>`;
        document.body.appendChild(modal);
    }
    document.getElementById('global-confirm-message').innerText = message;
    modal.style.display = 'flex';

    const btnOk = document.getElementById('global-confirm-ok');
    const btnCancel = document.getElementById('global-confirm-cancel');
    const newBtnOk = btnOk.cloneNode(true); const newBtnCancel = btnCancel.cloneNode(true);
    btnOk.parentNode.replaceChild(newBtnOk, btnOk); btnCancel.parentNode.replaceChild(newBtnCancel, btnCancel);

    newBtnOk.addEventListener('click', () => { modal.style.display = 'none'; if(onConfirm) onConfirm(); });
    newBtnCancel.addEventListener('click', () => { modal.style.display = 'none'; if(onCancel) onCancel(); });
};

// Drag to scroll for product sliders on desktop
document.addEventListener('DOMContentLoaded', () => {
    const sliders = document.querySelectorAll('.product-scroll');
    sliders.forEach(slider => {
        let isDown = false;
        let startX;
        let scrollLeft;
        let isDragging = false;
        
        slider.style.cursor = 'grab';

        // Prevent native dragging of images which interferes with mouse sliding
        slider.querySelectorAll('img, a').forEach(el => {
            el.addEventListener('dragstart', (e) => e.preventDefault());
        });

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            isDragging = false;
            slider.style.cursor = 'grabbing';
            slider.style.scrollSnapType = 'none';
            slider.style.scrollBehavior = 'auto';
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', () => { 
            if (!isDown) return;
            isDown = false; 
            slider.style.cursor = 'grab'; 
            slider.style.scrollSnapType = '';
            slider.style.scrollBehavior = 'smooth';
        });
        slider.addEventListener('mouseup', () => { 
            isDown = false; 
            slider.style.cursor = 'grab'; 
            slider.style.scrollSnapType = '';
            slider.style.scrollBehavior = 'smooth';
        });
        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            isDragging = true;
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 1.5; // Scroll speed
            slider.scrollLeft = scrollLeft - walk;
        });
        slider.querySelectorAll('a, button').forEach(el => {
            el.addEventListener('click', (e) => { 
                if (isDragging) { e.preventDefault(); e.stopPropagation(); } 
            });
        });
    });
});
</script>