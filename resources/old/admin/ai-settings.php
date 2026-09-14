<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/../db.php';

// Ensure AI/FB columns exist
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbAutoReplyEnabled` BOOLEAN DEFAULT FALSE"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbAutoPosterEnabled` BOOLEAN DEFAULT TRUE"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbPageId` VARCHAR(255) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbPageAccessToken` TEXT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbVerifyToken` VARCHAR(255) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbCommentReply` TEXT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbInboxReply` TEXT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `fbBotPrompt` TEXT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `geminiApiKey` VARCHAR(255) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `geminiPrompt` TEXT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `imagePrompt` TEXT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `geminiModelVersion` VARCHAR(50) DEFAULT 'gemini-1.5-flash'"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `openaiApiKey` VARCHAR(255) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingNotification` ADD COLUMN `activeAiProvider` VARCHAR(50) DEFAULT 'gemini'"); } catch (Exception $e) {}

// Add toggles for Advanced Features to SettingGeneral
try { $pdo->exec("ALTER TABLE `SettingGeneral` ADD COLUMN `aiChatbotEnabled` BOOLEAN DEFAULT TRUE"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingGeneral` ADD COLUMN `cartSyncEnabled` BOOLEAN DEFAULT TRUE"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `SettingGeneral` ADD COLUMN `fbtEnabled` BOOLEAN DEFAULT TRUE"); } catch (Exception $e) {}

// Ensure SEO columns exist in Product table
try { $pdo->exec("ALTER TABLE `Product` ADD COLUMN `seoTitle` VARCHAR(255) NULL, ADD COLUMN `seoDescription` TEXT NULL"); } catch (Exception $e) {}

// Update default image prompt to Eid theme
try { $pdo->exec("UPDATE `SettingNotification` SET `imagePrompt` = 'placed on an elegant table with Islamic Eid decorations, crescent moon and lanterns in the background, soft lighting, 4k high quality' WHERE `imagePrompt` IS NULL OR `imagePrompt` LIKE '%marble%'"); } catch (Exception $e) {}

$defaultCaptionPrompt = "Write a highly engaging, natural, and human-like Facebook post caption for this product in Bengali.\n\nProduct Name: {{product_name}}\nDescription: {{description}}\nPrice: {{price}} Tk\n\nRules:\n1. Write as if a real human is personally recommending or casually talking about this product.\n2. Do NOT sound like a robotic advertisement or a strict list of features.\n3. Keep it conversational, friendly, and relatable. Use emojis naturally.\n4. Mention the price attractively.\n5. Add 3-5 relevant hashtags at the bottom.\n6. Do NOT use markdown (like ** or # for headers). Output plain text.\n7. The entire caption MUST be in authentic conversational Bengali (Bangla).";
// Update default caption prompt to a highly optimized Bengali prompt
try { 
    $stmt = $pdo->query("SELECT geminiPrompt FROM SettingNotification LIMIT 1");
    $currentPrompt = $stmt->fetchColumn();
    if (empty($currentPrompt) || strpos($currentPrompt, 'Write a short, highly engaging') !== false || strpos($currentPrompt, 'short bullet points') !== false) {
        $pdo->prepare("UPDATE `SettingNotification` SET `geminiPrompt` = ? WHERE id = 1")->execute([$defaultCaptionPrompt]);
    }
} catch (Exception $e) {}

// Ensure ScheduledPost Table exists for Auto-Post Scheduler
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ScheduledPost` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `productId` INT NOT NULL,
        `caption` TEXT NULL,
        `imageUrl` VARCHAR(255) NULL,
        `publishAt` DATETIME NULL,
        `status` ENUM('PENDING', 'PUBLISHED', 'FAILED') DEFAULT 'PENDING',
        `fbPostId` VARCHAR(255) NULL,
        `useAiImage` BOOLEAN DEFAULT FALSE,
        `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`productId`) REFERENCES `Product`(`id`) ON DELETE CASCADE
    )");
} catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE `ScheduledPost` MODIFY `caption` TEXT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE `ScheduledPost` ADD COLUMN `useAiImage` BOOLEAN DEFAULT FALSE"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE `ScheduledPost` MODIFY `status` ENUM('PENDING', 'PUBLISHED', 'FAILED', 'DRAFT') DEFAULT 'PENDING'"); } catch(Exception $e) {}

