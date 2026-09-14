<?php
session_start();
require_once __DIR__ . '/db.php';

// If already logged in, redirect to account
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: account");
    exit;
}

$loginError = '';
$registerError = '';
$registerOtpError = '';
$registerOtpMsg = '';
$activeTab = 'login';

try {
    $setStmt = $pdo->query("SELECT g.*, n.* FROM SettingGeneral g LEFT JOIN SettingNotification n ON g.id = n.id LIMIT 1");
    $settings = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $settings = [];
}
$isOtpRegEnabled = (!empty($settings['otpRegistrationEnabled']));
$storeName = $settings['storeName'] ?? '';
$logoUrl = !empty($settings['logoUrl']) ? ltrim($settings['logoUrl'], '/') : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $activeTab = 'login';
        $contact = trim($_POST['contact'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($contact) || empty($password)) {
            $loginError = 'Please enter your email/phone and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM User WHERE (email = ? OR phone = ?) AND role = 'customer'");
            $stmt->execute([$contact, $contact]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                try { $pdo->exec("ALTER TABLE `User` ADD COLUMN `status` ENUM('active', 'blocked') DEFAULT 'active'"); } catch (Exception $e) {}
                if (($user['status'] ?? 'active') === 'blocked') {
                    $loginError = 'Your account has been blocked. Please contact support.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    header("Location: account");
                    exit;
                }
            } else {
                $loginError = 'Invalid email address or password.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $activeTab = 'register';
        $name = trim($_POST['name'] ?? '');
        $emailRaw = trim($_POST['email'] ?? '');
        $email = $emailRaw === '' ? null : $emailRaw;
        $password = trim($_POST['password'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name) || empty($phone) || empty($password)) {
            $registerError = 'Name, phone, and password are required.';
        } else {
            // Safe patches to prevent 500 errors on old live databases
            try { $pdo->exec("ALTER TABLE `User` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE `User` MODIFY `email` VARCHAR(100) NULL"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE `User` ADD UNIQUE (`phone`)"); } catch (Exception $e) {}

            try {
                $checkQuery = "SELECT id FROM User WHERE phone = ?";
                $checkParams = [$phone];
                if ($email) {
                    $checkQuery .= " OR email = ?";
                    $checkParams[] = $email;
                }
                
                $stmt = $pdo->prepare($checkQuery);
                $stmt->execute($checkParams);
                $isDuplicate = $stmt->fetchColumn();
            } catch (Exception $e) {
                $isDuplicate = false;
            }

            if ($isDuplicate) {
                $registerError = 'This phone number or email is already registered.';
            } else {
                if ($isOtpRegEnabled) {
                    $deliveryMethod = $_POST['delivery_method'] ?? 'email';
                    
                    if ($deliveryMethod === 'sms' && (empty($settings['smsEnabled']) || empty($settings['smsApiUrl']))) {
                        $registerError = 'SMS service is currently unavailable. Please use Email.';
                    } else if ($deliveryMethod === 'email' && empty($email)) {
                        $registerError = 'Email address is required to receive OTP via Email.';
                    } else {
                        $otp = mt_rand(100000, 999999);
                        $_SESSION['pending_reg'] = [
                            'name' => $name,
                            'email' => $email,
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'phone' => $phone,
                            'otp' => $otp,
                            'deliveryMethod' => $deliveryMethod,
                            'expires' => time() + 600
                        ];
                        $activeTab = 'register-otp';
                        
                        if ($deliveryMethod === 'email') {
                            $template = $settings['otpEmailTemplate'] ?? "Your OTP is: {{otp_code}}";
                            $htmlContent = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$name, $otp, $storeName, $storeName], $template);
                            require_once __DIR__ . '/api/mailer_helper.php';
                            sendDynamicEmail($pdo, $email, "Verification OTP - " . $storeName, $htmlContent);
                            $registerOtpMsg = "OTP sent to your email successfully!";
                        } else if ($deliveryMethod === 'sms') {
                            $template = $settings['otpSmsTemplate'] ?? "Your OTP is: {{otp_code}}";
                            $message = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$name, $otp, $storeName, $storeName], $template);
                            
                            $apiUrl = $settings['smsApiUrl'];
                            $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($phone), $apiUrl);
                            $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                            
                            @file_get_contents($apiUrl);
                            
                            $registerOtpMsg = "OTP sent to your phone via SMS successfully!";
                        }
                    }
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO User (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')");
                        $stmt->execute([$name, $email, $hashedPassword, $phone]);
                        $newUserId = $pdo->lastInsertId();
                        
                        $notifStmt = $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, ?, 'Thank you for registering. Your account is ready.', 'info')");
                        $notifStmt->execute([$newUserId, "Welcome to $storeName!"]);

                        if (!empty($email) && !empty($settings['emailEnabled'])) {
                            $template = $settings['welcomeEmailTemplate'] ?? "";
                            if ($template) {
                                $htmlContent = str_replace(['{{customer_name}}', '{{store_name}}', 'Idea Mart'], [$name, $storeName, $storeName], $template);
                                require_once __DIR__ . '/api/mailer_helper.php';
                                sendDynamicEmail($pdo, $email, "Welcome to " . $storeName . "!", $htmlContent);
                            }
                        }
                        if (!empty($phone) && !empty($settings['smsEnabled']) && !empty($settings['smsApiUrl'])) {
                            $template = $settings['welcomeSmsTemplate'] ?? "";
                            if ($template) {
                                $message = str_replace(['{{customer_name}}', '{{store_name}}', 'Idea Mart'], [$name, $storeName, $storeName], $template);
                                $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($phone), $settings['smsApiUrl']);
                                $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                                @file_get_contents($apiUrl);
                            }
                        }

                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['user_name'] = $name;
                        header("Location: account");
                        exit;
                    } catch (Exception $e) {
                        $registerError = 'Registration failed. Please try again.';
                    }
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'resend_reg_otp') {
        header('Content-Type: application/json');
        if (isset($_SESSION['pending_reg'])) {
            $regData = $_SESSION['pending_reg'];
            $otp = mt_rand(100000, 999999);
            $_SESSION['pending_reg']['otp'] = $otp;
            $_SESSION['pending_reg']['expires'] = time() + 600;
            
            $deliveryMethod = $regData['deliveryMethod'] ?? 'email';
            
            if ($deliveryMethod === 'email') {
                $template = $settings['otpEmailTemplate'] ?? "Your OTP is: {{otp_code}}";
                $htmlContent = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$regData['name'], $otp, $storeName, $storeName], $template);
                require_once __DIR__ . '/api/mailer_helper.php';
                sendDynamicEmail($pdo, $regData['email'], "Verification OTP - " . $storeName, $htmlContent);
                echo json_encode(['success' => true]);
            } else if ($deliveryMethod === 'sms') {
                $template = $settings['otpSmsTemplate'] ?? "Your OTP is: {{otp_code}}";
                $message = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$regData['name'], $otp, $storeName, $storeName], $template);
                
                $apiUrl = $settings['smsApiUrl'];
                $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($regData['phone']), $apiUrl);
                $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                
                @file_get_contents($apiUrl);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid delivery method']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Session expired. Please start over.']);
        }
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_reg_otp') {
        $activeTab = 'register-otp';
        $otp = trim($_POST['otp'] ?? '');
        
        if (isset($_SESSION['pending_reg']) && $_SESSION['pending_reg']['expires'] > time()) {
            if ($otp == $_SESSION['pending_reg']['otp']) {
                $regData = $_SESSION['pending_reg'];
                try {
                    $stmt = $pdo->prepare("INSERT INTO User (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')");
                    $stmt->execute([$regData['name'], $regData['email'], $regData['password'], $regData['phone']]);
                    $newUserId = $pdo->lastInsertId();
                    
                    $notifStmt = $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, ?, 'Thank you for registering. Your account is ready.', 'info')");
                    $notifStmt->execute([$newUserId, "Welcome to $storeName!"]);

                    if (!empty($regData['email']) && !empty($settings['emailEnabled'])) {
                        $template = $settings['welcomeEmailTemplate'] ?? "";
                        if ($template) {
                            $htmlContent = str_replace(['{{customer_name}}', '{{store_name}}', 'Idea Mart'], [$regData['name'], $storeName, $storeName], $template);
                            require_once __DIR__ . '/api/mailer_helper.php';
                            sendDynamicEmail($pdo, $regData['email'], "Welcome to " . $storeName . "!", $htmlContent);
                        }
                    }
                    if (!empty($regData['phone']) && !empty($settings['smsEnabled']) && !empty($settings['smsApiUrl'])) {
                        $template = $settings['welcomeSmsTemplate'] ?? "";
                        if ($template) {
                            $message = str_replace(['{{customer_name}}', '{{store_name}}', 'Idea Mart'], [$regData['name'], $storeName, $storeName], $template);
                            $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($regData['phone']), $settings['smsApiUrl']);
                            $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                            @file_get_contents($apiUrl);
                        }
                    }

                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $regData['name'];
                    unset($_SESSION['pending_reg']);
                    header("Location: account");
                    exit;
                } catch (Exception $e) {
                    $registerError = 'Registration failed. Please try again.';
                }
            } else {
                $registerOtpError = "Invalid OTP.";
            }
        } else {
            $registerError = "Session expired. Please register again.";
            $activeTab = 'register';
        }
    }
}

