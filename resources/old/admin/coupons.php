<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Auto-create Coupon table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `Coupon` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `couponType` VARCHAR(50) DEFAULT 'GENERAL',
        `applicableData` TEXT NULL,
        `discountType` ENUM('PERCENTAGE', 'FIXED') DEFAULT 'FIXED',
        `discountAmount` DECIMAL(10,2) NOT NULL,
        `minSpend` DECIMAL(10,2) DEFAULT 0,
        `expiryDate` DATETIME NULL,
        `usageLimit` INT DEFAULT NULL,
        `usagePerUser` INT DEFAULT 1,
        `usedCount` INT DEFAULT 0,
        `status` BOOLEAN DEFAULT TRUE,
        `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE `Coupon` ADD COLUMN `couponType` VARCHAR(50) DEFAULT 'GENERAL' AFTER `code`"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `Coupon` ADD COLUMN `applicableData` TEXT NULL AFTER `couponType`"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `Coupon` ADD COLUMN `usagePerUser` INT DEFAULT 1 AFTER `usageLimit`"); } catch (Exception $e) {}
} catch (Exception $e) {}

// Handle Search Users AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_users') {
    header('Content-Type: application/json');
    $term = trim($_POST['term'] ?? '');
    if (strlen($term) < 2) {
        echo json_encode([]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT name, phone, email FROM User WHERE role = 'customer' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?) LIMIT 10");
    $searchTerm = "%$term%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Handle POST request for Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json');
    $id = $_POST['id'] ?? '';
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $couponType = $_POST['couponType'] ?? 'GENERAL';
    $applicableData = $_POST['applicableData'] ?? null;
    $type = $_POST['discountType'] ?? 'FIXED';
    $amount = !empty($_POST['discountAmount']) ? floatval($_POST['discountAmount']) : 0;
    $minSpend = !empty($_POST['minSpend']) ? floatval($_POST['minSpend']) : 0;
    $expiryRaw = $_POST['expiryDate'] ?? '';
    $expiry = !empty($expiryRaw) ? date('Y-m-d H:i:s', strtotime($expiryRaw)) : null;
    $limit = !empty($_POST['usageLimit']) ? $_POST['usageLimit'] : null;
    $usagePerUserRaw = $_POST['usagePerUser'] ?? '';
    $usagePerUser = ($usagePerUserRaw !== '') ? (int)$usagePerUserRaw : 1;
    
    if (empty($code) || $amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Code and Discount amount are required.']);
        exit;
    }
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE Coupon SET code=?, couponType=?, applicableData=?, discountType=?, discountAmount=?, minSpend=?, expiryDate=?, usageLimit=?, usagePerUser=? WHERE id=?");
            $stmt->execute([$code, $couponType, $applicableData, $type, $amount, $minSpend, $expiry, $limit, $usagePerUser, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO Coupon (code, couponType, applicableData, discountType, discountAmount, minSpend, expiryDate, usageLimit, usagePerUser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $couponType, $applicableData, $type, $amount, $minSpend, $expiry, $limit, $usagePerUser]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle GET requests for toggle/delete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $id = $_GET['id'] ?? '';
    if ($id) {
        try {
            if ($_GET['action'] === 'toggle') {
                $pdo->prepare("UPDATE Coupon SET status = NOT status WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
            } elseif ($_GET['action'] === 'delete') {
                $pdo->prepare("DELETE FROM Coupon WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    exit;
}

// Fetch Coupons
$stmt = $pdo->query("SELECT * FROM Coupon ORDER BY id DESC");
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="coupons-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="ticket" class="w-6 h-6 text-blue-600"></i> Coupons & Discounts
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage promotional codes and discounts for customers.</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative group w-full md:w-64">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                    <input type="text" id="coupon-search" placeholder="Search coupons..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-blue-500 outline-none w-full transition-all shadow-sm" />
                </div>
                <button onclick="openCouponModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg transition-all flex items-center gap-2 text-sm whitespace-nowrap">
                    <i data-lucide="plus" class="w-4 h-4"></i> 
                    <span>Add Coupon</span>
                </button>
            </div>
        </div>

        <!-- Coupons Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-500">
                        <th class="p-5 sm:px-8 font-bold">Code</th>
                        <th class="p-5 font-bold">Type</th>
                        <th class="p-5 font-bold">Discount</th>
                        <th class="p-5 font-bold">Usage</th>
                        <th class="p-5 font-bold">Min Spend</th>
                        <th class="p-5 font-bold">Expiry Date</th>
                        <th class="p-5 font-bold">Status</th>
                        <th class="p-5 sm:px-8 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-700" id="coupons-table-body">
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="7" class="p-12 text-center">
                                <i data-lucide="ticket" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                                <p class="text-slate-500 font-medium">No coupons found.</p>
                                <button onclick="openCouponModal()" class="text-blue-600 hover:underline font-bold mt-2 inline-block">Create your first coupon</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $coupon): ?>
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors coupon-row" data-search="<?php echo strtolower($coupon['code']); ?>">
                                <td class="p-5 sm:px-8">
                                    <span class="px-3 py-1 bg-slate-100 border border-slate-200 text-slate-700 rounded-lg font-mono font-bold tracking-wider uppercase text-sm">
                                        <?php echo htmlspecialchars($coupon['code']); ?>
                                    </span>
                                </td>
                                <td class="p-5">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-bold text-slate-700"><?php echo str_replace('_', ' ', $coupon['couponType']); ?></span>
                                        <?php if($coupon['couponType'] === 'USER_SPECIFIC' && !empty($coupon['applicableData'])): ?>
                                            <span class="text-[10px] text-slate-400 font-medium truncate max-w-[100px]" title="<?php echo htmlspecialchars($coupon['applicableData']); ?>">Targeted</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-5 font-bold text-slate-800">
                                    <?php echo $coupon['discountType'] === 'PERCENTAGE' ? rtrim(rtrim($coupon['discountAmount'], '0'), '.') . '%' : '৳' . rtrim(rtrim($coupon['discountAmount'], '0'), '.'); ?>
                                </td>
                                <td class="p-5">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold text-slate-600">Used: <?php echo $coupon['usedCount']; ?></span>
                                        <span class="text-[10px] text-slate-400 font-medium">Limit: <?php echo $coupon['usageLimit'] ?: '∞'; ?></span>
                                    </div>
                                </td>
                                <td class="p-5 font-medium text-slate-600">
                                    <?php echo $coupon['minSpend'] > 0 ? '৳' . rtrim(rtrim($coupon['minSpend'], '0'), '.') : 'None'; ?>
                                </td>
                                <td class="p-5">
                                    <?php if ($coupon['expiryDate']): ?>
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-medium text-slate-700 text-xs"><?php echo date('M d, Y', strtotime($coupon['expiryDate'])); ?></span>
                                            <span class="text-[10px] text-slate-400"><?php echo date('h:i A', strtotime($coupon['expiryDate'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">Never expires</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-5">
                                    <button onclick="toggleStatus(<?php echo $coupon['id']; ?>)" class="px-3 py-1 rounded border <?php echo $coupon['status'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'; ?> text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 w-fit transition-colors">
                                        <div class="w-1.5 h-1.5 rounded-full <?php echo $coupon['status'] ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'; ?>"></div>
                                        <?php echo $coupon['status'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </td>
                                <td class="p-5 sm:px-8 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick='editCoupon(<?php echo json_encode($coupon); ?>)' class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                            <i data-lucide="edit-3" class="w-5 h-5"></i>
                                        </button>
                                        <button onclick="deleteCoupon(<?php echo $coupon['id']; ?>)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                                            <i data-lucide="trash-2" class="w-5 h-5"></i>
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

<!-- Modal -->
<div id="coupon-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="text-lg font-bold text-slate-800" id="modal-title">New Coupon</h2>
            <button onclick="closeCouponModal()" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form id="coupon-form" class="p-6 space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="coupon-id">

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Coupon Code</label>
                <div class="relative">
                    <input required type="text" name="code" id="coupon-code" class="w-full px-4 py-3 pr-24 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-bold uppercase focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
                    <button type="button" onclick="generateCode()" class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-1.5 rounded-lg hover:bg-blue-200 transition-colors uppercase tracking-widest">Generate</button>
                </div>
            </div>
            
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Coupon Type</label>
                <select name="couponType" id="coupon-type-select" onchange="toggleApplicableData()" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all cursor-pointer">
                    <option value="GENERAL">General (Everyone)</option>
                    <option value="WELCOME">Welcome (First Order Only)</option>
                    <option value="USER_SPECIFIC">Specific Users</option>
                </select>
            </div>
            
            <div id="applicable-data-container" class="space-y-1.5 hidden">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Applicable Users (Emails or Phones)</label>
                <div class="relative">
                    <div class="flex flex-wrap gap-2 mb-2" id="selected-users-container"></div>
                    <input type="hidden" name="applicableData" id="coupon-applicable-data" value="">
                    
                    <input type="text" id="user-search-input" placeholder="Search by name, phone or email..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" autocomplete="off">
                    <div id="user-search-results" class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-48 overflow-y-auto hidden"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Discount Type</label>
                    <select name="discountType" id="coupon-type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all cursor-pointer">
                        <option value="FIXED">Fixed Amount (৳)</option>
                        <option value="PERCENTAGE">Percentage (%)</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Value</label>
                    <input required type="number" step="0.01" name="discountAmount" id="coupon-amount" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Min Spend (৳)</label>
                    <input type="number" step="0.01" name="minSpend" id="coupon-min" placeholder="0 = No limit" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Total Limit</label>
                    <input type="number" name="usageLimit" id="coupon-limit" placeholder="∞" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Per User</label>
                    <input type="number" name="usagePerUser" id="coupon-user-limit" placeholder="0 = ∞" value="1" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Expiry Date & Time (Optional)</label>
                <input type="datetime-local" name="expiryDate" id="coupon-expiry" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeCouponModal()" class="flex-1 py-3 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" id="coupon-submit-btn" class="flex-[2] py-3 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-md hover:bg-blue-700 transition-colors flex justify-center items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> <span id="coupon-btn-text">Save Coupon</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search
document.getElementById('coupon-search').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.coupon-row').forEach(row => {
        const searchData = row.getAttribute('data-search');
        row.style.display = searchData.includes(term) ? '' : 'none';
    });
});

// Applicable Users Tag System
let selectedUsers = [];

function updateSelectedUsersUI() {
    const container = document.getElementById('selected-users-container');
    document.getElementById('coupon-applicable-data').value = selectedUsers.join(',');
    
    container.innerHTML = selectedUsers.map(user => `
        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg border border-blue-100">
            ${user}
            <button type="button" onclick="removeSelectedUser('${user}')" class="text-blue-400 hover:text-blue-700 focus:outline-none">
                <i data-lucide="x" class="w-3 h-3"></i>
            </button>
        </span>
    `).join('');
    lucide.createIcons();
}

function addSelectedUser(userValue) {
    if (!selectedUsers.includes(userValue) && userValue.trim() !== '') {
        selectedUsers.push(userValue.trim());
        updateSelectedUsersUI();
    }
    document.getElementById('user-search-input').value = '';
    document.getElementById('user-search-results').classList.add('hidden');
}

function removeSelectedUser(userValue) {
    selectedUsers = selectedUsers.filter(u => u !== userValue);
    updateSelectedUsersUI();
}

document.getElementById('user-search-input').addEventListener('input', async function(e) {
    const term = e.target.value.trim();
    const resultsContainer = document.getElementById('user-search-results');
    
    if (term.length < 2) {
        resultsContainer.classList.add('hidden');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'search_users');
    formData.append('term', term);
    
    try {
        const response = await fetch('coupons.php', { method: 'POST', body: formData });
        const users = await response.json();
        
        if (users.length > 0) {
            resultsContainer.innerHTML = users.map(user => `
                <div class="px-4 py-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0 transition-colors" onclick="addSelectedUser('${user.phone || user.email}')">
                    <p class="text-sm font-bold text-slate-800">${user.name}</p>
                    <p class="text-[10px] text-slate-500 font-semibold">${user.phone || ''} ${(user.phone && user.email) ? '•' : ''} ${user.email || ''}</p>
                </div>
            `).join('');
            
            resultsContainer.innerHTML += `
                <div class="px-4 py-2.5 bg-blue-50/50 hover:bg-blue-50 cursor-pointer border-t border-blue-100 transition-colors" onclick="addSelectedUser('${term}')">
                    <p class="text-xs font-bold text-blue-700">Add "${term}" manually</p>
                </div>
            `;
            
            resultsContainer.classList.remove('hidden');
        } else {
            resultsContainer.innerHTML = `
                <div class="px-4 py-3 text-sm text-slate-500 text-center">
                    No users found.
                    <button type="button" class="block mt-1 w-full text-center text-xs font-bold text-blue-600 hover:underline" onclick="addSelectedUser('${term}')">Add "${term}" manually</button>
                </div>`;
            resultsContainer.classList.remove('hidden');
        }
    } catch (err) {
        console.error(err);
    }
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('#applicable-data-container')) {
        document.getElementById('user-search-results').classList.add('hidden');
    }
});

function toggleApplicableData() {
    const type = document.getElementById('coupon-type-select').value;
    if (type === 'USER_SPECIFIC') document.getElementById('applicable-data-container').classList.remove('hidden');
    else document.getElementById('applicable-data-container').classList.add('hidden');
}

function openCouponModal() {
    document.getElementById('coupon-form').reset();
    document.getElementById('coupon-id').value = '';
    selectedUsers = [];
    updateSelectedUsersUI();
    document.getElementById('user-search-input').value = '';
    document.getElementById('modal-title').innerText = 'New Coupon';
    document.getElementById('coupon-modal').classList.remove('hidden');
    document.getElementById('coupon-modal').classList.add('flex');
    toggleApplicableData();
}

function closeCouponModal() {
    document.getElementById('coupon-modal').classList.add('hidden');
    document.getElementById('coupon-modal').classList.remove('flex');
}

function editCoupon(coupon) {
    document.getElementById('coupon-id').value = coupon.id;
    document.getElementById('coupon-code').value = coupon.code;
    document.getElementById('coupon-type-select').value = coupon.couponType || 'GENERAL';
    const applicableData = coupon.applicableData || '';
    selectedUsers = applicableData.split(',').map(s => s.trim()).filter(s => s !== '');
    updateSelectedUsersUI();
    document.getElementById('user-search-input').value = '';
    document.getElementById('coupon-type').value = coupon.discountType;
    document.getElementById('coupon-amount').value = coupon.discountAmount;
    document.getElementById('coupon-min').value = coupon.minSpend > 0 ? coupon.minSpend : '';
    document.getElementById('coupon-limit').value = coupon.usageLimit || '';
    document.getElementById('coupon-user-limit').value = coupon.usagePerUser !== null ? coupon.usagePerUser : 1;
    document.getElementById('coupon-expiry').value = coupon.expiryDate ? coupon.expiryDate.replace(' ', 'T').slice(0, 16) : '';
    
    toggleApplicableData();
    document.getElementById('modal-title').innerText = 'Edit Coupon';
    document.getElementById('coupon-modal').classList.remove('hidden');
    document.getElementById('coupon-modal').classList.add('flex');
}

function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = 'PROMO-';
    for (let i = 0; i < 6; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('coupon-code').value = code;
}

function setExpiry(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const offset = date.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(date - offset)).toISOString().slice(0, 16);
    document.getElementById('coupon-expiry').value = localISOTime;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Coupon code copied to clipboard!', 'success');
    }).catch(err => {
        showToast('Failed to copy text', 'error');
    });
}

document.getElementById('coupon-form').onsubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('coupon-submit-btn');
    const btnText = document.getElementById('coupon-btn-text');
    
    btn.disabled = true;
    btnText.innerText = 'Saving...';
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('coupons.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        try {
            const result = JSON.parse(text);
            if (result.success) {
                location.reload();
            } else {
                showToast(result.error || "Failed to save coupon.", "error");
            }
        } catch (e) {
            console.error("Server Error Response: ", text);
            showToast("Server returned an invalid response. Check console.", "error");
        }
    } catch (err) {
        console.error(err);
        showToast("Error: " + (err.message || "Network error"), "error");
    } finally {
        btn.disabled = false;
        btnText.innerText = 'Save Coupon';
    }
};

async function toggleStatus(id) {
    try {
        await fetch(`coupons.php?action=toggle&id=${id}`);
        location.reload();
    } catch(e) { console.error(e); }
}

async function deleteCoupon(id) {
    customConfirm("Are you sure you want to delete this coupon?", async () => {
        try {
            const res = await fetch(`coupons.php?action=delete&id=${id}`);
            const result = await res.json();
            if (result.success) {
                location.reload();
            } else {
                showToast(result.error, "error");
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