<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Auto-create SettingApi table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `SettingApi` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `steadfastEnabled` BOOLEAN DEFAULT FALSE,
        `steadfastApiKey` VARCHAR(255) DEFAULT NULL,
        `steadfastSecretKey` VARCHAR(255) DEFAULT NULL,
        `fraudCheckerEnabled` BOOLEAN DEFAULT FALSE,
        `fraudCheckerApiKey` VARCHAR(255) DEFAULT NULL
    )");
    
    // Safe patch to add new column if table already exists
    try { $pdo->exec("ALTER TABLE `SettingApi` ADD COLUMN `fraudCheckerMinRate` INT DEFAULT 50"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `SettingApi` ADD COLUMN `fraudCheckerAutoBlock` BOOLEAN DEFAULT FALSE"); } catch (Exception $e) {}

    // Insert default row if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM SettingApi");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO SettingApi (id) VALUES (1)");
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_api_settings') {
    header('Content-Type: application/json');
    try {
        $steadfastEnabled = isset($_POST['steadfastEnabled']) && $_POST['steadfastEnabled'] == '1' ? 1 : 0;
        $steadfastApiKey = $_POST['steadfastApiKey'] ?? '';
        $steadfastSecretKey = $_POST['steadfastSecretKey'] ?? '';
        
        $fraudCheckerEnabled = isset($_POST['fraudCheckerEnabled']) && $_POST['fraudCheckerEnabled'] == '1' ? 1 : 0;
        $fraudCheckerApiKey = $_POST['fraudCheckerApiKey'] ?? '';
        $fraudCheckerMinRate = isset($_POST['fraudCheckerMinRate']) ? (int)$_POST['fraudCheckerMinRate'] : 50;
        $fraudCheckerAutoBlock = isset($_POST['fraudCheckerAutoBlock']) && $_POST['fraudCheckerAutoBlock'] == '1' ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE SettingApi SET 
            steadfastEnabled = ?, steadfastApiKey = ?, steadfastSecretKey = ?,
            fraudCheckerEnabled = ?, fraudCheckerApiKey = ?, fraudCheckerMinRate = ?, fraudCheckerAutoBlock = ?
            WHERE id = 1");
            
        $stmt->execute([
            $steadfastEnabled, $steadfastApiKey, $steadfastSecretKey,
            $fraudCheckerEnabled, $fraudCheckerApiKey, $fraudCheckerMinRate, $fraudCheckerAutoBlock
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$stmt = $pdo->query("SELECT * FROM SettingApi LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

ob_start();
?>

<div class="max-w-6xl mx-auto pb-12 font-sans" id="api-settings-app">
    <form id="api-settings-form" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <input type="hidden" name="action" value="save_api_settings">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="webhook" class="w-6 h-6 text-blue-600"></i> API Configurations
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage third-party integrations and API keys.</p>
            </div>
            <button type="button" onclick="saveApiSettings()" id="save-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span id="btn-text">Save Changes</span>
            </button>
        </div>

        <!-- Global Toggles -->
        <div class="px-6 sm:px-8 py-5 border-y border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center justify-start gap-12">
            <div class="flex items-center gap-4">
                <div>
                    <h3 class="font-bold text-slate-800">SteadFast API</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Enable SteadFast courier integration.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="steadfastEnabled" value="1" class="sr-only peer" <?php echo !empty($settings['steadfastEnabled']) ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <div class="flex items-center gap-4">
                <div>
                    <h3 class="font-bold text-slate-800">Fraud Checker</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Enable customer fraud checking API.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="fraudCheckerEnabled" value="1" class="sr-only peer" <?php echo !empty($settings['fraudCheckerEnabled']) ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex border-b border-slate-200 px-6 sm:px-8 pt-2 gap-6 bg-slate-50/50">
            <button type="button" onclick="switchTab('steadfast')" id="tab-btn-steadfast" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-blue-600">
                <i data-lucide="truck" class="w-4 h-4"></i> SteadFast Courier
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-t-full"></div>
            </button>
            <button type="button" onclick="switchTab('fraud')" id="tab-btn-fraud" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500">
                <i data-lucide="shield-alert" class="w-4 h-4"></i> Fraud Checker
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-t-full hidden"></div>
            </button>
        </div>

        <!-- SteadFast Tab Content -->
        <div id="tab-content-steadfast" class="tab-content p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-12 gap-10">
            <div class="xl:col-span-8 space-y-6">
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">API Key</label>
                    <input type="text" name="steadfastApiKey" value="<?php echo htmlspecialchars($settings['steadfastApiKey'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="Enter SteadFast API Key">
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Secret Key</label>
                    <input type="password" name="steadfastSecretKey" value="<?php echo htmlspecialchars($settings['steadfastSecretKey'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="Enter SteadFast Secret Key">
                </div>
            </div>
            <div class="xl:col-span-4 space-y-6">
                <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
                    <h3 class="font-bold text-blue-900 mb-2">SteadFast Courier API</h3>
                    <p class="text-xs text-blue-700">Integrate SteadFast to easily place delivery requests directly from your order management page.</p>
                </div>
            </div>
        </div>

        <!-- Fraud Checker Tab Content (Hidden by default) -->
        <div id="tab-content-fraud" class="tab-content p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-12 gap-10 hidden">
            <div class="xl:col-span-8 space-y-6">
                <div class="pb-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Auto-Block COD for Bad History</h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Automatically disable Cash on Delivery for users with a low delivery success rate.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="fraudCheckerAutoBlock" value="1" class="sr-only peer" <?php echo !empty($settings['fraudCheckerAutoBlock']) ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Minimum Delivery Success Rate (%)</label>
                    <input type="number" min="0" max="100" name="fraudCheckerMinRate" value="<?php echo htmlspecialchars($settings['fraudCheckerMinRate'] ?? '50'); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="e.g. 50">
                    <p class="text-[10px] text-slate-400 mt-1">Customers with a delivery success rate below this percentage will be blocked from placing Cash on Delivery orders.</p>
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Fraud Checker API Key (Optional)</label>
                    <input type="text" name="fraudCheckerApiKey" value="<?php echo htmlspecialchars($settings['fraudCheckerApiKey'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="Enter API Key">
                    <p class="text-[10px] text-slate-400 mt-1">Integrate with 3rd party fraud checker APIs for nationwide data (if applicable).</p>
                </div>
            </div>
            <div class="xl:col-span-4 space-y-6">
                <div class="bg-amber-50 rounded-2xl p-6 border border-amber-100">
                    <h3 class="font-bold text-amber-900 mb-2">Fraud Checker Engine</h3>
                    <p class="text-xs text-amber-700">The system automatically tracks a customer's success rate based on their previous orders (Delivered vs Cancelled) and blocks COD if the rate is too low.</p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => { btn.classList.remove('text-blue-600'); btn.classList.add('text-slate-500'); btn.querySelector('.tab-indicator').classList.add('hidden'); });
    document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
    document.getElementById('tab-btn-' + tab).classList.add('text-blue-600');
    document.getElementById('tab-btn-' + tab).querySelector('.tab-indicator').classList.remove('hidden');
    document.getElementById('tab-content-' + tab).classList.remove('hidden');
    document.getElementById('tab-content-' + tab).classList.add('animate-in', 'fade-in');
    
    // URL-এ হ্যাশ (#) সেভ করা যাতে রিফ্রেশ করলে এই ট্যাবটিই থাকে
    window.history.replaceState(null, null, window.location.pathname + window.location.search + '#' + tab);
}

document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.substring(1);
    if (['steadfast', 'fraud'].includes(hash)) {
        switchTab(hash);
    } else {
        switchTab('steadfast');
    }
});

async function saveApiSettings() {
    const btn = document.getElementById('save-btn'); const btnText = document.getElementById('btn-text');
    const formData = new FormData(document.getElementById('api-settings-form'));
    btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-4 h-4"></i> Saving...'; lucide.createIcons();
    try {
        const response = await fetch('api-settings.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) { showToast("API configurations saved successfully!", "success"); } else { showToast(result.error || "Failed to save settings.", "error"); }
    } catch (err) { showToast("An error occurred while saving.", "error"); } finally {
        btn.disabled = false; btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Changes'; lucide.createIcons();
    }
}
lucide.createIcons();
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>