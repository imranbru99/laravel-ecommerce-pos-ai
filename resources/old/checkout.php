<?php
session_start();
require_once __DIR__ . '/db.php';

// Fetch Store Settings
try {
    $setStmt = $pdo->query("SELECT g.currencySymbol, g.storeName, g.supportEmail, g.guestCheckoutEnabled, d.deliveryInsideDhaka, d.deliveryOutsideDhaka, p.codEnabled, p.bkashEnabled FROM SettingGeneral g LEFT JOIN SettingDelivery d ON d.id=g.id LEFT JOIN SettingPayment p ON p.id=g.id LIMIT 1");
    $settings = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $settings = [];
}
$symbol = $settings['currencySymbol'] ?? '৳';
$storeName = $settings['storeName'] ?? '';

$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if (!$isLoggedIn && empty($settings['guestCheckoutEnabled'])) {
    header("Location: login");
    exit;
}

$user = ['name' => '', 'email' => '', 'phone' => ''];
$lastOrder = ['city' => 'Dhaka', 'deliveryAddress' => ''];
$userId = null;
$isBlocked = false;

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM User WHERE id = ?");
    $stmt->execute([$userId]);
    $fetchedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetchedUser) {
        $user = $fetchedUser;
        $isBlocked = ($user['status'] ?? 'active') === 'blocked';
    }
    
    $orderStmt = $pdo->prepare("SELECT city, deliveryAddress FROM `Order` WHERE userId = ? ORDER BY id DESC LIMIT 1");
    $orderStmt->execute([$userId]);
    $fetchedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if ($fetchedOrder) {
        $lastOrder = $fetchedOrder;
    }
}

$defaultAddress = !empty($user['address']) ? $user['address'] : ($lastOrder['deliveryAddress'] ?? '');

try {
    $pdo->exec("ALTER TABLE `Order` ADD COLUMN `couponCode` VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE `Order` ADD COLUMN `discountAmount` DECIMAL(10,2) DEFAULT 0.00");
} catch (Exception $e) {}

// Handle AJAX Request for fetching user by phone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_user_by_phone') {
    header('Content-Type: application/json');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($phone) || strlen($phone) !== 11) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT u.name, u.email, u.address as userAddress, o.city, o.deliveryAddress as orderAddress FROM User u LEFT JOIN `Order` o ON u.id = o.userId WHERE u.phone = ? ORDER BY o.id DESC LIMIT 1");
    $stmt->execute([$phone]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $data['deliveryAddress'] = !empty($data['userAddress']) ? $data['userAddress'] : $data['orderAddress'];
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

$allActiveCoupons = [];
$usedCoupons = [];
if ($isLoggedIn) {
    $couponsStmt = $pdo->prepare("SELECT * FROM Coupon WHERE status = 1 AND (expiryDate IS NULL OR expiryDate > NOW()) AND (usageLimit IS NULL OR usedCount < usageLimit) ORDER BY id DESC");
    $couponsStmt->execute();
    $allActiveCoupons = $couponsStmt->fetchAll(PDO::FETCH_ASSOC);

    $usedCouponsStmt = $pdo->prepare("SELECT couponCode, COUNT(*) as count FROM `Order` WHERE userId = ? AND status != 'CANCELLED' AND couponCode IS NOT NULL GROUP BY couponCode");
    $usedCouponsStmt->execute([$userId]);
    $usedCoupons = $usedCouponsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}


// Handle Apply Coupon AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_coupon') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subtotal = floatval($_POST['subtotal'] ?? 0);

    if (empty($code)) { echo json_encode(['success' => false, 'message' => 'Please enter a coupon code.']); exit; }
    if (empty($phone) && !$isLoggedIn) { echo json_encode(['success' => false, 'message' => 'Please enter your mobile number first.']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM Coupon WHERE code = ? AND status = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) { echo json_encode(['success' => false, 'message' => 'Invalid or inactive coupon.']); exit; }
    if ($coupon['expiryDate'] && strtotime($coupon['expiryDate']) < time()) { echo json_encode(['success' => false, 'message' => 'This coupon has expired.']); exit; }
    if ($coupon['usageLimit'] > 0 && $coupon['usedCount'] >= $coupon['usageLimit']) { echo json_encode(['success' => false, 'message' => 'Coupon usage limit reached.']); exit; }
    if ($coupon['minSpend'] > 0 && $subtotal < $coupon['minSpend']) { echo json_encode(['success' => false, 'message' => 'Minimum spend of ৳' . number_format($coupon['minSpend']) . ' required.']); exit; }

    $cUserId = null; $orderCount = 0;
    if ($isLoggedIn) {
        $cUserId = $_SESSION['user_id'];
        $uStmt = $pdo->prepare("SELECT phone, email FROM User WHERE id = ?");
        $uStmt->execute([$cUserId]);
        $uData = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uData) { $phone = $phone ?: $uData['phone']; $email = $email ?: $uData['email']; }
    } else if (!empty($phone)) {
        $uStmt = $pdo->prepare("SELECT id FROM User WHERE phone = ? LIMIT 1");
        $uStmt->execute([$phone]);
        $cUserId = $uStmt->fetchColumn();
    }

    if ($cUserId) {
        $oStmt = $pdo->prepare("SELECT COUNT(*) FROM `Order` WHERE userId = ? AND status != 'CANCELLED'");
        $oStmt->execute([$cUserId]);
        $orderCount = $oStmt->fetchColumn();
    }

    if ($coupon['couponType'] === 'WELCOME') {
        if ($orderCount > 0) { echo json_encode(['success' => false, 'message' => 'This coupon is valid for first-time orders only.']); exit; }
    } else if ($coupon['couponType'] === 'USER_SPECIFIC') {
        $allowed = array_map('trim', explode(',', $coupon['applicableData'] ?? ''));
        if (!in_array($phone, $allowed) && !in_array($email, $allowed)) { echo json_encode(['success' => false, 'message' => 'This coupon is not applicable for your account.']); exit; }
    }

    if ($coupon['usagePerUser'] > 0 && $cUserId) {
        $uStmt = $pdo->prepare("SELECT COUNT(*) FROM `Order` WHERE couponCode = ? AND userId = ? AND status != 'CANCELLED'");
        $uStmt->execute([$code, $cUserId]);
        $usedByUser = $uStmt->fetchColumn();
        if ($usedByUser >= $coupon['usagePerUser']) { echo json_encode(['success' => false, 'message' => 'You have already used this coupon maximum allowed times.']); exit; }
    }

    $discount = 0;
    if ($coupon['discountType'] === 'PERCENTAGE') {
        $discount = ($subtotal * $coupon['discountAmount']) / 100;
    } else {
        $discount = $coupon['discountAmount'];
    }
    if ($discount > $subtotal) { $discount = $subtotal; }

    echo json_encode(['success' => true, 'discount' => $discount, 'code' => $coupon['code'], 'message' => 'Coupon applied successfully!']);
    exit;
}

