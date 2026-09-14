<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/../db.php';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header("Location: orders");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $orderStmt = $pdo->prepare("SELECT status FROM `Order` WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $oldStatus = $orderStmt->fetchColumn();

        $status = $_POST['status'] ?? 'PENDING';
        $cancelReason = $_POST['cancelReason'] ?? null;
        if ($cancelReason === 'Other' && !empty($_POST['cancelReasonOther'])) {
            $cancelReason = trim($_POST['cancelReasonOther']);
        }
        if ($status !== 'CANCELLED') $cancelReason = null;
        
        // Update the order
        $pdo->prepare("UPDATE `Order` SET status = ?, cancelReason = ? WHERE id = ?")
            ->execute([$status, $cancelReason, $orderId]);
            
        // Send notifications only if the status has actually changed
        if ($oldStatus !== $status) {
            // Fetch full order and user info for notifications
            $orderNotifyStmt = $pdo->prepare("SELECT o.*, u.id as uid, u.name as customer_name, u.email as customer_email, u.phone as customer_phone FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = ?");
            $orderNotifyStmt->execute([$orderId]);
            $orderDataForNotif = $orderNotifyStmt->fetch(PDO::FETCH_ASSOC);
            
            // Fetch notification settings
            $setStmt = $pdo->query("SELECT n.*, g.storeName FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
            $settings = $setStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($orderDataForNotif && $settings) {
                $uid = $orderDataForNotif['uid'];
                $title = "Order " . ucfirst(strtolower($status));
                $paddedId = str_pad($orderId, 6, '0', STR_PAD_LEFT);
                $message = "Your order #$paddedId status has been updated to $status.";
                
                // 1. In-site Notification
                $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, ?, ?, 'order')")->execute([$uid, $title, $message]);
                
                // 2. Email Notification
                if (!empty($settings['emailEnabled']) && !empty($orderDataForNotif['customer_email'])) {
                    $to = $orderDataForNotif['customer_email'];
                    $subject = $title . " - #" . $paddedId;
                    $templateKey = 'order' . ucfirst(strtolower($status)) . 'Template';
                    $template = $settings[$templateKey] ?? '';
                    
                    if (!empty($template)) {
                        $storeName = $settings['storeName'] ?? '';
                        $itemsHtml = '';
                        $itemStmt = $pdo->prepare("SELECT oi.*, p.name FROM OrderItem oi JOIN Product p ON oi.productId = p.id WHERE oi.orderId = ?");
                        $itemStmt->execute([$orderId]);
                        $oItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($oItems as $item) {
                            $price = floatval($item['price']) * intval($item['quantity']);
                            $itemsHtml .= '<tr><td style="padding-bottom: 10px;">' . htmlspecialchars($item['name']) . ' x ' . $item['quantity'] . '</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">৳' . number_format($price) . '</td></tr>';
                        }
                        if (($orderDataForNotif['discountAmount'] ?? 0) > 0) {
                            $itemsHtml .= '<tr><td style="padding-bottom: 10px; color: #10b981;">Discount</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #10b981;">-৳' . number_format($orderDataForNotif['discountAmount']) . '</td></tr>';
                        }
                        $itemsHtml .= '<tr><td style="padding-bottom: 10px;">Shipping</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">৳' . number_format($orderDataForNotif['shippingCharge']) . '</td></tr>';
                        $supportEmail = $settings['supportEmail'] ?? 'support@' . $_SERVER['HTTP_HOST'];
                        $totalStr = '৳' . number_format($orderDataForNotif['total']);
                        
                        $search = ['{{customer_name}}', '{{order_id}}', '{{tracking_link}}', '{{store_name}}', '{{order_items_html}}', '{{total_amount}}', '{{shipping_address}}', '{{customer_phone}}', '{{support_email}}'];
                        $replace = [$orderDataForNotif['customer_name'], $paddedId, $orderDataForNotif['trackingLink'] ?? '#', $storeName, $itemsHtml, $totalStr, nl2br(htmlspecialchars($orderDataForNotif['deliveryAddress'])), htmlspecialchars($orderDataForNotif['customer_phone']), $supportEmail];
                        $htmlContent = str_replace($search, $replace, $template);
                        require_once __DIR__ . '/../api/mailer_helper.php';
                        sendDynamicEmail($pdo, $to, $subject, $htmlContent);
                    }
                }
                
                // 3. SMS Notification
                if (!empty($settings['smsEnabled']) && !empty($settings['smsApiUrl']) && !empty($orderDataForNotif['customer_phone'])) {
                    $smsTemplateKey = 'order' . ucfirst(strtolower($status)) . 'SmsTemplate';
                    $smsTemplate = $settings[$smsTemplateKey] ?? '';
                    
                    if (!empty($smsTemplate)) {
                        $storeName = $settings['storeName'] ?? '';
                        $smsMessage = str_replace(['{{customer_name}}', '{{order_id}}', '{{tracking_link}}', '{{store_name}}', 'Idea Mart'], [$orderDataForNotif['customer_name'], $paddedId, $orderDataForNotif['trackingLink'] ?? '#', $storeName, $storeName], $smsTemplate);
                        $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($orderDataForNotif['customer_phone']), $settings['smsApiUrl']);
                        $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($smsMessage), $apiUrl);
                        @file_get_contents($apiUrl);
                    }
                }
            }
        }
            
        header("Location: order-details?id=$orderId&success=status_updated");
        exit;
    }

    if ($action === 'update_tracking') {
        $orderStmt = $pdo->prepare("SELECT trackingLink FROM `Order` WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $oldLink = $orderStmt->fetchColumn();

        $courierName = $_POST['courierName'] ?? null;
        $trackingLink = $_POST['trackingLink'] ?? null;

        $pdo->prepare("UPDATE `Order` SET courierName = ?, trackingLink = ? WHERE id = ?")
            ->execute([$courierName, $trackingLink, $orderId]);

        // Send notification if tracking is newly added
        if (empty($oldLink) && !empty($trackingLink)) {
            // Fetch full order and user info for notifications
            $orderNotifyStmt = $pdo->prepare("SELECT o.*, u.id as uid, u.name as customer_name, u.email as customer_email, u.phone as customer_phone FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = ?");
            $orderNotifyStmt->execute([$orderId]);
            $orderDataForNotif = $orderNotifyStmt->fetch(PDO::FETCH_ASSOC);

            // Fetch notification settings
            $setStmt = $pdo->query("SELECT n.*, g.storeName FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
            $settings = $setStmt->fetch(PDO::FETCH_ASSOC);

            if ($orderDataForNotif && $settings) {
                $uid = $orderDataForNotif['uid'];
                $title = "Your Order is Shipped!";
                $paddedId = str_pad($orderId, 6, '0', STR_PAD_LEFT);
                $message = "Your order #$paddedId has been shipped via " . ($courierName ?: 'our partner') . ". Track here: $trackingLink";

                // 1. In-site Notification
                $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, ?, ?, 'order')")->execute([$uid, $title, $message]);

                // 2. Email Notification
                if (!empty($settings['emailEnabled']) && !empty($orderDataForNotif['customer_email'])) {
                    $to = $orderDataForNotif['customer_email'];
                    $subject = $title . " - #" . $paddedId;
                    $template = $settings['orderShippedTemplate'] ?? '';
                    
                    if (!empty($template)) {
                        $storeName = $settings['storeName'] ?? '';
                        $itemsHtml = '';
                        $itemStmt = $pdo->prepare("SELECT oi.*, p.name FROM OrderItem oi JOIN Product p ON oi.productId = p.id WHERE oi.orderId = ?");
                        $itemStmt->execute([$orderId]);
                        $oItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($oItems as $item) {
                            $price = floatval($item['price']) * intval($item['quantity']);
                            $itemsHtml .= '<tr><td style="padding-bottom: 10px;">' . htmlspecialchars($item['name']) . ' x ' . $item['quantity'] . '</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">৳' . number_format($price) . '</td></tr>';
                        }
                        if (($orderDataForNotif['discountAmount'] ?? 0) > 0) {
                            $itemsHtml .= '<tr><td style="padding-bottom: 10px; color: #10b981;">Discount</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #10b981;">-৳' . number_format($orderDataForNotif['discountAmount']) . '</td></tr>';
                        }
                        $itemsHtml .= '<tr><td style="padding-bottom: 10px;">Shipping</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">৳' . number_format($orderDataForNotif['shippingCharge']) . '</td></tr>';
                        $supportEmail = $settings['supportEmail'] ?? 'support@' . $_SERVER['HTTP_HOST'];
                        $totalStr = '৳' . number_format($orderDataForNotif['total']);
                        
                        $search = ['{{customer_name}}', '{{order_id}}', '{{tracking_link}}', '{{store_name}}', '{{order_items_html}}', '{{total_amount}}', '{{shipping_address}}', '{{customer_phone}}', '{{support_email}}'];
                        $replace = [$orderDataForNotif['customer_name'], $paddedId, $trackingLink ?? '#', $storeName, $itemsHtml, $totalStr, nl2br(htmlspecialchars($orderDataForNotif['deliveryAddress'])), htmlspecialchars($orderDataForNotif['customer_phone']), $supportEmail];
                        $htmlContent = str_replace($search, $replace, $template);
                        require_once __DIR__ . '/../api/mailer_helper.php';
                        sendDynamicEmail($pdo, $to, $subject, $htmlContent);
                    }
                }
            }
        }

        header("Location: order-details?id=$orderId&success=tracking_updated");
        exit;
    }
    
    if ($action === 'run_fraud_check') {
        $apiStmt = $pdo->query("SELECT * FROM SettingApi LIMIT 1");
        $apiSettings = $apiStmt->fetch(PDO::FETCH_ASSOC);

        if (empty($apiSettings['fraudCheckerEnabled'])) {
            header("Location: order-details?id=$orderId&error=api_error&msg=" . urlencode("Fraud Checker is not enabled in API settings."));
            exit;
        }
        if (empty($apiSettings['fraudCheckerApiKey'])) {
            header("Location: order-details?id=$orderId&error=api_error&msg=" . urlencode("Fraud Checker API Key is missing. Please set it in API Configurations."));
            exit;
        }

        $orderStmt = $pdo->prepare("SELECT o.id, u.phone as customer_phone FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = ?");
            $orderStmt->execute([$orderId]);
            $oData = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if ($oData && !empty($oData['customer_phone'])) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.bdcourier.com/courier-check");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["phone" => $oData['customer_phone']]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $apiSettings['fraudCheckerApiKey']
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                // Disable SSL Verification for Localhost/XAMPP
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($response === false) {
                    // cURL error
                    $curl_error = curl_error($ch);
                    $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Fraud Check Error', ?, 'alert')")->execute(["API connection failed for Order #{$orderId}. Error: {$curl_error}"]);
                    header("Location: order-details?id=$orderId&error=curl_error&msg=" . urlencode($curl_error));
                    exit;
                }

                $responseData = json_decode($response, true);
                
                // Check if API key is invalid (401 Unauthenticated or Token error)
                if ($httpcode == 401 || (isset($responseData['message']) && (stripos($responseData['message'], 'Unauthenticated') !== false || stripos($responseData['message'], 'token') !== false))) {
                    $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Fraud Check API Error', ?, 'alert')")->execute(["Invalid API Key or Unauthenticated for Order #{$orderId}."]);
                    header("Location: order-details?id=$orderId&error=api_error&msg=" . urlencode($responseData['message'] ?? "Invalid API Key or Unauthenticated."));
                    exit;
                }

                // Invalid JSON structure
                if ($responseData === null) {
                    header("Location: order-details?id=$orderId&error=api_error&msg=" . urlencode("API returned invalid data."));
                    exit;
                }

                // Save the data (Success or Clean Record "Not Found")
                $pdo->prepare("UPDATE `Order` SET fraudCheckData = ? WHERE id = ?")->execute([$response, $orderId]);
                header("Location: order-details?id=$orderId&success=fraud_check_success");
                exit;
            }
    }
    
    if ($action === 'send_to_steadfast') {
        $apiStmt = $pdo->query("SELECT * FROM SettingApi LIMIT 1");
        $apiSettings = $apiStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($apiSettings['steadfastEnabled']) && !empty($apiSettings['steadfastApiKey']) && !empty($apiSettings['steadfastSecretKey'])) {
            $orderStmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = ?");
            $orderStmt->execute([$orderId]);
            $oData = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            $data = [
                'invoice' => str_pad($oData['id'], 6, '0', STR_PAD_LEFT),
                'recipient_name' => $oData['customer_name'],
                'recipient_phone' => $oData['customer_phone'],
                'recipient_address' => $oData['deliveryAddress'],
                'cod_amount' => $oData['paymentMethod'] === 'CASH ON DELIVERY' ? $oData['total'] : 0,
                'note' => 'Delivery Request'
            ];
            
            $ch = curl_init('https://portal.steadfast.com.bd/api/v1/create_order');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Api-Key: ' . $apiSettings['steadfastApiKey'],
                'Secret-Key: ' . $apiSettings['steadfastSecretKey'],
                'Content-Type: application/json'
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            if ($response === false) {
                $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'SteadFast API Error', ?, 'alert')")->execute(["Failed to connect to SteadFast API for Order #{$orderId}."]);
                header("Location: order-details?id=$orderId&error=steadfast_failed");
                exit;
            }
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] == 200 && !empty($result['consignment']['tracking_code'])) {
                $trackingLink = "https://steadfast.com.bd/t/" . $result['consignment']['tracking_code'];
                $pdo->prepare("UPDATE `Order` SET courierName = 'SteadFast Courier', trackingLink = ? WHERE id = ?")->execute([$trackingLink, $orderId]);
                header("Location: order-details?id=$orderId&success=steadfast_success");
                exit;
            }
        }
        header("Location: order-details?id=$orderId&error=steadfast_failed");
        exit;
    }
    
    if ($action === 'edit_customer') {
        $editName = $_POST['edit_name'] ?? '';
        $editPhone = $_POST['edit_phone'] ?? '';
        $editEmail = $_POST['edit_email'] ?? '';
        $editCity = $_POST['edit_city'] ?? '';
        $editAddress = $_POST['edit_address'] ?? '';
        
        $pdo->prepare("UPDATE User SET name = ?, phone = ?, email = ? WHERE id = (SELECT userId FROM `Order` WHERE id = ?)")
            ->execute([$editName, $editPhone, $editEmail, $orderId]);
            
        $pdo->prepare("UPDATE `Order` SET city = ?, deliveryAddress = ? WHERE id = ?")
            ->execute([$editCity, $editAddress, $orderId]);
            
        header("Location: order-details?id=$orderId&success=customer_updated");
        exit;
    }
    
    if ($action === 'edit_products') {
        $editShippingCharge = floatval($_POST['edit_shipping_charge'] ?? 0);
        $editItems = $_POST['edit_items'] ?? [];
        $subtotal = 0;
        
        $pdo->prepare("DELETE FROM OrderItem WHERE orderId = ?")->execute([$orderId]);
        $insertItemStmt = $pdo->prepare("INSERT INTO OrderItem (orderId, productId, variantId, quantity, price) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($editItems as $item) {
            $pid = (int)($item['productId'] ?? 0);
            $vid = !empty($item['variantId']) ? (int)$item['variantId'] : null;
            $qty = (int)($item['quantity'] ?? 1);
            $price = (float)($item['price'] ?? 0);
            
            if ($pid > 0 && $qty > 0) {
                $subtotal += ($price * $qty);
                $insertItemStmt->execute([$orderId, $pid, $vid, $qty, $price]);
            }
        }
        
        $orderDiscountStmt = $pdo->prepare("SELECT discountAmount FROM `Order` WHERE id = ?");
        $orderDiscountStmt->execute([$orderId]);
        $discountAmt = floatval($orderDiscountStmt->fetchColumn() ?: 0);
        
        $newTotal = $subtotal + $editShippingCharge - $discountAmt;
        if ($newTotal < 0) $newTotal = 0;
        
        $pdo->prepare("UPDATE `Order` SET shippingCharge = ?, total = ? WHERE id = ?")->execute([$editShippingCharge, $newTotal, $orderId]);
            
        header("Location: order-details?id=$orderId&success=products_updated");
        exit;
    }
}

