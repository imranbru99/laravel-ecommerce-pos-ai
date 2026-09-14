<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$name = $_POST['name'] ?? '';
if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Product name required.']);
    exit;
}

$stmt = $pdo->query("SELECT geminiApiKey, openaiApiKey, activeAiProvider, geminiModelVersion FROM SettingNotification LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$provider = $settings['activeAiProvider'] ?? 'gemini';

$prompt = "Write a compelling, professional, and SEO-friendly e-commerce product description for: \"$name\". Highlight key features, benefits, and why customers should buy it. Output plain text with paragraphs (no markdown like ** or ##). Keep it around 100-150 words.";

$desc = '';
if ($provider === 'openai') {
    $apiKey = $settings['openaiApiKey'] ?? '';
    if (empty($apiKey)) { echo json_encode(['success' => false, 'error' => 'OpenAI API Key is missing.']); exit; }
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = ['model' => 'gpt-4o-mini', 'messages' => [['role' => 'system', 'content' => 'You are an expert e-commerce copywriter.'], ['role' => 'user', 'content' => $prompt]]];
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch); curl_close($ch); $json = json_decode($res, true);
    $desc = $json['choices'][0]['message']['content'] ?? '';
} else {
    $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'] ?? '')));
    if (empty($apiKeys)) { echo json_encode(['success' => false, 'error' => 'Gemini API Key is missing.']); exit; }
    $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
    foreach ($apiKeys as $apiKey) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
        $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
        $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch); curl_close($ch); $json = json_decode($res, true);
        $desc = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!empty($desc)) break;
    }
}

if (!empty($desc)) {
    // Remove possible markdown from AI
    $desc = str_replace(['**', '##', '###'], '', $desc);
    echo json_encode(['success' => true, 'description' => trim($desc)]);
} else {
    echo json_encode(['success' => false, 'error' => 'AI returned empty response. Check your API limits.']);
}
?>