// Handle Order Placement via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    header('Content-Type: application/json');
    try {
        $phone = trim($_POST['phone'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'CASH ON DELIVERY';
        $cartData = json_decode($_POST['cart_data'], true);
        
        if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
            throw new Exception("Please enter a valid 11-digit mobile number.");
        }
        
        if (empty($name) || empty($city) || empty($address)) {
            throw new Exception("Please fill out all required delivery information.");
        }
        if (empty($cartData)) {
            throw new Exception("Your cart is empty.");
        }

        // Fetch API Settings for Fraud Checker
        try {
            $apiStmt = $pdo->query("SELECT * FROM SettingApi LIMIT 1");
            $apiSettings = $apiStmt ? $apiStmt->fetch(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            $apiSettings = [];
        }
        
        // Fraud Checker Block Logic
        if (!empty($apiSettings['fraudCheckerAutoBlock']) && $paymentMethod === 'CASH ON DELIVERY') {
            $minRate = (int)($apiSettings['fraudCheckerMinRate'] ?? 50);
            
            // Check success rate internally based on phone
            $historyStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_resolved,
                    SUM(CASE WHEN o.status = 'DELIVERED' THEN 1 ELSE 0 END) as delivered
                FROM `Order` o 
                JOIN User u ON o.userId = u.id 
                WHERE u.phone = ? AND o.status IN ('DELIVERED', 'CANCELLED')
            ");
            $historyStmt->execute([$phone]);
            $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($history && $history['total_resolved'] > 0) {
                $successRate = ($history['delivered'] / $history['total_resolved']) * 100;
                if ($successRate < $minRate) {
                    throw new Exception("Your delivery success rate is " . round($successRate) . "%. Minimum required is {$minRate}%. Cash on Delivery is disabled for your account. Please use an advance payment method.");
                }
            }
        }
        
        // Calculate Subtotal safely
        $subtotal = 0;
        foreach ($cartData as $item) {
            $subtotal += floatval($item['price']) * intval($item['quantity']);
        }
        
        $couponCode = $_POST['coupon_code'] ?? null;
        $discountAmount = 0;

        if (!empty($couponCode)) {
            $stmt = $pdo->prepare("SELECT * FROM Coupon WHERE code = ? AND status = 1");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($coupon && (!$coupon['expiryDate'] || strtotime($coupon['expiryDate']) >= time())) {
                if ($coupon['minSpend'] == 0 || $subtotal >= $coupon['minSpend']) {
                    // Additional security checks for coupon could be added here
                    $isValid = true;
                    if ($coupon['couponType'] === 'USER_SPECIFIC') {
                        $allowed = array_map('trim', explode(',', $coupon['applicableData'] ?? ''));
                        if (!in_array($phone, $allowed) && !in_array($email, $allowed)) {
                            $isValid = false;
                        }
                    }
                    if ($isValid) {
                        if ($coupon['discountType'] === 'PERCENTAGE') {
                            $discountAmount = ($subtotal * $coupon['discountAmount']) / 100;
                        } else {
                            $discountAmount = $coupon['discountAmount'];
                        }
                        if ($discountAmount > $subtotal) $discountAmount = $subtotal;
                        
                        $pdo->prepare("UPDATE Coupon SET usedCount = usedCount + 1 WHERE id = ?")->execute([$coupon['id']]);
                    } else {
                        $couponCode = null;
                    }
                } else {
                    $couponCode = null;
                }
            } else {
                $couponCode = null;
            }
        }
        
        // Determine Shipping Charge
        $shippingCharge = (strtolower($city) === 'dhaka') ? floatval($settings['deliveryInsideDhaka']) : floatval($settings['deliveryOutsideDhaka']);
        $total = $subtotal + $shippingCharge - $discountAmount;
        if ($total < 0) $total = 0;
        
        $pdo->beginTransaction();
        
        $orderUserId = null;
        if (!$isLoggedIn) {
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id FROM User WHERE phone = ? OR email = ? LIMIT 1");
                $stmt->execute([$phone, $email]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM User WHERE phone = ? LIMIT 1");
                $stmt->execute([$phone]);
            }
            $existingUserId = $stmt->fetchColumn();
            
            if ($existingUserId) {
                $orderUserId = $existingUserId;
                $pdo->prepare("UPDATE User SET name = ?, phone = ? WHERE id = ?")->execute([$name, $phone, $orderUserId]);
            } else {
                $safeDomain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $storeName ?: 'store')) . '.com';
                $dummyEmail = !empty($email) ? $email : 'guest_' . time() . '@' . $safeDomain;
                $dummyPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                
                do {
                    $orderUserId = mt_rand(100000, 999999);
                    $check = $pdo->prepare("SELECT id FROM User WHERE id = ?");
                    $check->execute([$orderUserId]);
                } while ($check->fetch());
                
                $stmt = $pdo->prepare("INSERT INTO User (id, name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'customer')");
                $stmt->execute([$orderUserId, $name, $dummyEmail, $phone, $dummyPass]);
            }
        } else {
            $orderUserId = $userId;
            $checkStatus = $pdo->prepare("SELECT status FROM User WHERE id = ?");
            $checkStatus->execute([$orderUserId]);
            if ($checkStatus->fetchColumn() === 'blocked') {
                throw new Exception("Your account has been blocked. You cannot place orders.");
            }
            if (empty($user['phone'])) {
                $pdo->prepare("UPDATE User SET phone = ? WHERE id = ?")->execute([$phone, $orderUserId]);
            }
        }

        // Generate Unique Order ID (6 digits)
        do {
            $orderId = mt_rand(100000, 999999);
            $check = $pdo->prepare("SELECT id FROM `Order` WHERE id = ?");
            $check->execute([$orderId]);
        } while ($check->fetch());
        
        // Insert into Order table
        $stmt = $pdo->prepare("INSERT INTO `Order` (id, userId, total, shippingCharge, deliveryAddress, city, paymentMethod, status, couponCode, discountAmount) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)");
        $stmt->execute([$orderId, $orderUserId, $total, $shippingCharge, $address, $city, $paymentMethod, $couponCode, $discountAmount]);
        
        // Insert into OrderItem table and reduce stock
        foreach ($cartData as $item) {
            $productId = $item['productId'] ?? $item['id'];
            $qty = intval($item['quantity']);
            $price = floatval($item['price']);
            
            $stmt = $pdo->prepare("INSERT INTO OrderItem (orderId, productId, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $productId, $qty, $price]);
        }
        
        if ($paymentMethod !== 'BKASH') {
        // Send Notification
        $notifMsg = "Your order #$orderId has been placed successfully and is pending confirmation.";
        $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, 'Order Placed', ?, 'order')")->execute([$orderUserId, $notifMsg]);
        $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'New Order Received', ?, 'order')")->execute(["Order #$orderId has been placed by " . $name . "."]);

        $notifStmt = $pdo->query("SELECT * FROM SettingNotification LIMIT 1");
        $notifSettings = $notifStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Send Order Placed Email
        if (!empty($notifSettings['emailEnabled']) && !empty($email)) {
            $template = $notifSettings['orderPlacedTemplate'] ?? '';
            if ($template) {
                $itemsHtml = '';
                foreach ($cartData as $item) {
                    $itemName = htmlspecialchars($item['name'] ?? 'Item');
                    $qty = intval($item['quantity']);
                    $price = floatval($item['price']) * $qty;
                    $itemsHtml .= '<tr><td style="padding-bottom: 10px;">' . $itemName . ' x ' . $qty . '</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">' . $symbol . number_format($price) . '</td></tr>';
                }
                if ($discountAmount > 0) {
                    $itemsHtml .= '<tr><td style="padding-bottom: 10px; color: #10b981;">Discount</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #10b981;">-' . $symbol . number_format($discountAmount) . '</td></tr>';
                }
                $itemsHtml .= '<tr><td style="padding-bottom: 10px;">Shipping (' . htmlspecialchars($city) . ')</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">' . $symbol . number_format($shippingCharge) . '</td></tr>';
                
                $supportEmail = $settings['supportEmail'] ?? 'support@' . $_SERVER['HTTP_HOST'];
                $totalStr = $symbol . number_format($total);
                
                $search = ['{{customer_name}}', '{{order_id}}', '{{store_name}}', 'Idea Mart', '{{order_items_html}}', '{{total_amount}}', '{{shipping_address}}', '{{customer_phone}}', '{{support_email}}'];
                $replace = [$name, str_pad($orderId, 6, '0', STR_PAD_LEFT), $storeName, $storeName, $itemsHtml, $totalStr, nl2br(htmlspecialchars($address)), htmlspecialchars($phone), $supportEmail];
                $htmlContent = str_replace($search, $replace, $template);
                
                require_once __DIR__ . '/api/mailer_helper.php';
                $result = sendDynamicEmail($pdo, $email, "Order Confirmed - #" . str_pad($orderId, 6, '0', STR_PAD_LEFT), $htmlContent);
                if (is_array($result) && empty($result['success'])) {
                    $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Email Delivery Error', ?, 'alert')")->execute(["Failed to send order confirmation email to $email for Order #$orderId. Error: " . ($result['error'] ?? 'Unknown')]);
                }
            }
        }
        
        // Send Order Placed SMS
        if (!empty($notifSettings['smsEnabled']) && !empty($notifSettings['smsApiUrl']) && !empty($phone)) {
            $template = $notifSettings['orderPlacedSmsTemplate'] ?? '';
            if ($template) {
                $message = str_replace(['{{customer_name}}', '{{order_id}}', '{{store_name}}', 'Idea Mart'], [$name, str_pad($orderId, 6, '0', STR_PAD_LEFT), $storeName, $storeName], $template);
                $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($phone), $notifSettings['smsApiUrl']);
                $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($message), $apiUrl);
                $smsResult = @file_get_contents($apiUrl);
                if ($smsResult === false) {
                    $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'SMS API Error', ?, 'alert')")->execute(["Failed to send order confirmation SMS to $phone for Order #$orderId."]);
                }
            }
        }
        }

        $pdo->commit();
        
        echo json_encode(['success' => true, 'order_id' => $orderId, 'email' => $email, 'payment_method' => $paymentMethod]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

include 'Header.php';
?>

<div class="min-h-screen pt-24 md:pt-28 pb-28 md:pb-24 bg-slate-50 font-sans">
    <div class="max-w-[1200px] mx-auto px-6">
        
        <?php if (isset($_GET['success']) && isset($_GET['order_id'])): ?>
            <!-- SUCCESS STATE -->
            <div class="max-w-xl mx-auto bg-white p-10 sm:p-12 rounded-[2rem] shadow-xl border border-slate-100 text-center animate-in zoom-in duration-500 mt-8">
                <div class="w-24 h-24 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mb-3 tracking-tight">Order Confirmed!</h1>
                <p class="text-slate-500 font-medium mb-8 text-sm sm:text-base leading-relaxed">Thank you for your purchase. We've received your order and our team will start processing it soon.</p>
                
                <div class="bg-slate-50 rounded-2xl p-5 mb-8 border border-slate-200 shadow-inner inline-block min-w-[200px]">
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mb-1">Your Order ID</p>
                    <p class="text-3xl font-black text-[#003e86]">#<?php echo htmlspecialchars($_GET['order_id']); ?></p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="shop" class="flex-1 py-4 bg-white border-2 border-slate-200 text-slate-700 hover:border-[#003e86] hover:text-[#003e86] hover:bg-blue-50 rounded-xl font-bold transition-all active:scale-95">Continue Shopping</a>
                    <form action="track-order" method="POST" class="flex-1">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($_GET['order_id']); ?>">
                        <input type="hidden" name="contact" value="<?php echo htmlspecialchars($_GET['email'] ?? $user['email']); ?>">
                        <button type="submit" class="w-full py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-xl shadow-blue-900/20 transition-all active:scale-95">Track Order</button>
                    </form>
                </div>
            </div>
            <script>
                localStorage.removeItem('cart'); // Clear cart on success
                window.dispatchEvent(new Event('cartUpdated'));
            </script>
        <?php elseif (isset($_GET['payment_failed'])): ?>
            <!-- FAILURE STATE -->
            <div class="max-w-xl mx-auto bg-white p-10 sm:p-12 rounded-[2rem] shadow-xl border border-slate-100 text-center animate-in zoom-in duration-500 mt-8">
                <div class="w-24 h-24 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mb-3 tracking-tight">Payment Failed</h1>
                <p class="text-slate-500 font-medium mb-8 text-sm sm:text-base leading-relaxed">
                    <?php echo htmlspecialchars($_GET['msg'] ?? "Unfortunately, your payment could not be processed or was cancelled. Your order was not placed. You can try again."); ?>
                </p>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="checkout" class="flex-1 py-4 bg-[#003e86] hover:bg-blue-800 text-white rounded-xl font-bold shadow-xl shadow-blue-900/20 transition-all active:scale-95">Try Again</a>
                    <a href="shop" class="flex-1 py-4 bg-white border-2 border-slate-200 text-slate-700 hover:border-[#003e86] hover:text-[#003e86] hover:bg-blue-50 rounded-xl font-bold transition-all active:scale-95">Return to Shop</a>
                </div>
            </div>
        <?php else: ?>
            <!-- CHECKOUT FORM -->
            <h1 class="text-3xl font-black text-slate-900 mb-8 tracking-tight">Checkout</h1>
            
            <?php if ($isBlocked): ?>
            <div class="mb-8 bg-rose-50 border border-rose-200 rounded-3xl p-5 md:p-6 shadow-sm flex items-start gap-4 text-rose-700">
                <div class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center shrink-0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                </div>
                <div>
                    <h3 class="font-black text-base md:text-lg">Account Suspended</h3>
                    <p class="text-sm font-medium mt-1 leading-relaxed text-rose-600">Your account has been restricted by the administrator. You cannot place new orders.</p>
                </div>
            </div>
            <?php endif; ?>

            <form id="checkout-form" class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                <!-- Left: Shipping Details -->
                <div class="lg:col-span-7 space-y-8">
                    
                <!-- Step 1: Mobile Number Section -->
                    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-[#003e86]"></div>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-8 h-8 rounded-full bg-blue-50 text-[#003e86] flex items-center justify-center font-black text-sm shrink-0">1</div>
                            <h2 class="text-xl font-black text-slate-800 tracking-tight">Contact Information</h2>
                        </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 block">Mobile Number <span class="text-rose-500">*</span></label>
                        <input type="tel" id="checkout-phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required placeholder="e.g. 01700000000" pattern="[0-9]{11}" maxlength="11" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?php echo ($isLoggedIn && !empty($user['phone'])) ? 'readonly' : ''; ?> <?php echo $isBlocked ? 'disabled' : ''; ?>>
                        <p id="phone-msg" class="text-[11px] text-[#003e86] hidden ml-1 font-bold mt-2 flex items-center gap-1.5"><svg class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v4"/></svg> Checking details...</p>
                        </div>
                    </div>

                <!-- Hidden Sections for Guest -->
                <div id="hidden-checkout-sections" class="animate-in fade-in slide-in-from-top-4 duration-500 <?php echo $isLoggedIn ? 'space-y-8' : 'hidden space-y-8'; ?>">
                    <!-- Shipping Address -->
                    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-[#003e86]"></div>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-8 h-8 rounded-full bg-blue-50 text-[#003e86] flex items-center justify-center font-black text-sm shrink-0">2</div>
                            <h2 class="text-xl font-black text-slate-800 tracking-tight">Delivery Details</h2>
                        </div>
                        <div class="space-y-5">
                            <input type="hidden" id="checkout-email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 block">Full Name <span class="text-rose-500">*</span></label>
                                <input type="text" id="checkout-name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required <?php echo $isLoggedIn ? 'readonly' : ''; ?> class="w-full px-5 py-3.5 <?php echo $isLoggedIn ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-slate-50 text-slate-800'; ?> border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $isBlocked ? 'disabled' : ''; ?>>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 block">City / Region <span class="text-rose-500">*</span></label>
                                <select id="city-select" name="city" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $isBlocked ? 'disabled' : ''; ?>>
                                    <option value="Dhaka" <?php echo ($lastOrder['city'] ?? '') === 'Dhaka' ? 'selected' : ''; ?>>Inside Dhaka</option>
                                    <option value="Outside Dhaka" <?php echo ($lastOrder['city'] ?? '') === 'Outside Dhaka' ? 'selected' : ''; ?>>Outside Dhaka</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 block">Full Address <span class="text-rose-500">*</span></label>
                                <textarea id="checkout-address" name="address" required rows="3" placeholder="House/Flat No, Area, Thana..." class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:bg-white focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all resize-none disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $isBlocked ? 'disabled' : ''; ?>><?php echo htmlspecialchars($defaultAddress); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white p-6 sm:p-8 rounded-[2rem] border border-slate-100 shadow-sm relative transition-all hover:shadow-md">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-500 to-[#003e86] text-white flex items-center justify-center font-black text-base shadow-lg shadow-blue-500/30 transform -rotate-3 shrink-0">3</div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Payment Method</h2>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                            <?php if (!empty($settings['codEnabled'])): ?>
                            <label class="payment-radio relative flex sm:flex-col items-center sm:items-start gap-4 p-5 rounded-[1.5rem] border-2 border-emerald-500 bg-emerald-50/50 cursor-pointer transition-all hover:shadow-lg hover:shadow-emerald-500/10 hover:-translate-y-1 duration-300">
                                <div class="hidden sm:block absolute top-0 right-0 bg-emerald-500 text-white text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-bl-[1rem] rounded-tr-[1.25rem] shadow-sm">Pay at Home</div>
                                <input type="radio" name="payment_method" value="CASH ON DELIVERY" checked class="peer sr-only">
                                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm border border-emerald-100 shrink-0">
                                    <svg width="20" height="20" class="sm:w-6 sm:h-6 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="font-black text-slate-800 text-base block truncate">Cash on Delivery</span>
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 font-semibold mt-0.5 leading-relaxed block truncate">Pay with cash upon delivery.</span>
                                </div>
                                <div class="w-6 h-6 rounded-full border-2 border-emerald-500 flex items-center justify-center bg-emerald-500 text-white transition-all radio-check shrink-0 shadow-sm">
                                    <svg width="12" height="12" class="sm:w-3.5 sm:h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                </div>
                            </label>
                            <?php endif; ?>
                            <?php if (!empty($settings['bkashEnabled'])): ?>
                            <label class="payment-radio relative flex sm:flex-col items-center sm:items-start gap-4 p-5 rounded-[1.5rem] border-2 border-slate-200 bg-white cursor-pointer transition-all hover:shadow-lg hover:shadow-pink-500/10 hover:border-pink-300 hover:-translate-y-1 duration-300">
                                <div class="hidden sm:block absolute top-0 right-0 bg-pink-500 text-white text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-bl-[1rem] rounded-tr-[1.25rem] shadow-sm">Fast & Secure</div>
                                <input type="radio" name="payment_method" value="BKASH" <?php echo empty($settings['codEnabled']) ? 'checked' : ''; ?> class="peer sr-only">
                                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm border border-slate-100 p-2 shrink-0">
                                    <svg viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                                      <path d="M256 0C114.615 0 0 114.615 0 256C0 397.385 114.615 512 256 512C397.385 512 512 397.385 512 256C512 114.615 397.385 0 256 0Z" fill="#E2136E"/>
                                      <path d="M331.066 333.155H240.231L204.606 373.35H132.551V148.271H331.066V333.155ZM226.438 231.815H273.197V269.497H226.438V231.815Z" fill="white"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="font-black text-slate-800 text-base block truncate">bKash Payment</span>
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 font-semibold mt-0.5 leading-relaxed block truncate">Pay securely via bKash.</span>
                                </div>
                                <div class="w-6 h-6 rounded-full border-2 border-slate-300 flex items-center justify-center text-transparent transition-all radio-check shrink-0 shadow-sm">
                                    <svg width="12" height="12" class="sm:w-3.5 sm:h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                </div>
                            </label>
                            <?php endif; ?>
                            <?php if (empty($settings['codEnabled']) && empty($settings['bkashEnabled'])): ?>
                                <p class="text-sm text-rose-500 font-bold bg-rose-50 p-4 rounded-xl col-span-2">No payment methods available right now. Please contact support.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Right: Order Summary -->
                <div class="lg:col-span-5">
                    <div class="bg-white rounded-[2rem] shadow-[0_0_40px_-10px_rgba(0,0,0,0.08)] border border-slate-100 p-6 sm:p-8 sticky top-28 overflow-hidden relative">
                        <h2 class="text-xl font-black text-slate-900 mb-6">Order Summary</h2>
                        <div id="checkout-items" class="max-h-[35vh] overflow-y-auto custom-scrollbar pr-2 mb-6"></div>
                        
                        <!-- Coupon Accordion -->
                        <div class="border-t border-slate-100 pt-5 pb-2">
                            <button type="button" onclick="const s = document.getElementById('coupon-section'); s.classList.toggle('hidden'); this.querySelector('.chevron').classList.toggle('rotate-180');" class="w-full flex items-center justify-between text-sm font-bold text-slate-700 hover:text-[#003e86] transition-colors group py-1">
                                <span class="flex items-center gap-2">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-400 group-hover:text-[#003e86] transition-colors"><path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428A5 5 0 1 1 12 6.006a5 5 0 1 1 7.5 6.572"/></svg>
                                    Have a coupon code?
                                </span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="chevron transition-transform duration-300 text-slate-400"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            
                            <div id="coupon-section" class="hidden mt-4 animate-in slide-in-from-top-2 duration-300">
                                <div class="flex gap-2">
                                    <input type="text" id="coupon-input" placeholder="Enter promo code" class="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold uppercase focus:ring-2 focus:ring-[#003e86]/20 focus:border-[#003e86] outline-none transition-all shadow-sm" autocomplete="off">
                                    <button type="button" onclick="applyCoupon()" id="apply-coupon-btn" class="px-5 py-3 bg-slate-900 text-white rounded-xl font-bold text-sm shadow-md hover:bg-black transition-all active:scale-95">Apply</button>
                                </div>
                                <p id="coupon-msg" class="text-[11px] font-bold mt-2 hidden ml-1"></p>

                                <?php if ($isLoggedIn && !empty($allActiveCoupons)): ?>
                                <div class="mt-4 space-y-2">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Available for you</p>
                                    <div class="space-y-2 max-h-40 overflow-y-auto custom-scrollbar pr-1">
                                <?php foreach($allActiveCoupons as $c): 
                                    $usedCount = $usedCoupons[$c['code']] ?? 0;
                                    if ($c['usagePerUser'] > 0 && $usedCount >= $c['usagePerUser']) continue;
                                ?>
                                        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-between gap-2 hover:border-[#003e86]/30 hover:bg-blue-50/30 transition-all cursor-pointer group" onclick="applyCouponCode('<?php echo htmlspecialchars($c['code']); ?>')">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-[#003e86] shadow-sm shrink-0">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5 4 4"/><path d="M13 7 8.7 2.7a2.41 2.41 0 0 0-3.4 0L2.7 5.3a2.41 2.41 0 0 0 0 3.4L7 13"/><path d="m8 6 2-2"/><path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5Z"/><path d="m18 16 2-2"/><path d="m17 11 4.3 4.3c.94.94.94 2.46 0 3.4l-2.6 2.6c-.94.94-2.46.94-3.4 0L11 17"/></svg>
                                                </div>
                                                <div>
                                                    <p class="font-mono font-black text-slate-800 tracking-wider uppercase text-sm group-hover:text-[#003e86] transition-colors"><?php echo htmlspecialchars($c['code']); ?></p>
                                                    <p class="text-[10px] text-slate-500 font-medium mt-0.5">
                                                        <?php echo $c['discountType'] === 'PERCENTAGE' ? rtrim(rtrim($c['discountAmount'], '0'), '.') . '%' : '৳' . rtrim(rtrim($c['discountAmount'], '0'), '.'); ?> OFF
                                                        <?php echo $c['minSpend'] > 0 ? ' on ৳' . rtrim(rtrim($c['minSpend'], '0'), '.') . '+' : ''; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="text-[10px] font-bold text-slate-500 group-hover:text-[#003e86] transition-colors">Apply</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="space-y-3 text-sm font-medium text-slate-500 border-t border-slate-100 pt-5 mb-5">
                            <div class="flex justify-between items-center"><span class="font-bold">Subtotal</span><span id="checkout-subtotal" class="font-black text-slate-800">৳0</span></div>
                            <div id="discount-row" class="flex justify-between items-center hidden"><span id="checkout-discount-label" class="font-bold text-emerald-600">Discount</span><span id="checkout-discount" class="font-black text-emerald-600">-৳0</span></div>
                            <div class="flex justify-between items-center"><span class="font-bold">Shipping Fee</span><span id="checkout-shipping" class="font-black text-slate-800">৳0</span></div>
                        </div>
                        <div class="flex justify-between items-center bg-slate-50 p-5 rounded-2xl mb-8 border border-slate-200">
                            <span class="text-sm font-black text-slate-900 uppercase tracking-widest">Total</span>
                            <span id="checkout-total" class="text-2xl font-black text-[#003e86]">৳0</span>
                        </div>
                        <?php 
                        $canCheckout = true;
                        if (!$isLoggedIn && empty($settings['guestCheckoutEnabled'])) $canCheckout = false;
                        if ($isBlocked) $canCheckout = false;
                        ?>
                        <!-- Desktop Place Order Button -->
                        <button type="submit" id="place-order-btn" class="hidden md:flex w-full bg-[#003e86] hover:bg-blue-800 text-white py-4 rounded-xl font-bold shadow-[0_8px_20px_-6px_rgba(0,62,134,0.3)] transition-all active:scale-95 items-center justify-center gap-2 text-lg <?php echo $canCheckout ? '' : 'opacity-50 cursor-not-allowed'; ?>" <?php echo $canCheckout ? '' : 'disabled'; ?>>
                            Place Order <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="translate-x-1"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </button>
                        
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-4 opacity-60 grayscale filter text-slate-600">
                            <div class="flex items-center gap-1.5"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span class="text-[10px] font-bold uppercase tracking-widest">SSL Secure</span></div>
                            <div class="hidden sm:block w-1 h-1 rounded-full bg-slate-400"></div>
                            <div class="flex items-center gap-1.5"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"/><path d="M14 9h4l4 4v4c0 .6-.4 1-1 1h-2"/><circle cx="7" cy="18" r="2"/><path d="M15 18H9"/><circle cx="17" cy="18" r="2"/></svg><span class="text-[10px] font-bold uppercase tracking-widest">Fast Delivery</span></div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Mobile Sticky Place Order Bar (App Style) -->
            <div class="md:hidden fixed bottom-0 left-0 w-full bg-white/95 backdrop-blur-xl border-t border-slate-200 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] z-[90] flex items-center justify-between gap-4 shadow-[0_-10px_20px_rgba(0,0,0,0.05)]">
                <div class="flex flex-col min-w-[80px]">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total</span>
                    <span id="checkout-total-mobile" class="text-xl font-black text-[#003e86] leading-none">৳0</span>
                </div>
                <button type="submit" form="checkout-form" id="place-order-btn-mobile" class="flex-1 bg-[#003e86] hover:bg-blue-800 text-white py-3.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2 <?php echo $canCheckout ? '' : 'opacity-50 cursor-not-allowed'; ?>" <?php echo $canCheckout ? '' : 'disabled'; ?>>
                    Place Order <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </div>

            <script>
            const settings = <?php echo json_encode($settings); ?>;
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            if (cart.length === 0) { window.location.href = 'shop'; }

            const phoneInput = document.getElementById('checkout-phone');
            const nameInput = document.getElementById('checkout-name');
            const emailInput = document.getElementById('checkout-email');
            const citySelect = document.getElementById('city-select');
            const addressInput = document.getElementById('checkout-address');
            const phoneMsg = document.getElementById('phone-msg');
            const hiddenSections = document.getElementById('hidden-checkout-sections');
            const placeOrderBtn = document.getElementById('place-order-btn');

            let fetchTimeout;

            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, ''); // Allow only numbers
                
                if (this.value.length === 11) {
                    <?php if(!$isLoggedIn): ?>
                    clearTimeout(fetchTimeout);
                    phoneMsg.classList.remove('hidden');
                    phoneMsg.innerText = 'Checking details...';
                    
                    hiddenSections.classList.remove('hidden');
                    placeOrderBtn.disabled = false;
                    placeOrderBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    
                    fetchTimeout = setTimeout(async () => {
                        try {
                            const formData = new FormData();
                            formData.append('action', 'fetch_user_by_phone');
                            formData.append('phone', phoneInput.value);
                            
                            const res = await fetch('checkout', { method: 'POST', body: formData });
                            const result = await res.json();
                            
                            if (result.success && result.data) {
                                phoneMsg.innerText = 'Welcome back! Your details are pre-filled.';
                                phoneMsg.classList.replace('text-blue-600', 'text-emerald-600');
                                
                                if (result.data.name) nameInput.value = result.data.name;
                                if (result.data.email) document.getElementById('checkout-email').value = result.data.email;
                                if (result.data.deliveryAddress) {
                                    document.getElementById('checkout-address').value = result.data.deliveryAddress;
                                }
                                if (result.data.city) {
                                    document.getElementById('city-select').value = result.data.city;
                                    updateTotal();
                                }
                            } else {
                                phoneMsg.innerText = 'New number. Please fill out your delivery details.';
                                phoneMsg.classList.replace('text-emerald-600', 'text-blue-600');
                            }
                        } catch(e) { phoneMsg.classList.add('hidden'); }
                    }, 500);
                    <?php endif; ?>
                } else {
                    phoneMsg.classList.add('hidden');
                    <?php if(!$isLoggedIn): ?>
                    hiddenSections.classList.add('hidden');
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    <?php endif; ?>
                }
            });

            // Payment Method Styling
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.payment-radio').forEach(label => {
                        label.classList.remove('border-[#003e86]', 'bg-blue-50/50');
                        label.classList.add('border-slate-200', 'bg-white');
                        const check = label.querySelector('.radio-check');
                        if (check) {
                            check.classList.remove('border-[#003e86]', 'bg-[#003e86]', 'text-white');
                            check.classList.add('border-slate-300', 'text-transparent');
                        }
                    });
                    if(this.checked) {
                        const parent = this.closest('.payment-radio');
                        parent.classList.remove('border-slate-200', 'bg-white');
                        parent.classList.add('border-[#003e86]', 'bg-blue-50/50');
                        
                        const check = parent.querySelector('.radio-check');
                        if (check) {
                            check.classList.remove('border-slate-300', 'text-transparent');
                            check.classList.add('border-[#003e86]', 'bg-[#003e86]', 'text-white');
                        }
                    }
                });
                
                // Trigger initial visual state
                if(radio.checked) {
                    radio.dispatchEvent(new Event('change'));
                }
            });

            let appliedCoupon = null;
            let discountAmount = 0;

            function applyCouponCode(code) {
                document.getElementById('coupon-input').value = code;
                applyCoupon();
            }

            async function applyCoupon() {
                const codeInput = document.getElementById('coupon-input');
                const msg = document.getElementById('coupon-msg');
                const btn = document.getElementById('apply-coupon-btn');
                const code = codeInput.value.trim();
                
                if (!code) { msg.innerText = 'Please enter a coupon code.'; msg.className = 'text-[11px] font-bold mt-2 ml-1 text-rose-500'; msg.classList.remove('hidden'); return; }
                
                btn.disabled = true; btn.innerText = '...';
                let subtotal = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
                const phone = document.getElementById('checkout-phone').value;
                const email = document.getElementById('checkout-email').value;
                
                const formData = new FormData(); formData.append('action', 'apply_coupon'); formData.append('code', code); formData.append('subtotal', subtotal); formData.append('phone', phone); formData.append('email', email);
                
                try {
                    const res = await fetch('checkout', { method: 'POST', body: formData });
                    const result = await res.json();
                    
                    if (result.success) {
                        appliedCoupon = result.code; discountAmount = result.discount;
                        msg.innerText = result.message; msg.className = 'text-[11px] font-bold mt-2 ml-1 text-emerald-600'; msg.classList.remove('hidden');
                        document.getElementById('discount-row').classList.remove('hidden');
                        document.getElementById('checkout-discount-label').innerText = 'Discount (' + appliedCoupon + ')';
                        document.getElementById('checkout-discount').innerText = '-৳' + discountAmount.toLocaleString();
                    } else {
                        appliedCoupon = null; discountAmount = 0;
                        msg.innerText = result.message; msg.className = 'text-[11px] font-bold mt-2 ml-1 text-rose-500'; msg.classList.remove('hidden');
                        document.getElementById('discount-row').classList.add('hidden');
                    }
                    updateTotal();
                } catch (e) {
                    msg.innerText = 'Network error. Try again.'; msg.className = 'text-[11px] font-bold mt-2 ml-1 text-rose-500'; msg.classList.remove('hidden');
                } finally {
                    btn.disabled = false; btn.innerText = 'Apply';
                }
            }

            function renderSummary() {
                let subtotal = 0; let html = '';
                cart.forEach(item => {
                    subtotal += item.price * item.quantity;
                    html += `<div class="flex items-center gap-4 group mb-4 last:mb-0"><div class="w-16 h-16 rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden shrink-0 relative p-1"><img src="${item.image}" class="w-full h-full object-cover mix-blend-multiply rounded-xl"><span class="absolute top-0 right-0 bg-slate-900 text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-bl-xl rounded-tr-2xl shadow-sm border-b border-l border-white">${item.quantity}</span></div><div class="flex-1 min-w-0"><p class="text-sm font-bold text-slate-800 truncate leading-tight">${item.name}</p><p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest">${item.size !== 'Standard' ? item.size : ''} ${item.color !== 'Default' ? '• ' + item.color : ''}</p></div><p class="font-black text-slate-800 text-sm">৳${(item.price * item.quantity).toLocaleString()}</p></div>`;
                });
                document.getElementById('checkout-items').innerHTML = html;
                document.getElementById('checkout-subtotal').innerText = '৳' + subtotal.toLocaleString();
                updateTotal(subtotal);
            }

            function updateTotal(subtotal) {
                const citySelect = document.getElementById('city-select');
                if (!citySelect) return;
                const city = citySelect.value;
                const shipping = city === 'Dhaka' ? parseFloat(settings.deliveryInsideDhaka) : parseFloat(settings.deliveryOutsideDhaka);
                document.getElementById('checkout-shipping').innerText = '৳' + shipping.toLocaleString();
                let finalTotal = subtotal + shipping - discountAmount;
                if (finalTotal < 0) finalTotal = 0;
                document.getElementById('checkout-total').innerText = '৳' + finalTotal.toLocaleString();
                if(document.getElementById('checkout-total-mobile')) {
                    document.getElementById('checkout-total-mobile').innerText = '৳' + finalTotal.toLocaleString();
                }
            }

            document.getElementById('checkout-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const addressEl = document.getElementById('checkout-address');
                if (addressEl && !addressEl.value.trim()) {
                    if (window.showToast) showToast('Please enter your full delivery address.', 'error');
                    else alert('Please enter your delivery address.');
                    addressEl.focus();
                    return;
                }
                
                const btnDesktop = document.getElementById('place-order-btn');
                const btnMobile = document.getElementById('place-order-btn-mobile');
                const loaderHtml = '<svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Processing...';
                
                if(btnDesktop) { btnDesktop.disabled = true; btnDesktop.innerHTML = loaderHtml; }
                if(btnMobile) { btnMobile.disabled = true; btnMobile.innerHTML = loaderHtml; }
                
                const formData = new FormData(e.target);
                formData.append('action', 'place_order');
                formData.append('cart_data', JSON.stringify(cart));
                if (appliedCoupon) formData.append('coupon_code', appliedCoupon);
                
                try {
                    const res = await fetch('checkout', { method: 'POST', body: formData });
                    const result = await res.json();
                    if (result.success) {
                        if (result.payment_method === 'BKASH') {
                            window.location.href = `pay-bkash?order_id=${result.order_id}`;
                        } else {
                            window.location.href = `checkout?success=1&order_id=${result.order_id}&email=${result.email}`;
                        }
                    } else {
                        showToast(result.error || "Order placement failed.", "error");
                        if(btnDesktop) { btnDesktop.disabled = false; btnDesktop.innerHTML = 'Place Order <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'; }
                        if(btnMobile) { btnMobile.disabled = false; btnMobile.innerHTML = 'Place Order <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'; }
                    }
                } catch (err) {
                    showToast("Network error. Please try again.", "error");
                    if(btnDesktop) { btnDesktop.disabled = false; btnDesktop.innerHTML = 'Place Order <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'; }
                    if(btnMobile) { btnMobile.disabled = false; btnMobile.innerHTML = 'Place Order <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'; }
                }
            });

            document.addEventListener('DOMContentLoaded', renderSummary);
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include 'Footer.php'; ?>