<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Fetch Settings for Payment Methods
try {
    $setStmt = $pdo->query("SELECT codEnabled, bkashEnabled FROM SettingPayment LIMIT 1");
    $settings = $setStmt ? $setStmt->fetch(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $settings = [];
}

// Fetch products with variants
try {
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.basePrice, p.sku, p.serial_number, v.id as variant_id, v.size, v.color, v.imageUrl, v.stock
        FROM Product p
        LEFT JOIN Variant v ON p.id = v.productId
    ");
    $rawProducts = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $rawProducts = [];
}

$products = [];
foreach ($rawProducts as $row) {
    $pid = $row['id'];
    if (!isset($products[$pid])) {
        $products[$pid] = ['id' => $row['id'], 'name' => $row['name'], 'sku' => $row['sku'], 'serial_number' => $row['serial_number'], 'basePrice' => $row['basePrice'], 'variants' => []];
    }
    if ($row['variant_id']) {
        $products[$pid]['variants'][] = [
            'id' => $row['variant_id'], 'size' => $row['size'], 'color' => $row['color'], 
            'imageUrl' => $row['imageUrl'], 'stock' => $row['stock'], 'price' => $row['basePrice']
        ];
    } else {
        $products[$pid]['variants'][] = [
            'id' => 0, 'size' => 'Standard', 'color' => 'Default', 
            'imageUrl' => 'https://placehold.co/100x100?text=No+Image', 'stock' => 0, 'price' => $row['basePrice']
        ];
    }
}
$products = array_values($products);
ob_start();
?>

<!-- প্রিন্ট করার জন্য বিশেষ CSS -->
<style>
    @media print {
        body * { visibility: hidden; }
        #pos-invoice-area, #pos-invoice-area * { visibility: visible; }
        #pos-invoice-area { position: absolute; left: 0; top: 0; width: 100mm; }
    }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="flex flex-col lg:flex-row h-[calc(100vh-180px)] gap-6 font-sans">
    
    <!-- LEFT: Product Selection -->
    <div class="flex-1 flex flex-col min-w-0 bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="relative max-w-md">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5"></i>
                <input type="text" id="pos-search" placeholder="Search product name or SKU ID..." class="w-full pl-12 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar" id="product-grid">
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $p): ?>
                    <div class="product-card bg-white border border-slate-100 rounded-2xl hover:shadow-lg transition-all group overflow-hidden" 
                         data-search="<?php echo strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['serial_number']); ?>">
                        <div class="aspect-[4/3] bg-slate-50 relative">
                            <img src="<?php echo $p['variants'][0]['imageUrl'] ?? 'placeholder.jpg'; ?>" class="w-full h-full object-cover">
                            <div class="absolute top-3 right-3 bg-white/90 px-2 py-1 rounded-lg text-xs font-bold shadow-sm">
                                TK <?php echo number_format($p['basePrice']); ?>
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="text-sm font-bold text-slate-800 line-clamp-1"><?php echo $p['name']; ?></h3>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php foreach ($p['variants'] as $v): ?>
                                    <button onclick="addToCart(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8'); ?>)" 
                                            class="flex-1 min-w-[60px] text-[10px] font-semibold bg-slate-50 border border-slate-200 px-2 py-1.5 rounded-lg hover:bg-blue-600 hover:text-white transition-colors">
                                        <?php echo $v['size']; ?> • <?php echo $v['color']; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Cart Sidebar -->
    <div class="w-full lg:w-[400px] flex flex-col bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-100 text-blue-600 rounded-xl"><i data-lucide="shopping-cart" class="w-5 h-5"></i></div>
                <h2 class="font-bold text-lg">Current Order</h2>
            </div>
            <button onclick="clearCart()" class="text-xs font-bold text-red-500 hover:bg-red-50 px-3 py-1.5 rounded-lg">Clear</button>
        </div>
        
        <div id="cart-items" class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
            <!-- Cart items will be injected here via JS -->
            <div class="h-full flex flex-col items-center justify-center opacity-40">
                <i data-lucide="shopping-bag" class="w-12 h-12 mb-2"></i>
                <p class="font-bold">Cart is empty</p>
            </div>
        </div>
        
        <div class="p-6 bg-slate-50 border-t">
            <div class="space-y-2 mb-6">
                <div class="flex justify-between text-sm font-medium text-slate-500"><span>Subtotal</span><span id="subtotal">TK 0</span></div>
                <div class="flex justify-between items-center pt-3 border-t">
                    <span class="text-base font-bold">Total</span>
                    <span class="text-2xl font-black text-slate-900" id="grand-total">TK 0</span>
                </div>
            </div>
            <button onclick="openCheckoutModal()" id="checkout-btn" disabled class="w-full py-4 bg-blue-600 text-white font-bold rounded-2xl shadow-lg disabled:bg-slate-300 transition-all">
                Checkout
            </button>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div id="checkout-modal" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl overflow-hidden shadow-2xl">
        <div id="modal-form-content" class="p-6 space-y-5">
            <h3 class="text-xl font-bold">Customer Details</h3>
            <input type="text" id="cust-name" placeholder="Customer Name" class="w-full p-3 bg-slate-50 border rounded-xl outline-none">
            <input type="tel" id="cust-phone" placeholder="Phone Number *" class="w-full p-3 bg-slate-50 border rounded-xl outline-none">
            <textarea id="cust-address" placeholder="Address" class="w-full p-3 bg-slate-50 border rounded-xl outline-none"></textarea>
            <select id="payment-method" class="w-full p-3 bg-slate-50 border rounded-xl outline-none font-bold text-slate-700">
                <?php if (!empty($settings['codEnabled'])): ?>
                    <option value="CASH ON DELIVERY">Cash on Delivery</option>
                <?php endif; ?>
                <?php if (!empty($settings['bkashEnabled'])): ?>
                    <option value="BKASH">bKash</option>
                <?php endif; ?>
                <option value="POS CASH">POS Cash (In-Store)</option>
            </select>
            <button onclick="confirmOrder()" id="confirm-btn" class="w-full py-4 bg-blue-600 text-white font-bold rounded-xl shadow-lg">Confirm Order</button>
            <button onclick="closeModal()" class="w-full text-slate-400 font-bold">Cancel</button>
        </div>
        <div id="modal-success-content" class="hidden p-10 text-center">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">✓</div>
            <h3 class="text-2xl font-bold">Order Successful!</h3>
            <div class="mt-6 flex flex-col gap-3">
                <button onclick="window.print()" class="py-3 bg-slate-900 text-white rounded-xl font-bold">Print Receipt</button>
                <button onclick="resetPOS()" class="py-3 bg-slate-100 rounded-xl font-bold">New Sale</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Invoice Template -->
