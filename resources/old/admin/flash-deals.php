<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Handle AJAX Request for updating flash deal status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_flash_status') {
    header('Content-Type: application/json');
    $id = $_POST['id'] ?? '';
    $status = isset($_POST['status']) && $_POST['status'] === 'true' ? 1 : 0;
    $price = $_POST['price'] ?? null;

    try {
        if ($status) {
            $stmt = $pdo->prepare("UPDATE Product SET isFlashDeal = 1, flashDealPrice = ? WHERE id = ?");
            $stmt->execute([$price, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE Product SET isFlashDeal = 0, flashDealPrice = NULL WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX Request for saving timer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_timer') {
    header('Content-Type: application/json');
    $flashDealEndTime = !empty($_POST['flashDealEndTime']) ? date('Y-m-d H:i:s', strtotime($_POST['flashDealEndTime'])) : null;
    try {
        $stmt = $pdo->prepare("UPDATE SettingGeneral SET flashDealEndTime = ? WHERE id = 1");
        $stmt->execute([$flashDealEndTime]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch Settings
try {
    $stmt = $pdo->query("SELECT flashDealEndTime FROM SettingGeneral LIMIT 1");
    $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $settings = [];
}

// Fetch Products
try {
    $stmt = $pdo->query("SELECT * FROM Product ORDER BY id DESC");
    $products = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $products = [];
}

$endTime = !empty($settings['flashDealEndTime']) 
    ? date('Y-m-d\TH:i', strtotime($settings['flashDealEndTime'])) 
    : "";
ob_start();
?>

<!-- HTML structure -->
<div class="max-w-7xl mx-auto font-sans pb-12" id="flash-deals-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="zap" class="w-6 h-6 text-amber-500"></i> Flash Deals Manager
                </h2>
                <p class="text-sm text-slate-500 mt-1">Select which products to showcase in the Flash Deals section.</p>
            </div>
            
            <form id="settings-form" class="flex items-center gap-3">
                <input 
                    type="datetime-local" 
                    name="flashDealEndTime" 
                    value="<?php echo $endTime; ?>"
                    class="w-full max-w-[220px] px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-500/20 transition-all shadow-sm"
                >
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg transition-all flex items-center gap-2 text-sm">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    <span>Set Timer</span>
                </button>
            </form>
        </div>

        <!-- Search and Product Grid -->
        <div class="p-6 sm:p-8 bg-slate-50/30 space-y-6">
            <div class="relative max-w-md">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                <input 
                    type="text" 
                    id="product-search"
                    placeholder="Search product name or SKU ID..." 
                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-amber-500 outline-none transition-all shadow-sm"
                >
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="product-grid">
            <?php foreach ($products as $p): 
                $isFlash = !empty($p['isFlashDeal']);
                $img = !empty($p['imageUrl']) ? $p['imageUrl'] : "https://placehold.co/100x100?text=No+Image";
            ?>
                <div class="product-card p-4 rounded-2xl border transition-all hover:shadow-md <?php echo $isFlash ? 'border-amber-400 bg-amber-50/30' : 'border-slate-200 bg-white'; ?>" 
                     data-search="<?php echo strtolower($p['name'] . ' ' . $p['slug'] . ' ' . $p['sku']); ?>">
                    
                    <div class="flex gap-4 items-center">
                        <img src="<?php echo $img; ?>" class="w-16 h-16 object-cover rounded-xl border border-slate-200">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-slate-800 truncate text-sm"><?php echo htmlspecialchars($p['name']); ?></h4>
                            <p class="text-xs text-slate-500 mt-1">Base: ৳<?php echo $p['basePrice']; ?></p>
                            <?php if ($isFlash): ?>
                                <p class="text-xs font-bold text-amber-600 mt-0.5">Flash: ৳<?php echo htmlspecialchars($p['flashDealPrice'] ?? '0'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button 
                        onclick="initiateToggle(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)"
                        class="mt-4 w-full py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 transition-all <?php echo $isFlash ? 'bg-amber-500 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>"
                    >
                        <?php echo $isFlash ? '<i data-lucide="check" class="w-4 h-4"></i> Active Flash Deal' : '<i data-lucide="zap" class="w-4 h-4"></i> Add to Flash Deals'; ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</div>

<!-- Price Modal (Hidden by Default) -->
<div id="price-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-6">
        <h3 class="text-xl font-bold text-slate-800">Set Flash Deal Price</h3>
        <div>
            <p id="modal-product-name" class="text-slate-600 text-sm mb-4"></p>
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mb-4">
                <span class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Base Price</span>
                <div id="modal-base-price" class="text-2xl font-black text-slate-800"></div>
            </div>
            <input type="number" id="flash-price-input" class="w-full px-5 py-3.5 bg-white border-2 border-slate-200 rounded-xl outline-none text-lg font-bold">
        </div>
        <div class="flex justify-end gap-3">
            <button onclick="closeModal()" class="px-6 py-3 font-bold text-slate-600">Cancel</button>
            <button onclick="confirmFlashDeal()" class="px-6 py-3 rounded-xl font-bold bg-amber-500 text-white shadow-lg">Confirm</button>
        </div>
    </div>
</div>

<script>
let currentProduct = null;

// Search Logic
document.getElementById('product-search').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const searchData = card.getAttribute('data-search');
        card.style.display = searchData.includes(term) ? 'block' : 'none';
    });
});

function initiateToggle(product) {
    if (product.isFlashDeal == 0 || !product.isFlashDeal) {
        currentProduct = product;
        document.getElementById('modal-product-name').innerText = `Set price for ${product.name}`;
        document.getElementById('modal-base-price').innerText = `৳${product.basePrice}`;
        document.getElementById('flash-price-input').value = product.basePrice;
        document.getElementById('price-modal').classList.remove('hidden');
        document.getElementById('price-modal').classList.add('flex');
    } else {
        updateFlashStatus(product.id, false, null);
    }
}

function closeModal() {
    document.getElementById('price-modal').classList.add('hidden');
}

function confirmFlashDeal() {
    const price = document.getElementById('flash-price-input').value;
    if (price && currentProduct) {
        updateFlashStatus(currentProduct.id, true, price);
        closeModal();
    }
}

async function updateFlashStatus(id, status, price) {
    try {
        const formData = new FormData();
        formData.append('action', 'update_flash_status');
        formData.append('id', id);
        formData.append('status', status);
        if (price) formData.append('price', price);

        const res = await fetch('flash-deals.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        
        if (result.success) {
            showToast(status ? "Added to Flash Deals!" : "Removed from Flash Deals!");
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.error || "Failed to update status", "error");
        }
    } catch(e) {
        showToast("Network error occurred", "error");
    }
}

document.getElementById('settings-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'save_timer');
    try {
        const res = await fetch('flash-deals.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast("Flash deal timer updated successfully!");
        } else {
            showToast(result.error || "Failed to save timer", "error");
        }
    } catch(e) {
        showToast("Network error occurred", "error");
    }
});
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>