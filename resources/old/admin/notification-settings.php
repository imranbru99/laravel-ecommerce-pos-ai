<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query("SELECT n.*, g.otpSystemEnabled, g.otpLoginEnabled, g.otpRegistrationEnabled FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$defaultOrderHtml = '<div style="font-family: \'Segoe UI\', Arial, sans-serif; color: #333333; max-width: 600px; margin: 0 auto; padding: 40px 30px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #2563eb; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">{{store_name}}</h1>
    </div>
    <div style="text-align: center; margin-bottom: 30px;">
        <span style="font-size: 48px;">🎉</span>
        <h2 style="color: #0f172a; margin-top: 10px; margin-bottom: 5px; font-size: 24px; font-weight: 700;">Order Confirmed!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Order ID: <strong style="color: #2563eb;">#{{order_id}}</strong></p>
    </div>
    <p style="color: #475569; font-size: 16px; margin-bottom: 10px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6; margin-top: 0;">
        Thank you for shopping with us! We have successfully received your order and our team is already working hard to get it ready for shipment.
    </p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">
        You will receive another update via email or SMS with a tracking link once your package is on its way.
    </p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <h3 style="color: #0f172a; font-size: 16px; margin-top: 0; margin-bottom: 15px;">Order Summary</h3>
    <div style="background-color: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 15px; color: #475569;">
            {{order_items_html}}
            <tr style="border-top: 1px solid #e2e8f0;">
                <td style="padding-top: 15px; font-weight: 700; color: #0f172a;">Total Paid</td>
                <td style="text-align: right; padding-top: 15px; font-weight: 700; color: #2563eb; font-size: 18px;">{{total_amount}}</td>
            </tr>
        </table>
    </div>
    <h3 style="color: #0f172a; font-size: 16px; margin-top: 0; margin-bottom: 10px;">Shipping Address</h3>
    <p style="color: #64748b; font-size: 14px; line-height: 1.5; margin-top: 0; background-color: #f8fafc; border-radius: 12px; padding: 15px;">
        <strong>{{customer_name}}</strong><br>
        {{shipping_address}}<br>
        Phone: {{customer_phone}}
    </p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <div style="text-align: center;">
        <p style="color: #475569; font-size: 15px; margin-bottom: 5px;">Need help with your order?</p>
        <p style="margin-top: 0; margin-bottom: 25px;"><a href="mailto:{{support_email}}" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Contact Support Team →</a></p>
        <p style="color: #94a3b8; font-size: 13px; margin: 0;">Best regards,</p>
        <p style="color: #0f172a; font-size: 15px; font-weight: 700; margin-top: 5px; margin-bottom: 0;">{{store_name}} Team</p>
    </div>
</div>';

// Quick auto-update to apply the new advanced HTML to the live database
try { $pdo->prepare("UPDATE SettingNotification SET orderPlacedTemplate = ? WHERE id = 1 AND (orderPlacedTemplate LIKE '%font-family: Arial%' OR orderPlacedTemplate IS NULL OR orderPlacedTemplate = '')")->execute([$defaultOrderHtml]); } catch (Exception $e) {}
$stmt = $pdo->query("SELECT n.*, g.otpSystemEnabled, g.otpLoginEnabled, g.otpRegistrationEnabled FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If templates are missing, populate them automatically
if (empty($settings['orderProcessingTemplate'])) {
    $defaultProcessingHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">⚙️</span></div>
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px; text-align: center;">Order is Processing!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Great news! We are currently processing your order <strong style="color:#f59e0b;">#{{order_id}}</strong>. Our team is carefully packing your items and preparing them for shipment.</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">We will notify you again once your package is out for delivery.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';

    $defaultShippedHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">🚚</span></div>
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px; text-align: center;">Your Order has Shipped!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Great news! Your order <strong style="color:#2563eb;">#{{order_id}}</strong> has been handed over to our delivery partner and is on its way to you.</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">You can track your package using the link below:</p>
    <div style="text-align: center; margin: 30px 0;"><a href="{{tracking_link}}" style="background: #0f172a; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;">Track Order</a></div>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';

    $defaultDeliveredHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">📦</span></div>
    <h2 style="color: #10b981; margin-top:0; font-size: 24px; text-align: center;">Order Delivered Successfully!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Your order <strong style="color:#10b981;">#{{order_id}}</strong> has been successfully delivered. We hope you love your purchase!</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">If you have any issues or feedback, please feel free to reach out to our support team.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';

    $defaultCancelledHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">❌</span></div>
    <h2 style="color: #e11d48; margin-top:0; font-size: 24px; text-align: center;">Order Cancelled</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">We regret to inform you that your order <strong style="color:#e11d48;">#{{order_id}}</strong> has been cancelled.</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">If you have already paid for this order, the refund process will be initiated shortly according to our standard policy.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';

    $defaultWelcomeHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">👋</span></div>
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px; text-align: center;">Welcome to {{store_name}}!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">We are thrilled to have you here! Your account has been successfully created. You can now track your orders, save your favorite products, and enjoy a seamless shopping experience.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';

    $defaultOtpEmail = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px;">Security Verification 🔒</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Your One-Time Password (OTP) for verification is:</p>
    <div style="font-size: 32px; font-weight: 900; background: #f8fafc; padding: 20px; text-align: center; letter-spacing: 8px; border-radius: 12px; margin: 30px 0; color: #0f172a; border: 1px dashed #cbd5e1;">
        {{otp_code}}
    </div>
    <p style="font-size: 13px; color: #94a3b8; line-height: 1.5;">This code is valid for the next 10 minutes. For your security, please do not share this code with anyone.</p>
</div>';

    $defaultOrderSms = 'Hi {{customer_name}}, your order #{{order_id}} has been confirmed successfully.';
    $defaultProcessingSms = 'Your order #{{order_id}} is now processing. We will notify you once shipped.';
    $defaultShippedSms = 'Your order #{{order_id}} has been shipped! Track it here: {{tracking_link}}';
    $defaultDeliveredSms = 'Your order #{{order_id}} has been successfully delivered. Thank you for shopping with us!';
    $defaultCancelledSms = 'Your order #{{order_id}} has been cancelled. Contact support for more info.';
    $defaultWelcomeSms = 'Welcome to {{store_name}}, {{customer_name}}! We are excited to have you on board.';
    $defaultOtpSms = 'Your {{store_name}} verification code is: {{otp_code}}. Do not share this code with anyone.';

    $pdo->prepare("UPDATE `SettingNotification` SET 
        orderPlacedTemplate = ?, orderProcessingTemplate = ?, orderShippedTemplate = ?, orderDeliveredTemplate = ?, orderCancelledTemplate = ?, welcomeEmailTemplate = ?, otpEmailTemplate = ?, 
        orderPlacedSmsTemplate = ?, orderProcessingSmsTemplate = ?, orderShippedSmsTemplate = ?, orderDeliveredSmsTemplate = ?, orderCancelledSmsTemplate = ?, welcomeSmsTemplate = ?, otpSmsTemplate = ? 
        WHERE id = 1")->execute([
        $defaultOrderHtml, $defaultProcessingHtml, $defaultShippedHtml, $defaultDeliveredHtml, $defaultCancelledHtml, $defaultWelcomeHtml, $defaultOtpEmail, 
        $defaultOrderSms, $defaultProcessingSms, $defaultShippedSms, $defaultDeliveredSms, $defaultCancelledSms, $defaultWelcomeSms, $defaultOtpSms
    ]);
    
    // Re-fetch settings
    $stmt = $pdo->query("SELECT n.*, g.otpSystemEnabled, g.otpLoginEnabled, g.otpRegistrationEnabled FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

ob_start();
?>

<div class="max-w-6xl mx-auto pb-12 font-sans" id="notification-app">
    <form id="settings-form" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="mail" class="w-6 h-6 text-blue-600"></i> Notifications Configuration
                </h2>
                <p class="text-sm text-slate-500 mt-1">Configure your automated Email and SMS settings.</p>
            </div>
            <button type="button" onclick="saveSettings()" id="save-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span id="btn-text">Save Changes</span>
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex border-b border-slate-200 px-6 sm:px-8 pt-2 gap-6 bg-slate-50/50 overflow-x-auto custom-scrollbar">
            <button type="button" onclick="switchTab('email-smtp')" id="tab-btn-email-smtp" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-blue-600 whitespace-nowrap">
                <i data-lucide="server" class="w-4 h-4"></i> SMTP Configuration
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-t-full"></div>
            </button>
            <button type="button" onclick="switchTab('email-tpl')" id="tab-btn-email-tpl" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500 whitespace-nowrap">
                <i data-lucide="layout-template" class="w-4 h-4"></i> Email Templates
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-t-full hidden"></div>
            </button>
            <button type="button" onclick="switchTab('sms')" id="tab-btn-sms" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500 whitespace-nowrap">
                <i data-lucide="message-square" class="w-4 h-4"></i> SMS Configuration
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-t-full hidden"></div>
            </button>
            <button type="button" onclick="switchTab('otp')" id="tab-btn-otp" class="tab-btn pb-3 font-semibold text-sm transition-colors relative flex items-center gap-2 text-slate-500 whitespace-nowrap">
                <i data-lucide="shield-check" class="w-4 h-4"></i> OTP & Auth
                <div class="tab-indicator absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-t-full hidden"></div>
            </button>
        </div>

        <!-- Tab: SMTP Configuration -->
        <div id="tab-content-email-smtp" class="tab-content block p-6 sm:p-8 animate-in fade-in">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                <div class="xl:col-span-8 space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm">
                        <div class="flex items-center justify-between mb-8 pb-6 border-b border-slate-100">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Email Gateway Status</h3>
                                <p class="text-sm text-slate-500 mt-1">Enable or disable all outbound email communications.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="emailEnabled" class="sr-only peer" <?php echo !empty($settings['emailEnabled']) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-widest flex items-center gap-2"><i data-lucide="server" class="w-4 h-4 text-blue-600"></i> SMTP Configuration</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">SMTP Host</label>
                                <input type="text" name="smtpHost" value="<?php echo htmlspecialchars($settings['smtpHost'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="smtp.gmail.com">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">SMTP Port</label>
                                <input type="number" name="smtpPort" value="<?php echo htmlspecialchars($settings['smtpPort'] ?? '465'); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="465 or 587">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">SMTP Username</label>
                                <input type="text" name="smtpUser" value="<?php echo htmlspecialchars($settings['smtpUser'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="your-email@example.com">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">SMTP Password</label>
                                <input type="password" name="smtpPass" value="<?php echo htmlspecialchars($settings['smtpPass'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="********">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-4">
                    <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-6 shadow-sm">
                        <h3 class="font-bold text-indigo-900 flex items-center gap-2 mb-2"><i data-lucide="send" class="w-4 h-4"></i> Test Configuration</h3>
                        <p class="text-xs text-indigo-700 mb-6">Send a test email to verify your SMTP settings are correct before saving.</p>
                        <div class="space-y-3">
                            <input type="email" id="test-email-input" placeholder="Enter test email address..." class="w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm font-medium">
                            <button type="button" onclick="sendTestEmail()" id="test-email-btn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl font-bold shadow-md transition-all flex items-center justify-center gap-2 text-sm">
                                <i data-lucide="mail-check" class="w-4 h-4"></i> Send Test Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Email Templates -->
        <div id="tab-content-email-tpl" class="tab-content hidden p-6 sm:p-8 animate-in fade-in">
            <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm flex flex-col xl:flex-row h-[800px] xl:h-[650px]">
                
                <!-- Sidebar for template selection -->
                <div class="w-full xl:w-64 bg-slate-50 border-b xl:border-b-0 xl:border-r border-slate-200 flex flex-col shrink-0">
                    <div class="p-4 border-b border-slate-200 bg-white">
                        <h3 class="font-bold text-slate-800 text-sm">Select Template</h3>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 space-y-1">
                        <button type="button" onclick="switchEmailTemplate('orderPlaced', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all bg-blue-100 text-blue-700">Order Placed</button>
                        <button type="button" onclick="switchEmailTemplate('orderProcessing', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-600 hover:bg-slate-100">Order Processing</button>
                        <button type="button" onclick="switchEmailTemplate('orderShipped', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-600 hover:bg-slate-100">Order Shipped</button>
                        <button type="button" onclick="switchEmailTemplate('orderDelivered', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-600 hover:bg-slate-100">Order Delivered</button>
                        <button type="button" onclick="switchEmailTemplate('orderCancelled', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-600 hover:bg-slate-100">Order Cancelled</button>
                        <button type="button" onclick="switchEmailTemplate('welcomeEmail', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-600 hover:bg-slate-100">Welcome / Sign Up</button>
                        <button type="button" onclick="switchEmailTemplate('otpVerification', this)" class="tpl-btn w-full text-left px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-600 hover:bg-slate-100">OTP Verification</button>
                    </div>
                </div>
                
                <!-- Code Editor -->
                <div class="flex-1 flex flex-col min-w-0 bg-slate-900 border-b xl:border-b-0 xl:border-r border-slate-800 h-[400px] xl:h-auto shrink-0 xl:w-1/2">
                    <div class="p-3 border-b border-slate-800 flex justify-between items-center bg-slate-950">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2"><i data-lucide="code" class="w-3.5 h-3.5"></i> HTML Source</span>
                        <button type="button" class="text-[10px] bg-slate-800 text-slate-300 px-3 py-1.5 rounded-lg hover:bg-slate-700 hover:text-white transition-colors" title="Available Tags" onclick="document.getElementById('tags-modal').classList.remove('hidden'); document.getElementById('tags-modal').classList.add('flex');">
                            View Tags
                        </button>
                    </div>
                    <div class="flex-1 relative">
                        <?php 
                        $emailEditors = [
                            'orderPlaced' => 'orderPlacedTemplate', 
                            'orderProcessing' => 'orderProcessingTemplate', 
                            'orderShipped' => 'orderShippedTemplate', 
                            'orderDelivered' => 'orderDeliveredTemplate', 
                            'orderCancelled' => 'orderCancelledTemplate', 
                            'welcomeEmail' => 'welcomeEmailTemplate', 
                            'otpVerification' => 'otpEmailTemplate'
                        ];
                        foreach ($emailEditors as $id => $field): 
                        ?>
                            <textarea name="<?php echo $field; ?>" id="editor-<?php echo $id; ?>" oninput="updatePreview(this.value)" class="template-editor absolute inset-0 w-full h-full bg-slate-900 text-emerald-400 font-mono text-[12px] p-4 border-none focus:ring-0 outline-none resize-none custom-scrollbar leading-relaxed hidden"><?php echo htmlspecialchars($settings[$field] ?? ''); ?></textarea>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live Preview Area -->
                <div class="flex-1 bg-slate-100 flex flex-col h-[400px] xl:h-auto shrink-0 xl:w-1/2">
                    <div class="p-3 border-b border-slate-200 bg-white flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                        <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                        <span class="ml-2 text-xs font-semibold text-slate-500">Live Preview</span>
                    </div>
                    <div class="flex-1 overflow-auto custom-scrollbar p-4 flex justify-center">
                        <div class="w-full max-w-[600px] bg-transparent origin-top" id="email-preview-pane"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: SMS Configuration -->
        <div id="tab-content-sms" class="tab-content hidden p-6 sm:p-8 animate-in fade-in">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                <!-- API Setup -->
                <div class="xl:col-span-4 space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-100">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">SMS Gateway</h3>
                                <p class="text-xs text-slate-500 mt-1">Enable SMS functionality.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="smsEnabled" class="sr-only peer" <?php echo !empty($settings['smsEnabled']) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">SMS API URL</label>
                                <textarea name="smsApiUrl" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all text-sm font-mono resize-none" placeholder="https://api.sms.com/send?to=[TO]&msg=[MSG]"><?php echo htmlspecialchars($settings['smsApiUrl'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Sender ID</label>
                                <input type="text" name="smsSenderId" value="<?php echo htmlspecialchars($settings['smsSenderId'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all text-sm" placeholder="MYSTORE">
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 rounded-2xl p-5 border border-blue-100">
                        <h4 class="text-sm font-bold text-blue-900 mb-2 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i> API Parameters</h4>
                        <p class="text-xs text-blue-800 leading-relaxed">Ensure your API URL contains the parameters <code>[TO]</code> or <code>{number}</code> for the recipient's phone number, and <code>[MESSAGE]</code> or <code>{msg}</code> for the text body. The system will automatically replace them.</p>
                    </div>
                </div>

                <!-- SMS Templates -->
                <div class="xl:col-span-8">
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm">
                        <h3 class="text-base font-bold text-slate-800 mb-6 flex items-center gap-2"><i data-lucide="message-square-dashed" class="w-5 h-5 text-blue-600"></i> SMS Templates</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Order Placed</label>
                                <textarea name="orderPlacedSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['orderPlacedSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Order Processing</label>
                                <textarea name="orderProcessingSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['orderProcessingSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Order Shipped</label>
                                <textarea name="orderShippedSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['orderShippedSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Order Delivered</label>
                                <textarea name="orderDeliveredSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['orderDeliveredSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Order Cancelled</label>
                                <textarea name="orderCancelledSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['orderCancelledSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Welcome / Signup</label>
                                <textarea name="welcomeSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['welcomeSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">OTP Verification</label>
                                <textarea name="otpSmsTemplate" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500/20 transition-all resize-none text-sm font-medium text-slate-700"><?php echo htmlspecialchars($settings['otpSmsTemplate'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab: OTP & Auth -->
        <div id="tab-content-otp" class="tab-content hidden p-6 sm:p-8 animate-in fade-in">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- OTP Forgot Password -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 flex flex-col items-center text-center gap-5 hover:shadow-lg transition-all">
                    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shadow-sm">
                        <i data-lucide="key" class="w-8 h-8"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 text-lg mb-2">Forgot Password</h3>
                        <p class="text-sm text-slate-500">Enable OTP verification for customer password resets.</p>
                    </div>
                    <div class="w-full pt-5 mt-auto border-t border-slate-100 flex justify-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="otpSystemEnabled" class="sr-only peer" <?php echo (!isset($settings['otpSystemEnabled']) || $settings['otpSystemEnabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
                
                <!-- OTP Login -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 flex flex-col items-center text-center gap-5 hover:shadow-lg transition-all">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center shadow-sm">
                        <i data-lucide="log-in" class="w-8 h-8"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 text-lg mb-2">OTP Login System</h3>
                        <p class="text-sm text-slate-500">Enable passwordless login via OTP.</p>
                    </div>
                    <div class="w-full pt-5 mt-auto border-t border-slate-100 flex justify-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="otpLoginEnabled" class="sr-only peer" <?php echo (!empty($settings['otpLoginEnabled'])) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                </div>
                
                <!-- OTP Registration -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 flex flex-col items-center text-center gap-5 hover:shadow-lg transition-all">
                    <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center shadow-sm">
                        <i data-lucide="user-plus" class="w-8 h-8"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 text-lg mb-2">OTP Registration</h3>
                        <p class="text-sm text-slate-500">Verify email or phone during sign up.</p>
                    </div>
                    <div class="w-full pt-5 mt-auto border-t border-slate-100 flex justify-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="otpRegistrationEnabled" class="sr-only peer" <?php echo (!empty($settings['otpRegistrationEnabled'])) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
    </form>
</div>

<script>
// Tab Switching Logic
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('text-blue-600');
        btn.classList.add('text-slate-500');
        btn.querySelector('.tab-indicator').classList.add('hidden');
    });
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('block');
    });

    const activeBtn = document.getElementById('tab-btn-' + tab);
    if(activeBtn) {
        activeBtn.classList.add('text-blue-600');
        activeBtn.classList.remove('text-slate-500');
        activeBtn.querySelector('.tab-indicator').classList.remove('hidden');
    }
    
    const activeContent = document.getElementById('tab-content-' + tab);
    if(activeContent) {
        activeContent.classList.remove('hidden');
        activeContent.classList.add('block', 'animate-in', 'fade-in');
    }
}

