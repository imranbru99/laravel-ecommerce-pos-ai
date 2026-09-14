<?php
session_start();
require_once __DIR__ . '/db.php';

$orderData = null;
$error = null;

try {
    $setStmt = $pdo->query("SELECT storeName, logoUrl FROM SettingGeneral LIMIT 1");
    $settings = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $settings = [];
}
$logoUrl = !empty($settings['logoUrl']) ? ltrim($settings['logoUrl'], '/') : null;
$storeName = $settings['storeName'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['order_id'])) {
    $orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

    if ($orderId) {
        $stmt = $pdo->prepare("
            SELECT o.*, u.phone as customer_phone, u.email as customer_email 
            FROM `Order` o 
            JOIN User u ON o.userId = u.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $orderData = $order;
        } else {
            $error = "We couldn't find an order matching this ID.";
        }
    } else {
        $error = "Please provide an Order ID.";
    }
}

include 'Header.php';
?>

<div class="min-h-screen pt-28 md:pt-32 pb-28 md:pb-24 bg-slate-50 font-sans">
    <div class="max-w-3xl mx-auto px-6">
        <div class="text-center mb-10">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($storeName); ?>" class="h-16 w-auto mx-auto mb-4 object-contain">
            <?php else: ?>
                <div class="w-16 h-16 bg-[#003e86] text-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-900/20">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
            <?php endif; ?>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Track Your Order</h1>
            <p class="text-slate-500 font-medium mt-2">Enter your order details below to check the current status.</p>
        </div>

        <!-- Tracking Form -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 sm:p-10 mb-8">
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Order ID</label>
                    <input type="text" name="order_id" required placeholder="e.g. 100024" value="<?php echo htmlspecialchars($_GET['order_id'] ?? $_POST['order_id'] ?? ''); ?>" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#003e86] transition-all font-bold text-slate-800">
                </div>
                <div class="flex items-end pb-1">
                    <button type="submit" class="w-full sm:w-auto px-8 py-3.5 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 active:scale-95">
                        Track
                    </button>
                </div>
            </form>
            
            <?php if ($error): ?>
                <div class="mt-6 p-4 bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold rounded-xl flex items-center gap-3 animate-in fade-in slide-in-from-top-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Results / Timeline -->
        <?php if ($orderData): 
            $statuses = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED'];
            $currentStatusIndex = array_search($orderData['status'], $statuses);
            $isCancelled = $orderData['status'] === 'CANCELLED';
        ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 sm:p-10 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-6 border-b border-slate-100">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Order #<?php echo str_pad($orderData['id'], 6, '0', STR_PAD_LEFT); ?></h2>
                        <p class="text-sm text-slate-500 mt-1">Placed on <?php echo date('M d, Y', strtotime($orderData['createdAt'])); ?></p>
                    </div>
                    <div class="flex items-center gap-6">
                        <button type="button" onclick="copyTrackingLink(<?php echo $orderData['id']; ?>)" class="p-2.5 bg-blue-50 text-[#003e86] hover:bg-[#003e86] hover:text-white rounded-xl transition-all shadow-sm flex items-center gap-2 text-sm font-bold shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                            Copy Link
                        </button>
                        <div class="text-left sm:text-right">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Amount</p>
                            <p class="text-2xl font-black text-[#003e86]">৳<?php echo number_format($orderData['total']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Visual Progress Timeline -->
                <?php if ($isCancelled): ?>
                    <div class="mt-8 mb-8 p-6 sm:p-8 bg-rose-50 border border-rose-100 rounded-3xl text-center">
                        <div class="w-16 h-16 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <h3 class="text-xl font-black text-rose-700 mb-2">Order Cancelled</h3>
                        <p class="text-sm text-rose-600 font-medium">This order has been cancelled and will not be delivered.</p>
                    </div>
                <?php else: ?>
                    <div class="relative max-w-2xl mx-auto px-4 sm:px-8 mt-12 mb-8">
                        <div class="absolute left-4 sm:left-8 right-4 sm:right-8 top-5 -translate-y-1/2 h-1.5 bg-slate-100 rounded-full z-0"></div>
                        <div class="absolute left-4 sm:left-8 top-5 -translate-y-1/2 h-2 bg-gradient-to-r from-[#003e86] to-blue-400 rounded-full z-0 transition-all duration-1000 shadow-[0_0_10px_rgba(0,62,134,0.3)]" style="width: calc(<?php echo ($currentStatusIndex / 3) * 100; ?>% - <?php echo $currentStatusIndex == 3 ? '2rem' : '0rem'; ?>);"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <?php foreach($statuses as $index => $s): 
                                $isActive = $index <= $currentStatusIndex; 
                                $isCurrent = $index === $currentStatusIndex; 
                            ?>
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm transition-all duration-500 <?php echo $isActive ? 'bg-[#003e86] text-white ring-4 ring-white' : 'bg-slate-200 text-slate-400 ring-4 ring-white'; ?> <?php echo $isCurrent ? 'scale-110 shadow-lg shadow-blue-900/20' : ''; ?>">
                                    <?php if($isActive): ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?php else: echo $index + 1; endif; ?>
                                </div>
                                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest <?php echo $isActive ? 'text-[#003e86]' : 'text-slate-400'; ?> text-center">
                                    <?php echo $s; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Delivery & Tracking Info -->
                    <?php if (!empty($orderData['trackingLink']) || !empty($orderData['courierName'])): ?>
                        <div class="mt-10 bg-white border-2 border-[#003e86]/10 rounded-3xl overflow-hidden shadow-xl shadow-blue-900/5">
                            <div class="p-5 sm:p-6 bg-blue-50/80 border-b border-[#003e86]/10 flex items-center gap-4">
                                <div class="w-12 h-12 bg-white text-[#003e86] rounded-2xl flex items-center justify-center shrink-0 shadow-sm border border-blue-100">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                                </div>
                                <div>
                                    <h4 class="font-black text-slate-800 text-lg tracking-tight">Delivery & Tracking Info</h4>
                                    <p class="text-xs font-bold text-[#003e86] uppercase tracking-widest mt-0.5">Package Dispatched</p>
                                </div>
                            </div>
                            <div class="p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-8">
                                <div class="flex-1 flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
                                    <div class="w-16 h-16 bg-slate-50 border border-slate-200 rounded-[1.25rem] flex items-center justify-center text-slate-400 shrink-0 shadow-inner">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1">Courier Partner</p>
                                        <p class="text-2xl font-black text-slate-800">
                                            <?php echo htmlspecialchars($orderData['courierName'] ?: 'Our Delivery Partner'); ?>
                                        </p>
                                        <?php if (empty($orderData['trackingLink'])): ?>
                                            <p class="text-sm font-semibold text-amber-600 mt-2 flex items-center justify-center sm:justify-start gap-1.5 bg-amber-50 px-3 py-1.5 rounded-lg w-fit mx-auto sm:mx-0 border border-amber-200">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Link unavailable yet
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($orderData['trackingLink'])): ?>
                                <div class="shrink-0 w-full sm:w-auto">
                                    <a href="<?php echo htmlspecialchars($orderData['trackingLink']); ?>" target="_blank" class="w-full sm:w-auto px-8 py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-2xl font-bold shadow-xl shadow-blue-900/20 transition-all flex items-center justify-center gap-2 active:scale-95 group">
                                        Track Live 
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="group-hover:translate-x-1 transition-transform"><path d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyTrackingLink(orderId) {
    const url = window.location.origin + window.location.pathname + '?order_id=' + orderId;
    navigator.clipboard.writeText(url).then(() => {
        if(window.showToast) {
            showToast("Tracking link copied to clipboard!");
        } else {
            alert("Tracking link copied to clipboard!");
        }
    }).catch(err => {
        console.error("Could not copy text: ", err);
    });
}
</script>
<?php include 'Footer.php'; ?>