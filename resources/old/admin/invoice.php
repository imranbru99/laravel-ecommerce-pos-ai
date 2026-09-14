<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login"); exit;
}
require_once __DIR__ . '/../db.php';

$orderId = $_GET['id'] ?? null;
if (!$orderId) { die('Order ID required'); }

// Fetch Store Settings
$setStmt = $pdo->query("SELECT logoUrl, storeName, supportEmail, supportPhone FROM SettingGeneral LIMIT 1");
$settings = $setStmt->fetch(PDO::FETCH_ASSOC);

// Fetch Order
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) { die('Order not found'); }

// Fetch Items
$itemStmt = $pdo->prepare("SELECT oi.*, p.name, v.size, v.color FROM OrderItem oi JOIN Product p ON oi.productId = p.id LEFT JOIN Variant v ON oi.variantId = v.id WHERE oi.orderId = ?");
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { margin: 0; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #fff !important; }
            .no-print { display: none !important; }
            .invoice-container { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 2cm !important; width: 100% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans p-4 sm:p-8" onload="<?php echo isset($_GET['popup']) ? '' : 'window.print()'; ?>">
    <div class="invoice-container max-w-4xl mx-auto bg-white p-8 sm:p-16 border border-slate-200 shadow-xl rounded-2xl relative overflow-hidden">
        
        <!-- Decorative Background Element -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-slate-50 rounded-bl-[100%] z-0 no-print"></div>
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start mb-12 relative z-10 gap-6">
            <div class="flex-1">
                <?php if(!empty($settings['logoUrl'])): ?>
                    <img src="../<?php echo ltrim($settings['logoUrl'], '/'); ?>" alt="Logo" class="h-14 mb-4 object-contain">
                <?php else: ?>
                    <h1 class="text-3xl font-black text-indigo-900 uppercase tracking-tight mb-2"><?php echo htmlspecialchars($settings['storeName'] ?? ''); ?></h1>
                <?php endif; ?>
                <p class="text-slate-500 text-sm font-medium"><?php echo htmlspecialchars($settings['supportEmail'] ?? ''); ?></p>
                <p class="text-slate-500 text-sm font-medium"><?php echo htmlspecialchars($settings['supportPhone'] ?? ''); ?></p>
            </div>
            <div class="sm:text-right">
                <h2 class="text-4xl sm:text-5xl font-black text-slate-100 uppercase tracking-widest mb-2 select-none">INVOICE</h2>
                <div class="inline-block bg-slate-50 px-4 py-2 rounded-lg border border-slate-100">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Invoice Number</p>
                    <p class="font-black text-slate-800 text-xl">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-12 relative z-10">
            <!-- Billed To -->
            <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2"><span class="w-2 h-2 bg-indigo-500 rounded-full"></span> Billed To</p>
                <h3 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($order['customer_name']); ?></h3>
                <p class="text-sm font-medium text-slate-600"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                <p class="text-sm font-medium text-slate-600"><?php echo htmlspecialchars($order['customer_email']); ?></p>
            </div>
            <!-- Details -->
            <div class="sm:text-right space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center sm:justify-end gap-2"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span> Delivery Information</p>
                <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($order['city']); ?></p>
                <p class="text-sm font-medium text-slate-600 max-w-xs sm:ml-auto leading-relaxed"><?php echo nl2br(htmlspecialchars($order['deliveryAddress'])); ?></p>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-6 sm:gap-12 mb-10 pb-8 border-b border-slate-200 relative z-10">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Issue Date</p>
                <p class="text-sm font-bold text-slate-800"><?php echo date('d M, Y', strtotime($order['createdAt'])); ?></p>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Payment Method</p>
                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($order['paymentMethod'] ?? 'COD'); ?></p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="relative z-10 bg-slate-50 rounded-2xl overflow-hidden mb-10 border border-slate-100">
            <table class="w-full text-left">
                <thead class="bg-slate-100 border-b border-slate-200">
                    <tr class="text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <th class="py-4 px-6">Item Description</th>
                        <th class="py-4 px-6 text-center">Qty</th>
                        <th class="py-4 px-6 text-right">Unit Price</th>
                        <th class="py-4 px-6 text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100">
                    <?php foreach($items as $item): ?>
                    <tr class="bg-white">
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900"><?php echo htmlspecialchars($item['name']); ?></p>
                            <?php if ($item['size'] !== 'Standard' || $item['color'] !== 'Default'): ?>
                                <p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">
                                    <?php echo $item['size'] !== 'Standard' ? "{$item['size']} " : ''; ?>
                                    <?php echo $item['size'] !== 'Standard' && $item['color'] !== 'Default' ? "• " : ''; ?>
                                    <?php echo $item['color'] !== 'Default' ? "{$item['color']}" : ''; ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-6 text-center font-bold text-slate-600"><?php echo $item['quantity']; ?></td>
                        <td class="py-4 px-6 text-right font-bold text-slate-600">৳<?php echo number_format($item['price']); ?></td>
                        <td class="py-4 px-6 text-right font-black text-slate-900">৳<?php echo number_format($item['price'] * $item['quantity']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="flex flex-col sm:flex-row justify-between items-end gap-8 mb-12 relative z-10">
            <div class="text-slate-400 w-full sm:w-1/2">
                <p class="text-[10px] font-black uppercase tracking-widest mb-2">Terms & Conditions</p>
                <p class="text-xs font-medium leading-relaxed">Thank you for shopping with us! Please keep this invoice for any future warranty or return requests. For our return policy, visit our website.</p>
            </div>
            <div class="w-full sm:w-80 space-y-3 text-sm">
                <div class="flex justify-between font-bold text-slate-500">
                    <span>Item price:</span>
                    <span class="text-slate-800">৳<?php echo number_format($order['total'] - $order['shippingCharge'] + ($order['discountAmount'] ?? 0)); ?></span>
                </div>
                <div class="flex justify-between font-bold text-slate-500 pt-2 border-t border-slate-200">
                    <span>Sub total:</span>
                    <span class="text-slate-800">৳<?php echo number_format($order['total'] - $order['shippingCharge'] + ($order['discountAmount'] ?? 0)); ?></span>
                </div>
                <?php if (!empty($order['couponCode'])): ?>
                <div class="flex justify-between font-bold text-slate-500">
                    <span>Coupon (<?php echo htmlspecialchars($order['couponCode']); ?>):</span>
                    <span class="text-emerald-500 font-bold">- ৳<?php echo number_format($order['discountAmount'] ?? 0); ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between font-bold text-slate-500">
                    <span>Vat/Tax:</span>
                    <span class="text-slate-800">৳0.00</span>
                </div>
                <div class="flex justify-between items-center pt-4 mt-2 border-t-2 border-slate-300 border-dashed">
                    <span class="font-black text-slate-900 uppercase tracking-widest">Total</span>
                    <span class="text-2xl font-black text-slate-900">৳<?php echo number_format($order['total']); ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center pt-8 border-t border-slate-100 text-sm text-slate-400 font-bold relative z-10">
            <p class="mb-4">This is a system generated invoice and does not require a physical signature.</p>
            <div class="no-print space-x-3 flex justify-center <?php echo isset($_GET['popup']) ? 'hidden' : ''; ?>">
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-bold shadow-md transition-all flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg> Print Invoice</button>
                <button onclick="window.close()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-6 py-2.5 rounded-xl font-bold transition-all">Close Tab</button>
            </div>
        </div>
    </div>
</body>
</html>