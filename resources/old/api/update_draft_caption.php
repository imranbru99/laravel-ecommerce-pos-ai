<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? null;
$caption = $_POST['caption'] ?? '';

if (!$id || empty($caption)) {
    echo json_encode(['success' => false, 'error' => 'ID and Caption are required.']);
    exit;
}

try {
    $pdo->prepare("UPDATE ScheduledPost SET caption = ? WHERE id = ? AND status = 'DRAFT'")->execute([$caption, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>