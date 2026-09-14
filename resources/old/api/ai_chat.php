<?php
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$message = $data['message'] ?? '';
$history = $data['history'] ?? [];

if (empty($message)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'reply' => 'Please ask a question.']);
    exit;
}

if (empty($history)) {
    $history[] = ['role' => 'user', 'content' => $message];
}

$stmt = $pdo->query("SELECT n.*, g.whatsappNumber, g.supportPhone FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Smart search for products based on message keywords
$keywords = array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $message)), function($w) { return strlen($w) > 2; });
$searchConditions = [];
$params = [];
foreach ($keywords as $word) {
    $searchConditions[] = "name LIKE ? OR description LIKE ?";
    $params[] = "%$word%";
    $params[] = "%$word%";
}

$whereClause = "";
if (!empty($searchConditions)) {
    $whereClause = "WHERE " . implode(' OR ', $searchConditions);
}

$pStmt = $pdo->prepare("SELECT name, basePrice, slug, description, imageUrl FROM Product $whereClause ORDER BY RAND() LIMIT 10");
$pStmt->execute($params);
$products = $pStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    $pStmt = $pdo->query("SELECT name, basePrice, slug, description, imageUrl FROM Product ORDER BY RAND() LIMIT 10");
    $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);
}

$prodContext = "Catalog Context:\n";
foreach($products as $p) {
    $desc = substr(strip_tags($p['description'] ?? ''), 0, 150);
    $firstImage = !empty($p['imageUrl']) ? explode(',', $p['imageUrl'])[0] : '';
    $imageUrlAbsolute = "https://{$_SERVER['HTTP_HOST']}" . $firstImage;
    $prodContext .= "- {$p['name']} (৳{$p['basePrice']}). Link: https://{$_SERVER['HTTP_HOST']}/p/{$p['slug']}. Image: {$imageUrlAbsolute}. Details: $desc\n";
}

$basePrompt = "You are an expert AI shopping assistant for our e-commerce store. Your goal is to help customers find products and provide recommendations based on their needs. Guidelines:\n1. Reply in pure Bengali if the user speaks in Bengali/Banglish, else reply in English.\n2. Keep answers concise, helpful, and friendly. Use emojis.\n3. If recommending a product, you MUST include the provided link AND the image URL in this markdown format: ![Product Image](IMAGE_URL)\n4. Do not make up products that are not in the context.\n\n" . $prodContext;

$provider = $settings['activeAiProvider'] ?? 'gemini';
$reply = "I'm sorry, I cannot connect to my brain right now. Please try again later.";

if ($provider === 'openai' && !empty($settings['openaiApiKey'])) {
    $apiKey = $settings['openaiApiKey'];
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $messages = [['role' => 'system', 'content' => $basePrompt]];
    foreach ($history as $msg) {
        $role = ($msg['role'] === 'user') ? 'user' : 'assistant';
        $messages[] = ['role' => $role, 'content' => $msg['content']];
    }
    
    $postData = [
        'model' => 'gpt-4o-mini',
        'messages' => $messages
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($res === false) {
        $reply = "Connection Error: " . $err;
    } else {
        $json = json_decode($res, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $reply = $json['choices'][0]['message']['content'];
        } else {
            $reply = "OpenAI API Error: " . ($json['error']['message'] ?? 'Unknown Error');
        }
    }
} else if ($provider === 'gemini' && !empty($settings['geminiApiKey'])) {
    $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'] ?? '')));
    $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
    
    $contents = [];
    foreach ($history as $msg) {
        $role = ($msg['role'] === 'user') ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }
    
    $postData = [
        'system_instruction' => ['parts' => [['text' => $basePrompt]]],
        'contents' => $contents
    ];
    
    $reply = '';
    foreach ($apiKeys as $apiKey) {
        if (empty($apiKey)) continue;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($res === false) {
            $reply = "Connection Error: " . $err;
            continue;
        } else {
            $json = json_decode($res, true);
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = $json['candidates'][0]['content']['parts'][0]['text'];
                break;
            } else {
                $errorMsg = $json['error']['message'] ?? 'Unknown Error';
                if (stripos($errorMsg, 'high demand') !== false || stripos($errorMsg, 'quota') !== false || stripos($errorMsg, '429') !== false) {
                    $phone = !empty($settings['whatsappNumber']) ? preg_replace('/[^0-9]/', '', $settings['whatsappNumber']) : (!empty($settings['supportPhone']) ? $settings['supportPhone'] : '');
                    $contactStr = $phone ? " For instant help, you can directly message our live support: [Click Here](https://wa.me/$phone)." : " Please try asking again in a few moments.";
                    $reply = "I am currently assisting too many customers and my server is quite busy! 😅$contactStr";
                    continue;
                } else {
                    $reply = "Sorry, my brain is having a little trouble connecting right now. Please try again later!";
                    break;
                }
            }
        }
    }
} else {
    $reply = "API Provider not configured. Please add an API Key in Admin Settings.";
}

ob_end_clean();
echo json_encode(['success' => true, 'reply' => trim($reply)]);
exit;
?>