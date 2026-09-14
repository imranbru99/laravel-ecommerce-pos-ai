<?php
/**
 * 1. DATA FETCHING (Connected to Database)
 */
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

$isInstalled = false;
if (isset($pdo)) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'SettingGeneral'");
        if ($check && $check->rowCount() > 0) {
            $isInstalled = true;
        }
    } catch (Exception $e) {}
}

if (!$isInstalled && file_exists(__DIR__ . '/install.php')) {
    header("Location: install.php");
    exit;
}

// Initialize default variables to prevent page crashes
$settings = [];
$symbol = "৳";
$banners = []; 
$popupBanner = null;
$allCategories = []; 
$flashDealsProducts = []; 
$latestProducts = []; 
$bestSellingProducts = [];

try {

// Settings
$stmt = $pdo->query("SELECT * FROM SettingGeneral LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$symbol = $settings['currencySymbol'] ?? "৳";

// Helper to fetch products with variants
if (!function_exists('getProductsWithVariants')) {
    function getProductsWithVariants($pdo, $condition = "1", $limit = 10, $orderBy = "id DESC") {
        $stmt = $pdo->query("SELECT * FROM Product WHERE $condition ORDER BY $orderBy LIMIT $limit");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$p) {
            $vStmt = $pdo->prepare("SELECT * FROM Variant WHERE productId = ?");
            $vStmt->execute([$p['id']]);
            $p['variants'] = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $products;
    }
}

// Fetching sections
$latestProducts     = getProductsWithVariants($pdo, "1", 10, "id DESC");

$bannerStmt         = $pdo->query("SELECT * FROM Banner WHERE active = 1 AND (resourceType = 'campaign' OR resourceType IS NULL) ORDER BY id DESC");
$banners            = $bannerStmt->fetchAll(PDO::FETCH_ASSOC);

$popupStmt          = $pdo->query("SELECT * FROM Banner WHERE active = 1 AND resourceType = 'popup' ORDER BY id DESC LIMIT 1");
$popupBanner        = $popupStmt->fetch(PDO::FETCH_ASSOC);

$flashDealsProducts = getProductsWithVariants($pdo, "isFlashDeal = 1", 10, "id DESC");

// Fetch and organize categories (Main and Sub)
$catStmt            = $pdo->query("SELECT * FROM Category WHERE status = 1 ORDER BY name ASC");
$allCategoriesRaw   = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$allCategories = [];
foreach($allCategoriesRaw as $cat) {
    if(empty($cat['parentId'])) {
        $cat['subCategories'] = [];
        $allCategories[$cat['id']] = $cat;
    }
}
foreach($allCategoriesRaw as $cat) {
    if(!empty($cat['parentId']) && isset($allCategories[$cat['parentId']])) {
        $allCategories[$cat['parentId']]['subCategories'][] = $cat;
    }
}

$bestSellingProducts = getProductsWithVariants($pdo, "1", 10, "basePrice DESC"); // Example sorting logic for Best Sellers

} catch (Exception $e) {
    // Silent catch for missing tables during early load
}

// Real Render Functions maintaining the layout structure
if (!function_exists('renderCategorySidebar')) {
    function renderCategorySidebar($categories) { 
        echo "<div class='bg-white py-4 rounded-xl shadow-sm border border-[#c2c6d4] h-full flex flex-col relative z-40'>";
        echo "<div class='mx-3 mb-3 px-4 py-3 bg-blue-50/80 rounded-xl flex items-center gap-3 border border-blue-100/50'>";
        echo "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#003e86' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><rect width='7' height='7' x='3' y='3' rx='1'/><rect width='7' height='7' x='14' y='3' rx='1'/><rect width='7' height='7' x='14' y='14' rx='1'/><rect width='7' height='7' x='3' y='14' rx='1'/></svg>";
        echo "<h3 class='font-black text-[#003e86] uppercase text-xs tracking-widest'>Categories</h3>";
        echo "</div>";
        echo "<ul class='flex-1 space-y-1 px-3'>";
        if(empty($categories)) echo "<li class='px-2 text-sm text-slate-400'>No categories found</li>";
        foreach($categories as $cat) {
            $hasSub = !empty($cat['subCategories']);
            echo "<li class='group relative'>";
            echo "<a href='shop?category=".htmlspecialchars($cat['slug'])."' class='flex items-center justify-between px-3 py-2.5 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:text-[#003e86] font-medium transition-colors'>";
            echo "<span>".htmlspecialchars($cat['name'])."</span>";
            if($hasSub) echo "<svg class='w-4 h-4 text-slate-400 group-hover:text-[#003e86] transition-colors' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='m9 18 6-6-6-6'/></svg>";
            echo "</a>";
            if($hasSub) {
                echo "<div class='absolute left-full top-0 ml-1 w-56 bg-white border border-slate-200 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-[100] overflow-hidden'><ul class='py-2'>";
                foreach($cat['subCategories'] as $sub) echo "<li><a href='shop?category=".htmlspecialchars($sub['slug'])."' class='block px-5 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-[#003e86] transition-colors'>".htmlspecialchars($sub['name'])."</a></li>";
                echo "</ul></div>";
            }
            echo "</li>";
        }
        echo "</ul></div>";
    }
    
    function renderHeroSlider($banners) { 
        if(empty($banners)) {
            echo "<div class='w-full h-full bg-slate-100 flex items-center justify-center font-bold text-slate-400'>No Active Banners</div>";
            return;
        }
        echo "<div class='w-full h-full relative overflow-hidden group' id='hero-slider'>";
        echo "<div class='flex w-full h-full transition-transform duration-700 ease-out' id='hero-slider-track'>";
        foreach($banners as $ban) {
            $img = $ban['imageUrl'] ? ltrim($ban['imageUrl'], '/') : 'https://placehold.co/1200x400';
            $url = $ban['targetUrl'] ?: '#';
            echo "<a href='".htmlspecialchars($url)."' class='w-full h-full shrink-0 block relative'>";
            echo "<img src='".htmlspecialchars($img)."' class='w-full h-full object-cover' alt='".htmlspecialchars($ban['title'])."' />";
            echo "</a>";
        }
        echo "</div>";
        if(count($banners) > 1) {
            echo "<div class='absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 z-10'>";
            foreach($banners as $idx => $ban) {
                $activeClass = $idx === 0 ? 'w-8 bg-[#003e86]' : 'w-2 bg-white/60 hover:bg-white';
                echo "<button onclick='goToSlide({$idx})' class='h-2 rounded-full transition-all {$activeClass}' data-slide='{$idx}'></button>";
            }
            echo "</div>";
        }
        echo "</div>";
    }
}


// 2. TEMPLATE START
include 'Header.php'; 
?>

<div class="min-h-screen pt-24 pb-28 md:pb-20">
    <main class="max-w-[1200px] mx-auto px-6 py-6 space-y-12">
        
        <!-- Hero Slider Area -->
        <section class="grid grid-cols-12 gap-4">
            <div class="col-span-12 lg:col-span-3 hidden lg:block relative z-20">
                <?php renderCategorySidebar($allCategories); ?>
            </div>
            <div class="col-span-12 lg:col-span-9">
                <div class="rounded-xl overflow-hidden shadow-sm border border-[#c2c6d4] aspect-[16/9] sm:aspect-[21/9] lg:aspect-auto lg:h-[400px]">
                    <?php renderHeroSlider($banners); ?>
                </div>
            </div>
        </section>

        <!-- Categories Section -->
        <?php if (!empty($allCategories)): ?>
        <section id="shop-by-category">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl md:text-2xl font-black text-slate-900 border-l-4 border-[#003e86] pl-3 md:pl-4 tracking-tight">Shop by Category</h3>
            </div>
            <div class="flex overflow-x-auto gap-4 sm:gap-6 pb-6 pt-2 snap-x snap-mandatory product-scroll">
                <?php foreach($allCategories as $cat): 
                    $catImg = !empty($cat['imageUrl']) ? ltrim($cat['imageUrl'], '/') : 'https://placehold.co/200x200?text='.urlencode($cat['name']);
                ?>
                <a href="shop?category=<?php echo htmlspecialchars($cat['slug']); ?>" class="w-[100px] sm:w-[120px] shrink-0 snap-start flex flex-col items-center gap-3 group">
                    <div class="w-full aspect-square bg-white rounded-full border border-slate-200 overflow-hidden shadow-sm group-hover:shadow-md group-hover:border-[#003e86]/30 transition-all duration-300 p-3 md:p-5 flex items-center justify-center group-hover:-translate-y-1">
                        <img src="<?php echo htmlspecialchars($catImg); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-300 mix-blend-multiply">
                    </div>
                    <span class="text-xs sm:text-sm font-bold text-slate-700 text-center group-hover:text-[#003e86] transition-colors line-clamp-2"><?php echo htmlspecialchars($cat['name']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Flash Deals -->
        <?php 
        $flashEndTime = isset($settings['flashDealEndTime']) ? strtotime($settings['flashDealEndTime']) : 0;
        if ($flashEndTime > time() && !empty($flashDealsProducts)): 
        ?>
        <section id="flash-deals" class="scroll-mt-32">
            <div class="flex items-start sm:items-center justify-between mb-5 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6">
                    <h3 class="text-xl md:text-2xl font-black text-slate-900 border-l-4 border-[#003e86] pl-3 md:pl-4 tracking-tight leading-none pt-1 sm:pt-0">Flash Deals</h3>
                    <div id="flash-deal-timer" data-endtime="<?php echo date('c', $flashEndTime); ?>" class="flex items-center gap-1.5 sm:gap-2"></div>
                </div>
                <a href="flash-deals" class="flex items-center gap-1.5 bg-white text-slate-800 px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-full font-bold text-[11px] sm:text-sm hover:bg-slate-50 transition-all active:scale-95 shadow-sm border border-slate-200 hover:border-slate-300 shrink-0 mt-0.5 sm:mt-0">
                    <span class="hidden sm:block">View All Deals</span>
                    <span class="sm:hidden">View All</span> 
                    <svg width="14" height="14" class="sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            
            <div class="flex overflow-x-auto gap-4 pb-6 pt-2 snap-x snap-mandatory product-scroll">
                <?php foreach ($flashDealsProducts as $product): 
                        $firstImg = $product['imageUrl'] ? ltrim(explode(',', $product['imageUrl'])[0], '/') : null;
                        $img = $firstImg ?: (!empty($product['variants'][0]['imageUrl']) ? ltrim($product['variants'][0]['imageUrl'], '/') : "https://placehold.co/600x600?text=No+Image");
                        $oldPrice = $product['basePrice'];
                        $newPrice = $product['flashDealPrice'] ?? $product['basePrice'];
                        $discount = ($oldPrice > $newPrice) ? round((($oldPrice - $newPrice) / $oldPrice) * 100) : 0;
                        
                        $totalStock = array_sum(array_column($product['variants'] ?? [], 'stock'));
                        $initialStock = array_sum(array_map(function($v) { return $v['initialStock'] ?? $v['stock'] ?? 1; }, $product['variants'] ?? []));
                        $sold = max(0, $initialStock - $totalStock);
                        $widthPercent = min(100, round(($sold / max(1, $initialStock)) * 100)) . '%';
                    ?>
                        <div class="w-[170px] sm:w-[210px] shrink-0 snap-start bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl hover:shadow-[#003e86]/10 transition-all duration-300 group flex flex-col hover:-translate-y-1 relative">
                            <?php if ($discount > 0): ?>
                                <div class="absolute top-2.5 left-2.5 z-10 bg-[#ba1a1a] text-white text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm w-fit">
                                    -<?php echo $discount; ?>% OFF
                                </div>
                            <?php endif; ?>
                            
                            <button type="button" onclick="toggleWishlist(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', '<?php echo htmlspecialchars($img); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="wishlist-btn-global absolute top-2.5 right-2.5 z-30 p-2 bg-white/90 backdrop-blur-sm text-slate-400 rounded-full shadow-sm hover:shadow-md hover:text-rose-500 transition-all" data-product-id="<?php echo $product['id']; ?>" title="Add to Wishlist">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                            </button>

                            <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="relative aspect-square overflow-hidden bg-slate-50 block p-3">
                                <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500 drop-shadow-sm mix-blend-multiply">
                                <div class="absolute inset-0 bg-white/30 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-center justify-center z-20">
                                    <div class="bg-white text-[#003e86] p-2.5 rounded-full shadow-lg transform scale-50 group-hover:scale-110 transition-transform duration-300">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </div>
                                </div>
                            </a>
                            <div class="p-3 sm:p-4 flex flex-col flex-1 border-t border-slate-50">
                                <div class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($product['categoryName'] ?? 'Uncategorized'); ?></div>
                                <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="font-bold text-slate-800 text-[13px] sm:text-sm mb-2 line-clamp-2 hover:text-[#003e86] transition-colors flex-1 leading-snug">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                                <div class="mt-auto flex flex-col gap-2.5">
                                    <div class="flex items-end justify-between pt-1.5 border-t border-slate-50">
                                        <div class="flex flex-col">
                                            <?php if($discount > 0): ?>
                                                <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 line-through"><?php echo $symbol . number_format($oldPrice); ?></span>
                                            <?php endif; ?>
                                            <span class="font-black text-base sm:text-lg text-[#003e86]"><?php echo $symbol . number_format($newPrice); ?></span>
                                        </div>
                                        <button data-product-id="<?php echo $product['id']; ?>" onclick="quickAddToCart(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="quick-add-to-cart-btn w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-50 text-[#003e86] flex items-center justify-center hover:bg-[#003e86] hover:text-white transition-all shadow-sm z-30 shrink-0" title="Add to Cart">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                        </button>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-[9px] sm:text-[10px] font-semibold text-slate-500 mb-1">
                                            <span class="text-[#ba1a1a]">Sold: <?php echo $sold; ?></span>
                                            <span>Left: <?php echo $totalStock; ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                                            <div class="bg-gradient-to-r from-[#003e86] to-blue-400 h-1 rounded-full" style="width: <?php echo $widthPercent; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- New Arrivals -->
        <?php if (!empty($latestProducts)): ?>
        <section id="new-arrivals" class="scroll-mt-32">
            <div class="flex items-center justify-between mb-5 sm:mb-6">
                <h3 class="text-xl md:text-2xl font-black text-slate-900 border-l-4 border-[#003e86] pl-3 md:pl-4 tracking-tight">New Arrivals</h3>
                <a href="shop?sort=newest" class="flex items-center gap-1.5 bg-white text-slate-800 px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-full font-bold text-[11px] sm:text-sm hover:bg-slate-50 transition-all shadow-sm border border-slate-200 shrink-0">
                    <span class="hidden sm:block">View All Products</span>
                    <span class="sm:hidden">View All</span>
                    <svg width="14" height="14" class="sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="flex overflow-x-auto gap-4 pb-6 pt-2 snap-x snap-mandatory product-scroll">
                <?php foreach($latestProducts as $product): 
                    $firstImg = $product['imageUrl'] ? ltrim(explode(',', $product['imageUrl'])[0], '/') : null;
                    $img = $firstImg ?: (!empty($product['variants'][0]['imageUrl']) ? ltrim($product['variants'][0]['imageUrl'], '/') : 'https://placehold.co/300x300');
                    $oldPrice = $product['basePrice'];
                    $newPrice = $product['isFlashDeal'] ? ($product['flashDealPrice'] ?? $product['basePrice']) : $product['basePrice'];
                    $discount = ($oldPrice > $newPrice) ? round((($oldPrice - $newPrice) / $oldPrice) * 100) : 0;
                    
                    $totalStock = array_sum(array_column($product['variants'] ?? [], 'stock'));
                    $initialStock = array_sum(array_map(function($v) { return $v['initialStock'] ?? $v['stock'] ?? 1; }, $product['variants'] ?? []));
                    $sold = max(0, $initialStock - $totalStock);
                    $widthPercent = min(100, round(($sold / max(1, $initialStock)) * 100)) . '%';
                ?>
            <div class="w-[170px] sm:w-[210px] shrink-0 snap-start bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl hover:shadow-[#003e86]/10 transition-all duration-300 group flex flex-col hover:-translate-y-1 relative">
                    <?php if ($discount > 0): ?>
                        <div class="absolute top-2.5 left-2.5 z-10 bg-[#ba1a1a] text-white text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm w-fit">
                            -<?php echo $discount; ?>% OFF
                        </div>
                <?php else: ?>
                    <div class="absolute top-2.5 left-2.5 z-10 bg-emerald-100 text-emerald-700 text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm flex items-center gap-1 border border-emerald-200">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l2.4 7.6 8 2.4-8 2.4L12 22l-2.4-7.6-8-2.4 8-2.4L12 2z"/></svg> NEW
                    </div>
                    <?php endif; ?>
                    
                    <button type="button" onclick="toggleWishlist(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', '<?php echo htmlspecialchars($img); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="wishlist-btn-global absolute top-2.5 right-2.5 z-30 p-2 bg-white/90 backdrop-blur-sm text-slate-400 rounded-full shadow-sm hover:shadow-md hover:text-rose-500 transition-all" data-product-id="<?php echo $product['id']; ?>" title="Add to Wishlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    </button>

                    <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="relative aspect-square overflow-hidden bg-slate-50 block p-3">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500 drop-shadow-sm mix-blend-multiply">
                        <div class="absolute inset-0 bg-white/30 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-center justify-center z-20">
                            <div class="bg-white text-[#003e86] p-2.5 rounded-full shadow-lg transform scale-50 group-hover:scale-110 transition-transform duration-300">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </div>
                        </div>
                    </a>
                    <div class="p-3 sm:p-4 flex flex-col flex-1 border-t border-slate-50">
                        <div class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($product['categoryName'] ?? 'Uncategorized'); ?></div>
                        <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="font-bold text-slate-800 text-[13px] sm:text-sm mb-2 line-clamp-2 hover:text-[#003e86] transition-colors flex-1 leading-snug">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </a>
                        <div class="mt-auto flex flex-col gap-2.5">
                            <div class="flex items-end justify-between pt-1.5 border-t border-slate-50">
                                <div class="flex flex-col">
                                    <?php if($discount > 0): ?>
                                        <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 line-through"><?php echo $symbol . number_format($oldPrice); ?></span>
                                    <?php endif; ?>
                                    <span class="font-black text-base sm:text-lg text-[#003e86]"><?php echo $symbol . number_format($newPrice); ?></span>
                                </div>
                                <button data-product-id="<?php echo $product['id']; ?>" onclick="quickAddToCart(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="quick-add-to-cart-btn w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-50 text-[#003e86] flex items-center justify-center hover:bg-[#003e86] hover:text-white transition-all shadow-sm z-30 shrink-0" title="Add to Cart">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                </button>
                            </div>
                            <div>
                                <div class="flex justify-between text-[9px] sm:text-[10px] font-semibold text-slate-500 mb-1">
                                    <span class="text-[#ba1a1a]">Sold: <?php echo $sold; ?></span>
                                    <span>Left: <?php echo $totalStock; ?></span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                                    <div class="bg-gradient-to-r from-[#003e86] to-blue-400 h-1 rounded-full" style="width: <?php echo $widthPercent; ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Best Selling Products -->
        <?php if (!empty($bestSellingProducts)): ?>
        <section>
            <div class="flex items-center justify-between mb-5 sm:mb-6">
                <h3 class="text-xl md:text-2xl font-black text-slate-900 border-l-4 border-[#003e86] pl-3 md:pl-4 tracking-tight">Best Selling Products</h3>
                <a href="shop?sort=bestselling" class="flex items-center gap-1.5 bg-white text-slate-800 px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-full font-bold text-[11px] sm:text-sm hover:bg-slate-50 transition-all shadow-sm border border-slate-200 shrink-0">
                    <span class="hidden sm:block">Explore Best Sellers</span>
                    <span class="sm:hidden">View All</span>
                    <svg width="14" height="14" class="sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="flex overflow-x-auto gap-4 pb-6 pt-2 snap-x snap-mandatory product-scroll">
                <?php foreach($bestSellingProducts as $product): 
                    $firstImg = $product['imageUrl'] ? ltrim(explode(',', $product['imageUrl'])[0], '/') : null;
                    $img = $firstImg ?: (!empty($product['variants'][0]['imageUrl']) ? ltrim($product['variants'][0]['imageUrl'], '/') : 'https://placehold.co/300x300');
                    $oldPrice = $product['basePrice'];
                    $newPrice = $product['isFlashDeal'] ? ($product['flashDealPrice'] ?? $product['basePrice']) : $product['basePrice'];
                    $discount = ($oldPrice > $newPrice) ? round((($oldPrice - $newPrice) / $oldPrice) * 100) : 0;
                    
                    $totalStock = array_sum(array_column($product['variants'] ?? [], 'stock'));
                    $initialStock = array_sum(array_map(function($v) { return $v['initialStock'] ?? $v['stock'] ?? 1; }, $product['variants'] ?? []));
                    $sold = max(0, $initialStock - $totalStock);
                    $widthPercent = min(100, round(($sold / max(1, $initialStock)) * 100)) . '%';
                ?>
                <div class="w-[170px] sm:w-[210px] shrink-0 snap-start bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl hover:shadow-[#003e86]/10 transition-all duration-300 group flex flex-col hover:-translate-y-1 relative">
                    <?php if ($discount > 0): ?>
                        <div class="absolute top-2.5 left-2.5 z-10 bg-[#ba1a1a] text-white text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm w-fit">
                            -<?php echo $discount; ?>% OFF
                        </div>
                    <?php else: ?>
                        <div class="absolute top-2.5 left-2.5 z-10 bg-amber-100 text-amber-700 text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm flex items-center gap-1 border border-amber-200">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Top Rated
                        </div>
                    <?php endif; ?>
                    
                    <button type="button" onclick="toggleWishlist(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', '<?php echo htmlspecialchars($img); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="wishlist-btn-global absolute top-2.5 right-2.5 z-30 p-2 bg-white/90 backdrop-blur-sm text-slate-400 rounded-full shadow-sm hover:shadow-md hover:text-rose-500 transition-all" data-product-id="<?php echo $product['id']; ?>" title="Add to Wishlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    </button>

                    <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="relative aspect-square overflow-hidden bg-slate-50 block p-3">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500 drop-shadow-sm mix-blend-multiply">
                        <div class="absolute inset-0 bg-white/30 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-center justify-center z-20">
                            <div class="bg-white text-[#003e86] p-2.5 rounded-full shadow-lg transform scale-50 group-hover:scale-110 transition-transform duration-300">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </div>
                        </div>
                    </a>
                    <div class="p-3 sm:p-4 flex flex-col flex-1 border-t border-slate-50">
                        <div class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($product['categoryName'] ?? 'Uncategorized'); ?></div>
                        <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="font-bold text-slate-800 text-[13px] sm:text-sm mb-2 line-clamp-2 hover:text-[#003e86] transition-colors flex-1 leading-snug">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </a>
                        <div class="mt-auto flex flex-col gap-2.5">
                            <div class="flex items-end justify-between pt-1.5 border-t border-slate-50">
                                <div class="flex flex-col">
                                    <?php if($discount > 0): ?>
                                        <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 line-through"><?php echo $symbol . number_format($oldPrice); ?></span>
                                    <?php endif; ?>
                                    <span class="font-black text-base sm:text-lg text-[#003e86]"><?php echo $symbol . number_format($newPrice); ?></span>
                                </div>
                                <button data-product-id="<?php echo $product['id']; ?>" onclick="quickAddToCart(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="quick-add-to-cart-btn w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-50 text-[#003e86] flex items-center justify-center hover:bg-[#003e86] hover:text-white transition-all shadow-sm z-30 shrink-0" title="Add to Cart">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                </button>
                            </div>
                            <div>
                                <div class="flex justify-between text-[9px] sm:text-[10px] font-semibold text-slate-500 mb-1">
                                    <span class="text-[#ba1a1a]">Sold: <?php echo $sold; ?></span>
                                    <span>Left: <?php echo $totalStock; ?></span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                                    <div class="bg-gradient-to-r from-[#003e86] to-blue-400 h-1 rounded-full" style="width: <?php echo $widthPercent; ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Store Features / Why Choose Us -->
        <?php if (!isset($settings['storeFeaturesEnabled']) || $settings['storeFeaturesEnabled']): ?>
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-10 border-t border-slate-200">
            <div class="bg-white p-6 rounded-3xl border border-slate-100 flex flex-col items-center text-center gap-4 hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-blue-50 text-[#003e86] rounded-full flex items-center justify-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Fast Delivery</h4>
                    <p class="text-xs text-slate-500 mt-1 font-medium">Reliable & quick shipping across the country.</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-slate-100 flex flex-col items-center text-center gap-4 hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Secure Payments</h4>
                    <p class="text-xs text-slate-500 mt-1 font-medium">100% secure payment gateways & cash on delivery.</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-slate-100 flex flex-col items-center text-center gap-4 hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">24/7 Support</h4>
                    <p class="text-xs text-slate-500 mt-1 font-medium">Dedicated customer support whenever you need it.</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-slate-100 flex flex-col items-center text-center gap-4 hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Quality Guarantee</h4>
                    <p class="text-xs text-slate-500 mt-1 font-medium">Top-notch products carefully inspected for you.</p>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </main>
</div>

<script>
    // Banner Slider Logic
    const track = document.getElementById('hero-slider-track');
    if(track) {
        const dots = document.querySelectorAll('[data-slide]');
        const total = dots.length;
        let currentSlide = 0;
        let slideInterval;

        window.goToSlide = function(index) {
            currentSlide = index;
            track.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, i) => {
                dot.className = i === currentSlide ? 'h-2 rounded-full transition-all w-8 bg-[#003e86]' : 'h-2 rounded-full transition-all w-2 bg-white/60 hover:bg-white';
            });
        };

        function startSlider() {
            if(total > 1) {
                slideInterval = setInterval(() => { goToSlide((currentSlide + 1) % total); }, 4000);
            }
        }
        startSlider();
        track.parentElement.addEventListener('mouseenter', () => clearInterval(slideInterval));
        track.parentElement.addEventListener('mouseleave', startSlider);
    }

    // Flash Deal Timer Logic
    const timerEl = document.getElementById('flash-deal-timer');
    if (timerEl) {
        const endTime = new Date(timerEl.getAttribute('data-endtime')).getTime();
        const timerInterval = setInterval(() => {
            const now = new Date().getTime();
            const distance = endTime - now;
            if (distance < 0) {
                clearInterval(timerInterval);
                timerEl.innerHTML = "<span class='bg-rose-100 text-rose-700 px-3 py-1 rounded-lg text-sm font-bold border border-rose-200'>EXPIRED</span>";
                return;
            }
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            timerEl.innerHTML = `
                <div class="bg-rose-100 text-rose-700 w-8 h-8 sm:w-9 sm:h-9 flex flex-col items-center justify-center rounded-md sm:rounded-lg border border-rose-200"><span class="text-xs sm:text-sm font-black leading-none">${days}</span><span class="text-[6px] sm:text-[7px] uppercase font-bold tracking-wider">Days</span></div><span class="text-rose-400 font-black text-xs sm:text-base">:</span>
                <div class="bg-rose-100 text-rose-700 w-8 h-8 sm:w-9 sm:h-9 flex flex-col items-center justify-center rounded-md sm:rounded-lg border border-rose-200"><span class="text-xs sm:text-sm font-black leading-none">${hours.toString().padStart(2, '0')}</span><span class="text-[6px] sm:text-[7px] uppercase font-bold tracking-wider">Hrs</span></div><span class="text-rose-400 font-black text-xs sm:text-base">:</span>
                <div class="bg-rose-100 text-rose-700 w-8 h-8 sm:w-9 sm:h-9 flex flex-col items-center justify-center rounded-md sm:rounded-lg border border-rose-200"><span class="text-xs sm:text-sm font-black leading-none">${minutes.toString().padStart(2, '0')}</span><span class="text-[6px] sm:text-[7px] uppercase font-bold tracking-wider">Min</span></div><span class="text-rose-400 font-black text-xs sm:text-base">:</span>
                <div class="bg-rose-100 text-rose-700 w-8 h-8 sm:w-9 sm:h-9 flex flex-col items-center justify-center rounded-md sm:rounded-lg border border-rose-200"><span class="text-xs sm:text-sm font-black leading-none">${seconds.toString().padStart(2, '0')}</span><span class="text-[6px] sm:text-[7px] uppercase font-bold tracking-wider">Sec</span></div>
            `;
        }, 1000);
    }
</script>

<!-- Promotional Popup Banner -->
<?php if (!empty($popupBanner)): ?>
<div id="promo-popup" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 opacity-0 transition-opacity duration-300">
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden scale-95 transition-transform duration-300" id="promo-popup-content">
        <button onclick="closePopup()" class="absolute top-4 right-4 z-10 w-8 h-8 bg-black/40 hover:bg-black/70 text-white rounded-full flex items-center justify-center backdrop-blur-md transition-colors shadow-sm">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <a href="<?php echo htmlspecialchars($popupBanner['targetUrl'] ?: '#'); ?>" class="block w-full">
            <img src="<?php echo htmlspecialchars(ltrim($popupBanner['imageUrl'], '/')); ?>" alt="<?php echo htmlspecialchars($popupBanner['title']); ?>" class="w-full h-auto object-cover">
        </a>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const popup = document.getElementById('promo-popup');
        const content = document.getElementById('promo-popup-content');
        const popupId = 'popup_<?php echo $popupBanner['id']; ?>';
        
        if (!sessionStorage.getItem(popupId)) {
            setTimeout(() => {
                popup.classList.remove('hidden');
                popup.classList.add('flex');
                void popup.offsetWidth; // Trigger reflow
                popup.classList.remove('opacity-0');
                content.classList.remove('scale-95');
            }, 1500); // পপআপ ১.৫ সেকেন্ড পর আসবে
        }
        
        window.closePopup = function() {
            popup.classList.add('opacity-0');
            content.classList.add('scale-95');
            setTimeout(() => { popup.classList.add('hidden'); popup.classList.remove('flex'); }, 300);
            sessionStorage.setItem(popupId, 'true');
        };
    });
</script>
<?php endif; ?>

<?php include 'Footer.php'; ?>