<?php
session_start();
require_once __DIR__ . '/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

$userId = $_SESSION['user_id'];

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login");
    exit;
}

// Fetch User Info
$stmt = $pdo->prepare("SELECT * FROM User WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login");
    exit;
}

$isBlocked = ($user['status'] ?? 'active') === 'blocked';

// Parse existing address for the new UI format
$userAddressRaw = $user['address'] ?? '';
$userDivision = '';
$userDist = '';
$userUpazila = '';
$userStreet = $userAddressRaw;
if (preg_match('/Upazila:\s*(.*?),\s*District:\s*(.*?),\s*Division:\s*(.*)/s', $userAddressRaw, $matches)) {
    $userUpazila = trim($matches[1] ?? '');
    $userDist = trim($matches[2] ?? '');
    $userDivision = trim($matches[3] ?? '');
    $userStreet = trim(preg_replace('/(\r\n|\n|\r)?Upazila:.*$/s', '', $userAddressRaw));
} elseif (preg_match('/Sub-District\/Thana:\s*(.*?)\s*\|\s*District:\s*(.*)/', $userAddressRaw, $matches)) {
    $userUpazila = trim($matches[1]);
    $userDist = trim($matches[2]);
    $userStreet = trim(preg_replace('/(\r\n|\n|\r)?Sub-District\/Thana:.*$/', '', $userAddressRaw));
}

