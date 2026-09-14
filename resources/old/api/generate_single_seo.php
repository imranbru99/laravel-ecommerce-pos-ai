<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$productId = $_POST['productId'] ?? '';
if (empty($productId)) {
    echo json_encode(['success' => false, 'error' => 'Product ID required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT name, description FROM Product WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Product not found.']);
    exit;
}

$name = $product['name'];
$desc = strip_tags($product['description']);

$settingsStmt = $pdo->query("SELECT geminiApiKey, openaiApiKey, activeAiProvider, geminiModelVersion FROM SettingNotification LIMIT 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$provider = $settings['activeAiProvider'] ?? 'gemini';

$prompt = "Generate an SEO optimized Meta Title (max 60 chars) and Meta Description (max 150 chars) for this e-commerce product.\nProduct Name: $name\nDescription: $desc\n\nOutput STRICTLY in JSON format with no markdown formatting, like this exactly:\n{\"seoTitle\": \"your title here\", \"seoDescription\": \"your description here\"}";

$responseJson = '';
if ($provider === 'openai') {
    $apiKey = $settings['openaiApiKey'] ?? '';
    if (empty($apiKey)) { echo json_encode(['success' => false, 'error' => 'OpenAI API Key is missing.']); exit; }
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = ['model' => 'gpt-4o-mini', 'messages' => [['role' => 'system', 'content' => 'You are an expert SEO specialist.'], ['role' => 'user', 'content' => $prompt]]];
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch); curl_close($ch); $json = json_decode($res, true);
    $responseJson = $json['choices'][0]['message']['content'] ?? '';
} else {
    $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'] ?? '')));
    if (empty($apiKeys)) { echo json_encode(['success' => false, 'error' => 'Gemini API Key is missing.']); exit; }
    $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
    foreach ($apiKeys as $apiKey) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
        $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
        $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch); curl_close($ch); $json = json_decode($res, true);
        $responseJson = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!empty($responseJson)) break;
    }
}

preg_match('/\{.*\}/s', $responseJson, $matches);
if (!empty($matches[0])) {
    $parsed = json_decode($matches[0], true);
    if ($parsed && isset($parsed['seoTitle']) && isset($parsed['seoDescription'])) {
        $pdo->prepare("UPDATE Product SET seoTitle = ?, seoDescription = ? WHERE id = ?")->execute([$parsed['seoTitle'], $parsed['seoDescription'], $productId]);
        echo json_encode(['success' => true]);
        exit;
    }
}
echo json_encode(['success' => false, 'error' => 'Failed to parse AI response.']);
?>