$stmt = $pdo->query("SELECT * FROM SettingNotification LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$genStmt = $pdo->query("SELECT aiChatbotEnabled, cartSyncEnabled, fbtEnabled FROM SettingGeneral LIMIT 1");
$genSettings = $genStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$prodStmt = $pdo->query("SELECT id, name, imageUrl, seoTitle, seoDescription FROM Product ORDER BY id DESC");
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

$schedStmt = $pdo->query("SELECT sp.*, p.name, p.slug, p.basePrice, p.imageUrl as pImg FROM ScheduledPost sp JOIN Product p ON sp.productId = p.id WHERE sp.status != 'DRAFT' ORDER BY sp.publishAt DESC, sp.id DESC LIMIT 10");
$scheduledPosts = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

$reportStmt = $pdo->query("SELECT DATE(publishAt) as publish_date, COUNT(*) as total_posts, SUM(CASE WHEN status = 'PUBLISHED' THEN 1 ELSE 0 END) as published_posts, SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_posts, SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_posts FROM ScheduledPost WHERE status != 'DRAFT' AND publishAt IS NOT NULL GROUP BY DATE(publishAt) ORDER BY publish_date DESC LIMIT 7");
$posterReport = $reportStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-6xl mx-auto pb-12 font-sans" id="ai-app">
    <form id="ai-settings-form" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="bot" class="w-6 h-6 text-indigo-600"></i> AI & Social Automation
                </h2>
                <p class="text-sm text-slate-500 mt-1">Configure your Facebook bots, auto-replies, and AI assistants.</p>
            </div>
            <button type="button" onclick="saveAiSettings()" id="save-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span id="btn-text">Save Changes</span>
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex border-b border-slate-200 px-6 sm:px-8 pt-2 gap-6 bg-slate-50/50 overflow-x-auto custom-scrollbar">
            <button type="button" onclick="switchTab('bot')" id="tab-btn-bot" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-indigo-600 whitespace-nowrap">
                <i data-lucide="message-square" class="w-4 h-4"></i> Messenger Bot
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full"></div>
            </button>
            <button type="button" onclick="switchTab('ai-poster')" id="tab-btn-ai-poster" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500 whitespace-nowrap">
                <i data-lucide="sparkles" class="w-4 h-4"></i> AI Poster Settings
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full hidden"></div>
            </button>
            <button type="button" onclick="switchTab('fb-poster')" id="tab-btn-fb-poster" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500 whitespace-nowrap">
                <i data-lucide="facebook" class="w-4 h-4"></i> Facebook Auto-Poster
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full hidden"></div>
            </button>
            <button type="button" onclick="switchTab('advanced')" id="tab-btn-advanced" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500 whitespace-nowrap">
                <i data-lucide="sliders" class="w-4 h-4"></i> Advanced Features
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full hidden"></div>
            </button>
        </div>

        <!-- Content -->
        <div class="p-6 sm:p-8">
            <!-- TAB: Facebook Bot -->
            <div id="tab-content-bot" class="tab-content block animate-in fade-in">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                <!-- API Setup -->
                <div class="xl:col-span-4 space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Auto Reply (FB Bot)</h3>
                                <p class="text-xs text-slate-500 mt-1">Enable FB Auto Comment/Message Reply.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="fbAutoReplyEnabled" class="sr-only peer" <?php echo !empty($settings['fbAutoReplyEnabled']) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Page ID</label>
                                <input type="text" name="fbPageId" value="<?php echo htmlspecialchars($settings['fbPageId'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Page Access Token</label>
                                <textarea name="fbPageAccessToken" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm font-mono resize-none"><?php echo htmlspecialchars($settings['fbPageAccessToken'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Webhook Verify Token</label>
                                <input type="text" name="fbVerifyToken" value="<?php echo htmlspecialchars($settings['fbVerifyToken'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reply Setup -->
                <div class="xl:col-span-8 space-y-6">
                     <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                         <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-widest flex items-center gap-2"><i data-lucide="bot" class="w-4 h-4 text-indigo-600"></i> AI Bot Instructions (Prompt)</h3>
                         <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Base Bot Prompt</label>
                                <textarea name="fbBotPrompt" rows="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm resize-none"><?php echo htmlspecialchars($settings['fbBotPrompt'] ?? "You are a friendly customer service assistant for a Bangladesh e-commerce store.\n\nCRITICAL RULES:\n1. If the customer writes in Bengali or Banglish (e.g. 'dam koto', 'ki'), you MUST reply in pure Bengali script.\n2. If the customer writes in pure English, you MUST reply in pure English.\n3. Keep the reply conversational, very concise (1-2 sentences), and use emojis."); ?></textarea>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">This prompt guides how the AI will reply to Messenger and Comments. The system will automatically append the customer's message and matching product details to this base prompt.</p>
                            </div>
                         </div>
                     </div>
                     <div class="bg-indigo-50 rounded-2xl p-5 border border-indigo-100">
                        <h4 class="text-sm font-bold text-indigo-900 mb-2 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i> Webhook Setup Instructions</h4>
                        <p class="text-xs text-indigo-800 leading-relaxed mb-2">Set your webhook URL in the Meta App Dashboard to the following. Don't forget to configure your desired Webhook Verify Token.</p>
                        <code class="text-xs bg-white px-3 py-2 rounded-lg border border-indigo-200 text-indigo-900 break-all select-all font-mono block">https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/fb_webhook.php</code>
                    </div>
                </div>
            </div>
            </div>
            
            <!-- TAB: AI Poster Settings -->
            <div id="tab-content-ai-poster" class="tab-content hidden animate-in fade-in">
            <div class="grid grid-cols-1 max-w-3xl mx-auto gap-8">
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2"><i data-lucide="sparkles" class="w-4 h-4 text-indigo-600"></i> AI Poster Settings</h3>
                        <p class="text-xs text-slate-500 mt-1 mb-4">Configure API keys and default prompts.</p>
                        
                        <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar pr-2">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Text AI Engine</label>
                                <select name="activeAiProvider" onchange="toggleAiProviderFields()" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm font-bold text-slate-700 cursor-pointer">
                                    <option value="gemini" <?php echo ($settings['activeAiProvider'] ?? 'gemini') === 'gemini' ? 'selected' : ''; ?>>Google Gemini (Free/Fast)</option>
                                    <option value="openai" <?php echo ($settings['activeAiProvider'] ?? '') === 'openai' ? 'selected' : ''; ?>>ChatGPT (OpenAI)</option>
                                </select>
                            </div>
                            
                            <div id="gemini-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4 transition-all">
                                <div class="space-y-2" id="gemini-keys-container">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                                        Gemini API Keys
                                        <button type="button" onclick="addGeminiKeyBox()" class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-1 rounded hover:bg-indigo-100 transition-colors">+ Add Key</button>
                                    </label>
                                    <?php 
                                    $keys = !empty($settings['geminiApiKey']) ? explode(',', $settings['geminiApiKey']) : [''];
                                    foreach($keys as $idx => $k): 
                                    ?>
                                    <div class="flex gap-2 mt-2">
                                        <input type="text" name="geminiApiKey[]" value="<?php echo htmlspecialchars(trim($k)); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm" placeholder="AIzaSy...">
                                        <?php if($idx > 0): ?>
                                        <button type="button" onclick="this.parentElement.remove()" class="p-3 text-rose-500 hover:bg-rose-50 rounded-xl transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Gemini Model Version</label>
                                    <?php $currentModel = $settings['geminiModelVersion'] ?? 'gemini-2.5-flash'; ?>
                                    <select name="geminiModelVersion" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm font-semibold text-slate-700 cursor-pointer">
                                        <option value="gemini-2.5-flash" <?php echo $currentModel === 'gemini-2.5-flash' ? 'selected' : ''; ?>>Gemini 2.5 Flash (Latest & Fastest)</option>
                                        <option value="gemini-1.5-flash" <?php echo $currentModel === 'gemini-1.5-flash' ? 'selected' : ''; ?>>Gemini 1.5 Flash (Stable)</option>
                                        <option value="gemini-1.5-pro" <?php echo $currentModel === 'gemini-1.5-pro' ? 'selected' : ''; ?>>Gemini 1.5 Pro (Advanced)</option>
                                    </select>
                                </div>
                            </div>

                            <div id="openai-fields" class="space-y-2 transition-all hidden">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">OpenAI API Key</label>
                                    <input type="password" name="openaiApiKey" value="<?php echo htmlspecialchars($settings['openaiApiKey'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm" placeholder="sk-proj-...">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Default Caption Prompt</label>
                                <textarea name="geminiPrompt" rows="10" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm resize-none"><?php echo htmlspecialchars($settings['geminiPrompt'] ?? $defaultCaptionPrompt); ?></textarea>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Default Image Prompt (Background)</label>
                                <textarea name="imagePrompt" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm resize-none" placeholder="e.g. placed on a marble table with sunlight..."><?php echo htmlspecialchars($settings['imagePrompt'] ?? "placed on an elegant table with Islamic Eid decorations, crescent moon and lanterns in the background, soft lighting, 4k high quality"); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk SEO Generator Feature -->
                    <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-indigo-900 mb-2 flex items-center gap-2"><i data-lucide="search" class="w-4 h-4"></i> Bulk SEO Meta Generator</h3>
                        <p class="text-xs text-indigo-700 mb-4">Automatically generate highly optimized SEO Titles and Meta Descriptions for products missing them using AI.</p>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <button type="button" id="btn-bulk-seo" onclick="startBulkSeo()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2 shrink-0">
                                <i data-lucide="sparkles" class="w-4 h-4"></i> Generate Missing SEO
                            </button>
                            <span id="bulk-seo-status" class="text-xs font-bold text-slate-500 hidden"></span>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            
            <!-- TAB: Facebook Auto-Poster -->
            <div id="tab-content-fb-poster" class="tab-content hidden animate-in fade-in">
            <div class="grid grid-cols-1 max-w-5xl mx-auto gap-8">
                <div class="space-y-6">
                    <!-- Master Toggle for Auto-Poster -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Facebook Auto-Poster Active</h3>
                                <p class="text-xs text-slate-500 mt-1">Enable or disable background auto-publishing to your Facebook Page.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="fbAutoPosterEnabled" class="sr-only peer" <?php echo !isset($settings['fbAutoPosterEnabled']) || $settings['fbAutoPosterEnabled'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-indigo-200/50">
                            <div>
                                <h3 class="text-sm font-bold text-indigo-900 flex items-center gap-2"><i data-lucide="send" class="w-4 h-4 text-indigo-600"></i> Auto-Pilot Queue</h3>
                                <p class="text-xs text-indigo-700 mt-1">Select products and let AI auto-post them on a schedule.</p>
                            </div>
                            <label class="inline-flex items-center cursor-pointer bg-white px-3 py-2 rounded-xl border border-indigo-100 shadow-sm">
                                <span class="mr-3 text-xs font-bold text-indigo-800">Generate AI Image</span>
                                <input type="checkbox" id="use-ai-image" class="sr-only peer">
                                <div class="relative w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <label class="text-xs font-bold text-indigo-800 uppercase tracking-wider block">1. Select Products</label>
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <i data-lucide="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 w-3 h-3"></i>
                                        <input type="text" id="queue-search" oninput="filterQueue()" placeholder="Find product..." class="pl-7 pr-3 py-1.5 text-xs bg-white border border-indigo-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500/20 shadow-sm w-36 sm:w-auto">
                                    </div>
                                    <button type="button" onclick="selectAllQueue(true)" class="text-[10px] font-bold bg-indigo-100 text-indigo-700 px-2.5 py-1.5 rounded-lg hover:bg-indigo-200 transition-colors">All</button>
                                    <button type="button" onclick="selectAllQueue(false)" class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2.5 py-1.5 rounded-lg hover:bg-slate-200 transition-colors">None</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[250px] overflow-y-auto custom-scrollbar p-3 border border-indigo-100 rounded-xl bg-white/50">
                                <?php foreach($products as $p): ?>
                                    <label class="queue-item flex items-center gap-3 p-2.5 bg-white border border-indigo-100 rounded-xl cursor-pointer hover:border-indigo-400 transition-colors shadow-sm group" data-name="<?php echo htmlspecialchars($p['name']); ?>">
                                        <input type="checkbox" value="<?php echo $p['id']; ?>" class="autopilot-checkbox w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                                        <?php 
                                        $firstImg = explode(',', $p['imageUrl'])[0]; 
                                        $imgSrc = (strpos($firstImg, 'http') === 0 || empty($firstImg)) ? $firstImg : '..' . $firstImg;
                                        ?>
                                        <img src="<?php echo $imgSrc ? $imgSrc : 'https://placehold.co/100'; ?>" class="w-10 h-10 rounded-lg object-cover border border-slate-100 group-hover:scale-105 transition-transform">
                                        <span class="text-xs font-bold text-slate-700 truncate flex-1" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if(empty($products)): ?>
                                    <p class="text-xs text-slate-500 col-span-full text-center py-4">No products available.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="space-y-3 pt-4">
                                <label class="text-xs font-bold text-indigo-800 uppercase tracking-wider flex items-center justify-between">
                                    <span>2. Daily Posting Times & Rules</span>
                                    <button type="button" onclick="addDailyTimeSlot()" class="text-[10px] bg-indigo-100 text-indigo-700 px-2.5 py-1.5 rounded-lg hover:bg-indigo-200 transition-colors font-bold">+ Add Time</button>
                                </label>
                                <div id="daily-times-container" class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2 bg-slate-50 p-2 rounded-xl border border-indigo-100">
                                        <input type="time" name="daily_times[]" value="09:00" class="px-3 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700">
                                        <span class="text-xs text-slate-500 font-bold">Post</span>
                                        <input type="number" name="daily_count[]" value="1" min="1" class="w-16 px-2 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700 text-center">
                                        <span class="text-xs text-slate-500 font-bold">items, gap</span>
                                        <input type="number" name="daily_gap[]" value="15" min="0" class="w-16 px-2 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700 text-center">
                                        <span class="text-xs text-slate-500 font-bold">mins</span>
                                        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg ml-auto"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 bg-slate-50 p-2 rounded-xl border border-indigo-100">
                                        <input type="time" name="daily_times[]" value="14:00" class="px-3 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700">
                                        <span class="text-xs text-slate-500 font-bold">Post</span>
                                        <input type="number" name="daily_count[]" value="1" min="1" class="w-16 px-2 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700 text-center">
                                        <span class="text-xs text-slate-500 font-bold">items, gap</span>
                                        <input type="number" name="daily_gap[]" value="15" min="0" class="w-16 px-2 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700 text-center">
                                        <span class="text-xs text-slate-500 font-bold">mins</span>
                                        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg ml-auto"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    </div>
                                </div>
                                <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest"><i data-lucide="info" class="w-3 h-3 inline-block"></i> Posts will be distributed automatically across these times.</p>
                            </div>

                            <button type="button" onclick="scheduleAutoPilot()" id="btn-autopilot" class="w-full py-3.5 mt-2 bg-indigo-600 text-white font-bold rounded-xl shadow-md hover:bg-indigo-700 hover:shadow-lg transition-all flex justify-center items-center gap-2 text-sm active:scale-[0.99]">
                                <i data-lucide="calendar-plus" class="w-5 h-5"></i> Add Selected to Queue
                            </button>
                            <p class="text-center text-[10px] font-bold text-indigo-500 mt-2 uppercase tracking-widest"><i data-lucide="info" class="w-3 h-3 inline-block"></i> Turn AI image OFF to post all product images (Max 4)</p>
                        </div>
                    </div>
                    
                    <!-- Scheduled Posts List -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="list-todo" class="w-4 h-4 text-indigo-600"></i> Auto-Pilot Queue & Recent Posts</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="text-slate-400 text-[10px] uppercase tracking-widest border-b border-slate-100">
                                    <tr>
                                        <th class="pb-3 font-bold">Product</th>
                                        <th class="pb-3 font-bold">AI Image</th>
                                        <th class="pb-3 font-bold">Publish At</th>
                                        <th class="pb-3 font-bold">Status</th>
                                        <th class="pb-3 font-bold text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php if (empty($scheduledPosts)): ?>
                                    <tr><td colspan="5" class="py-8 text-center text-slate-500 text-xs font-medium">No posts in queue. Select products above and add them.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($scheduledPosts as $post): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-3 font-bold text-slate-700 truncate max-w-[200px]"><?php echo htmlspecialchars($post['name']); ?></td>
                                        <td class="py-3 text-slate-500">
                                            <?php echo !empty($post['useAiImage']) ? '<span class="text-indigo-600 font-bold flex items-center gap-1"><i data-lucide="sparkles" class="w-3 h-3"></i> Yes</span>' : '<span class="text-slate-400 font-medium">No (All Images)</span>'; ?>
                                        </td>
                                        <td class="py-3 font-medium text-slate-600"><?php echo !empty($post['publishAt']) ? date('M d, h:i A', strtotime($post['publishAt'])) : 'Instant'; ?></td>
                                        <td class="py-3">
                                            <?php if ($post['status'] === 'PENDING'): ?>
                                                <span class="px-2 py-1 bg-amber-50 text-amber-600 border border-amber-200 rounded-md text-[10px] font-bold uppercase tracking-wider">Pending</span>
                                            <?php elseif ($post['status'] === 'PUBLISHED'): ?>
                                                <span class="px-2 py-1 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-md text-[10px] font-bold uppercase tracking-wider">Published</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-rose-50 text-rose-600 border border-rose-200 rounded-md text-[10px] font-bold uppercase tracking-wider cursor-help" title="Check API settings">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <?php if ($post['status'] === 'PENDING' || $post['status'] === 'FAILED'): ?>
                                                <button type="button" onclick="publishNow(<?php echo $post['id']; ?>)" class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Publish Now">
                                                    <i data-lucide="send" class="w-4.5 h-4.5"></i>
                                                </button>
                                                <button type="button" onclick="editScheduledPost(<?php echo htmlspecialchars(json_encode($post), ENT_QUOTES, 'UTF-8'); ?>)" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Post">
                                                    <i data-lucide="edit-3" class="w-4.5 h-4.5"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($post['status'] === 'PUBLISHED' && !empty($post['fbPostId'])): ?>
                                                <a href="https://facebook.com/<?php echo htmlspecialchars($post['fbPostId']); ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View on Facebook">
                                                    <i data-lucide="external-link" class="w-4.5 h-4.5"></i>
                                                </a>
                                                <?php endif; ?>
                                                <button type="button" onclick="viewScheduledPost(<?php echo htmlspecialchars(json_encode($post), ENT_QUOTES, 'UTF-8'); ?>)" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Preview Post">
                                                    <i data-lucide="eye" class="w-4.5 h-4.5"></i>
                                                </button>
                                                <button type="button" onclick="regenerateScheduledPost(<?php echo $post['id']; ?>)" class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Regenerate Post">
                                                    <i data-lucide="refresh-cw" class="w-4.5 h-4.5"></i>
                                                </button>
                                                <button type="button" onclick="deleteScheduledPost(<?php echo $post['id']; ?>)" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete Post">
                                                    <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
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

                    <!-- Daily Report -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="bar-chart-2" class="w-4 h-4 text-indigo-600"></i> Publishing Report (Last 7 Days)</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="text-slate-400 text-[10px] uppercase tracking-widest border-b border-slate-100">
                                    <tr>
                                        <th class="pb-3 font-bold">Date</th>
                                        <th class="pb-3 font-bold text-center">Total Scheduled</th>
                                        <th class="pb-3 font-bold text-center">Published</th>
                                        <th class="pb-3 font-bold text-center">Pending</th>
                                        <th class="pb-3 font-bold text-center">Failed</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php if (empty($posterReport)): ?>
                                    <tr><td colspan="5" class="py-6 text-center text-slate-500 text-xs font-medium">No posting history available.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($posterReport as $report): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="py-3 font-bold text-slate-700"><?php echo date('M d, Y', strtotime($report['publish_date'])); ?></td>
                                        <td class="py-3 text-center font-bold text-slate-600"><?php echo $report['total_posts']; ?></td>
                                        <td class="py-3 text-center"><span class="px-2 py-1 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-md text-xs font-bold"><?php echo $report['published_posts']; ?></span></td>
                                        <td class="py-3 text-center"><span class="px-2 py-1 bg-amber-50 text-amber-600 border border-amber-100 rounded-md text-xs font-bold"><?php echo $report['pending_posts']; ?></span></td>
                                        <td class="py-3 text-center"><span class="px-2 py-1 bg-rose-50 text-rose-600 border border-rose-100 rounded-md text-xs font-bold"><?php echo $report['failed_posts']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            
            <!-- TAB: Advanced Features -->
            <div id="tab-content-advanced" class="tab-content hidden animate-in fade-in">
            <div class="max-w-3xl mx-auto space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-6 pb-4 border-b border-slate-100"><i data-lucide="sliders" class="w-4 h-4 text-indigo-600"></i> Website Advanced Features</h3>
                    
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-800">AI Shopping Assistant (Chatbot)</h4>
                                <p class="text-xs text-slate-500 mt-1">Show the floating AI chatbot on the frontend website.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="aiChatbotEnabled" class="sr-only peer" <?php echo !isset($genSettings['aiChatbotEnabled']) || $genSettings['aiChatbotEnabled'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-50 pt-6">
                            <div>
                                <h4 class="font-bold text-slate-800">Cart Sync to Database</h4>
                                <p class="text-xs text-slate-500 mt-1">Sync logged-in users' carts to DB for cross-device access and abandoned cart recovery.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="cartSyncEnabled" class="sr-only peer" <?php echo !isset($genSettings['cartSyncEnabled']) || $genSettings['cartSyncEnabled'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-50 pt-6">
                            <div>
                                <h4 class="font-bold text-slate-800">Frequently Bought Together</h4>
                                <p class="text-xs text-slate-500 mt-1">Show smart product recommendations on the product details page.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="fbtEnabled" class="sr-only peer" <?php echo !isset($genSettings['fbtEnabled']) || $genSettings['fbtEnabled'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </form>
</div>

<!-- Facebook Post Preview Modal -->
<div id="fb-preview-modal" class="fixed inset-0 z-[120] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-3xl overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="font-bold text-slate-800 flex items-center gap-2"><i data-lucide="facebook" class="w-5 h-5 text-blue-600"></i> Post Preview</h2>
            <button type="button" onclick="closeFbPreviewModal()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-4 bg-slate-100 flex justify-center max-h-[80vh] overflow-y-auto custom-scrollbar">
            <!-- Facebook Post Card Mockup -->
            <div class="w-full bg-white border border-slate-200 shadow-sm rounded-xl overflow-hidden">
                <!-- Header -->
                <div class="p-3 flex items-center gap-2">
                    <div class="w-10 h-10 bg-slate-200 rounded-full flex items-center justify-center shrink-0">
                        <i data-lucide="store" class="w-5 h-5 text-slate-400"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-800 leading-tight">Your Facebook Page</p>
                        <p class="text-xs text-slate-500" id="preview-time">Just now • <i data-lucide="globe" class="w-3 h-3 inline"></i></p>
                    </div>
                </div>
                <!-- Caption -->
                <div class="px-3 pb-3 text-sm text-slate-800 whitespace-pre-wrap" id="preview-caption"></div>
                <!-- Image -->
                <div class="w-full bg-slate-100 relative group">
                    <img id="preview-image" src="https://placehold.co/600x600" class="w-full h-auto object-cover max-h-[400px]">
                    <div id="preview-ai-badge" class="absolute top-3 right-3 bg-indigo-600/90 backdrop-blur-sm text-white text-[10px] font-black uppercase px-2.5 py-1 rounded-lg shadow-lg flex items-center gap-1.5 hidden">
                        <i data-lucide="sparkles" class="w-3 h-3"></i> AI Will Generate Image
                    </div>
                </div>
                <!-- Footer -->
                <div class="p-3 border-t border-slate-100 flex items-center justify-between text-slate-500">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1.5 text-xs font-bold hover:bg-slate-50 px-2 py-1 rounded-lg cursor-not-allowed"><i data-lucide="thumbs-up" class="w-4 h-4"></i> Like</span>
                        <span class="flex items-center gap-1.5 text-xs font-bold hover:bg-slate-50 px-2 py-1 rounded-lg cursor-not-allowed"><i data-lucide="message-square" class="w-4 h-4"></i> Comment</span>
                    </div>
                    <span class="flex items-center gap-1.5 text-xs font-bold hover:bg-slate-50 px-2 py-1 rounded-lg cursor-not-allowed"><i data-lucide="share-2" class="w-4 h-4"></i> Share</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Draft Review Modal -->
<div id="draft-review-modal" class="fixed inset-0 z-[130] bg-slate-900/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh] animate-in fade-in zoom-in duration-200">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i> Review Generated Posts</h2>
            <button type="button" onclick="cancelDrafts()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-slate-100/50 space-y-6" id="draft-posts-container">
            <!-- Mockups go here -->
        </div>
        <div class="p-5 border-t border-slate-100 bg-white flex gap-4 shrink-0">
            <button type="button" onclick="cancelDrafts()" class="flex-1 py-3 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">Discard All</button>
            <button type="button" onclick="confirmDrafts()" id="btn-confirm-drafts" class="flex-[2] py-3 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md rounded-xl transition-colors flex justify-center items-center gap-2">
                <i data-lucide="calendar-check" class="w-4 h-4"></i> Confirm & Schedule
            </button>
        </div>
    </div>
</div>

<!-- Edit Scheduled Post Modal -->
<div id="edit-post-modal" class="fixed inset-0 z-[120] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-3xl overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="font-bold text-slate-800 flex items-center gap-2"><i data-lucide="edit-3" class="w-5 h-5 text-indigo-600"></i> Edit Scheduled Post</h2>
            <button type="button" onclick="closeEditPostModal()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form id="edit-post-form" onsubmit="saveEditedPost(event)" class="p-5 space-y-4 max-h-[80vh] overflow-y-auto custom-scrollbar">
            <input type="hidden" name="id" id="edit-post-id">
            <div class="space-y-2">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Caption</label>
                <textarea name="caption" id="edit-post-caption" rows="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm resize-y custom-scrollbar"></textarea>
            </div>
            <div class="space-y-2">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Image (Leave empty to keep current)</label>
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 bg-slate-100 rounded-xl border border-slate-200 overflow-hidden shrink-0">
                        <img id="edit-post-img-preview" src="" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 relative border-2 border-dashed border-indigo-200 bg-indigo-50/50 hover:bg-indigo-50 rounded-xl p-4 flex flex-col items-center justify-center cursor-pointer transition-colors">
                        <input type="file" name="image" id="edit-post-image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="previewEditPostImage(this)">
                        <i data-lucide="upload-cloud" class="w-5 h-5 text-indigo-500 mb-1"></i>
                        <span class="text-xs font-bold text-indigo-700">Upload New Image</span>
                    </div>
                </div>
            </div>
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeEditPostModal()" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors text-sm">Cancel</button>
                <button type="submit" id="edit-post-submit-btn" class="flex-[2] py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md transition-colors text-sm flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const productsData = <?php echo json_encode($products); ?>;

function addGeminiKeyBox() {
    const container = document.getElementById('gemini-keys-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2 mt-2';
    div.innerHTML = `
        <input type="text" name="geminiApiKey[]" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm" placeholder="AIzaSy...">
        <button type="button" onclick="this.parentElement.remove()" class="p-3 text-rose-500 hover:bg-rose-50 rounded-xl transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
    `;
    container.appendChild(div);
    lucide.createIcons();
}

function addDailyTimeSlot() {
    const container = document.getElementById('daily-times-container');
    const div = document.createElement('div');
    div.className = 'flex flex-wrap items-center gap-2 bg-slate-50 p-2 rounded-xl border border-indigo-100';
    div.innerHTML = `
        <input type="time" name="daily_times[]" value="12:00" class="px-3 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700">
        <span class="text-xs text-slate-500 font-bold">Post</span>
        <input type="number" name="daily_count[]" value="1" min="1" class="w-16 px-2 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700 text-center">
        <span class="text-xs text-slate-500 font-bold">items, gap</span>
        <input type="number" name="daily_gap[]" value="15" min="0" class="w-16 px-2 py-2 bg-white border border-indigo-200 rounded-lg outline-none text-sm font-bold text-slate-700 text-center">
        <span class="text-xs text-slate-500 font-bold">mins</span>
        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg ml-auto"><i data-lucide="x" class="w-4 h-4"></i></button>
    `;
    container.appendChild(div);
    lucide.createIcons();
}

function toggleAiProviderFields() {
    const provider = document.querySelector('select[name="activeAiProvider"]').value;
    const geminiFields = document.getElementById('gemini-fields');
    const openaiFields = document.getElementById('openai-fields');
    
    if (provider === 'gemini') {
        geminiFields.classList.remove('hidden');
        openaiFields.classList.add('hidden');
    } else {
        geminiFields.classList.add('hidden');
        openaiFields.classList.remove('hidden');
    }
}

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('text-indigo-600');
        btn.classList.add('text-slate-500');
        btn.querySelector('.tab-indicator').classList.add('hidden');
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('block');
    });
    
    const activeBtn = document.getElementById('tab-btn-' + tab);
    if(activeBtn) {
        activeBtn.classList.add('text-indigo-600');
        activeBtn.classList.remove('text-slate-500');
        activeBtn.querySelector('.tab-indicator').classList.remove('hidden');
    }
    const activeContent = document.getElementById('tab-content-' + tab);
    if(activeContent) {
        activeContent.classList.remove('hidden');
        activeContent.classList.add('block', 'animate-in', 'fade-in');
    }
    
    // URL-এ হ্যাশ (#) সেভ করা যাতে রিফ্রেশ করলে এই ট্যাবটিই থাকে
    window.history.replaceState(null, null, window.location.pathname + window.location.search + '#' + tab);
}

document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.substring(1);
    if (['bot', 'ai-poster', 'fb-poster', 'advanced'].includes(hash)) {
        switchTab(hash);
    } else {
        switchTab('bot');
    }
});

