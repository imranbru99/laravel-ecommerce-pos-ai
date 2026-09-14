<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query("SELECT * FROM SettingPayment LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
ob_start();
?>

<div class="max-w-6xl mx-auto pb-12 font-sans" id="payment-app">
    <form id="payment-form" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="credit-card" class="w-6 h-6 text-blue-600"></i> Payment Gateway
                </h2>
                <p class="text-sm text-slate-500 mt-2">Manage credentials. Fields can be left blank if needed.</p>
            </div>
            
            <button 
                type="button" 
                onclick="savePaymentSettings()"
                id="save-btn"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
            >
                <i data-lucide="save" class="w-5 h-5"></i> 
                <span id="btn-text">Save Settings</span>
            </button>
        </div>

        <div class="p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-12 gap-10">
            <div class="xl:col-span-8 space-y-8">
                
                <!-- Cash on Delivery Toggle -->
                <div id="cod-container" class="p-6 rounded-3xl border-2 transition-all duration-500 <?php echo $settings['codEnabled'] ? 'border-emerald-500 bg-emerald-50/30' : 'border-slate-100 bg-slate-50/30 opacity-70'; ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div id="cod-icon" class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white transition-colors <?php echo $settings['codEnabled'] ? 'bg-emerald-500' : 'bg-slate-400'; ?>">
                                <i data-lucide="truck" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">Cash on Delivery (COD)</h3>
                                <p class="text-xs text-slate-500">Gateway Status</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="codEnabled" value="1" onchange="toggleUIVisuals(this, 'cod')" <?php echo $settings['codEnabled'] ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-14 h-7 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                </div>

                <!-- bKash Checkout Toggle -->
                <div id="bkash-container" class="p-6 rounded-3xl border-2 transition-all duration-500 <?php echo $settings['bkashEnabled'] ? 'border-pink-500 bg-pink-50/30' : 'border-slate-100 bg-slate-50/30 opacity-70'; ?>">
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div id="bkash-icon" class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white transition-colors <?php echo $settings['bkashEnabled'] ? 'bg-pink-600' : 'bg-slate-400'; ?>">৳</div>
                            <div>
                                <h3 class="font-bold text-slate-800">bKash Checkout</h3>
                                <p class="text-xs text-slate-500">Gateway Status</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="bkashEnabled" value="1" onchange="toggleUIVisuals(this, 'bkash')" <?php echo $settings['bkashEnabled'] ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-14 h-7 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></div>
                        </label>
                    </div>

                    <!-- bKash Inputs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">App Key</label>
                            <input type="text" name="bkashAppKey" value="<?php echo $settings['bkashAppKey']; ?>" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all font-medium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">App Secret</label>
                            <input type="password" name="bkashAppSecret" value="<?php echo $settings['bkashAppSecret']; ?>" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all font-medium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">Username</label>
                            <input type="text" name="bkashUsername" value="<?php echo $settings['bkashUsername']; ?>" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all font-medium">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">Password</label>
                            <input type="password" name="bkashPassword" value="<?php echo $settings['bkashPassword']; ?>" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all font-medium">
                        </div>
                        <div class="md:col-span-2 space-y-2 pt-2">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">Base URL</label>
                            <input type="text" name="bkashBaseUrl" value="<?php echo $settings['bkashBaseUrl'] ?: 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'; ?>" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all font-mono text-sm text-blue-600">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="xl:col-span-4 space-y-6">
                <div class="bg-slate-900 rounded-[2rem] p-8 text-white">
                    <h4 class="text-lg font-bold flex items-center gap-2 mb-4">
                        <i data-lucide="shield-check" class="w-4.5 h-4.5 text-green-400"></i> Information
                    </h4>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        You can save partial configuration and update the rest later. Empty fields will be saved as null or empty strings.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// UI ডাইনামিক ইফেক্ট হ্যান্ডলার (React style visuals in Vanilla JS)
function toggleUIVisuals(checkbox, type) {
    const container = document.getElementById(type + '-container');
    const icon = document.getElementById(type + '-icon');
    
    if (checkbox.checked) {
        container.classList.remove('border-slate-100', 'bg-slate-50/30', 'opacity-70');
        container.classList.add(type === 'cod' ? 'border-emerald-500' : 'border-pink-500', type === 'cod' ? 'bg-emerald-50/30' : 'bg-pink-50/30');
        icon.classList.remove('bg-slate-400');
        icon.classList.add(type === 'cod' ? 'bg-emerald-500' : 'bg-pink-600');
    } else {
        container.classList.add('border-slate-100', 'bg-slate-50/30', 'opacity-70');
        container.classList.remove('border-emerald-500', 'border-pink-500', 'bg-emerald-50/30', 'bg-pink-50/30');
        icon.classList.add('bg-slate-400');
        icon.classList.remove('bg-emerald-500', 'bg-pink-600');
    }
}

async function savePaymentSettings() {
    const btn = document.getElementById('save-btn');
    const btnText = document.getElementById('btn-text');
    const form = document.getElementById('payment-form');
    const formData = new FormData(form);
    
    formData.append('codEnabled', form.codEnabled.checked ? 1 : 0);
    formData.append('bkashEnabled', form.bkashEnabled.checked ? 1 : 0);

    // Loading State
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Saving...';
    lucide.createIcons();

    try {
        const response = await fetch('../api/save_settings.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if(result.success) {
            showToast("Settings updated successfully!", "success");
        } else {
            showToast(result.error || "Failed to save settings", "error");
        }
    } catch (err) {
        showToast("Error saving settings.", "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> Save Settings';
        lucide.createIcons();
    }
}

// Initial icons render
lucide.createIcons();
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>