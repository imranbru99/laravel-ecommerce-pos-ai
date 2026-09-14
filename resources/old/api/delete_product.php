<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Get product and variant images to delete from server
    $stmt = $pdo->prepare("SELECT imageUrl FROM Product WHERE id = ?");
    $stmt->execute([$id]);
    $productImage = $stmt->fetchColumn();
    
    if (!empty($productImage)) {
        foreach (explode(',', $productImage) as $img) {
            $filePath = __DIR__ . '/..' . $img;
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    $vStmt = $pdo->prepare("SELECT imageUrl FROM Variant WHERE productId = ?");
    $vStmt->execute([$id]);
    $variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($variants as $v) {
        if (!empty($v['imageUrl'])) {
            $vPath = __DIR__ . '/..' . $v['imageUrl'];
            if (file_exists($vPath) && is_file($vPath)) {
                unlink($vPath);
            }
        }
    }
    
    $pdo->prepare("DELETE FROM Product WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}