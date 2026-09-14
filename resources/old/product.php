<?php
session_start();
require_once __DIR__ . '/db.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: index.php");
    exit;
}

// Fetch Product & Category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as categoryName, c.slug as categorySlug 
    FROM Product p 
    LEFT JOIN Category c ON p.categoryId = c.id 
    WHERE p.slug = ?
");
$stmt->execute([$slug]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: 404.php");
    exit;
}

// Page-specific SEO Meta Data
$pageMetaTitle = !empty($product['seoTitle']) ? $product['seoTitle'] : $product['name'];
$pageMetaDescription = !empty($product['seoDescription']) ? $product['seoDescription'] : (!empty($product['description']) ? substr(strip_tags(str_replace(["\r", "\n"], ' ', $product['description'])), 0, 160) . '...' : '');
$firstImage = !empty($product['imageUrl']) ? explode(',', $product['imageUrl'])[0] : null;
$pageMetaImage = $firstImage;

// Fetch Variants
$vStmt = $pdo->prepare("SELECT * FROM Variant WHERE productId = ?");
$vStmt->execute([$product['id']]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

// Settings for currency and Flash Deal
$setStmt = $pdo->query("SELECT currencySymbol, flashDealEndTime, fbtEnabled FROM SettingGeneral LIMIT 1");
$settings = $setStmt->fetch(PDO::FETCH_ASSOC);
$symbol = $settings['currencySymbol'] ?? '৳';

// Organize variants
$sizes = [];
$colors = [];
$totalStock = 0;
$hasRealVariants = false;
$allImages = [];

if (!empty($product['imageUrl'])) {
    foreach(explode(',', $product['imageUrl']) as $img) {
        $allImages[] = ltrim($img, '/');
    }
}

foreach ($variants as $v) {
    if ($v['size'] !== 'Standard' && !in_array($v['size'], $sizes)) $sizes[] = $v['size'];
    if ($v['color'] !== 'Default' && !in_array($v['color'], $colors)) $colors[] = $v['color'];
    if ($v['size'] !== 'Standard' || $v['color'] !== 'Default') $hasRealVariants = true;
    
    $totalStock += $v['stock'];
    if ($v['imageUrl'] && !in_array(ltrim($v['imageUrl'], '/'), $allImages)) {
        $allImages[] = ltrim($v['imageUrl'], '/');
    }
}

if (empty($allImages)) {
    $allImages[] = 'https://placehold.co/600x600?text=No+Image';
}

$isFlashDeal = $product['isFlashDeal'] && strtotime($settings['flashDealEndTime'] ?? '0') > time();
$currentPrice = $isFlashDeal ? ($product['flashDealPrice'] ?? $product['basePrice']) : $product['basePrice'];
$oldPrice = $product['basePrice'];
$discount = ($isFlashDeal && $oldPrice > 0) ? round((($oldPrice - $currentPrice) / $oldPrice) * 100) : 0;

// Fetch Frequently Bought Together (Random products from the same category, excluding current)
$fbtEnabled = !isset($settings['fbtEnabled']) || $settings['fbtEnabled'];
$fbtProducts = [];
if ($fbtEnabled) {
    $fbtStmt = $pdo->prepare("SELECT p.id, p.name, p.slug, p.basePrice, p.imageUrl, p.isFlashDeal, p.flashDealPrice FROM Product p WHERE p.categoryId = ? AND p.id != ? AND p.categoryId IS NOT NULL ORDER BY RAND() LIMIT 4");
    $fbtStmt->execute([$product['categoryId'], $product['id']]);
    $fbtProducts = $fbtStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($fbtProducts) < 4) {
        $limit = 4 - count($fbtProducts);
        $fbtIds = array_column($fbtProducts, 'id');
        $fbtIds[] = $product['id'];
        $in = str_repeat('?,', count($fbtIds) - 1) . '?';
        $fbtMoreStmt = $pdo->prepare("SELECT p.id, p.name, p.slug, p.basePrice, p.imageUrl, p.isFlashDeal, p.flashDealPrice FROM Product p WHERE p.id NOT IN ($in) ORDER BY RAND() LIMIT $limit");
        $fbtMoreStmt->execute($fbtIds);
        $fbtProducts = array_merge($fbtProducts, $fbtMoreStmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

include 'Header.php';
?>

<div class="min-h-screen pt-24 pb-[140px] md:pb-24 bg-white">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6">
        
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm font-medium text-slate-500 mb-8 overflow-x-auto whitespace-nowrap pb-2">
            <a href="./" class="hover:text-blue-600 transition-colors">Home</a>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <a href="shop?category=<?php echo $product['categorySlug'] ?? ''; ?>" class="hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($product['categoryName'] ?? 'Uncategorized'); ?></a>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span class="text-slate-800 font-bold truncate"><?php echo htmlspecialchars($product['name']); ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-16 items-start">
            
            <!-- Left: Image Gallery -->
            <div class="space-y-4 lg:col-span-5 lg:sticky lg:top-28">
                <div class="aspect-square rounded-2xl sm:rounded-3xl bg-slate-50 border border-slate-200 overflow-hidden relative group flex items-center justify-center p-4 sm:p-10 mx-0 shadow-inner">
                    <?php if ($isFlashDeal): ?>
                        <div class="absolute top-4 left-4 z-10 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Flash Deal
                        </div>
                    <?php endif; ?>
                    <img id="main-image" src="<?php echo htmlspecialchars($allImages[0]); ?>" class="w-full h-full object-contain mix-blend-multiply drop-shadow-sm sm:group-hover:scale-105 transition-transform duration-500" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                
                <?php if (count($allImages) > 1): ?>
                <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-2 custom-scrollbar snap-x mt-4">
                    <?php foreach ($allImages as $idx => $img): ?>
                        <button onclick="changeMainImage('<?php echo htmlspecialchars($img); ?>', this)" class="w-16 h-16 sm:w-24 sm:h-24 shrink-0 snap-start rounded-xl sm:rounded-2xl bg-slate-50 border-2 <?php echo $idx === 0 ? 'border-[#003e86]' : 'border-transparent hover:border-blue-300'; ?> overflow-hidden transition-all image-thumbnail">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover mix-blend-multiply">
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Product Details -->
            <div class="flex flex-col lg:col-span-7 pb-20 md:pb-0">
                <div class="mb-5 sm:mb-6">
                    <div class="flex justify-between items-start gap-4 mb-3 sm:mb-4">
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <button type="button" onclick="toggleWishlist(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', '<?php echo htmlspecialchars($allImages[0]); ?>', <?php echo $currentPrice; ?>, '<?php echo htmlspecialchars($product['slug']); ?>', event)" class="wishlist-btn-global p-2.5 sm:p-3 bg-rose-50 text-slate-400 hover:text-rose-500 rounded-full transition-all shrink-0 shadow-sm border border-slate-200" data-product-id="<?php echo $product['id']; ?>" title="Add to Wishlist">
                            <svg width="20" height="20" class="sm:w-6 sm:h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        </button>
                    </div>
                    
                    <div class="flex items-end gap-3 sm:gap-4 mb-4">
                        <div class="flex flex-col">
                            <?php if ($isFlashDeal && $oldPrice > $currentPrice): ?>
                                <span class="text-slate-400 line-through text-sm sm:text-base font-bold"><?php echo $symbol . number_format($oldPrice); ?></span>
                            <?php endif; ?>
                            <span class="text-3xl sm:text-4xl font-black text-[#003e86] leading-none"><?php echo $symbol . number_format($currentPrice); ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                            <span class="bg-rose-100 text-rose-700 px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-widest mb-1.5">-<?php echo $discount; ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs sm:text-sm font-bold text-slate-500 pb-5 sm:pb-6 border-b border-slate-100">
                        <div class="flex items-center gap-1.5 <?php echo $totalStock > 0 ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'; ?> px-3 py-1.5 rounded-lg border <?php echo $totalStock > 0 ? 'border-emerald-200' : 'border-rose-200'; ?>">
                            <div class="w-1.5 h-1.5 rounded-full <?php echo $totalStock > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'; ?>"></div>
                            <?php echo $totalStock > 0 ? "In Stock ({$totalStock})" : "Out of Stock"; ?>
                        </div>
                        <span class="flex items-center gap-1.5"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> SKU: <?php echo htmlspecialchars($product['sku'] ?? $product['slug']); ?></span>
                    </div>
                </div>

                <!-- Description -->
                <div class="prose prose-sm sm:prose-base text-slate-600 mb-8 max-w-none leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                </div>

                <!-- Add To Cart Form -->
                <form id="add-to-cart-form" class="space-y-6 mt-auto">
                    <input type="hidden" name="productId" value="<?php echo $product['id']; ?>">
                    
                    <?php if ($hasRealVariants): ?>
                    <div class="space-y-4 sm:space-y-5">
                        <?php if (!empty($sizes)): ?>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 mb-2 sm:mb-3 uppercase tracking-widest">Select Size</h3>
                            <div class="flex flex-wrap gap-2 sm:gap-3">
                                <?php foreach ($sizes as $idx => $size): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="size" value="<?php echo htmlspecialchars($size); ?>" class="peer sr-only" <?php echo $idx === 0 ? 'checked' : ''; ?>>
                                        <div class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl border-2 border-slate-200 text-xs sm:text-sm font-bold text-slate-600 peer-checked:border-[#003e86] peer-checked:bg-blue-50 peer-checked:text-[#003e86] hover:border-slate-300 transition-all">
                                            <?php echo htmlspecialchars($size); ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($colors)): ?>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 mb-2 sm:mb-3 uppercase tracking-widest">Select Color</h3>
                            <div class="flex flex-wrap gap-2 sm:gap-3">
                                <?php foreach ($colors as $idx => $color): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="<?php echo htmlspecialchars($color); ?>" class="peer sr-only" <?php echo $idx === 0 ? 'checked' : ''; ?>>
                                        <div class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl border-2 border-slate-200 text-xs sm:text-sm font-bold text-slate-600 peer-checked:border-[#003e86] peer-checked:bg-blue-50 peer-checked:text-[#003e86] hover:border-slate-300 transition-all">
                                            <?php echo htmlspecialchars($color); ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="size" value="Standard">
                        <input type="hidden" name="color" value="Default">
                    <?php endif; ?>

                    <div class="fixed md:static bottom-[calc(64px+env(safe-area-inset-bottom))] md:bottom-0 left-0 w-full md:w-auto p-3 sm:p-0 bg-white/95 backdrop-blur-xl md:bg-transparent border-t md:border-t-0 border-slate-200 md:border-transparent flex flex-row gap-2 sm:gap-3 md:pt-6 md:border-t md:border-slate-100 z-[70] md:z-auto shadow-[0_-15px_30px_-15px_rgba(0,0,0,0.15)] md:shadow-none">
                        <div class="flex items-center bg-slate-50 border border-slate-200 rounded-xl sm:rounded-2xl p-1 h-12 w-24 sm:w-32 shrink-0">
                            <button type="button" onclick="updateQty(-1)" class="w-8 sm:w-10 h-full flex items-center justify-center text-slate-500 hover:bg-white hover:shadow-sm rounded-lg sm:rounded-xl transition-all font-bold text-xl">-</button>
                            <input type="number" name="quantity" id="qty-input" value="1" min="1" max="<?php echo $totalStock > 0 ? $totalStock : 1; ?>" class="w-full h-full bg-transparent border-none text-center font-black text-slate-900 focus:ring-0 p-0 text-sm sm:text-base" readonly>
                            <button type="button" onclick="updateQty(1)" class="w-8 sm:w-10 h-full flex items-center justify-center text-slate-500 hover:bg-white hover:shadow-sm rounded-lg sm:rounded-xl transition-all font-bold text-xl">+</button>
                        </div>
                        <div class="flex flex-1 gap-2 sm:gap-3">
                            <button type="button" id="btn-add-to-cart" onclick="addToCart(event)" <?php echo $totalStock <= 0 ? 'disabled' : ''; ?> class="flex-1 h-12 sm:h-12 bg-blue-50 hover:bg-[#003e86] hover:text-white text-[#003e86] disabled:bg-slate-100 disabled:text-slate-400 border border-blue-200 hover:border-[#003e86] disabled:border-slate-200 rounded-xl sm:rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-1 sm:gap-2 active:scale-[0.98]">
                                <svg width="18" height="18" class="sm:w-[18px] sm:h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                <span class="hidden sm:inline">Add to Cart</span><span class="sm:hidden">Cart</span>
                            </button>
                            <button type="button" onclick="buyNow()" <?php echo $totalStock <= 0 ? 'disabled' : ''; ?> class="flex-1 h-12 sm:h-12 bg-[#003e86] hover:bg-blue-800 disabled:bg-slate-300 text-white rounded-xl sm:rounded-2xl font-bold text-xs sm:text-sm shadow-lg shadow-blue-900/20 transition-all flex items-center justify-center gap-1 sm:gap-2 active:scale-[0.98]">
                                <svg width="18" height="18" class="sm:w-[18px] sm:h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                Buy Now
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Frequently Bought Together -->
        <?php if (!empty($fbtProducts) && $fbtEnabled): ?>
        <div class="mt-16 sm:mt-24 mb-10">
            <div class="flex items-center justify-between mb-5 sm:mb-6">
                <h2 class="text-xl md:text-2xl font-black text-slate-900 border-l-4 border-[#003e86] pl-3 md:pl-4 tracking-tight">Frequently Bought Together</h2>
            </div>
            <div class="flex overflow-x-auto gap-4 pb-6 pt-2 snap-x snap-mandatory product-scroll">
                <?php foreach ($fbtProducts as $fbtItem): 
                    $firstImg = $fbtItem['imageUrl'] ? ltrim(explode(',', $fbtItem['imageUrl'])[0], '/') : null;
                    $img = $firstImg ?: (!empty($fbtItem['variants'][0]['imageUrl']) ? ltrim($fbtItem['variants'][0]['imageUrl'], '/') : 'https://placehold.co/300x300');
                    $oldPrice = $fbtItem['basePrice'];
                    $newPrice = $fbtItem['isFlashDeal'] ? ($fbtItem['flashDealPrice'] ?? $fbtItem['basePrice']) : $fbtItem['basePrice'];
                    $discount = ($oldPrice > $newPrice) ? round((($oldPrice - $newPrice) / $oldPrice) * 100) : 0;
                    
                    $totalStock = array_sum(array_column($fbtItem['variants'] ?? [], 'stock'));
                    $initialStock = array_sum(array_map(function($v) { return $v['initialStock'] ?? $v['stock'] ?? 1; }, $fbtItem['variants'] ?? []));
                    $sold = max(0, $initialStock - $totalStock);
                    $widthPercent = min(100, round(($sold / max(1, $initialStock)) * 100)) . '%';
                ?>
                <div class="w-[170px] sm:w-[210px] shrink-0 snap-start bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl hover:shadow-[#003e86]/10 transition-all duration-300 group flex flex-col hover:-translate-y-1 relative">
                    <?php if ($discount > 0): ?>
                        <div class="absolute top-2.5 left-2.5 z-10 bg-[#ba1a1a] text-white text-[9px] sm:text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full shadow-sm w-fit">
                            -<?php echo $discount; ?>% OFF
                        </div>
                    <?php endif; ?>
                    
                    <button type="button" onclick="toggleWishlist(<?php echo $fbtItem['id']; ?>, '<?php echo addslashes(htmlspecialchars($fbtItem['name'])); ?>', '<?php echo htmlspecialchars($img); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($fbtItem['slug']); ?>', event)" class="wishlist-btn-global absolute top-2.5 right-2.5 z-30 p-2 bg-white/90 backdrop-blur-sm text-slate-400 rounded-full shadow-sm hover:shadow-md hover:text-rose-500 transition-all" data-product-id="<?php echo $fbtItem['id']; ?>" title="Add to Wishlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    </button>

                    <a href="product?slug=<?php echo htmlspecialchars($fbtItem['slug']); ?>" class="relative aspect-square overflow-hidden bg-slate-50 block p-3">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500 drop-shadow-sm mix-blend-multiply">
                        <div class="absolute inset-0 bg-white/30 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-center justify-center z-20">
                            <div class="bg-white text-[#003e86] p-2.5 rounded-full shadow-lg transform scale-50 group-hover:scale-110 transition-transform duration-300">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </div>
                        </div>
                    </a>
                    <div class="p-3 sm:p-4 flex flex-col flex-1 border-t border-slate-50">
                        <div class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($fbtItem['categoryName'] ?? 'Uncategorized'); ?></div>
                        <a href="product?slug=<?php echo htmlspecialchars($fbtItem['slug']); ?>" class="font-bold text-slate-800 text-[13px] sm:text-sm mb-2 line-clamp-2 hover:text-[#003e86] transition-colors flex-1 leading-snug">
                            <?php echo htmlspecialchars($fbtItem['name']); ?>
                        </a>
                        <div class="mt-auto flex flex-col gap-2.5">
                            <div class="flex items-end justify-between pt-1.5 border-t border-slate-50">
                                <div class="flex flex-col">
                                    <?php if($discount > 0): ?>
                                        <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 line-through"><?php echo $symbol . number_format($oldPrice); ?></span>
                                    <?php endif; ?>
                                    <span class="font-black text-base sm:text-lg text-[#003e86]"><?php echo $symbol . number_format($newPrice); ?></span>
                                </div>
                                <button data-product-id="<?php echo $fbtItem['id']; ?>" onclick="quickAddToCart(<?php echo $fbtItem['id']; ?>, '<?php echo addslashes(htmlspecialchars($fbtItem['name'])); ?>', <?php echo $newPrice; ?>, '<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($fbtItem['slug']); ?>', event)" class="quick-add-to-cart-btn w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-50 text-[#003e86] flex items-center justify-center hover:bg-[#003e86] hover:text-white transition-all shadow-sm z-30 shrink-0" title="Add to Cart">
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
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Image Gallery Logic
function changeMainImage(src, btn) {
    document.getElementById('main-image').src = src;
    document.querySelectorAll('.image-thumbnail').forEach(el => {
        el.classList.remove('border-[#003e86]');
        el.classList.add('border-transparent');
    });
    btn.classList.remove('border-transparent');
    btn.classList.add('border-[#003e86]');
}

// Quantity Logic
function updateQty(delta) {
    const input = document.getElementById('qty-input');
    let val = parseInt(input.value) + delta;
    const max = parseInt(input.getAttribute('max'));
    if (val < 1) val = 1;
    if (val > max) {
        val = max;
        showToast("Maximum available stock reached!", "error");
    }
    input.value = val;
}

function processCartAction(redirect = false, event = null) {
    const form = document.getElementById('add-to-cart-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Get existing cart
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    // Check if variant exists
    const existingIndex = cart.findIndex(item => 
        item.productId === data.productId && 
        item.size === data.size && 
        item.color === data.color
    );

    if (existingIndex >= 0) {
        if (redirect) {
            window.location.href = 'checkout';
        }
        return;
    } else {
        data.quantity = parseInt(data.quantity);
        data.name = <?php echo json_encode($product['name']); ?>;
        data.price = <?php echo $currentPrice; ?>;
        data.image = <?php echo json_encode($allImages[0]); ?>;
        data.slug = <?php echo json_encode($product['slug']); ?>;
        cart.push(data);
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Fire custom event to update badges globally
    window.dispatchEvent(new Event('cartUpdated'));
    
    if (redirect) {
        window.location.href = 'checkout';
    } else {
        if (event && window.flyToCart) {
            flyToCart(data.image, event);
        }
    }
}

function addToCart(event) {
    processCartAction(false, event);
}

function buyNow() {
    processCartAction(true);
}

// Auto update add to cart button state
function checkCartState() {
    const form = document.getElementById('add-to-cart-form');
    if (!form) return;
    const formData = new FormData(form);
    const productId = formData.get('productId');
    const size = formData.get('size') || 'Standard';
    const color = formData.get('color') || 'Default';

    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const existingIndex = cart.findIndex(item => 
        (item.productId == productId || item.id == productId) && 
        item.size === size && 
        item.color === color
    );

    const btn = document.getElementById('btn-add-to-cart');
    if (!btn) return;
    
    if (existingIndex >= 0) {
        btn.innerHTML = `<svg width="18" height="18" class="sm:w-[18px] sm:h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <span class="hidden sm:inline">Added to Cart</span><span class="sm:hidden">Added</span>`;
        btn.classList.remove('bg-blue-50', 'text-[#003e86]', 'hover:bg-[#003e86]', 'hover:text-white', 'border-blue-200', 'hover:border-[#003e86]');
        btn.classList.add('bg-emerald-500', 'text-white', 'hover:bg-emerald-600', 'border-emerald-600');
        btn.title = "Added to Cart";
    } else {
        btn.innerHTML = `<svg width="18" height="18" class="sm:w-[18px] sm:h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg> <span class="hidden sm:inline">Add to Cart</span><span class="sm:hidden">Cart</span>`;
        btn.classList.remove('bg-emerald-500', 'text-white', 'hover:bg-emerald-600', 'border-emerald-600');
        btn.classList.add('bg-blue-50', 'text-[#003e86]', 'hover:bg-[#003e86]', 'hover:text-white', 'border-blue-200', 'hover:border-[#003e86]');
        btn.title = "Add to Cart";
    }
}

// Frontend Toast Function (Fallback)
function showToast(message, type = 'success') {
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
    toast.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white shrink-0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
        <span class="text-xs md:text-sm font-bold tracking-tight">${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => { 
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(1rem)';
        setTimeout(() => toast.remove(), 300); 
    }, 3000);
}

// Auto-update Cart Counter Badge
window.addEventListener('cartUpdated', () => {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const count = cart.reduce((acc, item) => acc + item.quantity, 0);
    
    // Find all cart badges in the Header
    const badges = document.querySelectorAll('a[href="/cart"] span, a[href="cart.php"] span');
    badges.forEach(badge => {
        badge.innerText = count;
        // Add a simple pop animation
        badge.classList.add('scale-150');
        setTimeout(() => badge.classList.remove('scale-150'), 200);
    });
    if (typeof checkCartState === 'function') checkCartState();
});

// Initialize badge on load
window.dispatchEvent(new Event('cartUpdated'));

document.addEventListener('DOMContentLoaded', checkCartState);
document.querySelectorAll('input[type=radio]').forEach(radio => {
    radio.addEventListener('change', checkCartState);
});
</script>

<?php include 'Footer.php'; ?>