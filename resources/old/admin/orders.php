<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Auto-create OrderItem table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `OrderItem` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `orderId` INT NOT NULL,
        `productId` INT NOT NULL,
        `variantId` INT DEFAULT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `price` DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (`orderId`) REFERENCES `Order`(`id`) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// Auto-add tracking columns if missing
try { $pdo->exec("ALTER TABLE `Order` ADD COLUMN `courierName` VARCHAR(255) NULL AFTER `status`, ADD COLUMN `trackingLink` VARCHAR(255) NULL AFTER `courierName`"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `Order` ADD COLUMN `paymentMethod` VARCHAR(50) DEFAULT 'CASH ON DELIVERY' AFTER `total`"); } catch (Exception $e) {}

try {
    // Fetch Orders
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM `Order` o 
        JOIN User u ON o.userId = u.id 
        ORDER BY o.createdAt DESC
    ");
    $orders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $orders = [];
}

ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="orders-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="w-6 h-6 text-blue-600"></i> Order Management
                </h2>
                <p class="text-sm text-slate-500 mt-1">View detailed order information, update tracking, and print invoices.</p>
            </div>
        </div>

        <!-- Filters & Search Bar -->
        <div class="p-4 sm:px-6 flex flex-col lg:flex-row gap-4 justify-between items-center bg-slate-50/30 border-b border-slate-100">
            <div class="flex items-center gap-3 w-full lg:w-auto">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest hidden sm:inline">Show</span>
                <select id="per-page" onchange="currentPage = 1; filterOrders()" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer shadow-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest hidden sm:inline">Entries</span>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                <div class="relative w-full sm:w-64 shrink-0">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                    <input type="text" id="order-search" oninput="currentPage = 1; filterOrders()" placeholder="Search ID or Customer..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none w-full transition-all shadow-sm" />
                </div>
                <input type="date" id="filter-date" onchange="currentPage = 1; filterOrders()" class="px-4 py-2.5 w-full sm:w-auto bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer shadow-sm" title="Filter by Date">
                <select id="filter-status" onchange="currentPage = 1; filterOrders()" class="px-4 py-2.5 w-full sm:w-auto bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer shadow-sm">
                    <option value="">All Statuses</option>
                    <option value="PENDING">Pending</option>
                    <option value="PROCESSING">Processing</option>
                    <option value="SHIPPED">Shipped</option>
                    <option value="DELIVERED">Delivered</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-500">
                        <th class="p-5 font-bold w-12 text-center">SL</th>
                        <th class="p-5 sm:px-8 font-bold">Order ID</th>
                        <th class="p-5 font-bold">Customer</th>
                        <th class="p-5 font-bold">Date & Time</th>
                        <th class="p-5 font-bold text-right">Amount</th>
                        <th class="p-5 font-bold text-center">Payment</th>
                        <th class="p-5 font-bold">Status</th>
                        <th class="p-5 sm:px-8 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-700" id="orders-table-body">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="p-12 text-center">
                                <i data-lucide="inbox" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                                <p class="text-slate-500 font-medium">No orders found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php $formattedId = str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors order-row" data-search="<?php echo strtolower($formattedId . ' ' . $order['customer_name']); ?>" data-status="<?php echo $order['status']; ?>" data-date="<?php echo date('Y-m-d', strtotime($order['createdAt'])); ?>">
                                <td class="p-5 text-center font-bold text-slate-400 serial-number"></td>
                                <td class="p-5 sm:px-8 font-black text-blue-600 text-base">#<?php echo $formattedId; ?></td>
                                <td class="p-5">
                                    <p class="font-bold text-slate-800"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                    <p class="text-[11px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($order['customer_phone'] ?: $order['customer_email']); ?></p>
                                </td>
                                <td class="p-5 text-xs font-semibold text-slate-500">
                                    <?php echo date('M d, Y h:i A', strtotime($order['createdAt'])); ?>
                                </td>
                                <td class="p-5 font-black text-slate-900 text-base text-right">
                                    ৳<?php echo number_format($order['total']); ?>
                                </td>
                                <td class="p-5 text-center">
                                    <?php 
                                    $pm = !empty($order['paymentMethod']) ? $order['paymentMethod'] : 'CASH ON DELIVERY';
                                    $isPaid = in_array($pm, ['BKASH', 'POS CASH']) || $order['status'] === 'DELIVERED';
                                    ?>
                                    <span class="px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest border <?php echo $isPaid ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'; ?>">
                                        <?php echo $isPaid ? 'PAID' : 'UNPAID'; ?>
                                    </span>
                                </td>
                                <td class="p-5">
                                    <?php 
                                    $s = $order['status'];
                                    $bg = $s=='PENDING'?'bg-amber-50 text-amber-700 border-amber-200':($s=='PROCESSING'?'bg-blue-50 text-blue-700 border-blue-200':($s=='SHIPPED'?'bg-indigo-50 text-indigo-700 border-indigo-200':($s=='DELIVERED'?'bg-emerald-50 text-emerald-700 border-emerald-200':'bg-rose-50 text-rose-700 border-rose-200')));
                                    ?>
                                    <span class="px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest border <?php echo $bg; ?>">
                                        <?php echo $s; ?>
                                    </span>
                                </td>
                                <td class="p-5 sm:px-8 text-right">
                                    <a href="order-details?id=<?php echo $order['id']; ?>" class="inline-block p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors border border-transparent hover:border-blue-200" title="Manage Order">
                                        <i data-lucide="external-link" class="w-5 h-5"></i>
                                    </a>
                                    <a href="invoice?id=<?php echo $order['id']; ?>" target="_blank" class="inline-block p-2 text-emerald-600 hover:bg-emerald-100 rounded-lg transition-colors border border-transparent hover:border-emerald-200" title="Print Invoice">
                                        <i data-lucide="printer" class="w-5 h-5"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination UI -->
        <div id="pagination-controls" class="p-4 sm:px-8 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs font-bold text-slate-500" id="pagination-info">Showing 0 to 0 of 0 entries</p>
            <div class="flex items-center gap-2" id="pagination-buttons">
                <!-- JS Injected Buttons -->
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;

function filterOrders() {
    const term = document.getElementById('order-search').value.toLowerCase();
    const status = document.getElementById('filter-status').value;
    const date = document.getElementById('filter-date').value;
    const perPage = parseInt(document.getElementById('per-page').value);
    
    const allRows = Array.from(document.querySelectorAll('.order-row'));
    let visibleRows = [];

    allRows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        const rowStatus = row.getAttribute('data-status');
        const rowDate = row.getAttribute('data-date');

        const matchSearch = term === '' || searchData.includes(term);
        const matchStatus = status === '' || rowStatus === status;
        const matchDate = date === '' || rowDate === date;

        if (matchSearch && matchStatus && matchDate) {
            visibleRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    const totalItems = visibleRows.length;
    const totalPages = Math.ceil(totalItems / perPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * perPage;
    const end = start + perPage;

    visibleRows.forEach((row, index) => {
        if (index >= start && index < end) {
            row.style.display = '';
            row.querySelector('.serial-number').innerText = index + 1;
        } else {
            row.style.display = 'none';
        }
    });

    renderPagination(totalItems, start, Math.min(end, totalItems), totalPages);
}

function renderPagination(total, start, end, totalPages) {
    document.getElementById('pagination-info').innerText = `Showing ${total > 0 ? start + 1 : 0} to ${end} of ${total} entries`;
    
    let btnsHtml = `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all">Prev</button>`;
    
    for(let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            btnsHtml += `<button onclick="changePage(${i})" class="px-3 py-1.5 rounded-lg border ${currentPage === i ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100'} text-sm font-bold transition-all w-9">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            btnsHtml += `<span class="px-1 text-slate-400">...</span>`;
        }
    }
    
    btnsHtml += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all">Next</button>`;
    
    document.getElementById('pagination-buttons').innerHTML = btnsHtml;
}

function changePage(page) {
    currentPage = page;
    filterOrders();
}

// Initialize table on load
document.addEventListener('DOMContentLoaded', filterOrders);
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>