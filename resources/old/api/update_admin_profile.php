<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_SESSION['admin_id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';

    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Name and Email are required.']);
        exit;
    }

    try {
        if (!empty($newPassword)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE `User` SET name = ?, email = ?, phone = ?, password = ? WHERE id = ? AND role = 'admin'");
            $stmt->execute([$name, $email, $phone, $hashed, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE `User` SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'admin'");
            $stmt->execute([$name, $email, $phone, $id]);
        }
        $_SESSION['admin_name'] = $name;
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update profile. Email might be already in use.']);
    }
}
?>