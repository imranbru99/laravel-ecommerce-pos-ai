<?php
session_start();
require_once __DIR__ . '/db.php';

$categories = [];
$products = [];
$symbol = '৳';
$flashEndTime = 0;

try {
    // Fetch Categories that have flash deals
    $catStmt = $pdo->query("
        SELECT DISTINCT c.* 
        FROM Category c 
        JOIN Product p ON c.id = p.categoryId 
        WHERE p.isFlashDeal = 1 AND c.status = 1 
        ORDER BY c.name ASC
    ");
    $categories = $catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Fetch settings
    $setStmt = $pdo->query("SELECT currencySymbol, flashDealEndTime FROM settings LIMIT 1");
    $settings = $setStmt ? $setStmt->fetch(PDO::FETCH_ASSOC) : [];
    $symbol = $settings['currencySymbol'] ?? '৳';
    $flashEndTime = isset($settings['flashDealEndTime']) ? strtotime($settings['flashDealEndTime']) : 0;

    // Fetch Products
    $query = "SELECT p.*, c.name as categoryName FROM Product p LEFT JOIN Category c ON p.categoryId = c.id WHERE p.isFlashDeal = 1";
    $params = [];

    if (!empty($_GET['category'])) {
        $query .= " AND c.slug = ?";
        $params[] = $_GET['category'];
    }

    if (!empty($_GET['search'])) {
        $query .= " AND p.name LIKE ?";
        $params[] = '%' . $_GET['search'] . '%';
    }

    $sort = $_GET['sort'] ?? 'newest';
    if ($sort === 'price_low') {
        $query .= " ORDER BY p.flashDealPrice ASC";
    } elseif ($sort === 'price_high') {
        $query .= " ORDER BY p.flashDealPrice DESC";
    } else {
        $query .= " ORDER BY p.id DESC"; // newest
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$p) {
        $vStmt = $pdo->prepare("SELECT * FROM Variant WHERE productId = ?");
        $vStmt->execute([$p['id']]);
        $p['variants'] = $vStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Handle potential missing columns silently to prevent 500 error
}

include 'Header.php';
?>

<div class="min-h-screen pt-24 md:pt-28 pb-28 md:pb-24 bg-slate-50 font-sans">
    
    <!-- Flash Deal Hero Banner -->
    <div class="bg-gradient-to-r from-[#003e86] to-blue-800 py-12 px-6 mb-8 text-white relative overflow-hidden rounded-b-3xl sm:rounded-none shadow-sm border-b border-blue-900/50">
        <div class="absolute inset-0 opacity-10 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiNmZmYiLz48L3N2Zz4=')]"></div>
        <div class="max-w-[1200px] mx-auto relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
            <div>
                <h1 class="text-4xl sm:text-5xl font-black tracking-tight mb-2 flex items-center gap-3">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="text-amber-400"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    Flash Deals
                </h1>
                <p class="text-blue-100 font-medium text-lg">Grab the best products at unbeatable prices before they're gone!</p>
            </div>
            
            <?php if ($flashEndTime > time()): ?>
            <div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl text-center min-w-[280px] shadow-lg shadow-[#003e86]/20">
                <p class="text-sm font-bold uppercase tracking-widest text-blue-200 mb-3">Offer Ends In</p>
                <div id="flash-deal-timer-page" data-endtime="<?php echo date('c', $flashEndTime); ?>" class="flex items-center justify-center gap-3">
                    <!-- Timer Injected Here -->
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-6">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full lg:w-72 shrink-0">
                <!-- Mobile Filter Toggle -->
                <button type="button" onclick="document.getElementById('filter-sidebar').classList.toggle('hidden')" class="lg:hidden w-full flex items-center justify-between bg-white px-6 py-4 rounded-2xl border border-slate-200 shadow-sm font-bold text-slate-700 mb-6 active:scale-95 transition-transform">
                    <span class="flex items-center gap-3"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Filter & Sort</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                </button>

                <form method="GET" action="flash-deals" id="filter-sidebar" class="hidden lg:block bg-white rounded-3xl p-6 shadow-sm border border-slate-200 sticky top-28 space-y-8">
                    <div>
                        <h3 class="font-bold text-slate-800 mb-4 uppercase tracking-widest text-xs flex items-center gap-2"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg> Search Deals</h3>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Search products..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-700 placeholder-slate-400">
                        </div>
                    </div>
                    <?php if(!empty($categories)): ?>
                    <div>
                        <h3 class="font-bold text-slate-800 mb-4 uppercase tracking-widest text-xs flex items-center gap-2"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Categories</h3>
                        <div class="space-y-2 max-h-[50vh] overflow-y-auto custom-scrollbar pr-2">
                            <label class="block cursor-pointer group">
                                <input type="radio" name="category" value="" <?php echo empty($_GET['category']) ? 'checked' : ''; ?> class="peer sr-only" onchange="this.form.submit()">
                                <div class="flex items-center justify-between px-4 py-3 rounded-xl transition-all peer-checked:bg-[#003e86] peer-checked:text-white peer-checked:shadow-md text-slate-600 hover:bg-slate-50 hover:text-[#003e86] border border-transparent peer-checked:border-[#003e86]">
                                    <span class="text-sm font-bold">All Deals</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="opacity-0 peer-checked:opacity-100 transition-opacity"><path d="M20 6L9 17l-5-5"/></svg>
                                </div>
                            </label>
                            <?php foreach($categories as $cat): ?>
                            <label class="block cursor-pointer group">
                                <input type="radio" name="category" value="<?php echo $cat['slug']; ?>" <?php echo ($_GET['category'] ?? '') === $cat['slug'] ? 'checked' : ''; ?> class="peer sr-only" onchange="this.form.submit()">
                                <div class="flex items-center justify-between px-4 py-3 rounded-xl transition-all peer-checked:bg-[#003e86] peer-checked:text-white peer-checked:shadow-md text-slate-600 hover:bg-slate-50 hover:text-[#003e86] border border-transparent peer-checked:border-[#003e86]">
                                    <span class="text-sm font-bold truncate pr-2"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="shrink-0 opacity-0 peer-checked:opacity-100 transition-opacity"><path d="M20 6L9 17l-5-5"/></svg>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <h3 class="font-bold text-slate-800 mb-4 uppercase tracking-widest text-xs flex items-center gap-2"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="m21 8-4-4-4 4"/><path d="M17 4v16"/></svg> Sort By</h3>
                        <select name="sort" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all cursor-pointer">
                            <option value="newest" <?php echo ($_GET['sort'] ?? '') === 'newest' ? 'selected' : ''; ?>>Newest Deals</option>
                            <option value="price_low" <?php echo ($_GET['sort'] ?? '') === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo ($_GET['sort'] ?? '') === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <noscript><button type="submit" class="w-full bg-[#003e86] hover:bg-blue-800 text-white font-bold py-3 rounded-xl shadow-md transition-all">Apply Filters</button></noscript>
                </form>
            </div>
            
            <!-- Products Grid -->
            <div class="flex-1">
                <!-- Advanced Category Header -->
                <div class="mb-8 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="relative z-10">
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-2">Active Deals</h2>
                        <p class="text-sm sm:text-base text-slate-500 font-medium">Limited time offers. Grab them before they're gone!</p>
                    </div>
                    <div class="relative z-10 bg-slate-50 px-5 py-3.5 rounded-2xl border border-slate-100 flex items-center gap-4 shadow-inner shrink-0">
                        <span class="text-3xl font-black text-[#003e86]"><?php echo count($products); ?></span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-tight">Deals<br>Found</span>
                    </div>
                    <!-- Decorative blur -->
                    <div class="absolute right-0 top-0 w-64 h-64 bg-gradient-to-br from-[#003e86]/5 to-indigo-500/5 rounded-full blur-3xl -z-0 translate-x-1/3 -translate-y-1/3 pointer-events-none"></div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
                    <?php if(empty($products)): ?>
                        <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" class="text-slate-400" stroke="none"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">No active deals found</h3>
                            <p class="text-slate-500 mb-6 font-medium">Try adjusting your filters or check back later.</p>
                            <a href="flash-deals" class="inline-flex items-center gap-2 bg-[#003e86] hover:bg-blue-800 text-white font-bold px-6 py-3 rounded-xl shadow-md transition-all">Clear all filters</a>
                        </div>
                    <?php else: ?>
                        <?php foreach($products as $product): 
                            $firstImg = $product['imageUrl'] ? explode(',', $product['imageUrl'])[0] : null;
                            $img = $firstImg ?: ($product['variants'][0]['imageUrl'] ?? 'https://placehold.co/300x300');
                            $oldPrice = $product['basePrice'];
                            $newPrice = $product['flashDealPrice'] ?? $product['basePrice'];
                            $discount = ($oldPrice > $newPrice) ? round((($oldPrice - $newPrice) / $oldPrice) * 100) : 0;
                            
                            $totalStock = array_sum(array_column($product['variants'] ?? [], 'stock'));
                            $initialStock = array_sum(array_map(function($v) { return $v['initialStock'] ?? $v['stock'] ?? 1; }, $product['variants'] ?? []));
                            $sold = max(0, $initialStock - $totalStock);
                            $widthPercent = min(100, round(($sold / max(1, $initialStock)) * 100)) . '%';
                        ?>
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl hover:shadow-[#003e86]/10 transition-all duration-300 group flex flex-col hover:-translate-y-1 relative">
                            <?php if ($discount > 0): ?>
                                <div class="absolute top-2.5 left-2.5 z-10 bg-[#ba1a1a] text-white text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm w-fit">
                                    -<?php echo $discount; ?>% OFF
                                </div>
                            <?php endif; ?>
                            
                            <button type="button" onclick="toggleWishlist(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', '<?php echo htmlspecialchars($img); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="wishlist-btn-global absolute top-2.5 right-2.5 z-30 p-2 bg-white/90 backdrop-blur-sm text-slate-400 rounded-full shadow-sm hover:shadow-md hover:text-rose-500 transition-all" data-product-id="<?php echo $product['id']; ?>" title="Add to Wishlist">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                            </button>

                            <a href="product?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="relative aspect-square overflow-hidden bg-slate-50 block p-3">
                                <img src="<?php echo htmlspecialchars(ltrim($img, '/')); ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500 drop-shadow-sm mix-blend-multiply">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Flash Deal Timer Logic for Page
    const timerEl = document.getElementById('flash-deal-timer-page');
    if (timerEl) {
        const endTime = new Date(timerEl.getAttribute('data-endtime')).getTime();
        const timerInterval = setInterval(() => {
            const now = new Date().getTime();
            const distance = endTime - now;
            if (distance < 0) {
                clearInterval(timerInterval);
                timerEl.innerHTML = "<span class='bg-white/20 text-white px-4 py-2 rounded-xl text-sm font-bold border border-white/30'>EXPIRED</span>";
                return;
            }
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            timerEl.innerHTML = `
                <div class="bg-white text-[#003e86] w-12 h-12 flex flex-col items-center justify-center rounded-xl shadow-lg"><span class="text-lg font-black leading-none">${days}</span><span class="text-[8px] uppercase font-bold tracking-wider opacity-80">Days</span></div><span class="text-white/50 font-black text-xl">:</span>
                <div class="bg-white text-[#003e86] w-12 h-12 flex flex-col items-center justify-center rounded-xl shadow-lg"><span class="text-lg font-black leading-none">${hours.toString().padStart(2, '0')}</span><span class="text-[8px] uppercase font-bold tracking-wider opacity-80">Hrs</span></div><span class="text-white/50 font-black text-xl">:</span>
                <div class="bg-white text-[#003e86] w-12 h-12 flex flex-col items-center justify-center rounded-xl shadow-lg"><span class="text-lg font-black leading-none">${minutes.toString().padStart(2, '0')}</span><span class="text-[8px] uppercase font-bold tracking-wider opacity-80">Min</span></div><span class="text-white/50 font-black text-xl">:</span>
                <div class="bg-white text-[#003e86] w-12 h-12 flex flex-col items-center justify-center rounded-xl shadow-lg"><span class="text-lg font-black leading-none">${seconds.toString().padStart(2, '0')}</span><span class="text-[8px] uppercase font-bold tracking-wider opacity-80">Sec</span></div>
            `;
        }, 1000);
    }
</script>

<?php include 'Footer.php'; ?>