<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query("SELECT * FROM SettingDelivery LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$insideDhaka = $settings['deliveryInsideDhaka'] ?? 60;
$outsideDhaka = $settings['deliveryOutsideDhaka'] ?? 120;
ob_start();
?>

<div class="max-w-6xl mx-auto pb-12 px-4 sm:px-0 font-sans" id="shipping-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="truck" class="w-6 h-6 text-blue-600"></i> Shipping Rates
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage flat delivery fees for different regional zones.</p>
            </div>
            
            <button 
                type="button" 
                onclick="saveShippingSettings()"
                id="save-btn"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
            >
                <i data-lucide="save" class="w-5 h-5"></i> 
                <span id="btn-text">Update Rates</span>
            </button>
        </div>

        <div class="p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-12 gap-10">
            
            <!-- Main Form Content -->
            <div class="xl:col-span-8 space-y-8">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-1.5 h-6 bg-blue-600 rounded-full"></div>
                    <h3 class="text-lg font-bold text-slate-800">Standard Delivery Configuration</h3>
                </div>

                <form id="shipping-form" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Inside Dhaka -->
                    <div class="space-y-3">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-blue-600"></i> Inside Dhaka
                        </label>
                        <div class="relative">
                            <div class="absolute left-5 top-1/2 -translate-y-1/2 font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-lg text-xs">BDT</div>
                            <input type="number" name="deliveryInsideDhaka" value="<?php echo $insideDhaka; ?>" class="w-full pl-16 pr-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-600 outline-none transition-all">
                        </div>
                    </div>

                    <!-- Outside Dhaka -->
                    <div class="space-y-3">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                            <i data-lucide="globe" class="w-3.5 h-3.5 text-orange-500"></i> Outside Dhaka
                        </label>
                        <div class="relative">
                            <div class="absolute left-5 top-1/2 -translate-y-1/2 font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-lg text-xs">BDT</div>
                            <input type="number" name="deliveryOutsideDhaka" value="<?php echo $outsideDhaka; ?>" class="w-full pl-16 pr-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 focus:bg-white focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all">
                        </div>
                    </div>
                </form>

                <!-- Info Note -->
                <div class="bg-blue-50/50 rounded-3xl p-6 border border-blue-100/50 flex gap-4">
                    <i data-lucide="calculator" class="text-blue-500 shrink-0 w-6 h-6"></i>
                    <div>
                        <h4 class="text-sm font-bold text-blue-900">Auto-Calculation</h4>
                        <p class="text-xs text-blue-700 mt-1">These rates are automatically added to the cart subtotal during checkout based on the delivery zone.</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="xl:col-span-4 space-y-6">
                <div class="bg-slate-900 rounded-[2rem] p-8 text-white relative overflow-hidden group">
                    <h4 class="text-lg font-bold flex items-center gap-2 mb-4">
                        <i data-lucide="settings-2" class="w-4.5 h-4.5 text-blue-400"></i> System Info
                    </h4>
                    <div class="space-y-4 relative z-10">
                        <div class="flex items-center justify-between py-2 border-b border-slate-800">
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Currency</span>
                            <span class="text-xs font-bold">BDT (৳)</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-800">
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Status</span>
                            <span class="text-xs font-bold text-green-400">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function saveShippingSettings() {
    const btn = document.getElementById('save-btn');
    const btnText = document.getElementById('btn-text');
    const form = document.getElementById('shipping-form');
    const formData = new FormData(form);

    // Loading State
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Updating...';
    lucide.createIcons();

    try {
        const response = await fetch('../api/save_settings.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if(result.success) {
            showToast("Logistics updated successfully", "success");
        } else {
            showToast("Failed to sync logistics data", "error");
        }
    } catch (err) {
        showToast("Network synchronization failed", "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> Update Rates';
        lucide.createIcons();
    }
}

// ইনিশিয়াল আইকন রেন্ডার
lucide.createIcons();
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>