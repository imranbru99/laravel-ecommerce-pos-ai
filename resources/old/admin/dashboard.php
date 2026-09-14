<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

/**
 * 1. DATABASE & LOGIC
 * Assumes a $pdo connection is available (PDO instance)
 */

require_once __DIR__ . '/../db.php';

try {
// Basic Stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();
$totalOrders   = $pdo->query("SELECT COUNT(*) FROM `Order`")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM User")->fetchColumn();

// Revenue Logic (Delivered Only)
$stmt = $pdo->prepare("SELECT total, shippingCharge FROM `Order` WHERE status = 'DELIVERED'");
$stmt->execute();
$deliveredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalNetRevenue = 0;
$totalShippingCollected = 0;

foreach ($deliveredOrders as $o) {
    $totalNetRevenue += ($o['total'] - $o['shippingCharge']);
    $totalShippingCollected += $o['shippingCharge'];
}

// Inventory Alerts (Stock Out)
$stmt = $pdo->prepare("
    SELECT v.*, p.name as product_name 
    FROM Variant v 
    JOIN Product p ON v.productId = p.id 
    WHERE v.stock <= 0 
    LIMIT 6
");
$stmt->execute();
$outOfStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$outOfStockCount = $pdo->query("SELECT COUNT(*) FROM Variant WHERE stock <= 0")->fetchColumn();

// Chart Data (Last 7 Days)
$dailyData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT SUM(total - shippingCharge) as rev 
        FROM `Order` 
        WHERE status = 'DELIVERED' 
        AND DATE(createdAt) = :date
    ");
    $stmt->execute(['date' => $date]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $dailyData[] = [
        'date' => date('D', strtotime($date)),
        'revenue' => $res['rev'] ?? 0
    ];
}

// Recent Orders
$stmt = $pdo->prepare("
    SELECT o.id, o.total, o.status, o.createdAt, u.name as customer_name 
    FROM `Order` o 
    JOIN User u ON o.userId = u.id 
    ORDER BY o.createdAt DESC 
    LIMIT 5
");
$stmt->execute();
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $totalProducts = 0; $totalOrders = 0; $totalUsers = 0; $totalNetRevenue = 0; $totalShippingCollected = 0;
    $outOfStockItems = []; $outOfStockCount = 0; $dailyData = []; $recentOrders = [];
}

$stats = [
    ['title' => "Net Revenue", 'value' => "৳" . number_format($totalNetRevenue), 'icon' => 'banknote', 'color' => 'text-emerald-600 bg-emerald-50'],
    ['title' => "Shipping Fee", 'value' => "৳" . number_format($totalShippingCollected), 'icon' => 'truck', 'color' => 'text-blue-600 bg-blue-50'],
    ['title' => "Total Orders", 'value' => number_format($totalOrders), 'icon' => 'shopping-cart', 'color' => 'text-indigo-600 bg-indigo-50'],
    ['title' => "Stock Alerts", 'value' => $outOfStockCount, 'icon' => 'alert-triangle', 'color' => $outOfStockCount > 0 ? 'text-rose-600 bg-rose-50' : 'text-slate-500 bg-slate-100'],
];

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8 font-sans">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Dashboard Overview</h1>
            <p class="text-sm font-medium text-slate-500 mt-1">Business Intelligence & Store Performance</p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach ($stats as $stat): ?>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col gap-4 hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-600"><?php echo $stat['title']; ?></p>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center <?php echo $stat['color']; ?>">
                        <!-- SVG icons would be placed here based on $stat['icon'] -->
                    </div>
                </div>
                <div class="mt-2">
                    <h3 class="text-3xl font-bold text-slate-900"><?php echo $stat['value']; ?></h3>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Critical Stock Notification -->
    <?php if ($outOfStockCount > 0): ?>
        <div class="bg-white rounded-2xl border border-rose-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-rose-100 bg-rose-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-rose-100 text-rose-600 rounded-xl">⚠️</div>
                    <div>
                        <h2 class="text-lg font-bold text-rose-900">Critical Inventory Alerts</h2>
                        <p class="text-xs font-medium text-rose-600 mt-0.5"><?php echo $outOfStockCount; ?> items currently out of stock</p>
                    </div>
                </div>
                <a href="products" class="text-sm font-semibold text-rose-700 bg-rose-100 px-4 py-2.5 rounded-xl">Manage Inventory</a>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($outOfStockItems as $variant): ?>
                        <div class="border border-slate-200 bg-slate-50/50 p-4 rounded-xl flex items-center justify-between">
                            <div class="min-w-0 pr-4">
                                <h4 class="font-bold text-sm text-slate-800 truncate mb-1"><?php echo htmlspecialchars($variant['product_name']); ?></h4>
                                <p class="text-xs font-medium text-slate-500"><?php echo $variant['color']; ?> • <?php echo $variant['size']; ?></p>
                            </div>
                            <a href="edit_product?id=<?php echo $variant['productId']; ?>" class="p-2 bg-white border border-slate-200 rounded-lg">→</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Analytics Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Daily Sales Chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Revenue Flow</h2>
                    <p class="text-sm font-medium text-slate-500 mt-0.5">Net Sales (last 7 days)</p>
                </div>
            </div>
            <div class="relative flex-1 min-h-[250px] w-full">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Info Column -->
        <div class="bg-slate-900 text-white p-8 rounded-2xl shadow-xl flex flex-col justify-between overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-xl font-bold text-white mb-1">Global Metrics</h2>
                <p class="text-sm font-medium text-slate-400 mb-8">Store health overview</p>
                
                <div class="space-y-6">
                    <div class="border-b border-white/10 pb-6 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase mb-1">Total Users</p>
                            <p class="text-sm font-medium text-slate-300">Active Accounts</p>
                        </div>
                        <span class="text-3xl font-bold text-white"><?php echo number_format($totalUsers); ?></span>
                    </div>

                    <div class="border-b border-white/10 pb-6 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase mb-1">Total Products</p>
                            <p class="text-sm font-medium text-slate-300">Live in Catalog</p>
                        </div>
                        <span class="text-3xl font-bold text-white"><?php echo number_format($totalProducts); ?></span>
                    </div>
                </div>
            </div>

            <a href="products" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-bold text-sm text-center mt-8">Manage Catalog</a>
        </div>
    </div>

    <!-- Recent Orders Feature -->
    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-bold text-slate-800">Recent Orders</h2>
            <a href="orders" class="text-sm font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 px-4 py-2 rounded-xl transition-colors">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] uppercase tracking-widest text-slate-400">
                        <th class="pb-4 font-bold">Order ID</th>
                        <th class="pb-4 font-bold">Customer</th>
                        <th class="pb-4 font-bold">Date</th>
                        <th class="pb-4 font-bold">Status</th>
                        <th class="pb-4 font-bold text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-medium text-slate-700">
                    <?php foreach ($recentOrders as $order): ?>
                    <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                        <td class="py-4 text-blue-600 font-bold">#<?php echo $order['id']; ?></td>
                        <td class="py-4 text-slate-800 font-bold"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td class="py-4 text-slate-500 text-xs"><?php echo date('M d, Y h:i A', strtotime($order['createdAt'])); ?></td>
                        <td class="py-4">
                            <?php
                            $statusColors = ['PENDING' => 'bg-amber-100 text-amber-700 border-amber-200', 'DELIVERED' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'CANCELLED' => 'bg-rose-100 text-rose-700 border-rose-200'];
                            $color = $statusColors[$order['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                            ?>
                            <span class="px-3 py-1.5 rounded-lg text-[10px] font-bold tracking-widest uppercase border <?php echo $color; ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </td>
                        <td class="py-4 text-right font-black text-slate-900">৳<?php echo number_format($order['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="5" class="py-8 text-center text-slate-500 font-medium bg-slate-50 rounded-xl">No recent orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const chartData = <?php echo json_encode($dailyData); ?>;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => d.date),
            datasets: [{
                label: 'Revenue (৳)',
                data: chartData.map(d => d.revenue),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f1f5f9' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>