// Handle Order details API
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details' && isset($_GET['order_id'])) {
    header('Content-Type: application/json');
    try {
        $orderId = $_GET['order_id'];
        $oStmt = $pdo->prepare("SELECT * FROM `Order` WHERE id = ? AND userId = ?");
        $oStmt->execute([$orderId, $userId]);
        $order = $oStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $iStmt = $pdo->prepare("SELECT oi.*, p.name, p.imageUrl as pImg, v.imageUrl as vImg FROM OrderItem oi LEFT JOIN Product p ON oi.productId = p.id LEFT JOIN Variant v ON oi.variantId = v.id WHERE oi.orderId = ?");
            $iStmt->execute([$orderId]);
            $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Order Cancellation (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'cancel_order') {
    header('Content-Type: application/json');
    if (($user['status'] ?? 'active') === 'blocked') {
        echo json_encode(['success' => false, 'message' => 'Action prohibited. Your account is suspended.']);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $orderId = $data['order_id'] ?? null;
    $reason = trim($data['reason'] ?? '');
    
    if (!$orderId || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Order ID and cancellation reason are required.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT status FROM `Order` WHERE id = ? AND userId = ?");
        $stmt->execute([$orderId, $userId]);
        $status = $stmt->fetchColumn();
        
        if (!$status) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        
        if (strtoupper($status) !== 'PENDING') {
            echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled.']);
            exit;
        }
        
        $pdo->prepare("UPDATE `Order` SET status = 'CANCELLED', cancelReason = ? WHERE id = ? AND userId = ?")->execute([$reason, $orderId, $userId]);
        $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, 'Order Cancelled', ?, 'order')")->execute([$userId, "Your order #$orderId has been cancelled successfully."]);
        $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Order Cancelled by Customer', ?, 'alert')")->execute(["Order #$orderId has been cancelled by the customer. Reason: $reason"]);
        
        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Profile Update Request (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'request_profile_update') {
    header('Content-Type: application/json');
    if (($user['status'] ?? 'active') === 'blocked') {
        echo json_encode(['success' => false, 'message' => 'Action prohibited. Your account is suspended.']);
        exit;
    }
    $data = json_decode(file_get_contents("php://input"), true);
    
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $password = trim($data['password'] ?? '');


    if(empty($name)) { echo json_encode(['success' => false, 'message' => 'Name is required.']); exit; }

    $emailChanged = ($email !== $user['email']);
    $phoneChanged = ($phone !== $user['phone']);
    $passwordChanged = !empty($password);

    if ($emailChanged && !empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Email already in use.']); exit; }
    }
    
    if ($phoneChanged && !empty($phone)) {
        $stmt = $pdo->prepare("SELECT id FROM User WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $userId]);
        if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Phone already in use.']); exit; }
    }

    $setStmt = $pdo->query("SELECT g.storeName, n.emailEnabled, n.otpEmailTemplate, n.smsEnabled, n.otpSmsTemplate, n.smsApiUrl FROM SettingGeneral g LEFT JOIN SettingNotification n ON g.id = n.id LIMIT 1");
    $settings = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $storeName = !empty($settings['storeName']) ? $settings['storeName'] : 'Idea Mart';
    
    $isOtpPossible = !empty($settings['emailEnabled']) || (!empty($settings['smsEnabled']) && !empty($settings['smsApiUrl']));

    if (($emailChanged || $phoneChanged || $passwordChanged) && $isOtpPossible) {
        try { $pdo->exec("ALTER TABLE `User` ADD COLUMN `address` TEXT DEFAULT NULL"); } catch (Exception $e) {}
        
        $otp = mt_rand(100000, 999999);
        $_SESSION['pending_profile_update'] = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'password' => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
            'otp' => $otp,
            'expires' => time() + 600
        ];
        
        try {
            if ($emailChanged && !empty($email)) {
                $targetEmail = $email;
                $template = !empty($settings['otpEmailTemplate']) ? $settings['otpEmailTemplate'] : "Your OTP is: {{otp_code}}";
                $htmlContent = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$user['name'], $otp, $storeName, $storeName], $template);
                require_once __DIR__ . '/api/mailer_helper.php';
                sendDynamicEmail($pdo, $targetEmail, "Profile Update Verification", $htmlContent);
                echo json_encode(['success' => true, 'otp_required' => true, 'message' => 'OTP sent to your new email address.']);
            } else if (!empty($user['email'])) {
                $targetEmail = $user['email'];
                $template = !empty($settings['otpEmailTemplate']) ? $settings['otpEmailTemplate'] : "Your OTP is: {{otp_code}}";
                $htmlContent = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$user['name'], $otp, $storeName, $storeName], $template);
                require_once __DIR__ . '/api/mailer_helper.php';
                sendDynamicEmail($pdo, $targetEmail, "Profile Update Verification", $htmlContent);
                echo json_encode(['success' => true, 'otp_required' => true, 'message' => 'OTP sent to your registered email address.']);
            } else if (!empty($settings['smsEnabled']) && !empty($settings['smsApiUrl'])) {
                $targetPhone = $phoneChanged && !empty($phone) ? $phone : $user['phone'];
                if(!empty($targetPhone)) {
                    $template = !empty($settings['otpSmsTemplate']) ? $settings['otpSmsTemplate'] : "Your OTP is: {{otp_code}}";
                    $message = str_replace(['{{customer_name}}', '{{otp_code}}', '{{store_name}}', 'Idea Mart'], [$user['name'], $otp, $storeName, $storeName], $template);
                    $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($targetPhone), $settings['smsApiUrl']);
                    $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                    @file_get_contents($apiUrl);
                    echo json_encode(['success' => true, 'otp_required' => true, 'message' => 'OTP sent to your phone via SMS.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cannot send OTP. No email or phone available.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Contact support.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
        }
    } else {
        try {
            try { $pdo->exec("ALTER TABLE `User` ADD COLUMN `address` TEXT DEFAULT NULL"); } catch (Exception $e) {}
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE User SET name = ?, email = ?, phone = ?, address = ?, password = ? WHERE id = ?")->execute([$name, $email, $phone, $address, $hashed, $userId]);
            } else {
                $pdo->prepare("UPDATE User SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?")->execute([$name, $email, $phone, $address, $userId]);
            }
            echo json_encode(['success' => true, 'otp_required' => false, 'message' => 'Profile updated successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Verify Profile OTP (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'verify_profile_otp') {
    header('Content-Type: application/json');
    if (($user['status'] ?? 'active') === 'blocked') {
        echo json_encode(['success' => false, 'message' => 'Action prohibited. Your account is suspended.']);
        exit;
    }
    $data = json_decode(file_get_contents("php://input"), true);
    $otp = $data['otp'] ?? '';
    
    if (!isset($_SESSION['pending_profile_update']) || $_SESSION['pending_profile_update']['expires'] < time()) {
        echo json_encode(['success' => false, 'message' => 'OTP session expired. Please try again.']);
        exit;
    }
    
    if ($_SESSION['pending_profile_update']['otp'] == $otp) {
        $pending = $_SESSION['pending_profile_update'];
        
        try {
            try { $pdo->exec("ALTER TABLE `User` ADD COLUMN `address` TEXT DEFAULT NULL"); } catch (Exception $e) {}
            
            if ($pending['password']) {
                $pdo->prepare("UPDATE User SET name = ?, email = ?, phone = ?, address = ?, password = ? WHERE id = ?")
                    ->execute([$pending['name'], $pending['email'], $pending['phone'], $pending['address'], $pending['password'], $userId]);
            } else {
                $pdo->prepare("UPDATE User SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?")
                    ->execute([$pending['name'], $pending['email'], $pending['phone'], $pending['address'], $userId]);
            }
            
            unset($_SESSION['pending_profile_update']);
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
    }
    exit;
}

// Fetch Orders
$orders = [];
try {
    $ordersStmt = $pdo->prepare("SELECT * FROM `Order` WHERE userId = ? ORDER BY createdAt DESC");
    $ordersStmt->execute([$userId]);
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Order table might not exist
}
$totalOrders = count($orders);

try {
    $pdo->exec("ALTER TABLE `Order` ADD COLUMN `couponCode` VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE `Order` ADD COLUMN `discountAmount` DECIMAL(10,2) DEFAULT 0.00");
} catch (Exception $e) {}

$userPhone = $user['phone'] ?? '';
$userEmail = $user['email'] ?? '';
$hasOrders = $totalOrders > 0;

$couponsStmt = $pdo->prepare("SELECT * FROM Coupon WHERE status = 1 AND (expiryDate IS NULL OR expiryDate > NOW()) AND (usageLimit IS NULL OR usedCount < usageLimit) ORDER BY id DESC");
$couponsStmt->execute();
$allActiveCoupons = $couponsStmt->fetchAll(PDO::FETCH_ASSOC);

$usedCouponsStmt = $pdo->prepare("SELECT couponCode, COUNT(*) as count FROM `Order` WHERE userId = ? AND status != 'CANCELLED' AND couponCode IS NOT NULL GROUP BY couponCode");
$usedCouponsStmt->execute([$userId]);
$usedCoupons = $usedCouponsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$availableCoupons = [];
foreach ($allActiveCoupons as $c) {
    $usedCount = $usedCoupons[$c['code']] ?? 0;
    if ($c['usagePerUser'] > 0 && $usedCount >= $c['usagePerUser']) continue;

    if ($c['couponType'] === 'GENERAL') { $availableCoupons[] = $c; }
    elseif ($c['couponType'] === 'WELCOME' && !$hasOrders) { $availableCoupons[] = $c; }
    elseif ($c['couponType'] === 'USER_SPECIFIC') {
        $allowed = array_map('trim', explode(',', $c['applicableData'] ?? ''));
        if (in_array($userPhone, $allowed) || in_array($userEmail, $allowed)) { $availableCoupons[] = $c; }
    }
}

$pageMetaTitle = 'My Account';
include 'Header.php';
?>

<div class="min-h-screen pt-24 md:pt-28 pb-28 md:pb-24 bg-slate-50 font-sans">
    <div class="max-w-[1200px] mx-auto px-4 md:px-6">
        
        <!-- Desktop Page Title -->
        <h1 class="hidden md:block text-3xl font-black text-slate-900 tracking-tight mb-8">My Account</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 md:gap-8">
            
            <!-- Mobile App-like Profile Header & Sidebar Navigation -->
            <div class="md:col-span-3 space-y-4 md:space-y-6">
                
                <!-- Profile Card -->
                <div class="bg-white md:rounded-3xl rounded-2xl p-6 shadow-sm border border-slate-200 text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-blue-600 to-[#003e86] opacity-10"></div>
                    <div class="w-20 h-20 bg-[#003e86] text-white rounded-full flex items-center justify-center text-3xl font-black mx-auto mb-4 shadow-xl shadow-blue-900/20 relative z-10 border-4 border-white">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <h3 class="font-black text-slate-800 text-xl leading-tight mb-1 relative z-10"><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p class="text-xs font-bold tracking-wider uppercase text-slate-400 relative z-10"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <!-- Mobile Icon Grid Menu -->
                <div class="grid grid-cols-3 gap-2 md:hidden bg-white rounded-[2rem] p-3 shadow-sm border border-slate-200">
                    <button onclick="switchTab('dashboard')" id="tab-btn-mobile-dashboard" class="tab-btn-mobile flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl font-bold text-[11px] bg-blue-50 text-[#003e86] transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        <span>Dashboard</span>
                    </button>
                    <button onclick="switchTab('orders')" id="tab-btn-mobile-orders" class="tab-btn-mobile flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl font-bold text-[11px] text-slate-500 hover:bg-slate-50 transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span>Orders</span>
                    </button>
                    <a href="wishlist?ref=account" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        <span>Wishlist</span>
                    </a>
                    <button onclick="switchTab('coupons')" id="tab-btn-mobile-coupons" class="tab-btn-mobile flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl font-bold text-[11px] text-slate-500 hover:bg-slate-50 transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        <span>Coupons</span>
                    </button>
                    <a href="notifications?ref=account" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-slate-50 text-slate-500 font-bold text-[11px] text-center transition-all relative">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        <span>Notifs</span>
                    </a>
                    <button onclick="switchTab('settings')" id="tab-btn-mobile-settings" class="tab-btn-mobile flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl font-bold text-[11px] text-slate-500 hover:bg-slate-50 transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Settings</span>
                    </button>
                    <a href="account?action=logout" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl hover:bg-rose-50 text-rose-500 font-bold text-[11px] text-center transition-all">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        <span>Logout</span>
                    </a>
                </div>

                <!-- Desktop App Menu List -->
                <div class="hidden md:flex bg-white rounded-[2rem] p-4 shadow-sm border border-slate-200 flex-col gap-2">
                    <button onclick="switchTab('dashboard')" id="tab-btn-dashboard" class="tab-btn w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm bg-blue-50 text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg> Dashboard</div>
                    </button>
                    <button onclick="switchTab('orders')" id="tab-btn-orders" class="tab-btn w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> My Orders</div>
                        <span class="bg-slate-100 text-slate-500 py-0.5 px-2 rounded-md text-[10px]"><?php echo $totalOrders; ?></span>
                    </button>
                    <a href="wishlist?ref=account" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg> My Wishlist</div>
                    </a>
                    <button onclick="switchTab('coupons')" id="tab-btn-coupons" class="tab-btn w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg> My Coupons</div>
                    </button>
                    <a href="notifications?ref=account" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3 relative"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Notifications</div>
                    </a>
                    <button onclick="switchTab('settings')" id="tab-btn-settings" class="tab-btn w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-slate-500 hover:bg-slate-50 hover:text-[#003e86] transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profile Settings</div>
                    </button>
                    <div class="my-1 border-t border-slate-100 mx-2"></div>
                    <a href="account?action=logout" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl font-bold text-sm text-rose-500 hover:bg-rose-50 transition-all text-left">
                        <div class="flex items-center gap-3"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Logout</div>
                    </a>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="md:col-span-9 space-y-4">
                
                <?php if ($isBlocked): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-3xl p-5 md:p-6 shadow-sm flex items-start gap-4 text-rose-700 mb-6">
                    <div class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center shrink-0">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    </div>
                    <div>
                        <h3 class="font-black text-base md:text-lg">Account Suspended</h3>
                        <p class="text-sm font-medium mt-1 leading-relaxed text-rose-600">Your account has been restricted by the administrator. You cannot place new orders, update your profile, or perform sensitive actions. Please contact support for assistance.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dashboard Tab -->
                <div id="tab-dashboard" class="tab-content animate-in fade-in slide-in-from-bottom-4 duration-300">
                    <h2 class="text-2xl font-black text-slate-800 mb-6 hidden md:block">Dashboard</h2>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col items-center justify-center text-center">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-[#003e86] flex items-center justify-center mb-3">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            </div>
                            <h4 class="text-3xl font-black text-slate-800"><?php echo $totalOrders; ?></h4>
                            <p class="text-xs font-bold tracking-widest text-slate-400 uppercase mt-1">Total Orders</p>
                        </div>
                        
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col items-center justify-center text-center">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mb-3">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <h4 class="text-3xl font-black text-slate-800">
                                <?php echo count(array_filter($orders, fn($o) => in_array(strtolower($o['status']), ['pending', 'processing']))); ?>
                            </h4>
                            <p class="text-xs font-bold tracking-widest text-slate-400 uppercase mt-1">Pending</p>
                        </div>
                        
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col items-center justify-center text-center col-span-2 md:col-span-1">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-3">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <h4 class="text-3xl font-black text-slate-800">
                                <?php echo count(array_filter($orders, fn($o) => in_array(strtolower($o['status']), ['completed', 'delivered']))); ?>
                            </h4>
                            <p class="text-xs font-bold tracking-widest text-slate-400 uppercase mt-1">Delivered</p>
                        </div>
                    </div>

                    <!-- Recent Orders preview -->
                    <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-200">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="font-bold text-slate-800 text-lg">Recent Orders</h3>
                            <button onclick="switchTab('orders')" class="text-sm font-bold text-[#003e86] hover:underline">View All</button>
                        </div>
                        
                        <div class="space-y-3 md:space-y-4">
                            <?php if (empty($orders)): ?>
                                <p class="text-sm text-slate-500 text-center py-4">No recent orders found.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($orders, 0, 3) as $order): ?>
                                    <!-- Order Card -->
                                    <div class="flex items-center justify-between p-3 md:p-4 rounded-2xl border border-slate-100 hover:border-blue-100 hover:bg-blue-50/50 transition-colors cursor-pointer" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                        <div class="flex items-center gap-3 md:gap-4">
                                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600 shrink-0">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 text-xs md:text-sm">Order #<?php echo $order['id']; ?></p>
                                                <p class="text-[9px] md:text-[10px] font-bold tracking-wider text-slate-400 uppercase mt-0.5"><?php echo date('M d, Y', strtotime($order['createdAt'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-black text-[#003e86] text-xs md:text-sm">৳<?php echo number_format($order['total']); ?></p>
                                            <?php 
                                            $sLower = strtolower($order['status']);
                                            $textColor = 'text-slate-500';
                                            if ($sLower === 'pending') $textColor = 'text-amber-500';
                                            elseif ($sLower === 'processing') $textColor = 'text-blue-500';
                                            elseif ($sLower === 'shipped') $textColor = 'text-indigo-500';
                                            elseif (in_array($sLower, ['delivered', 'completed'])) $textColor = 'text-emerald-500';
                                            elseif ($sLower === 'cancelled') $textColor = 'text-rose-500';
                                            ?>
                                            <p class="text-[9px] md:text-[10px] font-bold tracking-wider uppercase mt-0.5 <?php echo $textColor; ?>">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div id="tab-orders" class="tab-content hidden animate-in fade-in slide-in-from-bottom-4 duration-300">
                    <h2 class="text-2xl font-black text-slate-800 mb-6 hidden md:block">My Orders</h2>
                    
                    <div class="space-y-4">
                        <?php if (empty($orders)): ?>
                            <div class="p-10 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                </div>
                                <p class="text-slate-500 font-medium">You haven't placed any orders yet.</p>
                                <a href="shop" class="mt-4 inline-block px-6 py-2.5 bg-[#003e86] text-white font-bold rounded-xl shadow-lg shadow-blue-900/20">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <!-- Full Order Card App Style -->
                                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                                        <div>
                                            <span class="bg-blue-50 text-[#003e86] text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg">Order #<?php echo $order['id']; ?></span>
                                            <p class="text-[10px] md:text-[11px] font-bold text-slate-400 mt-2"><?php echo date('d M, Y • h:i A', strtotime($order['createdAt'])); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php 
                                            $sLower = strtolower($order['status']);
                                            $badgeClass = 'bg-slate-50 text-slate-600';
                                            if ($sLower === 'pending') $badgeClass = 'bg-amber-50 text-amber-600';
                                            elseif ($sLower === 'processing') $badgeClass = 'bg-blue-50 text-blue-600';
                                            elseif ($sLower === 'shipped') $badgeClass = 'bg-indigo-50 text-indigo-600';
                                            elseif (in_array($sLower, ['delivered', 'completed'])) $badgeClass = 'bg-emerald-50 text-emerald-600';
                                            elseif ($sLower === 'cancelled') $badgeClass = 'bg-rose-50 text-rose-600';
                                            ?>
                                            <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg <?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-2">
                                            <span class="text-slate-500 text-xs md:text-sm font-medium">Total Amount:</span>
                                            <span class="text-base md:text-lg font-black text-[#003e86]">৳<?php echo number_format($order['total']); ?></span>
                                        </div>
                                        <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="px-4 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition-colors">Details</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Coupons Tab -->
                <div id="tab-coupons" class="tab-content hidden animate-in fade-in slide-in-from-bottom-4 duration-300">
                    <h2 class="text-2xl font-black text-slate-800 mb-6 hidden md:block">My Coupons</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (empty($availableCoupons)): ?>
                            <div class="md:col-span-2 p-10 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                </div>
                                <p class="text-slate-500 font-medium">No active coupons available for you right now.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($availableCoupons as $c): ?>
                                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm relative overflow-hidden flex flex-col justify-between">
                                    <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-bl-full -z-0"></div>
                                    <div class="relative z-10">
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-10 h-10 bg-[#003e86] text-white rounded-xl flex items-center justify-center shrink-0">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                            </div>
                                            <div>
                                                <h3 class="font-black text-slate-800 text-lg"><?php echo $c['discountType'] === 'PERCENTAGE' ? rtrim(rtrim($c['discountAmount'], '0'), '.') . '%' : '৳' . rtrim(rtrim($c['discountAmount'], '0'), '.'); ?> OFF</h3>
                                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?php echo $c['couponType'] === 'WELCOME' ? 'First Order Bonus' : 'Special Discount'; ?></p>
                                            </div>
                                        </div>
                                        <ul class="text-xs text-slate-600 font-medium space-y-1 mb-4 ml-1">
                                            <li>• Min Spend: <?php echo $c['minSpend'] > 0 ? '৳' . rtrim(rtrim($c['minSpend'], '0'), '.') : 'None'; ?></li>
                                            <?php if ($c['expiryDate']): ?>
                                            <li>• Valid till: <?php echo date('M d, Y h:i A', strtotime($c['expiryDate'])); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="relative z-10 flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-1 pl-4">
                                        <span class="font-mono font-black text-slate-800 tracking-wider uppercase"><?php echo htmlspecialchars($c['code']); ?></span>
                                        <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($c['code']); ?>'); if(window.showToast) showToast('Coupon code copied!', 'success');" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-xs font-bold hover:text-[#003e86] hover:border-[#003e86] transition-colors shadow-sm">Copy</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="tab-settings" class="tab-content hidden animate-in fade-in slide-in-from-bottom-4 duration-300">
                    <?php $hasEmptyFields = empty($user['name']) || empty($user['phone']) || empty($userDist) || empty($userUpazila) || empty($userStreet); ?>
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-black text-slate-800 hidden md:block">Profile Settings</h2>
                        <h2 class="text-xl font-black text-slate-800 md:hidden">Profile Settings</h2>
                        <?php if (!$isBlocked): ?>
                        <button type="button" id="btn-enable-edit" onclick="enableProfileEdit()" class="px-4 py-2 bg-indigo-50 text-indigo-600 font-bold rounded-xl hover:bg-indigo-100 transition-colors text-sm flex items-center gap-2 shadow-sm">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Edit Profile
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-white rounded-3xl p-5 md:p-6 border border-slate-200 shadow-sm">
                        <form id="profile-form" onsubmit="handleProfileUpdate(event)" class="space-y-5">
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Full Name</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required <?php echo !empty($user['name']) ? 'disabled' : ''; ?> class="profile-field w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-[#003e86] outline-none transition-all font-medium text-slate-800 text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled readonly title="Email cannot be changed" class="w-full px-5 py-3.5 bg-slate-100 border border-slate-200 rounded-2xl outline-none font-medium text-slate-500 text-sm cursor-not-allowed opacity-80">
                                </div>

                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" <?php echo !empty($user['phone']) ? 'disabled' : ''; ?> class="profile-field w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-[#003e86] outline-none transition-all font-medium text-slate-800 text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                                </div>
                            </div>

                            <div class="bg-indigo-50/30 p-5 md:p-6 rounded-3xl border border-indigo-50 mt-6">
                                <div class="flex items-center justify-between mb-5 border-b border-indigo-100/50 pb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center shadow-inner shrink-0">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Delivery Address</h3>
                                            <p class="text-xs font-semibold text-slate-500 mt-0.5">Where should we send your orders?</p>
                                        </div>
                                    </div>
                                    <button type="button" onclick="openAddressModal()" class="profile-field shrink-0 px-4 py-2 bg-white border border-indigo-200 text-indigo-600 rounded-xl font-bold text-xs hover:bg-indigo-50 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                        Edit Address
                                    </button>
                                </div>
                                
                                <div id="address-display-box" class="bg-white p-5 rounded-2xl border border-indigo-100 shadow-sm min-h-[80px] flex items-center justify-center text-slate-500 font-medium text-sm">
                                    <?php if (!empty($userAddressRaw)): ?>
                                        <p class="text-slate-800 font-bold leading-relaxed whitespace-pre-wrap text-left w-full"><?php echo htmlspecialchars($userAddressRaw); ?></p>
                                    <?php else: ?>
                                        <p>No address set. Click "Edit Address" to add one.</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Hidden fields to store data for submission -->
                                <input type="hidden" name="division" id="hidden-division" value="<?php echo htmlspecialchars($userDivision); ?>">
                                <input type="hidden" name="district" id="hidden-district" value="<?php echo htmlspecialchars($userDist); ?>">
                                <input type="hidden" name="upazila" id="hidden-upazila" value="<?php echo htmlspecialchars($userUpazila); ?>">
                                <input type="hidden" name="street" id="hidden-street" value="<?php echo htmlspecialchars($userStreet); ?>">
                            </div>

                            <div id="password-section" class="space-y-2 pt-4 border-t border-slate-100 hidden">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">New Password <span class="text-slate-400 lowercase">(Optional)</span></label>
                                <input type="password" name="password" placeholder="Leave blank to keep current password" disabled class="profile-field w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-[#003e86] outline-none transition-all font-medium text-slate-800 text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            </div>

                            <div id="profile-actions" class="pt-4 flex items-center gap-3 <?php echo $hasEmptyFields ? '' : 'hidden'; ?>">
                                <button type="button" onclick="cancelProfileEdit()" class="px-6 py-4 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold text-sm transition-all">
                                    Cancel
                                </button>
                                <button type="submit" id="btn-save-profile" class="flex-1 py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-2xl font-bold text-sm shadow-lg shadow-blue-900/20 transition-all active:scale-[0.98]">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal (App Style Bottom Sheet/Modal) -->
<div id="order-modal" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden items-end md:items-center justify-center md:p-4 transition-opacity duration-300 opacity-0">
    <div id="order-modal-content" class="bg-white w-full md:max-w-lg rounded-t-3xl md:rounded-3xl overflow-hidden shadow-2xl transition-transform duration-300 translate-y-full md:scale-95 flex flex-col max-h-[90vh]">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <h2 class="font-black text-slate-800 text-lg flex items-center gap-2">Order Details</h2>
            <button type="button" onclick="closeOrderModal()" class="p-2 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 rounded-full transition-colors"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="overflow-y-auto p-5 md:p-6 flex-1 custom-scrollbar bg-slate-50" id="order-modal-body">
            <div class="flex justify-center py-10">
                <svg class="animate-spin h-8 w-8 text-[#003e86]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
        </div>
    </div>
</div>

<!-- Profile OTP Modal -->
<div id="profile-otp-modal" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden items-end md:items-center justify-center md:p-4 transition-opacity duration-300 opacity-0">
    <div id="profile-otp-modal-content" class="bg-white w-full md:max-w-md rounded-t-3xl md:rounded-3xl overflow-hidden shadow-2xl transition-transform duration-300 translate-y-full md:scale-95 flex flex-col max-h-[90vh]">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <h2 class="font-black text-slate-800 text-lg flex items-center gap-2">Verify Changes</h2>
            <button type="button" onclick="closeProfileOtpModal()" class="p-2 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 rounded-full transition-colors"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6 bg-slate-50 overflow-y-auto">
            <div class="bg-blue-50 text-[#003e86] p-4 rounded-xl text-sm font-medium border border-blue-100 mb-4" id="profile-otp-msg">
                We've sent an OTP to verify your sensitive changes.
            </div>
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">6-Digit OTP</label>
                <input type="text" id="profile-otp-input" placeholder="------" maxlength="6" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-center text-lg tracking-[0.5em] font-black focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all text-slate-800">
                <div class="flex justify-between items-center mt-2 px-1">
                    <span id="profile-timer" class="text-xs font-bold text-slate-500">02:30</span>
                    <button type="button" id="btn-resend-profile-otp" onclick="resendProfileOtp()" class="text-xs font-bold text-[#003e86] hidden hover:underline">Resend Code</button>
                </div>
                <p id="profile-verify-error" class="text-rose-500 text-xs font-bold hidden ml-1 mt-1"></p>
            </div>
            <button type="button" id="btn-verify-profile" onclick="verifyProfileUpdate()" class="w-full py-3.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold text-sm shadow-lg shadow-emerald-500/20 transition-all flex justify-center items-center gap-2 mt-4 active:scale-95">
                Verify & Save
            </button>
        </div>
    </div>
</div>

<!-- Address Popup Modal -->
<div id="address-modal" class="fixed inset-0 z-[110] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div id="address-modal-content" class="bg-white w-full max-w-2xl rounded-3xl overflow-hidden shadow-2xl transition-transform duration-300 scale-95 flex flex-col max-h-[90vh]">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <h2 class="font-black text-slate-800 text-lg flex items-center gap-2">Update Delivery Address</h2>
            <button type="button" onclick="closeAddressModal()" class="p-2 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 rounded-full transition-colors"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6 bg-slate-50 overflow-y-auto flex-1 custom-scrollbar space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Division</label>
                    <select id="modal-division-select" onchange="fetchDistricts(this.options[this.selectedIndex].getAttribute('data-id'))" class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none transition-all font-bold text-slate-700 text-sm cursor-pointer shadow-sm">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="space-y-2" id="modal-district-wrapper">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">District</label>
                    <select id="modal-district-select" onchange="fetchUpazilas(this.options[this.selectedIndex].getAttribute('data-id'))" class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none transition-all font-bold text-slate-700 text-sm cursor-pointer shadow-sm">
                        <option value="">Waiting for Division...</option>
                    </select>
                </div>
                <div class="space-y-2 md:col-span-2" id="modal-upazila-wrapper">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Upazila / Police Station</label>
                    <select id="modal-upazila-select" class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none transition-all font-bold text-slate-700 text-sm cursor-pointer shadow-sm">
                        <option value="">Waiting for District...</option>
                    </select>
                </div>
                <div class="space-y-2 md:col-span-2" id="modal-street-wrapper">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">House, Village, Road No.</label>
                    <textarea id="modal-street-input" rows="2" placeholder="e.g. House 12, Road 5, Block C" class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none transition-all font-bold text-slate-700 text-sm resize-none shadow-sm"></textarea>
                </div>
            </div>
        </div>
        <div class="p-5 border-t border-slate-100 bg-white flex justify-end gap-3">
            <button type="button" onclick="closeAddressModal()" class="px-5 py-3 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
            <button type="button" onclick="saveAddressFromModal()" class="px-6 py-3 text-sm font-bold text-white bg-indigo-600 rounded-xl shadow-md hover:bg-indigo-700 transition-all">Done</button>
        </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        const activeTab = document.getElementById('tab-' + tabId);
        if (activeTab) activeTab.classList.remove('hidden');
        
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-blue-50', 'text-[#003e86]');
            btn.classList.add('text-slate-500', 'hover:bg-slate-50', 'hover:text-[#003e86]');
        });
        document.querySelectorAll('.tab-btn-mobile').forEach(btn => {
            btn.classList.remove('bg-blue-50', 'text-[#003e86]');
            btn.classList.add('text-slate-500', 'hover:bg-slate-50');
        });
        
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        if (activeBtn) {
            activeBtn.classList.add('bg-blue-50', 'text-[#003e86]');
            activeBtn.classList.remove('text-slate-500', 'hover:bg-slate-50', 'hover:text-[#003e86]');
        }
        
        const activeMobileBtn = document.getElementById('tab-btn-mobile-' + tabId);
        if (activeMobileBtn) {
            activeMobileBtn.classList.add('bg-blue-50', 'text-[#003e86]');
            activeMobileBtn.classList.remove('text-slate-500', 'hover:bg-slate-50');
        }
        
        window.history.replaceState(null, null, window.location.pathname + window.location.search + '#' + tabId);
        if (window.innerWidth < 768 && activeTab) {
            window.scrollTo({ top: document.querySelector('.md\\:col-span-9').offsetTop - 80, behavior: 'smooth' });
        }
    }
    
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.substring(1);
        if (['dashboard', 'orders', 'coupons', 'settings'].includes(hash)) switchTab(hash);
        else switchTab('dashboard');
        
        // Fetch districts and upazilas on load
        const userDivision = "<?php echo addslashes($userDivision); ?>";
        const userDist = "<?php echo addslashes($userDist); ?>";
        const userUpazila = "<?php echo addslashes($userUpazila); ?>";
        
        // Fetch divisions only if not editing mode and missing
        (async () => {
            await fetchDivisions(userDivision);
            if (userDivision) {
                const divSelect = document.getElementById('modal-division-select');
                const selectedOption = Array.from(divSelect.options).find(opt => opt.value === userDivision);
                if (selectedOption) {
                    const divisionId = selectedOption.getAttribute('data-id');
                    await fetchDistricts(divisionId, userDist);
                    
                    if (userDist) {
                        const distSelect = document.getElementById('modal-district-select');
                        const selectedDistOption = Array.from(distSelect.options).find(opt => opt.value === userDist);
                        if (selectedDistOption) {
                            const districtId = selectedDistOption.getAttribute('data-id');
                            await fetchUpazilas(districtId, userUpazila);
                        }
                    }
                }
            }
        })();
    });

    async function fetchDivisions(selected = '') {
        let divSelect = document.getElementById('modal-division-select');
        if (!divSelect) return;
        
        // Show loading state gracefully
        if (!selected) {
            divSelect.innerHTML = '<option value="">Loading Divisions...</option>';
        }
        
        try {
            const response = await fetch('https://bdapis.pro.bd/geo/v2.0/divisions');
            const result = await response.json();
            const data = result.data || result;
            
            if (data && Array.isArray(data) && data.length > 0) {
                let options = '<option value="">Select Division...</option>';
                data.sort((a,b) => (a.name || a.name_en).localeCompare(b.name || b.name_en));
                data.forEach(d => {
                    const id = d.id || d._id;
                    const name = d.name || d.name_en;
                    const isSelected = (name === selected) ? 'selected' : '';
                    options += `<option value="${name}" data-id="${id}" ${isSelected}>${name}</option>`;
                });
                divSelect.innerHTML = options;
            } else {
                divSelect.innerHTML = '<option value="">No divisions found</option>';
            }
        } catch (error) {
            if (!selected) {
                divSelect.innerHTML = '<option value="">Failed to load divisions</option>';
            }
        }
    }

    async function fetchDistricts(divisionId, selected = '') {
        let distSelect = document.getElementById('modal-district-select');
        let upazilaSelect = document.getElementById('modal-upazila-select');
        if (!distSelect) return;
        
        if (upazilaSelect) {
            upazilaSelect.innerHTML = '<option value="">Waiting for District...</option>';
        }

        if (!divisionId) {
            distSelect.innerHTML = '<option value="">Waiting for Division...</option>';
            return;
        }

        if (!selected) {
            distSelect.innerHTML = '<option value="">Loading Districts...</option>';
        }

        try {
            const response = await fetch(`https://bdapis.pro.bd/geo/v2.0/districts/${divisionId}`);
            const result = await response.json();
            const data = result.data || result;
            
            if (data && Array.isArray(data) && data.length > 0) {
                let options = '<option value="">Select District...</option>';
                data.sort((a,b) => (a.name || a.name_en).localeCompare(b.name || b.name_en));
                data.forEach(d => {
                    const id = d.id || d._id;
                    const name = d.name || d.name_en;
                    const isSelected = (name === selected) ? 'selected' : '';
                    options += `<option value="${name}" data-id="${id}" ${isSelected}>${name}</option>`;
                });
                distSelect.innerHTML = options;
            } else {
                distSelect.innerHTML = '<option value="">No districts found</option>';
            }
        } catch (error) {
            if (!selected) distSelect.innerHTML = '<option value="">Failed to load districts</option>';
        }
    }

    async function fetchUpazilas(districtId, selected = '') {
        let upazilaSelect = document.getElementById('modal-upazila-select');
        if (!upazilaSelect) return;

        if (!districtId) {
            upazilaSelect.innerHTML = '<option value="">Waiting for District...</option>'; 
            return; 
        }

        if (!selected) {
            upazilaSelect.innerHTML = '<option value="">Loading Upazilas...</option>';
        }

        try {
            const response = await fetch(`https://bdapis.pro.bd/geo/v2.0/upazilas/${districtId}`);
            const result = await response.json();
            const upazillas = result.data || result;
            
            if (upazillas && Array.isArray(upazillas) && upazillas.length > 0) {
                let options = '<option value="">Select Upazila...</option>';
                upazillas.sort((a,b) => (a.name || a.name_en).localeCompare(b.name || b.name_en));
                upazillas.forEach(u => {
                    const name = u.name || u.name_en;
                    const isSelected = (name === selected) ? 'selected' : '';
                    options += `<option value="${name}" ${isSelected}>${name}</option>`;
                });
                upazilaSelect.innerHTML = options;
            } else {
                upazilaSelect.innerHTML = '<option value="">No upazilas found</option>';
            }
        } catch (error) {
            if (!selected) upazilaSelect.innerHTML = '<option value="">Failed to load upazilas</option>';
        }
    }

    function enableProfileEdit() {
        document.querySelectorAll('.profile-field').forEach(el => el.disabled = false);
        document.getElementById('btn-enable-edit').classList.add('hidden');
        document.getElementById('password-section').classList.remove('hidden');
        document.getElementById('profile-actions').classList.remove('hidden');
    }

    function cancelProfileEdit() {
        window.location.reload();
    }
    
    function openAddressModal() {
        const modal = document.getElementById('address-modal');
        const content = document.getElementById('address-modal-content');
        
        document.getElementById('modal-street-input').value = document.getElementById('hidden-street').value;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
    }

    function closeAddressModal() {
        const modal = document.getElementById('address-modal');
        const content = document.getElementById('address-modal-content');
        modal.classList.add('opacity-0');
        content.classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 300);
    }

    function saveAddressFromModal() {
        const division = document.getElementById('modal-division-select').value.trim();
        const district = document.getElementById('modal-district-select').value.trim();
        const upazila = document.getElementById('modal-upazila-select').value.trim();
        const street = document.getElementById('modal-street-input').value.trim();
        
        if (!division || !district || !upazila || !street) {
            if(window.showToast) showToast('Please select and fill all address fields.', 'error');
            return;
        }
        
        document.getElementById('hidden-division').value = division;
        document.getElementById('hidden-district').value = district;
        document.getElementById('hidden-upazila').value = upazila;
        document.getElementById('hidden-street').value = street;
        
        const combined = `${street}\nUpazila: ${upazila}, District: ${district}, Division: ${division}`;
        document.getElementById('address-display-box').innerHTML = `<p class="text-slate-800 font-bold leading-relaxed whitespace-pre-wrap text-left w-full">${combined}</p>`;
        
        closeAddressModal();
    }

    function viewOrderDetails(orderId) {
        const modal = document.getElementById('order-modal');
        const content = document.getElementById('order-modal-content');
        const body = document.getElementById('order-modal-body');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        content.classList.remove('translate-y-full', 'md:scale-95');
        
        body.innerHTML = '<div class="flex justify-center py-10"><svg class="animate-spin h-8 w-8 text-[#003e86]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div>';
        
        fetch(`account.php?action=get_order_details&order_id=${orderId}`)
            .then(res => res.json())
            .then(result => {
                if (result.success && result.order) {
                    const order = result.order;
                    const date = new Date(order.createdAt).toLocaleString('en-US', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    
                    let sLower = order.status.toLowerCase();
                    let statusColor = 'bg-slate-100 text-slate-700';
                    if(sLower === 'pending') statusColor = 'bg-amber-100 text-amber-700';
                    else if(sLower === 'processing') statusColor = 'bg-blue-100 text-blue-700';
                    else if(sLower === 'shipped') statusColor = 'bg-indigo-100 text-indigo-700';
                    else if(sLower === 'delivered' || sLower === 'completed') statusColor = 'bg-emerald-100 text-emerald-700';
                    else if(sLower === 'cancelled') statusColor = 'bg-rose-100 text-rose-700';
                    
                    let html = `
                        <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm mb-4">
                            <div class="flex justify-between items-center mb-4 pb-4 border-b border-slate-100">
                                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Order ID</p><p class="font-black text-slate-800">#${order.id}</p></div>
                                <div class="text-right"><p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Status</p><span class="${statusColor} px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-widest inline-block">${order.status}</span></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Order Date</p><p class="text-xs font-bold text-slate-700">${date}</p></div>
                                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Payment Method</p><p class="text-xs font-bold text-slate-700">${order.paymentMethod || 'N/A'}</p></div>
                            </div>
                        </div>
                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-3 ml-1">Order Items</h4>
                        <div class="space-y-3 mb-5">
                    `;
                    
                    result.items.forEach(item => {
                        let img = item.vImg ? item.vImg.split(',')[0] : (item.pImg ? item.pImg.split(',')[0] : 'https://placehold.co/100');
                        if (!img.startsWith('http')) img = (img.startsWith('/') ? '' : '/') + img;
                        const escapeHtml = (str) => (str || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                        let variantText = '';
                        if (item.size && item.size !== 'Standard') variantText += `Size: ${item.size} `;
                        if (item.color && item.color !== 'Default') variantText += `Color: ${item.color}`;
                        
                        html += `
                        <div class="bg-white p-3 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3 md:gap-4">
                            <div class="w-14 h-14 md:w-16 md:h-16 bg-slate-50 rounded-xl overflow-hidden shrink-0 border border-slate-100 p-1">
                                <img src="${img}" class="w-full h-full object-contain mix-blend-multiply">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-slate-800 text-xs md:text-sm truncate">${escapeHtml(item.name)}</p>
                                ${variantText ? `<p class="text-[9px] md:text-[10px] font-bold text-slate-400 mt-0.5">${escapeHtml(variantText)}</p>` : ''}
                                <div class="flex justify-between items-center mt-1.5 md:mt-2">
                                    <p class="text-xs font-bold text-slate-500">${item.quantity} x ৳${Number(item.price).toLocaleString()}</p>
                                    <p class="text-sm font-black text-[#003e86]">৳${Number(item.price * item.quantity).toLocaleString()}</p>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += `
                        </div>
                        <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <p class="text-xs md:text-sm font-bold text-slate-500">Subtotal</p>
                                <p class="text-xs md:text-sm font-bold text-slate-700">৳${Number((order.total || 0) - (order.shippingCharge || 0) + Number(order.discountAmount || 0)).toLocaleString()}</p>
                            </div>
                            ${order.discountAmount > 0 ? `
                            <div class="flex justify-between items-center mb-2">
                                <p class="text-xs md:text-sm font-bold text-emerald-600">Discount ${order.couponCode ? '(' + order.couponCode + ')' : ''}</p>
                                <p class="text-xs md:text-sm font-bold text-emerald-600">-৳${Number(order.discountAmount).toLocaleString()}</p>
                            </div>` : ''}
                            <div class="flex justify-between items-center mb-4 pb-4 border-b border-slate-100">
                                <p class="text-xs md:text-sm font-bold text-slate-500">Shipping Fee</p>
                                <p class="text-xs md:text-sm font-bold text-slate-700">৳${Number(order.shippingCharge || 0).toLocaleString()}</p>
                            </div>
                            <div class="flex justify-between items-center">
                                <p class="text-sm md:text-base font-black text-slate-800">Total Amount</p>
                                <p class="text-lg md:text-xl font-black text-[#003e86]">৳${Number(order.total).toLocaleString()}</p>
                            </div>
                        </div>
                    `;
                    
                    if (order.status.toUpperCase() === 'CANCELLED' && order.cancelReason) {
                        const escapeHtml = (str) => (str || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                        html += `
                        <div class="mt-4 bg-rose-50 border border-rose-100 rounded-2xl p-4 md:p-5">
                            <h4 class="text-sm font-bold text-rose-700 mb-2 flex items-center gap-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Cancellation Reason
                            </h4>
                            <p class="text-xs text-rose-600">${escapeHtml(order.cancelReason)}</p>
                        </div>
                        `;
                    }
                    
                    if (order.status.toUpperCase() === 'PENDING') {
                        html += `
                        <div class="mt-4 bg-rose-50 border border-rose-100 rounded-2xl p-4 md:p-5">
                            <h4 class="text-sm font-bold text-rose-700 mb-2">Cancel Order</h4>
                            <p class="text-xs text-rose-600 mb-3">You can cancel this order since it is still pending.</p>
                            <div class="space-y-2">
                                <select id="cancel-reason-${order.id}" onchange="const other = document.getElementById('cancel-reason-other-${order.id}'); if(this.value === 'Other') { other.classList.remove('hidden'); other.focus(); } else { other.classList.add('hidden'); }" class="w-full px-4 py-3 bg-white border border-rose-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 outline-none transition-all cursor-pointer">
                                    <option value="">Select a reason...</option>
                                    <option value="Changed my mind">Changed my mind</option>
                                    <option value="Found a better price elsewhere">Found a better price elsewhere</option>
                                    <option value="Delivery time is too long">Delivery time is too long</option>
                                    <option value="Ordered by mistake">Ordered by mistake</option>
                                    <option value="Duplicate order">Duplicate order</option>
                                    <option value="Shipping cost is too high">Shipping cost is too high</option>
                                    <option value="Other">Other</option>
                                </select>
                                <textarea id="cancel-reason-other-${order.id}" placeholder="Please type your reason here..." class="hidden w-full mt-2 px-4 py-3 bg-white border border-rose-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 outline-none transition-all resize-none" rows="2"></textarea>
                                <button type="button" onclick="cancelOrder(${order.id})" id="btn-cancel-${order.id}" class="w-full py-3 bg-rose-500 hover:bg-rose-600 text-white rounded-xl font-bold text-sm transition-colors flex justify-center items-center gap-2">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg> Cancel Order
                                </button>
                            </div>
                        </div>
                        `;
                    }

                    body.innerHTML = html;
                } else {
                    body.innerHTML = `<div class="p-6 text-center text-rose-500 font-bold bg-white rounded-2xl shadow-sm border border-slate-100">${result.message || 'Failed to load order details'}</div>`;
                }
            })
            .catch(err => {
                body.innerHTML = `<div class="p-6 text-center text-rose-500 font-bold bg-white rounded-2xl shadow-sm border border-slate-100">Error loading details.</div>`;
            });
    }

    function closeOrderModal() {
        const modal = document.getElementById('order-modal');
        const content = document.getElementById('order-modal-content');
        modal.classList.add('opacity-0');
        content.classList.add('translate-y-full', 'md:scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
    
    async function cancelOrder(orderId) {
        const reasonSelect = document.getElementById('cancel-reason-' + orderId);
        const reasonOther = document.getElementById('cancel-reason-other-' + orderId);
        let reason = reasonSelect.value.trim();
        const btn = document.getElementById('btn-cancel-' + orderId);
        
        if (reason === 'Other') {
            reason = reasonOther.value.trim();
            if (!reason) {
                if (window.showToast) showToast('Please type your reason for cancellation.', 'error');
                reasonOther.focus();
                return;
            }
        } else if (!reason) {
            if (window.showToast) showToast('Please select a reason for cancellation.', 'error');
            reasonSelect.focus();
            return;
        }
        
        const confirmMessage = 'Are you sure you want to cancel this order?';
        const executeCancel = async () => {
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Cancelling...';
            btn.disabled = true;
            
            try {
                const response = await fetch('account.php?action=cancel_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, reason: reason })
                });
                
                const text = await response.text();
                let result;
                try { result = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON response'); }
                
                if (result.success) {
                    if (window.showToast) showToast(result.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    if (window.showToast) showToast(result.message, 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error(error);
                if (window.showToast) showToast('Network error. Try again.', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        };

        if (window.customConfirm) {
            customConfirm(confirmMessage, executeCancel);
        } else {
            if (confirm(confirmMessage)) {
                executeCancel();
            }
        }
    }

    let profileTimerInterval;
    function startProfileTimer(duration) {
        clearInterval(profileTimerInterval);
        const timerEl = document.getElementById('profile-timer');
        const resendBtn = document.getElementById('btn-resend-profile-otp');
        let timer = duration;
        
        timerEl.classList.remove('hidden');
        resendBtn.classList.add('hidden');
        
        profileTimerInterval = setInterval(function () {
            let minutes = parseInt(timer / 60, 10);
            let seconds = parseInt(timer % 60, 10);
            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;
            timerEl.textContent = minutes + ":" + seconds;
            
            if (--timer < 0) {
                clearInterval(profileTimerInterval);
                timerEl.classList.add('hidden');
                resendBtn.classList.remove('hidden');
            }
        }, 1000);
    }
    
    function openProfileOtpModal() {
        const modal = document.getElementById('profile-otp-modal');
        const content = document.getElementById('profile-otp-modal-content');
        document.getElementById('profile-otp-input').value = '';
        document.getElementById('profile-verify-error').classList.add('hidden');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        content.classList.remove('translate-y-full', 'md:scale-95');
        startProfileTimer(150);
    }

    function closeProfileOtpModal() {
        const modal = document.getElementById('profile-otp-modal');
        const content = document.getElementById('profile-otp-modal-content');
        modal.classList.add('opacity-0');
        content.classList.add('translate-y-full', 'md:scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    async function resendProfileOtp() {
        const btn = document.getElementById('btn-resend-profile-otp');
        btn.innerText = 'Sending...';
        btn.disabled = true;
        
        const form = document.getElementById('profile-form');
        const division = document.getElementById('hidden-division').value.trim();
        const district = document.getElementById('hidden-district').value.trim();
        const upazila = document.getElementById('hidden-upazila').value.trim();
        const street = document.getElementById('hidden-street').value.trim();
        let combinedAddress = street;
        if (upazila && district && division) {
            combinedAddress = street.trim() + "\n" + `Upazila: ${upazila}, District: ${district}, Division: ${division}`;
        }

        const data = {
            name: form.name.value, email: form.email.value, phone: form.phone.value,
            address: combinedAddress, password: form.password.value
        };
        
        try {
            const res = await fetch('account.php?action=request_profile_update', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                if (window.showToast) showToast("OTP Resent Successfully", "success");
                startProfileTimer(150);
            } else { if (window.showToast) showToast(result.message, "error"); }
        } catch (error) {
            if (window.showToast) showToast("Network error. Please try again.", "error");
        } finally {
            btn.innerText = 'Resend Code';
            btn.disabled = false;
        }
    }

    async function handleProfileUpdate(e) {
        e.preventDefault();
        const form = e.target;
        const btn = document.getElementById('btn-save-profile');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Saving...'; btn.disabled = true;

        const division = document.getElementById('hidden-division').value.trim();
        const district = document.getElementById('hidden-district').value.trim();
        const upazila = document.getElementById('hidden-upazila').value.trim();
        const street = document.getElementById('hidden-street').value.trim();
        let combinedAddress = street;
        if (upazila && district && division) {
            combinedAddress = street.trim() + "\n" + `Upazila: ${upazila}, District: ${district}, Division: ${division}`;
        }

        const data = {
            name: form.name.value, email: form.email.value, phone: form.phone.value,
            address: combinedAddress, password: form.password.value
        };
        try {
            const res = await fetch('account.php?action=request_profile_update', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                if (result.otp_required) {
                    document.getElementById('profile-otp-msg').innerText = result.message;
                    openProfileOtpModal();
                } else {
                    if (window.showToast) showToast(result.message, "success");
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                if (window.showToast) showToast(result.message, "error");
            }
        } catch (error) {
            if (window.showToast) showToast("Network error. Please try again.", "error");
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    
    async function verifyProfileUpdate() {
        const otp = document.getElementById('profile-otp-input').value;
        const errorEl = document.getElementById('profile-verify-error');
        if (!otp) { errorEl.innerText = 'Please enter the OTP.'; errorEl.classList.remove('hidden'); return; }
        errorEl.classList.add('hidden');
        const btn = document.getElementById('btn-verify-profile');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Verifying...'; btn.disabled = true;
        try {
            const response = await fetch('account.php?action=verify_profile_otp', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ otp: otp })
            });
            const result = await response.json();
            if (result.success) {
                if (window.showToast) showToast(result.message, "success");
                closeProfileOtpModal(); setTimeout(() => window.location.reload(), 1000);
            } else { errorEl.innerText = result.message; errorEl.classList.remove('hidden'); }
        } catch (error) { errorEl.innerText = 'Network error.'; errorEl.classList.remove('hidden'); } 
        finally { btn.innerHTML = originalText; btn.disabled = false; }
    }
</script>

<?php include 'Footer.php'; ?>