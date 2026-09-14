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

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$name = !empty($data['name']) ? $data['name'] : 'Walk-in Customer';
$phone = !empty($data['phone']) ? $data['phone'] : '00000000000';
$paymentMethod = $data['paymentMethod'] ?? 'POS CASH';
$total = $data['total'] ?? 0;
$items = $data['items'] ?? [];

try {
    $pdo->beginTransaction();

    // Generate an email for walk-in customer using their phone number
    $email = preg_replace('/[^0-9]/', '', $phone) . '@walkin.store';
    if (empty($email) || $email == '@walkin.store') {
        $email = 'walkin_' . time() . '@walkin.store';
    }

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM User WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $dummyPass = password_hash('walkin123', PASSWORD_DEFAULT);
        
        do {
            $userId = mt_rand(100000, 999999);
            $check = $pdo->prepare("SELECT id FROM User WHERE id = ?");
            $check->execute([$userId]);
        } while ($check->fetch());
        
        $stmt = $pdo->prepare("INSERT INTO User (id, name, email, password, role) VALUES (?, ?, ?, ?, 'customer')");
        $stmt->execute([$userId, $name, $email, $dummyPass]);
    }

    // Generate 6-digit random Order ID
    do {
        $orderId = mt_rand(100000, 999999);
        $check = $pdo->prepare("SELECT id FROM `Order` WHERE id = ?");
        $check->execute([$orderId]);
    } while ($check->fetch());

    // Insert Order (status DELIVERED for POS)
    $stmt = $pdo->prepare("INSERT INTO `Order` (id, userId, total, paymentMethod, status) VALUES (?, ?, ?, ?, 'DELIVERED')");
    $stmt->execute([$orderId, $userId, $total, $paymentMethod]);

    // Deduct Stock & Insert Items
    foreach ($items as $item) {
        $productId = $item['id'] ?? 0;
        $variantId = $item['variantId'] ?? 0;
        $qty = $item['quantity'] ?? 1;
        $price = $item['price'] ?? 0;

        if ($productId) {
            $pdo->prepare("INSERT INTO OrderItem (orderId, productId, variantId, quantity, price) VALUES (?, ?, ?, ?, ?)")->execute([$orderId, $productId, $variantId ?: null, $qty, $price]);
        }
        if ($variantId) {
            $pdo->prepare("UPDATE Variant SET stock = stock - ? WHERE id = ?")->execute([$qty, $variantId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'order_id' => $orderId]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>