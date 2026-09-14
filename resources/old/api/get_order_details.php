<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

try {
    // Get Order & Customer Info
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM `Order` o 
        JOIN User u ON o.userId = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Get Order Items (if OrderItem table exists)
    $items = [];
    try {
        $itemStmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, v.size, v.color, p.imageUrl as p_img, v.imageUrl as v_img
            FROM OrderItem oi
            JOIN Product p ON oi.productId = p.id
            LEFT JOIN Variant v ON oi.variantId = v.id
            WHERE oi.orderId = ?
        ");
        $itemStmt->execute([$id]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {} // Ignore if table doesn't exist yet

    echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>