// Queue Filter and Select All
function filterQueue() {
    const term = document.getElementById('queue-search').value.toLowerCase();
    document.querySelectorAll('.queue-item').forEach(item => {
        const name = item.getAttribute('data-name').toLowerCase();
        item.style.display = name.includes(term) ? 'flex' : 'none';
    });
}

function selectAllQueue(select) {
    document.querySelectorAll('.queue-item').forEach(item => {
        if (item.style.display !== 'none') {
            item.querySelector('.autopilot-checkbox').checked = select;
        }
    });
}

function updateProductPreview() {
    const productId = document.getElementById('post-product-id').value;
    const imgPreview = document.getElementById('product-img-preview');
    document.getElementById('custom-image-url').value = ''; // Reset custom image on product change
    
    if (productId) {
        const product = productsData.find(p => p.id == productId);
        if (product && product.imageUrl) {
            const firstImg = product.imageUrl.split(',')[0];
            imgPreview.src = firstImg.startsWith('http') ? firstImg : '..' + firstImg;
            return;
        }
    }
    imgPreview.src = 'https://placehold.co/400?text=Select+Product';
}

async function saveAiSettings() {
    const btn = document.getElementById('save-btn');
    const form = document.getElementById('ai-settings-form');
    const formData = new FormData(form);
    
    formData.set('fbAutoReplyEnabled', form.fbAutoReplyEnabled.checked ? 1 : 0);
    formData.set('fbAutoPosterEnabled', form.fbAutoPosterEnabled.checked ? 1 : 0);
    formData.set('aiChatbotEnabled', form.aiChatbotEnabled.checked ? 1 : 0);
    formData.set('cartSyncEnabled', form.cartSyncEnabled.checked ? 1 : 0);
    formData.set('fbtEnabled', form.fbtEnabled.checked ? 1 : 0);

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-4 h-4"></i> Saving...';
    lucide.createIcons();

    try {
        const response = await fetch('../api/save_ai_settings.php', { method: 'POST', body: formData });
        const text = await response.text();
        try {
            const result = JSON.parse(text);
            if(result.success) { showToast("AI settings updated successfully!", "success"); } 
            else { showToast(result.error || "Failed to save settings.", "error"); }
        } catch (e) { showToast("Server error. Please check console.", "error"); }
    } catch (err) { showToast("An error occurred while saving.", "error"); } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Changes';
        lucide.createIcons();
    }
}

