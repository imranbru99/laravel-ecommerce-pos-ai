<?php
session_start();
require_once __DIR__ . '/db.php';

try {
    $setStmt = $pdo->query("SELECT g.*, n.* FROM SettingGeneral g LEFT JOIN SettingNotification n ON g.id = n.id LIMIT 1");
    $settings = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $settings = [];
}
$isOtpEnabled = (!isset($settings['otpSystemEnabled']) || $settings['otpSystemEnabled']);
$logoUrl = !empty($settings['logoUrl']) ? ltrim($settings['logoUrl'], '/') : null;
$storeName = $settings['storeName'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!$isOtpEnabled) {
        echo json_encode(['success' => false, 'error' => 'Password reset is currently disabled by the administrator.']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'request_otp') {
        $contact = trim($_POST['contact'] ?? '');
        $deliveryMethod = $_POST['delivery_method'] ?? 'email';
        $stmt = $pdo->prepare("SELECT id, name, email, phone FROM User WHERE (email = ? OR phone = ?) AND role = 'customer'");
        $stmt->execute([$contact, $contact]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check delivery capabilities
            if ($deliveryMethod === 'sms') {
                if (empty($user['phone'])) {
                    echo json_encode(['success' => false, 'error' => 'No phone number registered with this account to send SMS.']);
                    exit;
                }
                if (empty($settings['smsEnabled']) || empty($settings['smsApiUrl'])) {
                    echo json_encode(['success' => false, 'error' => 'SMS service is currently unavailable. Please use Email.']);
                    exit;
                }
            } else {
                if (empty($user['email'])) {
                    echo json_encode(['success' => false, 'error' => 'No email registered with this account to send email OTP.']);
                    exit;
                }
            }

            $otp = mt_rand(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $pdo->prepare("UPDATE User SET otpCode = ?, otpExpiresAt = ? WHERE id = ?")->execute([$otp, $expires, $user['id']]);
            
            if ($deliveryMethod === 'email') {
                // Send OTP via Email
                $template = $settings['otpEmailTemplate'] ?? "Your OTP is: {{otp_code}}";
                $htmlContent = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$user['name'], $otp, $storeName, $storeName], $template);
                require_once __DIR__ . '/api/mailer_helper.php';
                sendDynamicEmail($pdo, $user['email'], "Password Reset OTP - " . $storeName, $htmlContent);
                echo json_encode(['success' => true, 'userId' => $user['id'], 'msg' => 'OTP sent to your email successfully!']);
            } else if ($deliveryMethod === 'sms') {
                // Send OTP via SMS API
                $template = $settings['otpSmsTemplate'] ?? "Your OTP is: {{otp_code}}";
                $message = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$user['name'], $otp, $storeName, $storeName], $template);
                
                $apiUrl = $settings['smsApiUrl'];
                $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($user['phone']), $apiUrl);
                $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                
                @file_get_contents($apiUrl); // Execute SMS API GET Request
                
                echo json_encode(['success' => true, 'userId' => $user['id'], 'msg' => 'OTP sent to your phone via SMS successfully!']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'No account found matching this email or phone number. Check and try again.']);
        }
        exit;
    }
    
    if ($action === 'verify_otp') {
        $userId = $_POST['userId'] ?? '';
        $otp = $_POST['otp'] ?? '';
        $stmt = $pdo->prepare("SELECT id FROM User WHERE id = ? AND otpCode = ? AND otpExpiresAt > NOW()");
        $stmt->execute([$userId, $otp]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP.']);
        }
        exit;
    }
    
    if ($action === 'reset_password') {
        $userId = $_POST['userId'] ?? '';
        $otp = $_POST['otp'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT id FROM User WHERE id = ? AND otpCode = ? AND otpExpiresAt > NOW()");
        $stmt->execute([$userId, $otp]);
        if ($stmt->fetch()) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE User SET password = ?, otpCode = NULL, otpExpiresAt = NULL WHERE id = ?")->execute([$hashed, $userId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Session expired. Please start over.']);
        }
        exit;
    }
}

include 'Header.php';
?>

