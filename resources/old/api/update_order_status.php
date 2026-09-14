<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['orderId'] ?? null;
    $status = $_POST['status'] ?? null;
    $courierName = !empty($_POST['courierName']) ? trim($_POST['courierName']) : null;
    $trackingLink = !empty($_POST['trackingLink']) ? trim($_POST['trackingLink']) : null;

    try {
        $pdo->exec("ALTER TABLE `Order` ADD COLUMN `courierName` VARCHAR(255) NULL AFTER `status`");
        $pdo->exec("ALTER TABLE `Order` ADD COLUMN `trackingLink` VARCHAR(255) NULL AFTER `courierName`");
    } catch (Exception $e) {}

    if ($orderId && $status) {
        try {
            // Fetch previous status and link to prevent duplicate notifications
            $oldDataStmt = $pdo->prepare("SELECT status, trackingLink FROM `Order` WHERE id = ?");
            $oldDataStmt->execute([$orderId]);
            $oldData = $oldDataStmt->fetch(PDO::FETCH_ASSOC);
            $oldStatus = $oldData['status'];
            $oldLink = $oldData['trackingLink'];

            // Update Order Status in Database
            $stmt = $pdo->prepare("UPDATE `Order` SET status = :status, courierName = :courierName, trackingLink = :trackingLink WHERE id = :id");
            $stmt->execute(['status' => $status, 'courierName' => $courierName, 'trackingLink' => $trackingLink, 'id' => $orderId]);
            
            $statusChanged = $oldStatus !== $status;
            $linkAdded = empty($oldLink) && !empty($trackingLink);

            if ($statusChanged || $linkAdded) {
                // Fetch Order & User Info for Notification
                $orderStmt = $pdo->prepare("SELECT o.*, u.id as uid, u.name as customer_name, u.email as customer_email FROM `Order` o JOIN User u ON o.userId = u.id WHERE o.id = :id");
                $orderStmt->execute(['id' => $orderId]);
                $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

                // Fetch Settings for Email Templates
                $setStmt = $pdo->query("SELECT n.*, g.storeName FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
                $settings = $setStmt->fetch(PDO::FETCH_ASSOC);

                // Send automated notification to the customer
                if ($orderData) {
                    $uid = $orderData['uid'];
                    $title = "Order " . ucfirst(strtolower($status));
                    
                    if (!$statusChanged && $linkAdded) {
                        $title = "Tracking Updated";
                    }
                    
                    $paddedId = str_pad($orderId, 6, '0', STR_PAD_LEFT);
                    if ($trackingLink) {
                        $message = "Your order #$paddedId has been shipped via " . ($courierName ?: 'our partner') . ". Track here: $trackingLink";
                    } else {
                        $message = "Your order #$paddedId has been marked as $status.";
                    }
                    $type = 'order'; // Blue icon for order updates

                    $notifStmt = $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (:uid, :title, :message, :type)");
                    $notifStmt->execute([
                        'uid' => $uid,
                        'title' => $title,
                        'message' => $message,
                        'type' => $type
                    ]);

                    // Send Email to Customer if enabled in Settings
                    if (!empty($settings['emailEnabled']) && !empty($orderData['customer_email'])) {
                        $to = $orderData['customer_email'];
                        $subject = $title . " - #" . str_pad($orderId, 6, '0', STR_PAD_LEFT);
                        $template = '';
                        
                        if ($status === 'DELIVERED') $template = $settings['orderDeliveredTemplate'] ?? '';
                        elseif ($status === 'SHIPPED') $template = $settings['orderShippedTemplate'] ?? '';
                        elseif ($status === 'PROCESSING') $template = $settings['orderProcessingTemplate'] ?? '';
                        elseif ($status === 'CANCELLED') $template = $settings['orderCancelledTemplate'] ?? '';
                        
                        if (!empty($template)) {
                            $storeName = $settings['storeName'] ?? '';
                            $htmlContent = str_replace(
                                ['{{customer_name}}', '{{order_id}}', '{{tracking_link}}', '{{store_name}}', 'Idea Mart'], 
                                [$orderData['customer_name'], str_pad($orderId, 6, '0', STR_PAD_LEFT), $trackingLink ?? '#', $storeName, $storeName], 
                                $template
                            );
                            require_once __DIR__ . '/mailer_helper.php';
                            $result = sendDynamicEmail($pdo, $to, $subject, $htmlContent);
                            if (is_array($result) && empty($result['success'])) {
                                $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Email Delivery Error', ?, 'alert')")->execute(["Failed to send status update email for Order #{$orderId}. Error: " . ($result['error'] ?? 'Unknown')]);
                            }
                        }
                    }

                    // Send SMS to Customer if enabled
                    if (!empty($settings['smsEnabled']) && !empty($settings['smsApiUrl']) && !empty($orderData['customer_phone'])) {
                        $smsTemplate = '';
                        if ($status === 'DELIVERED') $smsTemplate = $settings['orderDeliveredSmsTemplate'] ?? '';
                        elseif ($status === 'SHIPPED') $smsTemplate = $settings['orderShippedSmsTemplate'] ?? '';
                        elseif ($status === 'PROCESSING') $smsTemplate = $settings['orderProcessingSmsTemplate'] ?? '';
                        elseif ($status === 'CANCELLED') $smsTemplate = $settings['orderCancelledSmsTemplate'] ?? '';
                        
                        if (!empty($smsTemplate)) {
                            $storeName = $settings['storeName'] ?? '';
                            $smsMessage = str_replace(
                                ['{{customer_name}}', '{{order_id}}', '{{tracking_link}}', '{{store_name}}', 'Idea Mart'], 
                                [$orderData['customer_name'], str_pad($orderId, 6, '0', STR_PAD_LEFT), $trackingLink ?? '#', $storeName, $storeName], 
                                $smsTemplate
                            );
                            $apiUrl = str_replace(['[TO]', '{to}', '[NUMBER]', '{number}'], urlencode($orderData['customer_phone']), $settings['smsApiUrl']);
                            $apiUrl = str_replace(['[MESSAGE]', '{message}', '[MSG]', '{msg}'], urlencode($smsMessage), $apiUrl);
                            $smsResult = @file_get_contents($apiUrl);
                            if ($smsResult === false) {
                                $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'SMS API Error', ?, 'alert')")->execute(["Failed to send SMS for Order #{$orderId}. Please check SMS API configuration."]);
                            }
                        }
                    }
                }
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
?>