async function scheduleAutoPilot() {
    const checkboxes = document.querySelectorAll('.autopilot-checkbox:checked');
    const productIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (productIds.length === 0) {
        showToast("Please select at least one product.", "error");
        return;
    }
    
    const useAiImage = document.getElementById('use-ai-image').checked;
    const timeInputs = document.querySelectorAll('input[name="daily_times[]"]');
    const countInputs = document.querySelectorAll('input[name="daily_count[]"]');
    const gapInputs = document.querySelectorAll('input[name="daily_gap[]"]');
    
    const dailyTimes = [];
    for (let i = 0; i < timeInputs.length; i++) {
        if (timeInputs[i].value) {
            dailyTimes.push({ time: timeInputs[i].value, count: parseInt(countInputs[i].value) || 1, gap: parseInt(gapInputs[i].value) || 0 });
        }
    }
    
    if (dailyTimes.length === 0) {
        showToast("Please add at least one post time.", "error");
        return;
    }
    
    const btn = document.getElementById('btn-autopilot');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-5 h-5"></i> Generating AI Posts... (Please Wait)';
    lucide.createIcons();

    try {
        const formData = new FormData();
        formData.append('productIds', JSON.stringify(productIds));
        formData.append('useAiImage', useAiImage);
        formData.append('dailyTimes', JSON.stringify(dailyTimes));
        
        const response = await fetch('../api/post_to_fb.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) { 
            showDraftReviewModal(result.drafts);
        } else { 
            showToast(result.error || "Failed to schedule posts.", "error"); 
            btn.disabled = false;
            btn.innerHTML = originalText;
            lucide.createIcons();
        }
    } catch (e) { 
        showToast("Network Error.", "error"); 
        btn.disabled = false;
        btn.innerHTML = originalText;
        lucide.createIcons();
    }
}

