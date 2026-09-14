<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$productId = $_POST['productId'] ?? null;
if (!$productId) {
    echo json_encode(['success' => false, 'error' => 'Product ID required.']);
    exit;
}

$stmt = $pdo->query("SELECT geminiApiKey, openaiApiKey, activeAiProvider, geminiPrompt, geminiModelVersion FROM SettingNotification LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$provider = $settings['activeAiProvider'] ?? 'gemini';
$customPrompt = $settings['geminiPrompt'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM Product WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Product not found.']);
    exit;
}

$defaultPrompt = "Write a highly engaging, natural, and human-like Facebook post caption for this product in Bengali.\n\nProduct Name: {{product_name}}\nDescription: {{description}}\nPrice: {{price}} Tk\n\nRules:\n1. Write as if a real human is personally recommending or casually talking about this product.\n2. Do NOT sound like a robotic advertisement or a strict list of features.\n3. Keep it conversational, friendly, and relatable. Use emojis naturally.\n4. Mention the price attractively.\n5. Add 3-5 relevant hashtags at the bottom.\n6. Do NOT use markdown (like ** or # for headers). Output plain text.\n7. The entire caption MUST be in authentic conversational Bengali (Bangla).";
$promptTemplate = !empty($customPrompt) ? $customPrompt : $defaultPrompt;

$search = ['{{product_name}}', '{{description}}', '{{price}}'];
$replace = [$product['name'], $product['description'] ?? '', $product['basePrice']];
$prompt = str_replace($search, $replace, $promptTemplate);

if ($provider === 'openai') {
    $apiKey = $settings['openaiApiKey'] ?? '';
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'OpenAI API Key is missing in settings.']);
        exit;
    }
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert social media marketer.'],
            ['role' => 'user', 'content' => $prompt]
        ]
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
} else {
    $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'] ?? '')));
    $apiKey = !empty($apiKeys) ? $apiKeys[array_rand($apiKeys)] : '';
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Gemini API Key is missing in settings.']);
        exit;
    }
    $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $headers = ['Content-Type: application/json'];
}

if ($provider === 'openai') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $curlError]);
        exit;
    }
    $resData = json_decode($response, true);
    if (isset($resData['choices'][0]['message']['content'])) {
        echo json_encode(['success' => true, 'caption' => trim($resData['choices'][0]['message']['content'])]);
    } else {
        $errorMsg = $resData['error']['message'] ?? 'Unknown OpenAI Error';
        echo json_encode(['success' => false, 'error' => 'OpenAI API Error: ' . $errorMsg]);
    }
} else {
    $success = false;
    $errorMsg = '';
    foreach ($apiKeys as $apiKey) {
        if (empty($apiKey)) continue;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response !== false) {
            $resData = json_decode($response, true);
            if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
                echo json_encode(['success' => true, 'caption' => trim($resData['candidates'][0]['content']['parts'][0]['text'])]);
                $success = true;
                break;
            } else {
                $errorMsg = $resData['error']['message'] ?? 'Unknown Error. Response: ' . $response;
            }
        }
    }
    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Gemini API Error: ' . $errorMsg]);
    }
}
?>