include 'Header.php';
?>

<div class="min-h-screen pt-28 md:pt-32 pb-28 md:pb-24 bg-slate-50 font-sans flex flex-col justify-center relative overflow-hidden">
    <!-- Background decorative shape -->
    <div class="absolute top-0 left-0 w-full h-80 bg-gradient-to-b from-[#003e86]/10 to-transparent pointer-events-none"></div>
    
    <div class="max-w-[450px] w-full mx-auto px-6 relative z-10">
        
        <div class="text-center mb-8">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($storeName); ?>" class="h-16 w-auto mx-auto mb-4 object-contain">
            <?php else: ?>
                <div class="w-16 h-16 bg-[#003e86] text-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl shadow-blue-900/20 transform rotate-12 transition-transform hover:rotate-0 duration-300 cursor-pointer">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="-rotate-12 transition-transform duration-300"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            <?php endif; ?>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">My Account</h1>
        </div>

        <!-- Custom Tabs Switcher -->
        <div class="bg-white/60 backdrop-blur-md p-1.5 rounded-2xl flex mb-6 relative shadow-sm border border-slate-200">
            <div id="toggle-slider" class="absolute left-1.5 top-1.5 bottom-1.5 w-[calc(50%-6px)] bg-white rounded-xl shadow-md border border-slate-100 transition-transform duration-500 ease-in-out"></div>
            <button type="button" onclick="switchForm('login')" id="btn-login" class="flex-1 py-3 text-sm font-black z-10 text-[#003e86] transition-colors relative">Sign In</button>
            <button type="button" onclick="switchForm('register')" id="btn-register" class="flex-1 py-3 text-sm font-bold z-10 text-slate-500 hover:text-slate-800 transition-colors relative">Create Account</button>
        </div>

        <!-- Login Card -->
        <div id="login-card" class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl shadow-slate-200/40 border border-slate-100 hidden">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-black text-slate-800 mb-2">Welcome Back!</h2>
                <p class="text-sm text-slate-500 font-medium">Please enter your details to sign in.</p>
            </div>
                
                <?php if($loginError): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold rounded-2xl flex items-center gap-3 animate-in fade-in slide-in-from-top-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> 
                    <?php echo $loginError; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="login">
                    <input type="hidden" name="action" value="login">
                    <div class="space-y-5 mb-8">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Email or Phone</label>
                            <input type="text" name="contact" required placeholder="you@example.com or 017..." class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center ml-1">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Password</label>
                                <?php if (!isset($settings['otpSystemEnabled']) || $settings['otpSystemEnabled']): ?>
                                <a href="forgot-password" class="text-xs font-bold text-[#003e86] hover:underline">Forgot?</a>
                                <?php endif; ?>
                            </div>
                            <input type="password" name="password" required placeholder="••••••••" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                        Sign In <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                </form>
            </div>

        <!-- Register Card -->
        <div id="register-card" class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl shadow-slate-200/40 border border-slate-100 hidden">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-black text-slate-800 mb-2">Join Us Today</h2>
                <p class="text-sm text-slate-500 font-medium">Create an account to easily track your orders.</p>
            </div>
                
                <?php if($registerError): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold rounded-2xl flex items-center gap-3 animate-in fade-in slide-in-from-top-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> 
                    <?php echo $registerError; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="login">
                    <input type="hidden" name="action" value="register">
                    <div class="space-y-5 mb-8">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Full Name</label>
                            <input type="text" name="name" required placeholder="John Doe" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                        </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Phone <span class="text-rose-500">*</span></label>
                                <input type="tel" name="phone" required placeholder="017..." class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Email <span class="text-slate-400 lowercase">(Optional)</span></label>
                                <input type="email" name="email" placeholder="you@example.com" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                            </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Password</label>
                            <input type="password" name="password" required placeholder="Create a strong password" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                        </div>
                    </div>

                    <?php if (!empty($settings['smsEnabled']) && $isOtpRegEnabled): ?>
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

                    <button type="submit" class="w-full py-4 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold shadow-xl shadow-slate-900/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                        Create Account
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                </form>
            </div>

        <!-- Register OTP Card -->
        <div id="register-otp-card" class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl shadow-slate-200/40 border border-slate-100 hidden">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-black text-slate-800 mb-2">Verify Account</h2>
                <p class="text-sm text-slate-500 font-medium">Please enter the 6-digit OTP sent to you.</p>
            </div>
                
                <?php if(!empty($registerOtpMsg)): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-600 text-sm font-bold rounded-2xl flex items-center gap-3 animate-in fade-in slide-in-from-top-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg> 
                    <?php echo $registerOtpMsg; ?>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($registerOtpError)): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-600 text-sm font-bold rounded-2xl flex items-center gap-3 animate-in fade-in slide-in-from-top-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> 
                    <?php echo $registerOtpError; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="login">
                    <input type="hidden" name="action" value="verify_reg_otp">
                    <div class="space-y-5 mb-8">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 text-center block">6-Digit OTP</label>
                            <input type="text" name="otp" required placeholder="------" maxlength="6" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl text-2xl tracking-[1em] text-center font-black focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                            <div class="flex justify-between items-center mt-2 px-1 max-w-[250px] mx-auto">
                                <span id="register-timer" class="text-xs font-bold text-slate-500">02:30</span>
                                <button type="button" id="btn-resend-reg-otp" onclick="resendRegOtp()" class="text-xs font-bold text-[#003e86] hidden hover:underline">Resend Code</button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                        Verify & Create Account
                    </button>
                    <div class="mt-6 text-center">
                        <button type="button" onclick="switchForm('register')" class="text-sm font-bold text-slate-500 hover:text-[#003e86]">Cancel</button>
                    </div>
                </form>
        </div>
        
    </div>