<div class="min-h-screen pt-28 md:pt-32 pb-28 md:pb-24 bg-slate-50 font-sans flex flex-col justify-center relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-80 bg-gradient-to-b from-[#003e86]/10 to-transparent pointer-events-none"></div>
    
    <div class="max-w-[450px] w-full mx-auto px-6 relative z-10">
        
        <div class="text-center mb-8">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($storeName); ?>" class="h-16 w-auto mx-auto mb-4 object-contain">
            <?php else: ?>
                <div class="w-16 h-16 bg-[#003e86] text-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl shadow-blue-900/20 transform rotate-12 transition-transform hover:rotate-0 duration-300 cursor-pointer">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="-rotate-12 transition-transform duration-300"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
            <?php endif; ?>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Reset Password</h1>
        </div>

        <?php if (!$isOtpEnabled): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-8 rounded-3xl shadow-sm text-center">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mx-auto mb-3"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                <h2 class="text-lg font-bold mb-2">Service Unavailable</h2>
                <p class="text-sm font-medium">Password reset via OTP is currently disabled by the administrator. Please contact support.</p>
                <a href="login" class="mt-6 inline-block bg-rose-600 text-white font-bold px-6 py-2.5 rounded-xl hover:bg-rose-700 transition-colors">Return to Login</a>
            </div>
        <?php else: ?>
            <!-- Multi-step Container -->
            <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl shadow-slate-200/40 border border-slate-100 relative overflow-hidden min-h-[350px]">
                
                <!-- Step 1: Request OTP -->
                <form id="step-1" class="absolute inset-0 p-8 sm:p-10 w-full h-full transition-all duration-500 transform translate-x-0" onsubmit="handleRequestOTP(event)">
                    <div class="text-center mb-8">
                        <h2 class="text-xl font-black text-slate-800 mb-2">Forgot your password?</h2>
                        <p class="text-sm text-slate-500 font-medium">Enter your registered email or phone number to receive a 6-digit OTP.</p>
                    </div>
                    
                    <!-- Inline Error Message Box -->
                    <div id="request-error" class="hidden mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold rounded-2xl items-center gap-3 animate-in fade-in slide-in-from-top-2">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> 
                        <span id="request-error-text"></span>
                    </div>

                    <div class="space-y-1.5 mb-5">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Email or Phone</label>
                        <input type="text" id="contact-input" required placeholder="you@example.com or 0170..." class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                    </div>

                    <?php if (!empty($settings['smsEnabled'])): ?>
                    <div class="mb-8 space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Receive OTP via</label>
                        <div class="flex gap-4">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="delivery_method" value="email" class="peer sr-only" checked>
                                <div class="p-3 flex items-center justify-center gap-2 border-2 border-slate-200 rounded-xl peer-checked:border-[#003e86] peer-checked:bg-blue-50 peer-checked:text-[#003e86] font-bold text-sm text-slate-500 transition-all">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Email
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="delivery_method" value="sms" class="peer sr-only">
                                <div class="p-3 flex items-center justify-center gap-2 border-2 border-slate-200 rounded-xl peer-checked:border-[#003e86] peer-checked:bg-blue-50 peer-checked:text-[#003e86] font-bold text-sm text-slate-500 transition-all">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg> SMS
                                </div>
                            </label>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="delivery_method" value="email">
                    <?php endif; ?>

                    <button type="submit" id="btn-1" class="w-full py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                        Send OTP <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    </button>
                    <div class="mt-6 text-center"><a href="login" class="text-sm font-bold text-slate-500 hover:text-[#003e86]">Back to Login</a></div>
                </form>

                <!-- Step 2: Verify OTP -->
                <form id="step-2" class="absolute inset-0 p-8 sm:p-10 w-full h-full transition-all duration-500 transform translate-x-full" onsubmit="handleVerifyOTP(event)">
                    <div class="text-center mb-8">
                        <h2 class="text-xl font-black text-slate-800 mb-2">Enter Verification Code</h2>
                        <p class="text-sm text-slate-500 font-medium">We've sent a 6-digit code. Please enter it below.</p>
                    </div>
                    <div class="space-y-1.5 mb-8">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 text-center block">6-Digit OTP</label>
                        <input type="text" id="otp-input" required placeholder="------" maxlength="6" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl text-2xl tracking-[1em] text-center font-black focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                        <div class="flex justify-between items-center mt-2 px-1 max-w-[250px] mx-auto">
                            <span id="forgot-timer" class="text-xs font-bold text-slate-500">02:30</span>
                            <button type="button" id="btn-resend-forgot-otp" onclick="resendForgotOtp()" class="text-xs font-bold text-[#003e86] hidden hover:underline">Resend Code</button>
                        </div>
                    </div>
                    <button type="submit" id="btn-2" class="w-full py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                        Verify Code
                    </button>
                </form>

                <!-- Step 3: Reset Password -->
                <form id="step-3" class="absolute inset-0 p-8 sm:p-10 w-full h-full transition-all duration-500 transform translate-x-full" onsubmit="handleResetPassword(event)">
                    <div class="text-center mb-8">
                        <h2 class="text-xl font-black text-slate-800 mb-2">Create New Password</h2>
                        <p class="text-sm text-slate-500 font-medium">Your new password must be different from previous used passwords.</p>
                    </div>
                    <div class="space-y-5 mb-8">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">New Password</label>
                            <input type="password" id="new-password" required placeholder="••••••••" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                        </div>
                    </div>
                    <button type="submit" id="btn-3" class="w-full py-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-emerald-500/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                        Reset Password <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    </button>
                </form>

            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let currentUserId = null;
