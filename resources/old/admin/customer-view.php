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

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header("Location: customers");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'edit_customer') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        $pdo->prepare("UPDATE User SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'customer'")
            ->execute([$name, $email, $phone, $userId]);
            
        header("Location: customer-view?id=$userId&success=customer_updated");
        exit;
    }
    
    if ($action === 'change_password') {
        $password = $_POST['password'] ?? '';
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE User SET password = ? WHERE id = ? AND role = 'customer'")
                ->execute([$hashed, $userId]);
            header("Location: customer-view?id=$userId&success=password_changed");
            exit;
        }
    }
    
    if ($action === 'toggle_status') {
        $newStatus = $_POST['status'] ?? 'active';
        $pdo->prepare("UPDATE User SET status = ? WHERE id = ? AND role = 'customer'")
            ->execute([$newStatus, $userId]);
        header("Location: customer-view?id=$userId&success=status_updated");
        exit;
    }
}

// Fetch Customer
$stmt = $pdo->prepare("SELECT * FROM User WHERE id = ? AND role = 'customer'");
$stmt->execute([$userId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customers");
    exit;
}

// Fetch Stats
$statStmt = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(total) as total_spent FROM `Order` WHERE userId = ?");
$statStmt->execute([$userId]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC);

// Fetch Orders
$orderStmt = $pdo->prepare("SELECT * FROM `Order` WHERE userId = ? ORDER BY createdAt DESC");
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="max-w-7xl mx-auto font-sans pb-12 animate-in fade-in slide-in-from-bottom-4 duration-500">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <a href="customers" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:shadow-md transition-all">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Customer Profile</h1>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column: Profile & Actions -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- Profile Summary -->
            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden relative text-center">
                <div class="h-24 bg-gradient-to-r from-indigo-500 to-blue-600"></div>
                <?php if (($customer['status'] ?? 'active') === 'blocked'): ?>
                    <div class="absolute top-4 right-4 bg-rose-500 text-white px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1 shadow-sm border border-white/20">
                        <i data-lucide="ban" class="w-3 h-3"></i> Blocked
                    </div>
                <?php endif; ?>
                <div class="relative px-6 pb-6">
                    <div class="w-20 h-20 bg-white text-indigo-600 rounded-full flex items-center justify-center text-3xl font-black mx-auto -mt-10 shadow-lg border-4 border-white mb-3">
                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                    </div>
                    <h2 class="text-xl font-black text-slate-900 truncate mb-1"><?php echo htmlspecialchars($customer['name']); ?></h2>
                    <p class="text-xs font-bold text-slate-500 tracking-wide">Joined <?php echo date('M d, Y', strtotime($customer['createdAt'])); ?></p>
                    
                    <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-5 mt-5">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Orders</p>
                            <p class="text-lg font-black text-slate-800"><?php echo $stats['total_orders'] ?: 0; ?></p>
                    </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Spent</p>
                            <p class="text-lg font-black text-indigo-600">৳<?php echo number_format($stats['total_spent'] ?: 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Info Card -->
            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                        <i data-lucide="edit-2" class="w-4 h-4 text-indigo-600"></i> Edit Details
                    </h3>
                <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="edit_customer">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <button type="submit" class="w-full py-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold transition-all shadow-md mt-2 flex items-center justify-center gap-2">
                        Save Changes
                    </button>
                    </form>
                </div>

            <!-- Security Card -->
            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                        <i data-lucide="key" class="w-4 h-4 text-indigo-600"></i> Change Password
                    </h3>
                <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">New Password</label>
                        <input type="password" name="password" required minlength="6" placeholder="Enter new password" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <button type="submit" class="w-full py-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold transition-all shadow-md mt-2">
                        Update Password
                    </button>
                    </form>
                </div>

            <!-- Danger Zone Card -->
            <div class="bg-rose-50 rounded-[2rem] border border-rose-100 shadow-sm p-6">
                <h3 class="text-sm font-black text-rose-700 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i> Danger Zone
                </h3>
                <form id="status-form" method="POST">
                        <input type="hidden" name="action" value="toggle_status">
                        <?php if (($customer['status'] ?? 'active') === 'active'): ?>
                            <input type="hidden" name="status" value="blocked">
                        <p class="text-sm font-medium text-rose-600 mb-4 leading-relaxed">Block this customer from logging in or placing new orders.</p>
                        <button type="button" onclick="confirmStatusChange()" class="w-full py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold transition-all shadow-md flex items-center justify-center gap-2">
                                <i data-lucide="ban" class="w-4 h-4"></i> Block Customer
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="status" value="active">
                        <p class="text-sm font-medium text-rose-600 mb-4 leading-relaxed">Unblock this customer to restore their access.</p>
                        <button type="button" onclick="confirmStatusChange()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold transition-all shadow-md flex items-center justify-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i> Unblock Customer
                            </button>
                        <?php endif; ?>
                    </form>
            </div>
        </div>

        <!-- Right Column: Order History -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-black text-slate-800 flex items-center gap-2 text-lg">
                        <i data-lucide="shopping-bag" class="w-5 h-5 text-indigo-600"></i> Order History
                    </h3>
                </div>
                
                <?php if(empty($orders)): ?>
                    <div class="p-12 text-center text-slate-500">
                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-slate-300"></i>
                        <p class="font-medium text-sm">No orders found for this customer.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Order ID</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Date</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Total</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Status</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($orders as $o): 
                                    $statusColors = [
                                        'PENDING' => 'bg-amber-50 text-amber-600 border-amber-200',
                                        'PROCESSING' => 'bg-blue-50 text-blue-600 border-blue-200',
                                        'SHIPPED' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                                        'DELIVERED' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                        'CANCELLED' => 'bg-rose-50 text-rose-600 border-rose-200'
                                    ];
                                    $sColor = $statusColors[$o['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200';
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-3">
                                        <span class="font-black text-slate-800 text-sm">#<?php echo str_pad($o['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td class="px-5 py-3 text-xs font-semibold text-slate-500">
                                        <?php echo date('M d, Y', strtotime($o['createdAt'])); ?>
                                    </td>
                                    <td class="px-5 py-3 font-black text-indigo-600 text-sm">
                                        ৳<?php echo number_format($o['total']); ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="px-2 py-1 text-[9px] font-bold uppercase tracking-widest border rounded <?php echo $sColor; ?>">
                                            <?php echo $o['status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="order-details?id=<?php echo $o['id']; ?>" class="inline-flex items-center justify-center w-7 h-7 rounded bg-slate-100 text-slate-500 hover:bg-indigo-600 hover:text-white transition-all">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('success')) {
            let message = 'Operation successful!';
            const successType = params.get('success');
            if (successType === 'customer_updated') message = 'Customer details updated successfully!';
            else if (successType === 'password_changed') message = 'Password changed successfully!';
            else if (successType === 'status_updated') message = 'Customer status updated successfully!';
            
            showToast(message, 'success');
        }
        // Clean URL
        if(params.has('success')) {
            const newUrl = window.location.pathname + '?id=<?php echo $userId; ?>';
            window.history.replaceState({}, document.title, newUrl);
        }
    });

    function confirmStatusChange() {
        if (typeof customConfirm === 'function') {
            customConfirm('Are you sure you want to change this customer\'s status?', () => {
                document.getElementById('status-form').submit();
            });
        } else {
            if (confirm('Are you sure you want to change this customer\'s status?')) {
                document.getElementById('status-form').submit();
            }
        }
    }
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>