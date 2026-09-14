<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Check if subcategories exist
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Category WHERE parentId = ?");
    $checkStmt->execute([$id]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete main category. Please delete its subcategories first.']);
        exit;
    }

    // Get Image URL to delete from server
    $stmt = $pdo->prepare("SELECT imageUrl FROM Category WHERE id = ?");
    $stmt->execute([$id]);
    $imageUrl = $stmt->fetchColumn();
    
    if (!empty($imageUrl)) {
        $filePath = __DIR__ . '/..' . $imageUrl;
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
    }
    
    $pdo->prepare("DELETE FROM Category WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}