// Fetch Order
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM `Order` o 
    JOIN User u ON o.userId = u.id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders");
    exit;
}

// Fetch Items
$itemStmt = $pdo->prepare("
    SELECT oi.*, p.name, p.imageUrl, v.size, v.color
    FROM OrderItem oi
    JOIN Product p ON oi.productId = p.id
    LEFT JOIN Variant v ON oi.variantId = v.id
    WHERE oi.orderId = ?
");
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$statuses = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED'];
$currentStatusIndex = array_search($order['status'], $statuses);
if ($currentStatusIndex === false) $currentStatusIndex = -1;
$isCancelled = $order['status'] === 'CANCELLED';

// Fetch All Products for Edit Items Select
$prodStmt = $pdo->query("SELECT p.id as productId, p.name, p.basePrice, p.imageUrl as pImg, v.id as variantId, v.size, v.color, v.imageUrl as vImg FROM Product p LEFT JOIN Variant v ON p.id = v.productId");
$allProds = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
$availableProducts = [];
foreach ($allProds as $p) {
    $label = $p['name'];
    if (!empty($p['variantId']) && ($p['size'] !== 'Standard' || $p['color'] !== 'Default')) {
        $label .= " (" . $p['size'] . " / " . $p['color'] . ")";
    }
    $img = !empty($p['vImg']) ? explode(',', $p['vImg'])[0] : (!empty($p['pImg']) ? explode(',', $p['pImg'])[0] : 'https://placehold.co/100');
    $availableProducts[] = [
        'label' => $label,
        'name' => $p['name'],
        'size' => $p['size'],
        'color' => $p['color'],
        'productId' => $p['productId'],
        'variantId' => $p['variantId'],
        'price' => $p['basePrice'],
        'image' => $img
    ];
}

$pm = !empty($order['paymentMethod']) ? $order['paymentMethod'] : 'CASH ON DELIVERY';
$isPaid = ($pm === 'BKASH' && !empty($order['trxID'])) || $pm === 'POS CASH' || $order['status'] === 'DELIVERED';

// Fetch API Settings
try {
    $apiStmt = $pdo->query("SELECT * FROM SettingApi LIMIT 1");
    $apiSettings = $apiStmt ? $apiStmt->fetch(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $apiSettings = [];
}

// Fraud Checker Insights
$fraudInsights = ['total_orders' => 0, 'delivered' => 0, 'success_rate' => 0];
if (!empty($order['customer_phone'])) {
    // Find all user IDs associated with this phone number to get a comprehensive history
    $userStmt = $pdo->prepare("SELECT id FROM User WHERE phone = ?");
    $userStmt->execute([$order['customer_phone']]);
    $userIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($userIds)) {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $historyStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders, 
                SUM(CASE WHEN status = 'DELIVERED' THEN 1 ELSE 0 END) as delivered 
            FROM `Order` 
            WHERE userId IN ($placeholders)
        ");
        $historyStmt->execute($userIds);
        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
        if ($history && $history['total_orders'] > 0) {
            $fraudInsights['total_orders'] = (int)$history['total_orders'];
            $fraudInsights['delivered'] = (int)$history['delivered'];
            $fraudInsights['success_rate'] = round(((int)$history['delivered'] / (int)$history['total_orders']) * 100);
        }
    }
}

$fraudApiSummary = null;
if (!empty($order['fraudCheckData'])) {
    $fData = json_decode($order['fraudCheckData'], true);
    if (isset($fData['status']) && $fData['status'] === 'success') {
        $fraudApiSummary = $fData['data']['summary'] ?? null;
    }
}

ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12 animate-in fade-in slide-in-from-bottom-4 duration-500">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="orders" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:shadow-md transition-all">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Order Details #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1"><?php echo date('d M, Y • h:i A', strtotime($order['createdAt'])); ?></p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" onclick="openInvoiceModal()" class="px-4 py-2 bg-white text-slate-700 border border-slate-200 font-bold rounded-xl hover:bg-slate-50 transition-all text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="printer" class="w-4 h-4"></i> Invoice
            </button>
        </div>
    </div>

    <!-- Status Stepper -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 mb-6">
        <div class="flex items-center justify-between">
            <?php foreach($statuses as $idx => $s): 
                $isComplete = $currentStatusIndex >= $idx;
                $isCurrent = $currentStatusIndex === $idx;
            ?>
            <div class="flex-1 flex items-center gap-4 <?php echo $idx > 0 ? 'justify-center' : ''; ?> relative">
                <?php if ($idx > 0): ?>
                    <div class="absolute right-1/2 -translate-x-1/2 top-1/2 -translate-y-1/2 w-full h-0.5 <?php echo $isComplete ? 'bg-indigo-600' : 'bg-slate-200'; ?>"></div>
                <?php endif; ?>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold border-2 <?php echo $isComplete ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-200 text-slate-400'; ?> transition-all duration-300">
                        <?php echo $isComplete ? '<i data-lucide="check" class="w-5 h-5"></i>' : ($idx + 1); ?>
                    </div>
                    <span class="text-xs font-bold <?php echo $isCurrent ? 'text-indigo-600' : 'text-slate-400'; ?> uppercase tracking-wider"><?php echo $s; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Fraud Checker Summary -->
    <?php if (!empty($apiSettings['fraudCheckerEnabled'])): ?>
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-5">
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 <?php echo $fraudInsights['success_rate'] >= 50 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'; ?>">
                <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800">Fraud Check Insights</h3>
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 mt-1">
                    <p class="text-xs font-semibold text-slate-500">
                        Store Success Rate: 
                        <span class="font-black <?php echo $fraudInsights['success_rate'] >= 50 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?php echo $fraudInsights['success_rate']; ?>%</span>
                         (<?php echo $fraudInsights['delivered']; ?>/<?php echo $fraudInsights['total_orders']; ?> Delivered)
                    </p>
                    <?php if ($fraudApiSummary): ?>
                    <div class="hidden sm:block w-1.5 h-1.5 rounded-full bg-slate-200"></div>
                    <p class="text-xs font-semibold text-slate-500">
                        Nationwide Success Rate: 
                        <span class="font-black <?php echo $fraudApiSummary['success_ratio'] >= 50 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?php echo $fraudApiSummary['success_ratio']; ?>%</span>
                         (<?php echo $fraudApiSummary['success_parcel']; ?>/<?php echo $fraudApiSummary['total_parcel']; ?> Delivered)
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="shrink-0 w-full md:w-auto mt-2 md:mt-0">
            <?php if (empty($order['fraudCheckData'])): ?>
                <button type="button" onclick="runFraudCheck()" class="w-full md:w-auto px-6 py-3 bg-indigo-50 text-indigo-600 font-bold rounded-xl hover:bg-indigo-100 transition-colors text-sm flex items-center justify-center gap-2 shadow-sm">
                    <i data-lucide="search" class="w-4 h-4"></i> Run Check
                </button>
            <?php else: ?>
                <button type="button" onclick="openFraudModal()" class="w-full md:w-auto px-6 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-50 transition-colors text-sm flex items-center justify-center gap-2 shadow-sm">
                    <i data-lucide="eye" class="w-4 h-4"></i> View Details
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Order Items -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg">
                        <i data-lucide="shopping-bag" class="w-5 h-5 text-indigo-600"></i> Order Items
                    </h3>
                    <button type="button" onclick="toggleProductEditMode()" id="btn-edit-products" class="px-4 py-2 bg-indigo-50 text-indigo-600 font-bold rounded-xl hover:bg-indigo-100 transition-colors text-sm flex items-center gap-2">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> Edit Products
                    </button>
                </div>
                <div class="overflow-x-auto" id="products-view-mode">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-500">
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider">Item</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-center">Qty</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-right">Unit Price</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($items as $idx => $item): 
                                $img = $item['imageUrl'] ? explode(',', $item['imageUrl'])[0] : 'https://placehold.co/100';
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group text-sm">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden shrink-0 group-hover:shadow-md transition-all">
                                            <img src="<?php echo '..' . $img; ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-slate-800 text-sm truncate uppercase tracking-tight"><?php echo htmlspecialchars($item['name']); ?></h4>
                                            <?php if ($item['size'] !== 'Standard' || $item['color'] !== 'Default'): ?>
                                                <p class="text-[10px] font-bold text-indigo-500 uppercase mt-1"><?php echo $item['size']; ?> / <?php echo $item['color']; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-slate-700"><?php echo $item['quantity']; ?></td>
                                <td class="px-6 py-4 text-right font-bold text-slate-500">৳<?php echo number_format($item['price']); ?></td>
                                <td class="px-6 py-4 text-right font-black text-slate-900">৳<?php echo number_format($item['price'] * $item['quantity']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Edit Mode for Products -->
                <div id="products-edit-mode" class="hidden p-6 space-y-6">
                    <form id="edit-products-form" method="POST">
                        <input type="hidden" name="action" value="edit_products">
                        <input type="hidden" name="edit_shipping_charge" value="<?php echo $order['shippingCharge']; ?>">
                        
                        <!-- Search and Add -->
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5"></i>
                            <input type="text" id="product-search-input" placeholder="Search product to add..." autocomplete="off" class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                            <div id="product-search-results" class="absolute top-full left-0 right-0 bg-white border border-slate-200 rounded-xl shadow-xl mt-1 hidden z-50 max-h-60 overflow-y-auto"></div>
                        </div>

                        <div class="overflow-x-auto mt-4">
                            <table class="w-full text-left whitespace-nowrap min-w-[600px]">
                                <thead class="text-slate-400 uppercase tracking-widest text-[10px] border-b border-slate-100">
                                    <tr>
                                        <th class="pb-3 font-bold w-14">Image</th>
                                        <th class="pb-3 font-bold">Product</th>
                                        <th class="pb-3 font-bold w-24">Price (৳)</th>
                                        <th class="pb-3 font-bold w-32 text-center">Qty</th>
                                        <th class="pb-3 font-bold w-24 text-right">Total</th>
                                        <th class="pb-3 w-10 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50" id="edit-items-tbody">
                                    <!-- JS generated rows -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" onclick="toggleProductEditMode()" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 text-sm transition-all">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 text-sm transition-all flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Save Products</button>
                        </div>
                    </form>
                </div>

                <!-- Totals -->
                <div class="p-6 bg-slate-50/50 border-t border-slate-100 flex justify-end">
                    <div class="w-full max-w-sm space-y-3">
                        <div class="flex justify-between text-sm font-medium text-slate-500">
                            <span>Item price</span>
                            <span class="text-slate-700">৳<?php echo number_format($order['total'] - $order['shippingCharge'] + ($order['discountAmount'] ?? 0)); ?></span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-slate-600 pt-2 border-t border-slate-200">
                            <span>Sub total</span>
                            <span class="text-slate-700">৳<?php echo number_format($order['total'] - $order['shippingCharge'] + ($order['discountAmount'] ?? 0)); ?></span>
                        </div>
                        <?php if (!empty($order['couponCode'])): ?>
                        <div class="flex justify-between text-sm font-medium text-slate-500">
                            <span>Coupon (<?php echo htmlspecialchars($order['couponCode']); ?>)</span>
                            <span class="text-emerald-500 font-bold">- ৳<?php echo number_format($order['discountAmount'] ?? 0); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm font-medium text-slate-500">
                            <span>Vat/Tax</span>
                            <span class="text-slate-700">৳0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-medium text-slate-500">
                            <span>Shipping fee</span>
                            <span class="text-slate-700">৳<?php echo number_format($order['shippingCharge']); ?></span>
                        </div>
                        <div class="pt-4 mt-2 border-t-2 border-slate-300 border-dashed flex justify-between items-center">
                            <span class="text-sm font-black text-indigo-600 uppercase tracking-widest">Total</span>
                            <span class="text-2xl font-black text-slate-900">৳<?php echo number_format($order['total']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-800 mb-4">Payment</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 text-sm font-medium">Payment Status</span>
                        <span class="px-3 py-1 <?php echo $isPaid ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-amber-50 text-amber-600 border border-amber-200'; ?> text-[10px] font-black uppercase rounded-full"><?php echo $isPaid ? 'Paid' : 'Unpaid'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 text-sm font-medium">Payment Method</span>
                        <span class="text-slate-800 font-bold text-sm"><?php echo htmlspecialchars($pm); ?></span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                        <span class="text-slate-500 text-sm font-medium">Order Amount</span>
                        <span class="text-slate-900 font-black text-xl">৳<?php echo number_format($order['total']); ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <i data-lucide="settings-2" class="w-4 h-4 text-indigo-600"></i> Order & Shipping Info
                    </h3>
                </div>
                
                <div class="p-6">
                    <?php if ($order['status'] === 'CANCELLED' && !empty($order['cancelReason'])): ?>
                    <div class="mb-6 p-5 bg-rose-50 border border-rose-200 rounded-2xl">
                        <h3 class="text-xs font-black text-rose-700 uppercase tracking-widest flex items-center gap-2 mb-2">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i> Cancellation Reason
                        </h3>
                        <p class="text-rose-600 text-sm font-medium leading-relaxed"><?php echo nl2br(htmlspecialchars($order['cancelReason'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Status Update Form -->
                    <form action="" method="POST" id="status-update-form" class="space-y-4">
                        <input type="hidden" name="action" value="update_status">                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Change order status</label>
                            <select name="status" id="order-status-select" onchange="promptStatusChange(this)" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 transition-all cursor-pointer">
                                <?php foreach($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst(strtolower($s)); ?></option>
                                <?php endforeach; ?>
                                <option value="CANCELLED" <?php echo $order['status'] === 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div id="cancel-reason-field" class="space-y-2 <?php echo $order['status'] === 'CANCELLED' ? 'block' : 'hidden'; ?> animate-in fade-in slide-in-from-top-2">
                            <label class="text-[10px] font-black text-rose-500 uppercase tracking-widest ml-1">Cancellation Reason</label>
                            <select name="cancelReason" id="admin-cancel-reason" onchange="const other = document.getElementById('admin-cancel-reason-other'); if(this.value === 'Other') { other.classList.remove('hidden'); other.required = true; other.focus(); } else { other.classList.add('hidden'); other.required = false; }" class="w-full px-5 py-3.5 bg-rose-50/50 border border-rose-200 rounded-2xl text-sm font-bold outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all text-rose-700 cursor-pointer">
                                <option value="">Select a reason...</option>
                                <?php
                                $reasons = ['Changed my mind', 'Found a better price elsewhere', 'Delivery time is too long', 'Ordered by mistake', 'Duplicate order', 'Shipping cost is too high', 'Customer requested cancellation', 'Fraudulent order suspected', 'Item is out of stock', 'Other'];
                                $currentReason = $order['cancelReason'] ?? '';
                                $found = false;
                                foreach($reasons as $r) {
                                    $sel = ($currentReason === $r) ? 'selected' : '';
                                    if ($currentReason === $r) $found = true;
                                    echo "<option value=\"$r\" $sel>$r</option>";
                                }
                                if (!empty($currentReason) && !$found) {
                                    echo "<option value=\"Other\" selected>Other</option>";
                                }
                                ?>
                            </select>
                            <textarea name="cancelReasonOther" id="admin-cancel-reason-other" class="<?php echo (!empty($currentReason) && !$found) ? 'block' : 'hidden'; ?> w-full px-5 py-3.5 bg-rose-50/50 border border-rose-200 rounded-2xl text-sm font-bold outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all text-rose-700 placeholder-rose-300 mt-2" placeholder="Please specify the reason..." rows="2"><?php echo (!empty($currentReason) && !$found) ? htmlspecialchars($currentReason) : ''; ?></textarea>
                        </div>
                    </form>

                    <!-- Tracking Update Form -->
                    <form action="" method="POST" id="tracking-update-form" class="mt-6 pt-6 border-t border-slate-100 <?php echo in_array($order['status'], ['SHIPPED', 'DELIVERED']) ? 'block' : 'hidden'; ?>">
                        <input type="hidden" name="action" value="update_tracking">
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Courier Name</label>
                                <input type="text" name="courierName" value="<?php echo htmlspecialchars($order['courierName'] ?? ''); ?>" placeholder="e.g. Steadfast, RedX" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Tracking Link or ID</label>
                                <input type="text" name="trackingLink" value="<?php echo htmlspecialchars($order['trackingLink'] ?? ''); ?>" placeholder="Enter Track ID or Link" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 transition-all">
                                <?php if (!empty($order['trackingLink']) && filter_var($order['trackingLink'], FILTER_VALIDATE_URL)): ?>
                                    <a href="<?php echo htmlspecialchars($order['trackingLink']); ?>" target="_blank" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 ml-1 inline-flex items-center gap-1 mt-1"><i data-lucide="external-link" class="w-3 h-3"></i> Track Package</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="submit" id="tracking-update-btn" class="w-full py-3.5 mt-4 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold shadow-md transition-all flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Update Tracking Info
                        </button>
                        
                        <?php if (!empty($apiSettings['steadfastEnabled'])): ?>
                            <div class="my-4 flex items-center justify-center gap-3"><div class="h-px bg-slate-100 flex-1"></div><span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">OR</span><div class="h-px bg-slate-100 flex-1"></div></div>
                            <button type="button" onclick="sendToSteadFast()" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-md transition-all flex items-center justify-center gap-2">
                                <i data-lucide="send" class="w-4 h-4"></i> Send to SteadFast Courier
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-indigo-600"></i> Shipping Address
                    </h3>
                    <button onclick="toggleCustomerEditMode()" id="btn-edit-customer" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors">
                        <i data-lucide="edit-3" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <div id="customer-view-mode" class="p-6 space-y-4">
                    <div class="flex justify-between items-start border-b border-slate-50 pb-3">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Name</span>
                        <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div class="flex justify-between items-start border-b border-slate-50 pb-3">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Contact</span>
                        <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                    </div>
                    <div class="pt-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Full Address</span>
                        <p class="text-sm font-semibold text-slate-500 leading-relaxed"><?php echo nl2br(htmlspecialchars($order['deliveryAddress'])); ?></p>
                    </div>
                </div>
                
                <form id="customer-edit-mode" method="POST" class="hidden p-6 space-y-5">
                    <input type="hidden" name="action" value="edit_customer">
                    <input type="hidden" name="edit_email" value="<?php echo htmlspecialchars($order['customer_email']); ?>">
                    <input type="hidden" name="edit_city" value="<?php echo htmlspecialchars($order['city']); ?>">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Full Name</label>
                        <input type="text" name="edit_name" value="<?php echo htmlspecialchars($order['customer_name']); ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Phone</label>
                        <input type="text" name="edit_phone" value="<?php echo htmlspecialchars($order['customer_phone']); ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Full Address</label>
                        <textarea name="edit_address" required rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all resize-none"><?php echo htmlspecialchars($order['deliveryAddress']); ?></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" onclick="toggleCustomerEditMode()" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 text-sm transition-all">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 text-sm transition-all flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Save Details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Fraud Check Modal -->
<div id="fraud-modal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
    <div class="bg-white w-full max-w-2xl h-auto max-h-[90vh] rounded-[2rem] shadow-2xl overflow-hidden my-4 animate-in zoom-in duration-200 border border-slate-100 flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 sticky top-0 z-10">
            <h2 class="font-bold text-slate-800 flex items-center gap-2"><i data-lucide="shield-alert" class="w-5 h-5 text-blue-600"></i> Fraud Check Details</h2>
            <button type="button" onclick="closeFraudModal()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
            <?php if (!empty($order['fraudCheckData'])): 
                $fraudData = json_decode($order['fraudCheckData'], true);
                if (isset($fraudData['status']) && $fraudData['status'] === 'success'):
                    $summary = $fraudData['data']['summary'] ?? null;
                    $couriers = $fraudData['data'] ?? [];
                    unset($couriers['summary']);
                    $reports = $fraudData['reports'] ?? [];
            ?>
                <div class="space-y-8">
                    <!-- Summary Section -->
                    <?php if ($summary): ?>
                    <div>
                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-4 flex items-center gap-2"><i data-lucide="pie-chart" class="w-4 h-4 text-indigo-500"></i> Nationwide Summary</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2 grid grid-cols-2 gap-4">
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex flex-col justify-center text-center">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Orders</p>
                                <p class="text-2xl font-black text-slate-700"><?php echo $summary['total_parcel']; ?></p>
                            </div>
                            <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 flex flex-col justify-center text-center">
                                <p class="text-[10px] font-black text-indigo-600/70 uppercase tracking-widest mb-1">Success Rate</p>
                                <p class="text-2xl font-black text-indigo-600"><?php echo $summary['success_ratio']; ?>%</p>
                            </div>
                            <div class="bg-emerald-50 p-4 rounded-2xl border border-emerald-100 flex flex-col justify-center text-center">
                                <p class="text-[10px] font-black text-emerald-600/70 uppercase tracking-widest mb-1">Delivered</p>
                                <p class="text-2xl font-black text-emerald-600"><?php echo $summary['success_parcel']; ?></p>
                            </div>
                            <div class="bg-rose-50 p-4 rounded-2xl border border-rose-100 flex flex-col justify-center text-center">
                                <p class="text-[10px] font-black text-rose-600/70 uppercase tracking-widest mb-1">Cancelled</p>
                                <p class="text-2xl font-black text-rose-600"><?php echo $summary['cancelled_parcel']; ?></p>
                            </div>
                        </div>
                        <div class="md:col-span-1 bg-slate-50 rounded-3xl border border-slate-100 p-5 flex flex-col items-center justify-center relative min-h-[160px] shadow-inner">
                            <div class="w-full h-full relative flex items-center justify-center">
                                <canvas id="fraudChart" class="w-full max-h-[140px]"></canvas>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none flex-col">
                                    <span class="text-lg font-black text-slate-800 mt-1"><?php echo $summary['success_ratio']; ?>%</span>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Courier Breakdown -->
                    <?php if (!empty($couriers)): ?>
                    <div>
                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-4 flex items-center gap-2"><i data-lucide="truck" class="w-4 h-4 text-indigo-500"></i> Courier Breakdown</h4>
                        <div class="space-y-3">
                            <?php foreach ($couriers as $key => $c): ?>
                                <div class="flex items-center justify-between p-4 border border-slate-100 rounded-2xl bg-white shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden border border-slate-100 bg-slate-50 shrink-0 p-1">
                                            <img src="<?php echo htmlspecialchars($c['logo']); ?>" alt="<?php echo htmlspecialchars($c['name']); ?>" class="w-full h-full object-contain mix-blend-multiply">
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($c['name']); ?></p>
                                            <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest mt-0.5"><?php echo $c['success_parcel']; ?> / <?php echo $c['total_parcel']; ?> Delivered</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-black <?php echo $c['success_ratio'] >= 50 ? 'text-emerald-500' : 'text-rose-500'; ?>"><?php echo $c['success_ratio']; ?>%</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Reports Section -->
                    <?php if (!empty($reports)): ?>
                    <div>
                        <h4 class="text-xs font-black text-rose-600 uppercase tracking-widest mb-4 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4"></i> Fraud Reports</h4>
                        <div class="space-y-4">
                            <?php foreach ($reports as $r): ?>
                                <div class="p-5 bg-rose-50 border border-rose-100 rounded-2xl relative overflow-hidden">
                                    <div class="absolute right-0 top-0 w-20 h-20 bg-rose-100 rounded-bl-[100%] z-0"></div>
                                    <div class="relative z-10">
                                        <div class="flex items-center gap-3 mb-3">
                                            <span class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($r['name']); ?></span>
                                            <span class="text-[10px] font-bold text-rose-400 bg-rose-100 px-2.5 py-1 rounded-lg border border-rose-200/50"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></span>
                                        </div>
                                        <p class="text-sm font-medium text-rose-800 leading-relaxed bg-white/60 p-4 rounded-xl border border-rose-100/50"><?php echo htmlspecialchars($r['details']); ?></p>
                                        <div class="mt-4 flex items-center gap-3">
                                            <img src="<?php echo htmlspecialchars($r['courierLogo']); ?>" class="w-5 h-5 rounded-md border border-rose-200">
                                            <span class="text-xs font-bold text-rose-500 uppercase tracking-wider">Reported by <?php echo htmlspecialchars($r['courierName']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php elseif (isset($fraudData['status']) && $fraudData['status'] === 'error'): ?>
                <?php if (isset($fraudData['message']) && (stripos($fraudData['message'], 'token') !== false || stripos($fraudData['message'], 'unauthenticated') !== false)): ?>
                    <div class="p-8 text-center bg-rose-50 rounded-3xl border border-rose-100 my-8">
                        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                            <i data-lucide="alert-triangle" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-xl font-black text-rose-800 mb-2">API Configuration Error</h3>
                        <p class="text-sm font-medium text-rose-600"><?php echo htmlspecialchars($fraudData['message']); ?></p>
                        <p class="text-xs font-bold text-rose-500 mt-4">Please check your API key in API Configurations.</p>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center bg-emerald-50 rounded-3xl border border-emerald-100 my-8">
                        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                            <i data-lucide="shield-check" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-xl font-black text-emerald-800 mb-2">Clean Record</h3>
                        <p class="text-sm font-medium text-emerald-600"><?php echo htmlspecialchars($fraudData['message'] ?? 'No data found for this number.'); ?> This indicates a safe order with no reported fraud history.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-6 text-center text-sm font-medium text-slate-500 bg-slate-50 border border-slate-100 rounded-xl">
                    Fraud check failed or returned invalid data.
                </div>
            <?php endif; ?>
            <?php else: ?>
                <div class="p-6 text-center text-sm font-medium text-slate-500">No fraud check data available.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Invoice Modal -->
<div id="invoice-modal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
    <div class="bg-white w-full max-w-4xl h-[90vh] rounded-[2rem] shadow-2xl overflow-hidden my-4 animate-in zoom-in duration-200 border border-slate-100 flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 sticky top-0 z-10">
            <h2 class="font-bold text-slate-800 flex items-center gap-2"><i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i> Invoice Preview</h2>
            <div class="flex gap-2">
                <button type="button" onclick="printInvoiceIframe()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm transition-all flex items-center gap-2 shadow-md"><i data-lucide="printer" class="w-4 h-4"></i> Print</button>
                <button type="button" onclick="closeInvoiceModal()" class="p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
        </div>
        <div class="flex-1 w-full bg-slate-100 relative">
            <div id="invoice-loader" class="absolute inset-0 flex items-center justify-center">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
            </div>
            <iframe id="invoice-iframe" class="w-full h-full relative z-10" onload="document.getElementById('invoice-loader').classList.add('hidden');" src="invoice?id=<?php echo $order['id']; ?>&popup=1"></iframe>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let orderItems = <?php echo json_encode($items); ?>;
    const availableProducts = <?php echo json_encode($availableProducts); ?>;
    let editItemsList = [];

    lucide.createIcons();
    
    // Invoice Modal
    function openInvoiceModal() {
        document.getElementById('invoice-loader').classList.remove('hidden');
        document.getElementById('invoice-modal').classList.remove('hidden');
        document.getElementById('invoice-modal').classList.add('flex');
    }
    function closeInvoiceModal() {
        document.getElementById('invoice-modal').classList.add('hidden');
        document.getElementById('invoice-modal').classList.remove('flex');
    }
    function printInvoiceIframe() {
        const iframe = document.getElementById('invoice-iframe');
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }

    // Fraud Modal
    let fraudChartInstance = null;
    function openFraudModal() {
        document.getElementById('fraud-modal').classList.remove('hidden');
        document.getElementById('fraud-modal').classList.add('flex');
        
        <?php if ($fraudApiSummary): ?>
        if (!fraudChartInstance) {
            const ctx = document.getElementById('fraudChart');
            if (ctx) {
                fraudChartInstance = new Chart(ctx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Delivered', 'Cancelled'],
                        datasets: [{
                            data: [<?php echo $fraudApiSummary['success_parcel']; ?>, <?php echo $fraudApiSummary['cancelled_parcel']; ?>],
                            backgroundColor: ['#10b981', '#f43f5e'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw; } } }
                        }
                    }
                });
            }
        }
        <?php endif; ?>
    }
    function closeFraudModal() {
        document.getElementById('fraud-modal').classList.add('hidden');
        document.getElementById('fraud-modal').classList.remove('flex');
    }
    
    function runFraudCheck() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="run_fraud_check">';
        document.body.appendChild(form);
        form.submit();
    }

    function sendToSteadFast() {
        customConfirm("Are you sure you want to send this order to SteadFast Courier?", () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="send_to_steadfast">';
            document.body.appendChild(form);
            form.submit();
        });
    }

    function toggleProductEditMode() {
        const viewMode = document.getElementById('products-view-mode');
        const editMode = document.getElementById('products-edit-mode');
        if (viewMode.classList.contains('hidden')) {
            viewMode.classList.remove('hidden');
            editMode.classList.add('hidden');
        } else {
            viewMode.classList.add('hidden');
            editMode.classList.remove('hidden');
            if(editItemsList.length === 0) { editItemsList = orderItems.map(item => ({...item})); }
            renderEditItems();
        }
    }

    function toggleCustomerEditMode() {
        const viewMode = document.getElementById('customer-view-mode');
        const editMode = document.getElementById('customer-edit-mode');
        if (viewMode.classList.contains('hidden')) {
            viewMode.classList.remove('hidden');
            editMode.classList.add('hidden');
        } else {
            viewMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }
    }

    function handleVisibility() {
        const status = document.getElementById('order-status-select').value;
        const trackingForm = document.getElementById('tracking-update-form');
        const cancelField = document.getElementById('cancel-reason-field');
        
        if (status === 'SHIPPED' || status === 'DELIVERED') {
            trackingForm.classList.remove('hidden');
            trackingForm.classList.add('block');
        } else {
            trackingForm.classList.add('hidden');
            trackingForm.classList.remove('block');
        }

        if (status === 'CANCELLED') {
            cancelField.classList.remove('hidden');
            cancelField.classList.add('block');
        } else {
            cancelField.classList.add('hidden');
            cancelField.classList.remove('block');
        }
    }

    function promptStatusChange(selectElement) {
        const originalStatus = "<?php echo $order['status']; ?>";
        const newStatus = selectElement.value;

        if (originalStatus === newStatus) return;

        handleVisibility();

        const form = document.getElementById('status-update-form');
        const cancelReasonField = form.querySelector('[name="cancelReason"]');

        let message = `Are you sure you want to update the order status to ${newStatus}?`;
        if (newStatus === 'SHIPPED') message = `You are marking this order as SHIPPED. The customer will be notified. Proceed?`;
        else if (newStatus === 'DELIVERED') message = `You are marking this order as DELIVERED. This finalizes the order. Proceed?`;
        else if (newStatus === 'CANCELLED') message = `You are about to CANCEL this order. This action cannot be easily undone. Proceed?`;

        customConfirm(message, () => {
            if (newStatus === 'CANCELLED' && cancelReasonField.value.trim() === '') {
                showToast('Please provide a reason for cancellation before confirming.', 'error');
                selectElement.value = originalStatus;
                handleVisibility();
                return;
            }
            form.submit();
        }, () => {
            // on cancel, revert the select to its original value
            selectElement.value = originalStatus; 
            handleVisibility();
        });
    }

    // Search functionality for adding products during edit
    const searchInput = document.getElementById('product-search-input');
    const searchResults = document.getElementById('product-search-results');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            searchResults.innerHTML = '';
            if (term.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }
            
            const matches = availableProducts.filter(p => p.label.toLowerCase().includes(term));
            if (matches.length > 0) {
                matches.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-slate-50 cursor-pointer flex items-center gap-3 border-b border-slate-100 last:border-0';
                    div.innerHTML = `
                        <img src="${p.image.startsWith('http') ? p.image : '..' + p.image}" class="w-10 h-10 rounded-lg object-cover">
                        <div>
                            <p class="text-sm font-bold text-slate-800">${p.name}</p>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">${p.size} / ${p.color} - ৳${p.price}</p>
                        </div>
                    `;
                    div.onclick = () => {
                        editItemsList.push({ 
                            productId: p.productId, 
                            variantId: p.variantId, 
                            quantity: 1, 
                            price: p.price, 
                            name: p.name,
                            size: p.size,
                            color: p.color,
                            imageUrl: p.image 
                        });
                        renderEditItems();
                        searchInput.value = '';
                        searchResults.classList.add('hidden');
                    };
                    searchResults.appendChild(div);
                });
                searchResults.classList.remove('hidden');
            } else {
                searchResults.innerHTML = '<div class="p-4 text-center text-sm text-slate-500">No products found</div>';
                searchResults.classList.remove('hidden');
            }
        });
        
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    }
    
    function renderEditItems() {
        let html = '';
            
        editItemsList.forEach((item, idx) => {
            let imgSrc = 'https://placehold.co/100';
            if (item.productId) {
                const prod = availableProducts.find(p => p.productId == item.productId && p.variantId == item.variantId);
                if (prod && prod.image && prod.image !== 'https://placehold.co/100') {
                    imgSrc = prod.image.startsWith('http') ? prod.image : '..' + prod.image;
                } else if (item.imageUrl) {
                    imgSrc = item.imageUrl.split(',')[0];
                    if(!imgSrc.startsWith('http')) imgSrc = '..' + imgSrc;
                }
            }
            
            const name = item.name || availableProducts.find(p => p.productId == item.productId)?.name || 'Unknown Product';
            const size = item.size || 'Standard';
            const color = item.color || 'Default';

            html += `
            <tr class="hover:bg-slate-50/50 transition-colors group">
                <td class="py-4 pr-4 pl-4">
                    <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 p-1 shrink-0 shadow-sm transition-all">
                        <img src="${imgSrc}" class="w-full h-full object-contain mix-blend-multiply">
                    </div>
                </td>
                <td class="py-4 pr-4">
                    <input type="hidden" name="edit_items[${idx}][productId]" value="${item.productId || ''}">
                    <input type="hidden" name="edit_items[${idx}][variantId]" value="${item.variantId || ''}">
                    <p class="font-bold text-slate-800 text-sm truncate max-w-[200px]">${name}</p>
                    ${(size !== 'Standard' || color !== 'Default') ? `<p class="text-[10px] font-bold text-indigo-500 uppercase mt-0.5">${size} / ${color}</p>` : ''}
                </td>
                <td class="py-4 pr-4 text-right font-bold text-slate-500 tabular-nums">
                    ৳${Number(item.price).toLocaleString()}
                    <input type="hidden" name="edit_items[${idx}][price]" value="${item.price}">
                </td>
                <td class="py-4 pr-4">
                    <div class="flex items-center justify-center bg-slate-50 border border-slate-200 rounded-lg overflow-hidden w-24 mx-auto">
                        <button type="button" onclick="updateEditItem(${idx}, 'quantity', ${parseInt(item.quantity) - 1})" class="px-2.5 py-1.5 text-slate-500 hover:bg-slate-200 font-black transition-colors">-</button>
                        <input type="number" min="1" name="edit_items[${idx}][quantity]" value="${item.quantity}" onchange="updateEditItem(${idx}, 'quantity_input', this.value)" class="w-full bg-transparent border-none text-center font-black text-slate-700 p-0 text-sm focus:ring-0 appearance-none">
                        <button type="button" onclick="updateEditItem(${idx}, 'quantity', ${parseInt(item.quantity) + 1})" class="px-2.5 py-1.5 text-slate-500 hover:bg-slate-200 font-black transition-colors">+</button>
                    </div>
                </td>
                <td class="py-4 pr-4 text-right font-black text-slate-900 tabular-nums">৳${(item.price * item.quantity).toLocaleString()}</td>
                <td class="py-4 text-center">
                    <button type="button" onclick="removeEditItem(${idx})" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </td>
            </tr>`;
        });
        document.getElementById('edit-items-tbody').innerHTML = html;
        lucide.createIcons();
    }

    function updateEditItem(idx, field, value) {
        if (field === 'product') {
            const parts = value.split('-');
            const prod = availableProducts.find(p => p.productId == parts[0] && p.variantId == (parts[1] || null));
            if (prod) { editItemsList[idx].productId = prod.productId; editItemsList[idx].variantId = prod.variantId; editItemsList[idx].price = prod.price; }
        } else if (field === 'price') { editItemsList[idx].price = parseFloat(value) || 0;
        } else if (field === 'quantity' || field === 'quantity_input') { 
            let qty = parseInt(value) || 1;
            if (qty < 1) qty = 1;
            editItemsList[idx].quantity = qty; 
        }
        renderEditItems();
    }
    function addEditItemRow() { editItemsList.push({ productId: '', variantId: null, quantity: 1, price: 0, imageUrl: '' }); renderEditItems(); }
    function removeEditItem(idx) { editItemsList.splice(idx, 1); renderEditItems(); }

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('success')) {
            let message = 'Operation successful!';
            const successType = params.get('success');
            if (successType === 'status_updated') message = 'Order status updated successfully!';
            else if (successType === 'tracking_updated') message = 'Tracking information updated successfully!';
            else if (successType === 'customer_updated') message = 'Customer details updated successfully!';
            else if (successType === 'products_updated') message = 'Order items updated successfully!';
            else if (successType === 'steadfast_success') message = 'Order successfully sent to SteadFast Courier!';
            else if (successType === 'fraud_check_success') {
                message = 'Fraud check completed successfully!';
                openFraudModal();
            }
            showToast(message, 'success');
        }
        if (params.has('error')) {
            let message = 'An unknown error occurred.';
            const errorType = params.get('error');
            const errorMsg = params.get('msg') || '';

            if (errorType === 'fraud_check_failed') message = 'Failed to run fraud check. Please check your API key.';
            else if (errorType === 'curl_error') message = 'Could not connect to API: ' + errorMsg;
            else if (errorType === 'api_error') message = 'API Error: ' + errorMsg;
            else if (errorType === 'steadfast_failed') message = 'Failed to send order to SteadFast. Check API settings.';
            else message = 'Operation failed. Please check your settings or try again.';
            
            showToast(message, 'error');
        }
        // Clean URL
        if(params.has('success') || params.has('error')) {
            const newUrl = window.location.pathname + '?id=<?php echo $orderId; ?>';
            window.history.replaceState({}, document.title, newUrl);
        }
    });

</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>