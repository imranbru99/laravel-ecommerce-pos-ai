<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$orderId = $data['orderId'] ?? null;
$items = $data['items'] ?? [];

if (!$orderId || !is_array($items)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Delete existing items for this order
    $stmt = $pdo->prepare("DELETE FROM OrderItem WHERE orderId = ?");
    $stmt->execute([$orderId]);

    $subtotal = 0;
    $insertStmt = $pdo->prepare("INSERT INTO OrderItem (orderId, productId, variantId, quantity, price) VALUES (?, ?, ?, ?, ?)");

    foreach ($items as $item) {
        $pid = $item['productId'] ?? 0;
        $vid = !empty($item['variantId']) ? $item['variantId'] : null;
        $qty = (int)($item['quantity'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        
        $subtotal += ($price * $qty);
        if ($pid > 0 && $qty > 0) {
            $insertStmt->execute([$orderId, $pid, $vid, $qty, $price]);
        }
    }

    $stmt = $pdo->prepare("SELECT shippingCharge FROM `Order` WHERE id = ?");
    $stmt->execute([$orderId]);
    $shipping = (float)($stmt->fetchColumn() ?: 0);
    $newTotal = $subtotal + $shipping;

    $updateStmt = $pdo->prepare("UPDATE `Order` SET total = ? WHERE id = ?");
    $updateStmt->execute([$newTotal, $orderId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'newTotal' => $newTotal]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>