let currentOtp = null;

function slideTo(stepId) {
    document.getElementById('step-1').style.transform = 'translateX(-100%)';
    document.getElementById('step-2').style.transform = 'translateX(100%)';
    document.getElementById('step-3').style.transform = 'translateX(100%)';
    setTimeout(() => { document.getElementById(stepId).style.transform = 'translateX(0)'; }, 50);
}

let forgotTimerInterval;
function startForgotTimer(duration) {
    clearInterval(forgotTimerInterval);
    const timerEl = document.getElementById('forgot-timer');
    const resendBtn = document.getElementById('btn-resend-forgot-otp');
    let timer = duration;
    
    timerEl.classList.remove('hidden');
    resendBtn.classList.add('hidden');
    
    forgotTimerInterval = setInterval(function () {
        let minutes = parseInt(timer / 60, 10);
        let seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        timerEl.textContent = minutes + ":" + seconds;

        if (--timer < 0) {
            clearInterval(forgotTimerInterval);
            timerEl.classList.add('hidden');
            resendBtn.classList.remove('hidden');
        }
    }, 1000);
}

async function handleRequestOTP(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-1'); btn.disabled = true; btn.innerText = "Sending...";
    
    const errorBox = document.getElementById('request-error');
    errorBox.classList.add('hidden');
    errorBox.classList.remove('flex');

    const formData = new FormData();
    formData.append('action', 'request_otp'); formData.append('contact', document.getElementById('contact-input').value);
    
    const deliveryMethodEl = document.querySelector('input[name="delivery_method"]:checked') || document.querySelector('input[name="delivery_method"][type="hidden"]');
    if (deliveryMethodEl) formData.append('delivery_method', deliveryMethodEl.value);

    const res = await fetch('forgot-password.php', { method: 'POST', body: formData });
    const result = await res.json();
    btn.disabled = false; btn.innerHTML = 'Send OTP <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>';
    if (result.success) { 
        currentUserId = result.userId; showToast(result.msg, "success"); slideTo('step-2'); startForgotTimer(150);
    } else { 
        errorBox.classList.remove('hidden');
        errorBox.classList.add('flex');
        document.getElementById('request-error-text').innerText = result.error;
    }
}

async function resendForgotOtp() {
    const btn = document.getElementById('btn-resend-forgot-otp');
    btn.innerText = 'Sending...';
    btn.disabled = true;
    const formData = new FormData();
    formData.append('action', 'request_otp'); 
    formData.append('contact', document.getElementById('contact-input').value);
    const deliveryMethodEl = document.querySelector('input[name="delivery_method"]:checked') || document.querySelector('input[name="delivery_method"][type="hidden"]');
    if (deliveryMethodEl) formData.append('delivery_method', deliveryMethodEl.value);
    try {
        const res = await fetch('forgot-password.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            currentUserId = result.userId; 
            if(window.showToast) showToast("OTP Resent Successfully", "success");
            startForgotTimer(150);
        } else {
            if(window.showToast) showToast(result.error, "error");
        }
    } catch (e) {
        if(window.showToast) showToast("Network error. Please try again.", "error");
    } finally {
        btn.innerText = 'Resend Code';
        btn.disabled = false;
    }
}

async function handleVerifyOTP(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-2'); btn.disabled = true; btn.innerText = "Verifying...";
    const otp = document.getElementById('otp-input').value;
    const formData = new FormData(); formData.append('action', 'verify_otp'); formData.append('userId', currentUserId); formData.append('otp', otp);
    const res = await fetch('forgot-password.php', { method: 'POST', body: formData });
    const result = await res.json();
    btn.disabled = false; btn.innerText = 'Verify Code';
    if (result.success) { currentOtp = otp; showToast("OTP Verified successfully!", "success"); slideTo('step-3'); } else { showToast(result.error, "error"); }
}

async function handleResetPassword(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-3'); btn.disabled = true; btn.innerText = "Updating...";
    const formData = new FormData(); formData.append('action', 'reset_password'); formData.append('userId', currentUserId); formData.append('otp', currentOtp); formData.append('password', document.getElementById('new-password').value);
    const res = await fetch('forgot-password.php', { method: 'POST', body: formData });
    const result = await res.json();
    if (result.success) { showToast("Password Reset Successful! Redirecting...", "success"); setTimeout(() => window.location.href = 'login', 2000); } else { btn.disabled = false; btn.innerText = 'Reset Password'; showToast(result.error, "error"); }
}
</script>

<?php include 'Footer.php'; ?>