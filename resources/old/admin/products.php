<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Fetch Products with Category, Total Stock, and Sold Count
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, 
               COALESCE(SUM(v.stock), 0) as total_stock,
               COALESCE(SUM(v.initialStock), 0) as initial_stock
        FROM Product p 
        LEFT JOIN Variant v ON p.id = v.productId 
        LEFT JOIN Category c ON p.categoryId = c.id
        GROUP BY p.id 
        ORDER BY p.id DESC
    ");
    $products = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $products = [];
}

// Extract unique categories for the filter dropdown
$uniqueCategories = array_unique(array_filter(array_column($products, 'category_name')));
sort($uniqueCategories);
ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="products-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="package" class="w-6 h-6 text-blue-600"></i> Product Catalog
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage your store inventory, pricing, and availability.</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="add-product" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg transition-all flex items-center gap-2 text-sm whitespace-nowrap">
                    <i data-lucide="plus" class="w-4 h-4"></i> 
                    <span>Add Product</span>
                </a>
            </div>
        </div>

        <!-- Filters & Search Bar -->
        <div class="p-4 sm:px-6 flex flex-col lg:flex-row gap-4 justify-between items-center bg-white border-b border-slate-100">
            <div class="relative w-full lg:w-80 shrink-0">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                <input type="text" id="product-search" oninput="filterProducts()" placeholder="Search product name or SKU ID..." class="pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none w-full transition-all shadow-sm" />
            </div>
            
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <select id="filter-category" onchange="filterProducts()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer transition-all shadow-sm">
                    <option value="">All Categories</option>
                    <?php foreach($uniqueCategories as $uc): ?>
                        <option value="<?php echo htmlspecialchars($uc); ?>"><?php echo htmlspecialchars($uc); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="filter-status" onchange="filterProducts()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer transition-all shadow-sm">
                    <option value="">All Stock Status</option>
                    <option value="In Stock">In Stock (> 10)</option>
                    <option value="Low Stock">Low Stock (1-10)</option>
                    <option value="Out of Stock">Out of Stock (0)</option>
                </select>

                <select id="filter-type" onchange="filterProducts()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer transition-all shadow-sm">
                    <option value="">All Types</option>
                    <option value="Standard">Standard</option>
                    <option value="Flash">Flash Deals</option>
                </select>
            </div>
        </div>

        <!-- Products Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-[11px] uppercase tracking-widest text-slate-500">
                        <th class="p-5 sm:px-6 font-bold w-12 text-center">SL</th>
                        <th class="p-5 font-bold">Product Details</th>
                        <th class="p-5 font-bold">Category</th>
                        <th class="p-5 font-bold">Pricing</th>
                        <th class="p-5 font-bold">Inventory</th>
                        <th class="p-5 sm:px-6 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-700" id="products-table-body">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="p-12 text-center">
                                <i data-lucide="package-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                                <p class="text-slate-500 font-medium">No products found in the catalog.</p>
                                <a href="add-product" class="text-blue-600 hover:underline font-bold mt-2 inline-block">Add your first product</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $serial = 1; ?>
                        <?php foreach ($products as $product): ?>
                            <?php 
                            $sold = max(0, $product['initial_stock'] - $product['total_stock']); 
                            $stockStatusColor = $product['total_stock'] > 10 ? 'emerald' : ($product['total_stock'] > 0 ? 'amber' : 'rose');
                            $stockStatusText = $product['total_stock'] > 10 ? 'In Stock' : ($product['total_stock'] > 0 ? 'Low Stock' : 'Out of Stock');
                            ?>
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors product-row" 
                                data-search="<?php echo strtolower($product['name'] . ' ' . $product['slug'] . ' ' . $product['sku']); ?>"
                                data-category="<?php echo htmlspecialchars($product['category_name'] ?? ''); ?>"
                                data-status="<?php echo $stockStatusText; ?>"
                                data-type="<?php echo $product['isFlashDeal'] ? 'Flash' : 'Standard'; ?>"
                            >
                                <td class="p-5 sm:px-6 text-center font-bold text-slate-400"><?php echo $serial++; ?></td>
                                <td class="p-5 sm:px-8">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0">
                                            <?php $firstImage = $product['imageUrl'] ? explode(',', $product['imageUrl'])[0] : ''; ?>
                                            <img src="<?php echo $firstImage ? '..' . $firstImage : 'https://placehold.co/100x100?text=No+Image'; ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="min-w-0 max-w-[200px] sm:max-w-xs">
                                            <p class="font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($product['name']); ?>"><?php echo htmlspecialchars($product['name']); ?></p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-[10px] font-mono font-bold bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">SKU: <?php echo htmlspecialchars($product['sku'] ?: $product['slug']); ?></span>
                                                <?php if ($product['isFlashDeal']): ?>
                                                    <span class="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded flex items-center gap-1">
                                                        <i data-lucide="zap" class="w-3 h-3"></i> Flash
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <?php if ($product['category_name']): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100 whitespace-nowrap">
                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">Uncategorized</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-5">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-black text-slate-900 text-sm">৳<?php echo number_format($product['basePrice']); ?></span>
                                        <?php if ($product['isFlashDeal'] && $product['flashDealPrice']): ?>
                                            <span class="text-[10px] font-bold text-rose-500 line-through">৳<?php echo number_format($product['flashDealPrice']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-1 rounded border border-<?php echo $stockStatusColor; ?>-200 bg-<?php echo $stockStatusColor; ?>-50 text-<?php echo $stockStatusColor; ?>-700 text-[10px] font-bold uppercase tracking-widest flex items-center gap-1.5 w-fit whitespace-nowrap">
                                                <div class="w-1.5 h-1.5 rounded-full bg-<?php echo $stockStatusColor; ?>-500 <?php echo $product['total_stock'] <= 10 && $product['total_stock'] > 0 ? 'animate-pulse' : ''; ?>"></div>
                                                <?php echo $stockStatusText; ?>: <?php echo $product['total_stock']; ?>
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-bold text-slate-500">Sold: <span class="text-blue-600"><?php echo $sold; ?> units</span></p>
                                    </div>
                                </td>
                                <td class="p-5 sm:px-6 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="../p/<?php echo $product['slug']; ?>" target="_blank" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="View in Store">
                                            <i data-lucide="external-link" class="w-4.5 h-4.5"></i>
                                        </a>
                                        <a href="edit-product?id=<?php echo $product['id']; ?>" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                            <i data-lucide="edit-3" class="w-4.5 h-4.5"></i>
                                        </a>
                                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                                            <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Search & Filter Logic
function filterProducts() {
    const term = document.getElementById('product-search').value.toLowerCase();
    const category = document.getElementById('filter-category').value;
    const status = document.getElementById('filter-status').value;
    const type = document.getElementById('filter-type').value;

    document.querySelectorAll('.product-row').forEach(row => {
        const rowSearch = row.getAttribute('data-search');
        const rowCategory = row.getAttribute('data-category');
        const rowStatus = row.getAttribute('data-status');
        const rowType = row.getAttribute('data-type');

        const matchSearch = term === '' || rowSearch.includes(term);
        const matchCategory = category === '' || rowCategory === category;
        const matchStatus = status === '' || rowStatus === status;
        const matchType = type === '' || rowType === type;

        row.style.display = (matchSearch && matchCategory && matchStatus && matchType) ? '' : 'none';
    });
}

async function deleteProduct(id) {
    customConfirm("Are you sure you want to delete this product? All its variants and images will also be removed.", async () => {
        try {
            const res = await fetch(`../api/delete_product.php?id=${id}`, { method: 'DELETE' });
            const result = await res.json();
            if (result.success) {
                location.reload();
            } else {
                showToast(result.error || "Failed to delete product.", "error");
            }
        } catch(e) { 
            console.error(e);
            showToast("Error: " + (e.message || "Network error"), "error"); 
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>