<div id="pos-invoice-area" class="hidden print:block p-6 text-black bg-white">
    <!-- Invoice content handled via JS -->
</div>

<script>
let cart = [];

function addToCart(product, variant) {
    const cartId = `${product.id}-${variant.id}`;
    const existing = cart.find(item => item.cartId === cartId);
    
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            cartId, id: product.id, variantId: variant.id, 
            name: product.name, size: variant.size, color: variant.color,
            price: variant.price || product.basePrice, quantity: 1,
            image: variant.imageUrl || 'placeholder.jpg'
        });
    }
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items');
    if (cart.length === 0) {
        container.innerHTML = `<div class="h-full flex flex-col items-center justify-center opacity-40"><p class="font-bold">Cart is empty</p></div>`;
        document.getElementById('checkout-btn').disabled = true;
    } else {
        container.innerHTML = cart.map(item => `
            <div class="flex items-center gap-4 p-3 hover:bg-slate-50 rounded-2xl border border-transparent hover:border-slate-100">
                <img src="${item.image}" class="w-12 h-12 rounded-lg object-cover">
                <div class="flex-1">
                    <p class="font-bold text-sm truncate">${item.name}</p>
                    <p class="text-[10px] text-slate-500">${item.size} • ${item.color}</p>
                    <p class="font-bold text-blue-600 text-xs">TK ${item.price}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="updateQty('${item.cartId}', -1)" class="w-6 h-6 border rounded">-</button>
                    <span class="font-bold text-xs">${item.quantity}</span>
                    <button onclick="updateQty('${item.cartId}', 1)" class="w-6 h-6 border rounded">+</button>
                </div>
            </div>
        `).join('');
        document.getElementById('checkout-btn').disabled = false;
    }
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    document.getElementById('subtotal').innerText = `TK ${total.toLocaleString()}`;
    document.getElementById('grand-total').innerText = `TK ${total.toLocaleString()}`;
}