async function startBulkSeo() {
    const missingSeo = productsData.filter(p => !p.seoTitle || !p.seoDescription);
    if (missingSeo.length === 0) {
        showToast("All products already have SEO data!", "success");
        return;
    }

    const btn = document.getElementById('btn-bulk-seo');
    const status = document.getElementById('bulk-seo-status');
    btn.disabled = true;
    status.classList.remove('hidden');

    let successCount = 0;
    for (let i = 0; i < missingSeo.length; i++) {
        status.innerHTML = `<i data-lucide="loader-2" class="w-3 h-3 animate-spin inline"></i> Processing ${i + 1} of ${missingSeo.length}: ${missingSeo[i].name}...`;
        lucide.createIcons();

        const formData = new FormData();
        formData.append('productId', missingSeo[i].id);

        try {
            const res = await fetch('../api/generate_single_seo.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) successCount++;
        } catch(e) { console.error(e); }
    }

    status.innerHTML = `<i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 inline"></i> Complete! Updated SEO for ${successCount} products.`;
    lucide.createIcons();
    btn.disabled = false;
    if (window.showToast) showToast(`Bulk SEO Generation Complete!`, "success");
}

let currentDraftIds = [];

function showDraftReviewModal(drafts) {
    currentDraftIds = drafts.map(d => d.id);
    const container = document.getElementById('draft-posts-container');
    container.innerHTML = '';

    drafts.forEach(post => {
        let firstImg = post.imageUrl;
        let imgSrc = (firstImg && firstImg.startsWith('http')) ? firstImg : '..' + firstImg;
        
        let d = new Date(post.publishAt);
        let timeStr = d.toLocaleString('en-US', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});

        container.innerHTML += `
            <div class="w-full bg-white border border-slate-200 shadow-sm rounded-xl overflow-hidden">
                <div class="p-3 flex items-center justify-between border-b border-slate-50 bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center shrink-0">
                            <i data-lucide="store" class="w-4 h-4 text-slate-400"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800 leading-tight">Your Facebook Page</p>
                            <p class="text-[10px] text-slate-500">${timeStr} • <i data-lucide="globe" class="w-2.5 h-2.5 inline"></i></p>
                        </div>
                    </div>
                </div>
                
                <div class="px-4 py-3 relative group">
                    <div id="draft-cap-view-${post.id}" class="text-sm text-slate-800 whitespace-pre-wrap">${post.caption}</div>
                    <textarea id="draft-cap-edit-${post.id}" class="hidden w-full min-h-[120px] p-3 text-sm bg-slate-50 border border-indigo-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 custom-scrollbar resize-y">${post.caption}</textarea>
                    
                    <button onclick="toggleDraftEdit(${post.id})" id="btn-edit-draft-${post.id}" class="absolute top-2 right-2 p-1.5 bg-white border border-slate-200 shadow-sm rounded-lg text-slate-400 hover:text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity" title="Edit Caption">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </button>
                    <div id="draft-edit-actions-${post.id}" class="hidden flex justify-end gap-2 mt-3">
                        <button onclick="cancelDraftEdit(${post.id})" class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">Cancel</button>
                        <button onclick="saveDraftEdit(${post.id})" id="btn-save-draft-${post.id}" class="px-3 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm rounded-lg transition-colors flex items-center gap-1.5"><i data-lucide="save" class="w-3.5 h-3.5"></i> Save</button>
                    </div>
                </div>
                <div class="w-full bg-slate-100 relative">
                    <img src="${imgSrc}" class="w-full h-auto object-cover max-h-[400px]">
                    ${post.useAiImage == 1 ? `<div class="absolute top-3 right-3 bg-indigo-600/90 backdrop-blur-sm text-white text-[10px] font-black uppercase px-2.5 py-1 rounded-lg shadow-lg flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3 h-3"></i> AI Generated</div>` : ''}
                </div>
            </div>
        `;
    });

    const btn = document.getElementById('btn-autopilot');
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="calendar-plus" class="w-5 h-5"></i> Add Selected to Queue';

    document.getElementById('draft-review-modal').classList.remove('hidden');
    document.getElementById('draft-review-modal').classList.add('flex');
    lucide.createIcons();
}

