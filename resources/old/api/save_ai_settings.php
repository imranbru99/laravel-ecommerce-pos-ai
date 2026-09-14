<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get and format the array of Gemini API Keys into a comma-separated string
    $geminiApiKey = isset($_POST['geminiApiKey']) ? (is_array($_POST['geminiApiKey']) ? implode(',', array_filter(array_map('trim', $_POST['geminiApiKey']))) : trim($_POST['geminiApiKey'])) : '';

    // 1. Update Social & AI Notifications Settings
    $stmt = $pdo->prepare("UPDATE SettingNotification SET 
        fbAutoReplyEnabled = ?, fbPageId = ?, fbPageAccessToken = ?, 
        fbVerifyToken = ?, fbBotPrompt = ?, activeAiProvider = ?, 
        geminiApiKey = ?, geminiModelVersion = ?, openaiApiKey = ?, 
        geminiPrompt = ?, imagePrompt = ?, fbAutoPosterEnabled = ? WHERE id = 1");
    
    $stmt->execute([
        isset($_POST['fbAutoReplyEnabled']) ? 1 : 0,
        $_POST['fbPageId'] ?? '',
        $_POST['fbPageAccessToken'] ?? '',
        $_POST['fbVerifyToken'] ?? '',
        $_POST['fbBotPrompt'] ?? '',
        $_POST['activeAiProvider'] ?? 'gemini',
        $geminiApiKey,
        $_POST['geminiModelVersion'] ?? 'gemini-1.5-flash',
        $_POST['openaiApiKey'] ?? '',
        $_POST['geminiPrompt'] ?? '',
        $_POST['imagePrompt'] ?? '',
        isset($_POST['fbAutoPosterEnabled']) ? 1 : 0
    ]);

    // 2. Update Website Advanced Features Settings
    $stmtGen = $pdo->prepare("UPDATE SettingGeneral SET aiChatbotEnabled = ?, cartSyncEnabled = ?, fbtEnabled = ? WHERE id = 1");
    $stmtGen->execute([
        isset($_POST['aiChatbotEnabled']) ? 1 : 0,
        isset($_POST['cartSyncEnabled']) ? 1 : 0,
        isset($_POST['fbtEnabled']) ? 1 : 0
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>