function updateQty(cartId, delta) {
    const item = cart.find(i => i.cartId === cartId);
    if (item) {
        item.quantity = Math.max(1, item.quantity + delta);
        renderCart();
    }
}

function clearCart() { cart = []; renderCart(); }
function openCheckoutModal() { document.getElementById('checkout-modal').style.display = 'flex'; }
function closeModal() { document.getElementById('checkout-modal').style.display = 'none'; }

async function confirmOrder() {
    const btn = document.getElementById('confirm-btn');
    const phone = document.getElementById('cust-phone').value;
    if (!phone) {
        showToast("Phone number is required", "error");
        return;
    }

    btn.innerText = "Processing...";
    btn.disabled = true;

    // AJAX কল (এখানে আপনার PHP API কল করুন)
    const orderData = {
        name: document.getElementById('cust-name').value,
        phone: phone,
        paymentMethod: document.getElementById('payment-method').value,
        items: cart,
        total: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
    };

    try {
        const res = await fetch('../api/pos_checkout.php', {
            method: 'POST',
            body: JSON.stringify(orderData)
        });
        
        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(e) {
            console.error("API Error Response:", text);
            showToast("Server error. Please check the console.", "error");
            return;
        }
        
        if (result.success) {
            prepareInvoice(orderData, result.order_id);
            document.getElementById('modal-form-content').classList.add('hidden');
            document.getElementById('modal-success-content').classList.remove('hidden');
        } else {
            // যদি কোনো এরর থাকে সেটি এখন টোস্ট আকারে দেখাবে
            showToast(result.error || "Failed to process the order.", "error");
        }
    } catch (e) {
        showToast("Network error. Please try again.", "error");
    } finally {
        btn.innerText = "Confirm Order";
        btn.disabled = false;
    }
}

function prepareInvoice(data, orderId) {
    const area = document.getElementById('pos-invoice-area');
    area.innerHTML = `
        <div class="text-center border-b-2 border-black pb-4 mb-4">
            <h1 class="text-xl font-black uppercase">STORE RECEIPT</h1>
            <p class="text-xs">Order ID: #${orderId}</p>
        </div>
        <div class="text-xs mb-4">
            <p>Customer: ${data.name || 'Walk-in'}</p>
            <p>Phone: ${data.phone}</p>
            <p>Date: ${new Date().toLocaleString()}</p>
        </div>
        <table class="w-full text-xs mb-4">
            <tr class="border-b"><th>Item</th><th>Qty</th><th>Total</th></tr>
            ${data.items.map(i => `<tr><td>${i.name}</td><td>${i.quantity}</td><td>${i.price * i.quantity}</td></tr>`).join('')}
        </table>
        <div class="text-right font-black border-t-2 pt-2">GRAND TOTAL: TK ${data.total}</div>
    `;
}

function resetPOS() {
    clearCart();
    document.getElementById('modal-form-content').classList.remove('hidden');
    document.getElementById('modal-success-content').classList.add('hidden');
    closeModal();
    setTimeout(() => location.reload(), 300); // স্টক আপডেট করার জন্য পেজ রিলোড
}

// Search Functionality
document.getElementById('pos-search').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const searchData = card.getAttribute('data-search');
        card.style.display = searchData.includes(term) ? 'block' : 'none';
    });
});

lucide.createIcons();
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>