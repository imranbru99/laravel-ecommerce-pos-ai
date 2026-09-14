<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

set_time_limit(300); // Allow up to 5 minutes to generate multiple posts

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$productIds = json_decode($_POST['productIds'] ?? '[]');
$dailyTimes = json_decode($_POST['dailyTimes'] ?? '[]', true);
$useAiImage = isset($_POST['useAiImage']) && $_POST['useAiImage'] === 'true' ? 1 : 0;

if (empty($productIds) || !is_array($productIds)) {
    echo json_encode(['success' => false, 'error' => 'No products selected.']);
    exit;
}

if (empty($dailyTimes)) {
    echo json_encode(['success' => false, 'error' => 'Valid times are required.']);
    exit;
}
usort($dailyTimes, function($a, $b) { return strcmp($a['time'], $b['time']); });
$stmt = $pdo->query("SELECT * FROM SettingNotification LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';

try {
    $currentDate = date('Y-m-d');
    $now = time();
    $timeIndex = 0;
    $postCountInSlot = 0;
    
    // Find the next available future time slot
    while (true) {
        $slot = $dailyTimes[$timeIndex];
        $publishAtStr = $currentDate . ' ' . $slot['time'] . ':00';
        $publishTime = strtotime($publishAtStr) + ($postCountInSlot * $slot['gap'] * 60);
        if ($publishTime > $now) {
            break;
        }
        $postCountInSlot++;
        if ($postCountInSlot >= $slot['count']) {
            $postCountInSlot = 0;
            $timeIndex++;
            if ($timeIndex >= count($dailyTimes)) {
                $timeIndex = 0;
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
        }
    }

    $draftPosts = [];
    foreach ($productIds as $pid) {
        $slot = $dailyTimes[$timeIndex];
        $publishAtStr = $currentDate . ' ' . $slot['time'] . ':00';
        $publishTime = strtotime($publishAtStr) + ($postCountInSlot * $slot['gap'] * 60);
        $publishAt = date('Y-m-d H:i:s', $publishTime);
        
        $prodStmt = $pdo->prepare("SELECT name, description, basePrice, imageUrl, slug FROM Product WHERE id = ?");
        $prodStmt->execute([$pid]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) continue;

        // 1. Generate Caption
        $provider = $settings['activeAiProvider'] ?? 'gemini';
        $defaultCaptionPrompt = "Write a highly engaging, natural, and human-like Facebook post caption for this product in Bengali.\n\nProduct Name: {{product_name}}\nDescription: {{description}}\nPrice: {{price}} Tk\n\nRules:\n1. Write as if a real human is personally recommending or casually talking about this product.\n2. Do NOT sound like a robotic advertisement or a strict list of features.\n3. Keep it conversational, friendly, and relatable. Use emojis naturally.\n4. Mention the price attractively.\n5. Add 3-5 relevant hashtags at the bottom.\n6. Do NOT use markdown (like ** or # for headers). Output plain text.\n7. The entire caption MUST be in authentic conversational Bengali (Bangla).";
        $promptTemplate = $settings['geminiPrompt'] ?? $defaultCaptionPrompt;
        $prompt = str_replace(
            ['{{product_name}}', '{{description}}', '{{price}}'],
            [$product['name'], $product['description'], $product['basePrice']],
            $promptTemplate
        );

        $aiCaption = '';

        if ($provider === 'openai' && !empty($settings['openaiApiKey'])) {
            $url = 'https://api.openai.com/v1/chat/completions';
            $data = ['model' => 'gpt-4o-mini', 'messages' => [['role' => 'system', 'content' => 'You are an expert social media marketer. Do not use markdown like ** or # for headers. Output plain text.'], ['role' => 'user', 'content' => $prompt]]];
            $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $settings['openaiApiKey']]); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch); curl_close($ch); $json = json_decode($res, true);
            $aiCaption = $json['choices'][0]['message']['content'] ?? '';
        } elseif ($provider === 'gemini' && !empty($settings['geminiApiKey'])) {
            $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'])));
            $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
            foreach ($apiKeys as $apiKey) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
                $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
                $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $res = curl_exec($ch); curl_close($ch); $json = json_decode($res, true);
                $aiCaption = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($aiCaption)) break;
            }
        }

        if (!empty($aiCaption)) {
            $aiCaption = str_replace(['**', '##', '###'], '', $aiCaption);
            $caption = trim($aiCaption);
        } else {
            $caption = $product['name'] . "\nPrice: ৳" . $product['basePrice'];
        }
        $caption .= "\n\n🛍️ Order yours here: " . $protocol . $host . "/p/" . $product['slug'];

        // 2. Generate Image
        $finalImageUrl = null;
        if ($useAiImage) {
            $bgPrompt = $settings['imagePrompt'] ?? 'studio lighting';
            $shortDesc = mb_substr(strip_tags($product['description'] ?? ''), 0, 150);
            $fullPrompt = "High quality realistic e-commerce product photography of: " . $product['name'] . ". Details: " . $shortDesc . ". Style and background: " . $bgPrompt;
            $generatedImageUrl = '';

            if ($provider === 'openai' && !empty($settings['openaiApiKey'])) {
                $ch = curl_init('https://api.openai.com/v1/images/generations');
                $postData = ['model' => 'dall-e-3', 'prompt' => $fullPrompt, 'n' => 1, 'size' => '1024x1024'];
                curl_setopt($ch, CURLOPT_POST, 1); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData)); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $settings['openaiApiKey']]); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch); curl_close($ch); $resData = json_decode($response, true);
                if (isset($resData['data'][0]['url'])) { $generatedImageUrl = $resData['data'][0]['url']; }
            } else {
                $generatedImageUrl = 'https://image.pollinations.ai/prompt/' . urlencode($fullPrompt) . '?width=1024&height=1024&nologo=true';
            }

            if (!empty($generatedImageUrl)) {
                $imgContent = @file_get_contents($generatedImageUrl);
                if ($imgContent !== false) {
                    if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);
                    $fileName = time() . '_ai_post_' . uniqid() . '.jpg';
                    $targetPath = '../uploads/' . $fileName;
                    if (file_put_contents($targetPath, $imgContent)) {
                        $finalImageUrl = '/uploads/' . $fileName;
                    }
                }
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO ScheduledPost (productId, caption, imageUrl, publishAt, status, useAiImage) VALUES (?, ?, ?, ?, 'DRAFT', ?)");
        $stmt->execute([$pid, $caption, $finalImageUrl, $publishAt, $useAiImage]);
        $draftId = $pdo->lastInsertId();
        
        $draftPosts[] = [
            'id' => $draftId,
            'name' => $product['name'],
            'caption' => $caption,
            'imageUrl' => $finalImageUrl ? $finalImageUrl : explode(',', $product['imageUrl'])[0],
            'publishAt' => $publishAt,
            'useAiImage' => $useAiImage
        ];
        
        $postCountInSlot++;
        if ($postCountInSlot >= $slot['count']) {
            $postCountInSlot = 0;
            $timeIndex++;
            if ($timeIndex >= count($dailyTimes)) {
                $timeIndex = 0;
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
        }
    }
    echo json_encode(['success' => true, 'drafts' => $draftPosts]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>