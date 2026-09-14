<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query("SELECT * FROM SettingGeneral LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
ob_start();
?>

<div class="max-w-6xl mx-auto pb-12 px-4 sm:px-0 font-sans" id="social-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="share-2" class="w-6 h-6 text-blue-600"></i> Social Channels
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage your brand's global social presence.</p>
            </div>
            
            <button 
                type="button" 
                onclick="saveSocialSettings()"
                id="sync-btn"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
            >
                <i data-lucide="save" class="w-5 h-5"></i> 
                <span id="btn-text">Update Links</span>
            </button>
        </div>

        <form id="social-form">
            <div class="p-6 sm:p-8 grid grid-cols-1 xl:grid-cols-12 gap-10">
                
                <!-- Main Social Form -->
                <div class="xl:col-span-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        
                        <!-- Facebook -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                                <span class="text-blue-600"><i data-lucide="facebook" class="w-4 h-4"></i></span> Facebook Profile
                            </label>
                            <input name="facebookUrl" type="url" value="<?php echo $settings['facebookUrl']; ?>" placeholder="facebook.com/yourbrand" class="w-full px-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-600 outline-none transition-all">
                        </div>

                        <!-- Instagram -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                                <span class="text-pink-500"><i data-lucide="instagram" class="w-4 h-4"></i></span> Instagram Account
                            </label>
                            <input name="instagramUrl" type="url" value="<?php echo $settings['instagramUrl']; ?>" placeholder="instagram.com/yourbrand" class="w-full px-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-4 focus:ring-pink-500/10 focus:border-pink-500 outline-none transition-all">
                        </div>

                        <!-- Twitter/X -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                                <span class="text-slate-900"><i data-lucide="twitter" class="w-4 h-4"></i></span> X / Twitter Handle
                            </label>
                            <input name="twitterUrl" type="url" value="<?php echo $settings['twitterUrl']; ?>" placeholder="twitter.com/yourbrand" class="w-full px-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-4 focus:ring-slate-900/5 focus:border-slate-900 outline-none transition-all">
                        </div>

                        <!-- Youtube -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                                <span class="text-red-600"><i data-lucide="youtube" class="w-4 h-4"></i></span> YouTube Channel
                            </label>
                            <input name="youtubeUrl" type="url" value="<?php echo $settings['youtubeUrl']; ?>" placeholder="youtube.com/c/yourbrand" class="w-full px-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-4 focus:ring-red-500/10 focus:border-red-600 outline-none transition-all">
                        </div>
                    </div>

                    <!-- WhatsApp (Floating Chat) -->
                    <div class="mt-8 space-y-3">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center gap-2">
                            <span class="text-emerald-500"><i data-lucide="message-circle" class="w-4 h-4"></i></span> WhatsApp Number (Floating Chat)
                        </label>
                        <input name="whatsappNumber" type="text" value="<?php echo htmlspecialchars($settings['whatsappNumber'] ?? ''); ?>" placeholder="e.g. 8801700000000" class="w-full px-6 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all">
                    </div>

                    <div class="mt-12 p-8 border-2 border-dashed border-slate-100 rounded-[2rem] bg-slate-50/30 flex flex-col items-center text-center">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                            <i data-lucide="zap" class="w-5 h-5 text-yellow-500"></i>
                        </div>
                        <h4 class="font-bold text-slate-800">Direct Links Enabled</h4>
                        <p class="text-xs text-slate-500 max-w-sm mt-2">Changes here will reflect immediately on your store's footer. Ensure URLs start with https://.</p>
                    </div>
                </div>

                <!-- Side Insight Card -->
                <div class="xl:col-span-4 space-y-6">
                    <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white relative overflow-hidden group">
                        <h4 class="text-lg font-bold flex items-center gap-2 mb-6">
                            <i data-lucide="globe" class="w-4 h-4 text-indigo-400"></i> Web Presence
                        </h4>
                        <div class="space-y-5">
                            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Sync Status</span>
                                <span class="text-xs font-bold text-emerald-400">Real-time</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                                <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Platform Data</span>
                                <span class="text-xs font-bold text-blue-400">Encrypted</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
async function saveSocialSettings() {
    const btn = document.getElementById('sync-btn');
    const btnText = document.getElementById('btn-text');
    const form = document.getElementById('social-form');
    const formData = new FormData(form);

    // UI Loading State
    btn.disabled = true;
    btnText.innerText = "Syncing...";
    btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Syncing...';
    lucide.createIcons();

    try {
        const response = await fetch('../api/save_settings.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if(result.success) {
            showToast("Social ecosystem updated successfully", "success");
        } else {
            showToast("Update failed. Check configuration.", "error");
        }
    } catch (err) {
        showToast("Network synchronization error.", "error");
    } finally {
        btn.disabled = false;
        btnText.innerText = "Update Links";
        btn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> Update Links';
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