</div>

<script>
function switchForm(formType, isInitial = false) {
    const loginCard = document.getElementById('login-card');
    const registerCard = document.getElementById('register-card');
    const registerOtpCard = document.getElementById('register-otp-card');
    const slider = document.getElementById('toggle-slider');
    const btnLogin = document.getElementById('btn-login');
    const btnRegister = document.getElementById('btn-register');

    loginCard.classList.add('hidden');
    registerCard.classList.add('hidden');
    if(registerOtpCard) registerOtpCard.classList.add('hidden');

    if (formType === 'login') {
        if (!isInitial) {
            loginCard.classList.remove('hidden');
            loginCard.classList.remove('animate-in', 'fade-in', 'slide-in-from-right-8');
            loginCard.classList.add('animate-in', 'fade-in', 'slide-in-from-left-8', 'duration-500');
        } else {
            loginCard.classList.remove('hidden');
        }
        slider.style.transform = 'translateX(0)';
        btnLogin.classList.add('font-black', 'text-[#003e86]');
        btnLogin.classList.remove('font-bold', 'text-slate-500');
        btnRegister.classList.add('font-bold', 'text-slate-500');
        btnRegister.classList.remove('font-black', 'text-[#003e86]');
    } else if (formType === 'register-otp') {
        if (registerOtpCard) registerOtpCard.classList.remove('hidden');
        slider.style.transform = 'translateX(100%)';
        btnRegister.classList.add('font-black', 'text-[#003e86]');
        btnRegister.classList.remove('font-bold', 'text-slate-500');
        btnLogin.classList.add('font-bold', 'text-slate-500');
        btnLogin.classList.remove('font-black', 'text-[#003e86]');
    } else {
        if (!isInitial) {
            registerCard.classList.remove('hidden');
            registerCard.classList.remove('animate-in', 'fade-in', 'slide-in-from-left-8');
            registerCard.classList.add('animate-in', 'fade-in', 'slide-in-from-right-8', 'duration-500');
        } else {
            registerCard.classList.remove('hidden');
        }
        slider.style.transform = 'translateX(100%)';
        btnRegister.classList.add('font-black', 'text-[#003e86]');
        btnRegister.classList.remove('font-bold', 'text-slate-500');
        btnLogin.classList.add('font-bold', 'text-slate-500');
        btnLogin.classList.remove('font-black', 'text-[#003e86]');
    }
}