function toggleDraftEdit(id) {
    const viewEl = document.getElementById('draft-cap-view-' + id);
    const editEl = document.getElementById('draft-cap-edit-' + id);
    const editBtn = document.getElementById('btn-edit-draft-' + id);
    const actionsEl = document.getElementById('draft-edit-actions-' + id);
    
    viewEl.classList.add('hidden');
    editEl.classList.remove('hidden');
    editBtn.classList.add('hidden');
    actionsEl.classList.remove('hidden');
    actionsEl.classList.add('flex');
}

function cancelDraftEdit(id) {
    const viewEl = document.getElementById('draft-cap-view-' + id);
    const editEl = document.getElementById('draft-cap-edit-' + id);
    const editBtn = document.getElementById('btn-edit-draft-' + id);
    const actionsEl = document.getElementById('draft-edit-actions-' + id);
    
    editEl.value = viewEl.innerText; // Reset to original
    
    editEl.classList.add('hidden');
    viewEl.classList.remove('hidden');
    actionsEl.classList.add('hidden');
    actionsEl.classList.remove('flex');
    editBtn.classList.remove('hidden');
}

async function saveDraftEdit(id) {
    const newCaption = document.getElementById('draft-cap-edit-' + id).value;
    const saveBtn = document.getElementById('btn-save-draft-' + id);
    const originalHtml = saveBtn.innerHTML;
    
    saveBtn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Saving...';
    saveBtn.disabled = true;
    lucide.createIcons();
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('caption', newCaption);
        const res = await fetch('../api/update_draft_caption.php', { method: 'POST', body: formData });
        const result = await res.json();
        
        if (result.success) {
            document.getElementById('draft-cap-view-' + id).innerText = newCaption;
            cancelDraftEdit(id);
            showToast("Caption updated successfully!", "success");
        } else { showToast("Failed to update.", "error"); }
    } catch(e) {
        showToast("Network Error.", "error");
    } finally {
        saveBtn.innerHTML = originalHtml;
        saveBtn.disabled = false;
        lucide.createIcons();
    }
}

