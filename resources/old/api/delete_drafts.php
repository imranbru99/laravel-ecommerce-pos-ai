<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false]);
    exit;
}

$ids = json_decode($_POST['ids'] ?? '[]');
if (!empty($ids)) {
    // Delete associated images if they were AI generated
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT imageUrl FROM ScheduledPost WHERE id IN ($in) AND useAiImage = 1 AND status = 'DRAFT'");
    $stmt->execute($ids);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($images as $img) {
        if ($img && strpos($img, '_ai_post_') !== false) {
            @unlink(__DIR__ . '/..' . $img);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM ScheduledPost WHERE id IN ($in)");
    $stmt->execute($ids);
}
echo json_encode(['success' => true]);
?>