let regTimerInterval;
function startRegTimer(duration) {
    clearInterval(regTimerInterval);
    const timerEl = document.getElementById('register-timer');
    const resendBtn = document.getElementById('btn-resend-reg-otp');
    if (!timerEl || !resendBtn) return;
    
    let timer = duration;
    timerEl.classList.remove('hidden');
    resendBtn.classList.add('hidden');
    
    regTimerInterval = setInterval(function () {
        let minutes = parseInt(timer / 60, 10);
        let seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        timerEl.textContent = minutes + ":" + seconds;
        if (--timer < 0) {
            clearInterval(regTimerInterval);
            timerEl.classList.add('hidden');
            resendBtn.classList.remove('hidden');
        }
    }, 1000);
}

async function resendRegOtp() {
    const btn = document.getElementById('btn-resend-reg-otp');
    btn.innerText = 'Sending...';
    btn.disabled = true;
    const formData = new FormData();
    formData.append('action', 'resend_reg_otp');
    try {
        const res = await fetch('login.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            if (window.showToast) showToast("OTP Resent Successfully", "success");
            startRegTimer(150);
        } else {
            if (window.showToast) showToast(result.error, "error");
        }
    } catch (e) {
        if (window.showToast) showToast("Network error. Please try again.", "error");
    } finally {
        btn.innerText = 'Resend Code';
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    switchForm('<?php echo htmlspecialchars($activeTab); ?>', true);
    if ('<?php echo htmlspecialchars($activeTab); ?>' === 'register-otp') {
        startRegTimer(150);
    }
});
</script>
<?php include 'Footer.php'; ?>