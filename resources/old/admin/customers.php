<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Safe patch for status column
try { $pdo->exec("ALTER TABLE `User` ADD COLUMN `status` ENUM('active', 'blocked') DEFAULT 'active'"); } catch (Exception $e) {}

// Handle Bulk Action via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_status') {
    header('Content-Type: application/json');
    $ids = json_decode($_POST['customer_ids'], true);
    $status = $_POST['status'] === 'blocked' ? 'blocked' : 'active';
    
    if (!empty($ids) && is_array($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $params = array_merge([$status], $ids);
        
        try {
            $stmt = $pdo->prepare("UPDATE User SET status = ? WHERE id IN ($placeholders) AND role = 'customer'");
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No customers selected.']);
    }
    exit;
}

try {
    // Fetch Customers with their order stats
    $stmt = $pdo->query("
        SELECT 
            u.id, u.name, u.email, u.phone, u.createdAt, u.status,
            COUNT(o.id) as total_orders,
            SUM(CASE WHEN o.status = 'DELIVERED' THEN o.total ELSE 0 END) as total_spent
        FROM User u
        LEFT JOIN `Order` o ON u.id = o.userId
        WHERE u.role = 'customer'
        GROUP BY u.id
        ORDER BY u.createdAt DESC
    ");
    $customers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $customers = [];
}

ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="customers-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="users" class="w-6 h-6 text-blue-600"></i> Customer Directory
                </h2>
                <p class="text-sm text-slate-500 mt-1">View and manage your customer base.</p>
            </div>
        </div>

        <!-- Filters & Search Bar -->
        <div class="p-4 sm:px-6 flex flex-col lg:flex-row gap-4 justify-between items-center bg-slate-50/30 border-b border-slate-100">
            <div class="flex items-center gap-3 w-full lg:w-auto">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest hidden sm:inline">Show</span>
                <select id="per-page" onchange="currentPage = 1; filterCustomers()" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 cursor-pointer shadow-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest hidden sm:inline">Entries</span>
                
                <!-- Bulk Actions (Hidden by default) -->
                <div class="hidden items-center gap-2 ml-4 animate-in fade-in slide-in-from-left-4" id="bulk-actions">
                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1.5 rounded-lg"><span id="selected-count">0</span> Selected</span>
                    <button onclick="bulkUpdateStatus('blocked')" class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white rounded-lg text-xs font-bold transition-colors border border-rose-200 hover:border-rose-600 shadow-sm flex items-center gap-1.5"><i data-lucide="ban" class="w-3.5 h-3.5"></i> Block</button>
                    <button onclick="bulkUpdateStatus('active')" class="px-3 py-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white rounded-lg text-xs font-bold transition-colors border border-emerald-200 hover:border-emerald-600 shadow-sm flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Unblock</button>
                </div>
            </div>

            <div class="relative w-full lg:w-80">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                <input type="text" id="customer-search" oninput="currentPage = 1; filterCustomers()" placeholder="Search by name, email, or phone..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none w-full transition-all shadow-sm" />
            </div>
        </div>

        <!-- Customers Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-500">
                        <th class="p-5 font-bold w-12 text-center">
                            <input type="checkbox" id="select-all" onchange="toggleAll(this)" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                        </th>
                        <th class="p-5 font-bold w-12 text-center">SL</th>
                        <th class="p-5 sm:px-8 font-bold">Customer</th>
                        <th class="p-5 font-bold">Join Date</th>
                        <th class="p-5 font-bold text-center">Total Orders</th>
                        <th class="p-5 font-bold text-right">Total Spent</th>
                        <th class="p-5 sm:px-8 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-700" id="customers-table-body">
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7" class="p-12 text-center">
                                <i data-lucide="user-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                                <p class="text-slate-500 font-medium">No customers found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <!-- Rows will be injected by JS -->
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
const allCustomers = <?php echo json_encode($customers); ?>;
let currentPage = 1;
let selectedIds = [];

function filterCustomers() {
    const term = document.getElementById('customer-search').value.toLowerCase();
    const perPage = parseInt(document.getElementById('per-page').value);
    
    const visibleRows = allCustomers.filter(customer => {
        const searchData = `${customer.name} ${customer.email} ${customer.phone}`.toLowerCase();
        return searchData.includes(term);
    });

    const totalItems = visibleRows.length;
    const totalPages = Math.ceil(totalItems / perPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * perPage;
    const end = start + perPage;
    const paginatedItems = visibleRows.slice(start, end);

    const tableBody = document.getElementById('customers-table-body');
    if (paginatedItems.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" class="p-12 text-center"><i data-lucide="user-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i><p class="text-slate-500 font-medium">No customers found.</p></td></tr>`;
    } else {
        tableBody.innerHTML = paginatedItems.map((customer, index) => `
            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors customer-row ${customer.status === 'blocked' ? 'bg-rose-50/30' : ''}">
                <td class="p-5 text-center">
                    <input type="checkbox" class="customer-checkbox w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer" value="${customer.id}" onchange="updateBulkActions()">
                </td>
                <td class="p-5 text-center font-bold text-slate-400">${start + index + 1}</td>
                <td class="p-5 sm:px-8">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br ${customer.status === 'blocked' ? 'from-rose-100 to-red-100 text-rose-700 border-rose-200' : 'from-indigo-100 to-blue-100 text-blue-700 border-blue-200'} rounded-full flex items-center justify-center font-black text-lg shrink-0 shadow-inner">${customer.name.charAt(0).toUpperCase()}</div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-slate-800 truncate max-w-[150px] sm:max-w-xs" title="${customer.name}">${customer.name}</p>
                                ${customer.status === 'blocked' ? '<span class="bg-rose-100 text-rose-600 px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider">Blocked</span>' : ''}
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">${customer.email || customer.phone}</p>
                        </div>
                    </div>
                </td>
                <td class="p-5 text-xs font-semibold text-slate-500">${new Date(customer.createdAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                <td class="p-5 font-black text-slate-900 text-base text-center">${customer.total_orders}</td>
                <td class="p-5 font-black text-emerald-600 text-base text-right">৳${Number(customer.total_spent).toLocaleString()}</td>
                <td class="p-5 sm:px-8 text-right">
                    <a href="customer-view?id=${customer.id}" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-600 hover:text-white px-4 py-2.5 rounded-xl transition-all shadow-sm border border-indigo-100 hover:border-indigo-600" title="View Details">
                        <i data-lucide="eye" class="w-4 h-4"></i> View
                    </a>
                </td>
            </tr>
        `).join('');
    }
    lucide.createIcons();
    renderPagination(totalItems, start, Math.min(end, totalItems), totalPages);
    
    // Reset bulk selection on filter/pagination change
    document.getElementById('select-all').checked = false;
    updateBulkActions();
}

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.customer-checkbox:checked');
    selectedIds = Array.from(checkboxes).map(cb => cb.value);
    
    const bulkActions = document.getElementById('bulk-actions');
    const selectedCount = document.getElementById('selected-count');
    
    if (selectedIds.length > 0) {
        bulkActions.classList.remove('hidden');
        bulkActions.classList.add('flex');
        selectedCount.innerText = selectedIds.length;
    } else {
        bulkActions.classList.add('hidden');
        bulkActions.classList.remove('flex');
        document.getElementById('select-all').checked = false;
    }
}

async function bulkUpdateStatus(status) {
    if (selectedIds.length === 0) return;
    
    const actionText = status === 'blocked' ? 'block' : 'unblock';
    customConfirm(`Are you sure you want to ${actionText} ${selectedIds.length} selected customers?`, async () => {
        try {
            const formData = new FormData();
            formData.append('action', 'bulk_status');
            formData.append('status', status);
            formData.append('customer_ids', JSON.stringify(selectedIds));

            const res = await fetch('customers.php', { method: 'POST', body: formData });
            const result = await res.json();
            
            if (result.success) {
                showToast(`Customers ${status} successfully.`, "success");
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.error || "Failed to update status.", "error");
            }
        } catch(e) { 
            console.error(e);
            showToast("Error: " + (e.message || "Network error"), "error"); 
        }
    });
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

function changePage(page) { currentPage = page; filterCustomers(); }
document.addEventListener('DOMContentLoaded', filterCustomers);
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>