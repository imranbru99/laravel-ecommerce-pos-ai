<?php
session_start();
require_once __DIR__ . '/db.php';

$paymentID = $_GET['paymentID'] ?? '';
$status = $_GET['status'] ?? '';
$orderIdStr = $_GET['merchantInvoiceNumber'] ?? '';

if (empty($paymentID) || empty($status)) {
    header("Location: 404");
    exit;
}

$orderId = (int)str_replace('INV', '', $orderIdStr);

// Helper to show animation
function showAnimationAndRedirect($type, $msg, $redirectUrl) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $type === 'success' ? 'Payment Successful' : 'Payment Failed'; ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @keyframes scaleIn { 0% { transform: scale(0.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
            .animate-scale { animation: scaleIn 0.5s ease-out forwards; }
        </style>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans">
        <div class="bg-white p-8 md:p-12 rounded-[2rem] shadow-2xl max-w-sm w-full text-center animate-scale border border-slate-100">
            <?php if ($type === 'success'): ?>
                <div class="w-24 h-24 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                </div>
                <h2 class="text-2xl font-black text-slate-900 mb-2">Payment Successful!</h2>
                <p class="text-slate-500 font-medium mb-6">Your order has been confirmed.</p>
            <?php else: ?>
                <div class="w-24 h-24 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <h2 class="text-2xl font-black text-slate-900 mb-2">Payment Failed</h2>
                <p class="text-slate-500 font-medium mb-6"><?php echo htmlspecialchars($msg); ?></p>
            <?php endif; ?>
            <div class="flex justify-center">
                <svg class="animate-spin h-6 w-6 text-slate-400" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span class="ml-3 text-sm font-bold text-slate-500">Redirecting...</span>
            </div>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = "<?php echo $redirectUrl; ?>";
            }, 3000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// If payment is cancelled or failed
if ($status === 'cancel' || $status === 'failure') {
    // Delete the order and items since payment failed
    $pdo->prepare("DELETE FROM `Order` WHERE id = ? AND status = 'PENDING'")->execute([$orderId]);
    
    showAnimationAndRedirect('failed', 'Payment was cancelled or failed.', 'checkout?payment_failed=1');
}

if ($status === 'success') {
    $setStmt = $pdo->query("SELECT * FROM SettingPayment LIMIT 1");
    $paymentSettings = $setStmt->fetch(PDO::FETCH_ASSOC);

    $appKey = trim(preg_replace('/\s+/', '', $paymentSettings['bkashAppKey'] ?? ''));
    $baseUrl = !empty($paymentSettings['bkashBaseUrl']) ? rtrim($paymentSettings['bkashBaseUrl'], '/') : "https://tokenized.pay.bka.sh/v1.2.0-beta";
    $token = $_SESSION['bkash_token'] ?? '';

    if (empty($token)) {
        showAnimationAndRedirect('failed', 'Token expired. Payment could not be verified.', 'checkout?payment_failed=1&msg=' . urlencode("Token expired."));
    }

    $post_execute = ['paymentID' => $paymentID];
    $url = "$baseUrl/tokenized/checkout/execute";
    $postexecute = json_encode($post_execute);
    $header = [
        'Content-Type: application/json',
        "Authorization: $token",
        "X-APP-Key: $appKey"
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postexecute);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    $resultdata = curl_exec($curl);
    curl_close($curl);
    
    $executeResponse = json_decode($resultdata, true);

    if (isset($executeResponse['statusCode']) && $executeResponse['statusCode'] === '0000' && isset($executeResponse['transactionStatus']) && $executeResponse['transactionStatus'] === 'Completed') {
        $trxID = $executeResponse['trxID'];
        try { $pdo->exec("ALTER TABLE `Order` ADD COLUMN `trxID` VARCHAR(100) NULL"); } catch (Exception $e) {}
        $pdo->prepare("UPDATE `Order` SET trxID = ? WHERE id = ?")->execute([$trxID, $orderId]);
        
        // Fetch complete order details for notifications
        $stmtOrder = $pdo->prepare("SELECT o.*, u.name, u.email, u.phone FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = ?");
        $stmtOrder->execute([$orderId]);
        $orderData = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if ($orderData) {
            $name = $orderData['name'];
            $email = $orderData['email'];
            $phone = $orderData['phone'];
            $address = $orderData['deliveryAddress'];
            $city = $orderData['city'];
            $total = $orderData['total'];
            $shippingCharge = $orderData['shippingCharge'];
            $discountAmount = $orderData['discountAmount'];
            
            // Send Notification
            $notifMsg = "Your order #$orderId has been placed successfully and paid via bKash.";
            $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (?, 'Order Placed', ?, 'order')")->execute([$orderData['userId'], $notifMsg]);
            $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'New Order Received', ?, 'order')")->execute(["Order #$orderId has been placed and paid by " . $name . "."]);

            $notifStmt = $pdo->query("SELECT * FROM SettingNotification LIMIT 1");
            $notifSettings = $notifStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
            $setStmtGen = $pdo->query("SELECT storeName, supportEmail, currencySymbol FROM SettingGeneral LIMIT 1");
            $settingsGen = $setStmtGen->fetch(PDO::FETCH_ASSOC) ?: [];
            $storeName = $settingsGen['storeName'] ?? '';
            $symbol = $settingsGen['currencySymbol'] ?? '৳';

            if (!empty($notifSettings['emailEnabled']) && !empty($email)) {
                $template = $notifSettings['orderPlacedTemplate'] ?? '';
                if ($template) {
                    $itemsHtml = '';
                    $itemStmt = $pdo->prepare("SELECT oi.*, p.name as productName FROM OrderItem oi JOIN Product p ON oi.productId = p.id WHERE oi.orderId = ?");
                    $itemStmt->execute([$orderId]);
                    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($items as $item) {
                        $itemsHtml .= '<tr><td style="padding-bottom: 10px;">' . htmlspecialchars($item['productName'] ?? 'Item') . ' x ' . intval($item['quantity']) . '</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">' . $symbol . number_format(floatval($item['price']) * intval($item['quantity'])) . '</td></tr>';
                    }
                    if ($discountAmount > 0) $itemsHtml .= '<tr><td style="padding-bottom: 10px; color: #10b981;">Discount</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #10b981;">-' . $symbol . number_format($discountAmount) . '</td></tr>';
                    $itemsHtml .= '<tr><td style="padding-bottom: 10px;">Shipping</td><td style="text-align: right; padding-bottom: 10px; font-weight: 600; color: #0f172a;">' . $symbol . number_format($shippingCharge) . '</td></tr>';
                    
                    $search = ['{{customer_name}}', '{{order_id}}', '{{store_name}}', 'Idea Mart', '{{order_items_html}}', '{{total_amount}}', '{{shipping_address}}', '{{customer_phone}}', '{{support_email}}'];
                    $replace = [$name, str_pad($orderId, 6, '0', STR_PAD_LEFT), $storeName, $storeName, $itemsHtml, $symbol . number_format($total), nl2br(htmlspecialchars($address)), htmlspecialchars($phone), $settingsGen['supportEmail'] ?? ''];
                    require_once __DIR__ . '/api/mailer_helper.php';
                    sendDynamicEmail($pdo, $email, "Order Confirmed - #" . str_pad($orderId, 6, '0', STR_PAD_LEFT), str_replace($search, $replace, $template));
                }
            }
            if (!empty($notifSettings['smsEnabled']) && !empty($notifSettings['smsApiUrl']) && !empty($phone)) {
                $template = $notifSettings['orderPlacedSmsTemplate'] ?? '';
                if ($template) {
                    $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($phone), $notifSettings['smsApiUrl']);
                    @file_get_contents(str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode(str_replace(['{{customer_name}}', '{{order_id}}', '{{store_name}}', 'Idea Mart'], [$name, str_pad($orderId, 6, '0', STR_PAD_LEFT), $storeName, $storeName], $template)), $apiUrl));
                }
            }
        }

        showAnimationAndRedirect('success', 'Payment Successful!', "checkout?success=1&order_id=$orderId&email=" . urlencode($orderData['email'] ?? ''));
    } else {
        $pdo->prepare("DELETE FROM `Order` WHERE id = ? AND status = 'PENDING'")->execute([$orderId]);
        $msg = $executeResponse['statusMessage'] ?? 'Unknown Error';
        showAnimationAndRedirect('failed', 'Payment Verification Failed: ' . $msg, 'checkout?payment_failed=1&msg=' . urlencode("Payment Verification Failed. " . $msg));
    }
}
?>