async function confirmDrafts() {
    const btn = document.getElementById('btn-confirm-drafts');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-4 h-4"></i> Confirming...';
    
    try {
        const formData = new FormData();
        formData.append('ids', JSON.stringify(currentDraftIds));
        const res = await fetch('../api/confirm_drafts.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            showToast("Posts scheduled successfully!", "success");
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast("Failed to confirm.", "error");
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="calendar-check" class="w-4 h-4"></i> Confirm & Schedule';
        }
    } catch(e) {
        showToast("Error confirming posts.", "error");
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="calendar-check" class="w-4 h-4"></i> Confirm & Schedule';
    }
}

async function cancelDrafts() {
    const modal = document.getElementById('draft-review-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    
    if (currentDraftIds.length > 0) {
        const formData = new FormData();
        formData.append('ids', JSON.stringify(currentDraftIds));
        fetch('../api/delete_drafts.php', { method: 'POST', body: formData });
    }
    currentDraftIds = [];
}

function viewScheduledPost(post) {
    const modal = document.getElementById('fb-preview-modal');
    const timeEl = document.getElementById('preview-time');
    const captionEl = document.getElementById('preview-caption');
    const imgEl = document.getElementById('preview-image');
    const aiBadge = document.getElementById('preview-ai-badge');
    
    if (post.publishAt) {
        const d = new Date(post.publishAt);
        timeEl.innerHTML = d.toLocaleString('en-US', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'}) + ' • <i data-lucide="globe" class="w-3 h-3 inline"></i>';
    } else {
        timeEl.innerHTML = 'Just now • <i data-lucide="globe" class="w-3 h-3 inline"></i>';
    }

    let captionText = post.caption;
    if (!captionText) {
        captionText = `[ 🤖 Generating Post... ]\n\nHey everyone! Check out our amazing ${post.name}.\n\nPrice: ৳${post.basePrice}\n\n🛍️ Order yours here: https://${window.location.host}/product?slug=${post.slug}`;
    }
    captionEl.innerText = captionText;

    if (post.useAiImage == 1 && post.status === 'PENDING' && !post.imageUrl) aiBadge.classList.remove('hidden');
    else aiBadge.classList.add('hidden');
    
    let firstImg = post.imageUrl ? post.imageUrl : (post.pImg ? post.pImg.split(',')[0] : 'https://placehold.co/600');
    imgEl.src = firstImg.startsWith('http') ? firstImg : '..' + firstImg;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    lucide.createIcons();
}

function closeFbPreviewModal() {
    const modal = document.getElementById('fb-preview-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.addEventListener('DOMContentLoaded', () => {
    // Persist Generate AI Image preference
    const useAiImage = document.getElementById('use-ai-image');
    if (useAiImage) {
        const savedAiOpt = localStorage.getItem('autopilot_use_ai_image');
        if (savedAiOpt !== null) useAiImage.checked = savedAiOpt === 'true';
        useAiImage.addEventListener('change', function() {
            localStorage.setItem('autopilot_use_ai_image', this.checked);
        });
    }
});

async function deleteScheduledPost(id) {
    customConfirm("Are you sure you want to delete this scheduled post from the queue?", async () => {
        try {
            const formData = new FormData();
            formData.append('id', id);
            const response = await fetch('../api/delete_scheduled_post.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                showToast("Post deleted successfully!", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.error || "Failed to delete.", "error");
            }
        } catch (e) {
            showToast("Network Error.", "error");
        }
    });
}

async function regenerateScheduledPost(id) {
    customConfirm("Regenerate this post with AI? This will create a new caption and image.", async () => {
        try {
            const formData = new FormData();
            formData.append('id', id);
            
            if (window.showToast) showToast("Regenerating... Please wait.", "success");
            
            const response = await fetch('../api/regenerate_scheduled_post.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                showToast("Post regenerated successfully!", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.error || "Failed to regenerate.", "error");
            }
        } catch (e) {
            showToast("Network Error.", "error");
        }
    });
}

function editScheduledPost(post) {
    document.getElementById('edit-post-id').value = post.id;
    document.getElementById('edit-post-caption').value = post.caption;
    
    let firstImg = post.imageUrl ? post.imageUrl : (post.pImg ? post.pImg.split(',')[0] : 'https://placehold.co/600');
    document.getElementById('edit-post-img-preview').src = firstImg.startsWith('http') ? firstImg : '..' + firstImg;
    document.getElementById('edit-post-image').value = '';
    
    document.getElementById('edit-post-modal').classList.remove('hidden');
    document.getElementById('edit-post-modal').classList.add('flex');
    lucide.createIcons();
}

function closeEditPostModal() {
    document.getElementById('edit-post-modal').classList.add('hidden');
    document.getElementById('edit-post-modal').classList.remove('flex');
}

function previewEditPostImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { document.getElementById('edit-post-img-preview').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

async function saveEditedPost(e) {
    e.preventDefault();
    const btn = document.getElementById('edit-post-submit-btn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...';
    
    try {
        const formData = new FormData(document.getElementById('edit-post-form'));
        const res = await fetch('../api/edit_scheduled_post.php', { method: 'POST', body: formData });
        const result = await res.json();
        
        if (result.success) { showToast("Post updated successfully!", "success"); setTimeout(() => location.reload(), 1000); } 
        else { showToast(result.error || "Failed to update.", "error"); btn.disabled = false; btn.innerHTML = originalHtml; }
    } catch(err) { showToast("Network Error.", "error"); btn.disabled = false; btn.innerHTML = originalHtml; }
}

async function publishNow(id) {
    customConfirm("Are you sure you want to publish this post to Facebook right now?", async () => {
        try {
            const formData = new FormData();
            formData.append('id', id);
            
            if (window.showToast) showToast("Publishing... Please wait.", "success");
            
            const response = await fetch('../api/publish_single_post.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                showToast("Post published successfully!", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.error || "Failed to publish.", "error");
            }
        } catch (e) {
            showToast("Network Error.", "error");
        }
    });
}

lucide.createIcons();
document.addEventListener('DOMContentLoaded', toggleAiProviderFields);
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>