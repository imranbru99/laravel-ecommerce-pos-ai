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
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("UPDATE ScheduledPost SET status = 'PENDING' WHERE id IN ($in)");
    $stmt->execute($ids);
}
echo json_encode(['success' => true]);
?>