// Save Settings AJAX
async function saveSettings() {
    const btn = document.getElementById('save-btn');
    const form = document.getElementById('settings-form');
    const formData = new FormData(form);
    
    // Checkboxes value fix 
    formData.set('emailEnabled', form.emailEnabled.checked ? 1 : 0);
    formData.set('smsEnabled', form.smsEnabled.checked ? 1 : 0);
    formData.set('otpSystemEnabled', form.otpSystemEnabled.checked ? 1 : 0);
    formData.set('otpLoginEnabled', form.otpLoginEnabled.checked ? 1 : 0);
    formData.set('otpRegistrationEnabled', form.otpRegistrationEnabled.checked ? 1 : 0);

    // Base64 encode HTML templates to prevent ModSecurity blocks on live server
    const htmlFields = [
        'orderPlacedTemplate', 'orderProcessingTemplate', 'orderShippedTemplate', 
        'orderDeliveredTemplate', 'orderCancelledTemplate', 'welcomeEmailTemplate', 'otpEmailTemplate'
    ];
    htmlFields.forEach(field => {
        if (formData.has(field)) {
            formData.set(field, btoa(unescape(encodeURIComponent(formData.get(field)))));
        }
    });

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-4 h-4"></i> Saving...';
    lucide.createIcons();

    try {
        const response = await fetch('../api/save_settings.php', {
            method: 'POST',
            body: formData
        });
        const text = await response.text();
        try {
            const result = JSON.parse(text);
            if(result.success) {
                showToast("Settings updated successfully!", "success");
            } else {
                showToast(result.error || "Failed to save settings.", "error");
            }
        } catch (e) {
            console.error(text);
            showToast("Server error. Please check console.", "error");
        }
    } catch (err) {
        showToast("An error occurred while saving.", "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Changes';
        lucide.createIcons();
    }
}

// Test Email AJAX
async function sendTestEmail() {
    const email = document.getElementById('test-email-input').value;
    if(!email) {
        showToast("Please enter an email address to test.", "error");
        return;
    }
    const btn = document.getElementById('test-email-btn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Sending...';
    lucide.createIcons();

    const formData = new FormData();
    formData.append('email', email);

    try {
        const response = await fetch('../api/test_email.php', { method: 'POST', body: formData });
        const text = await response.text();
        try {
            const result = JSON.parse(text);
            if(result.success) {
                showToast("Test email sent successfully! Please check your inbox/spam folder.", "success");
            } else {
                showToast(result.error || "Failed to send email.", "error");
            }
        } catch(e) {
            console.error("Server Error:", text);
            showToast("Server returned an invalid response. Check console.", "error");
        }
    } catch (e) {
        showToast("Network error occurred.", "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Test';
        lucide.createIcons();
    }
}

// Initialize Icons
lucide.createIcons();

// Template Editor Logic
function switchEmailTemplate(type, btnEl) {
    document.querySelectorAll('.template-editor').forEach(el => {
        el.classList.add('hidden');
        el.classList.remove('block');
    });
    
    document.querySelectorAll('.tpl-btn').forEach(btn => {
        btn.classList.remove('bg-blue-100', 'text-blue-700');
        btn.classList.add('text-slate-600', 'hover:bg-slate-100');
    });
    
    if(btnEl) {
        btnEl.classList.add('bg-blue-100', 'text-blue-700');
        btnEl.classList.remove('text-slate-600', 'hover:bg-slate-100');
    }

    const activeEditor = document.getElementById('editor-' + type);
    if(activeEditor) {
        activeEditor.classList.remove('hidden');
        activeEditor.classList.add('block');
        updatePreview(activeEditor.value);
    }
}

function updatePreview(html) {
    document.getElementById('email-preview-pane').innerHTML = html;
}

document.addEventListener("DOMContentLoaded", () => { 
    const firstBtn = document.querySelector('.tpl-btn');
    switchEmailTemplate('orderPlaced', firstBtn); 
});
</script>

<!-- Tags Modal -->
<div id="tags-modal" class="fixed inset-0 z-[110] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2"><i data-lucide="tag" class="w-5 h-5 text-blue-600"></i> Available Tags</h2>
            <button type="button" onclick="document.getElementById('tags-modal').classList.add('hidden'); document.getElementById('tags-modal').classList.remove('flex');" class="p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-6">
            <ul class="space-y-2 text-sm font-mono text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <li>{{customer_name}}</li>
                <li>{{order_id}}</li>
                <li>{{tracking_link}}</li>
                <li>{{store_name}}</li>
                <li>{{order_items_html}}</li>
                <li>{{total_amount}}</li>
                <li>{{shipping_address}}</li>
                <li>{{customer_phone}}</li>
                <li>{{support_email}}</li>
            </ul>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-100">
            <button type="button" onclick="document.getElementById('tags-modal').classList.add('hidden'); document.getElementById('tags-modal').classList.remove('flex');" class="w-full py-3 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors">Close</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>