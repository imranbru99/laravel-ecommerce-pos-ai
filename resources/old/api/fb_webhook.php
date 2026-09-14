<?php
require_once __DIR__ . '/../db.php';

// Fetch FB settings
try {
    $stmt = $pdo->query("SELECT * FROM SettingNotification LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    exit;
}

$verify_token = $settings['fbVerifyToken'] ?? '';
$page_access_token = $settings['fbPageAccessToken'] ?? '';
$is_enabled = !empty($settings['fbAutoReplyEnabled']);

// Helper function to generate dynamic reply using Gemini or OpenAI
function callAIText($prompt, $settings) {
    $provider = $settings['activeAiProvider'] ?? 'gemini';
    
    if ($provider === 'openai') {
        $apiKey = $settings['openaiApiKey'] ?? '';
        if (empty($apiKey)) return false;
        
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful customer service bot.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $res = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($res, true);
        return $json['choices'][0]['message']['content'] ?? false;
    } else {
        $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'] ?? '')));
            if (empty($apiKeys)) return false;
        $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
            foreach ($apiKeys as $apiKey) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
                $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $res = curl_exec($ch);
                curl_close($ch);
                $json = json_decode($res, true);
                if (isset($json['candidates'][0]['content']['parts'][0]['text'])) return $json['candidates'][0]['content']['parts'][0]['text'];
            }
            return false;
    }
}

// Handle Webhook Verification (Always allow verification even if auto-reply is disabled)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe' && isset($_GET['hub_verify_token'])) {
        if ($_GET['hub_verify_token'] === $verify_token) {
            @ob_clean(); // Clean any unwanted whitespace or PHP warnings before echoing
            echo $_GET['hub_challenge'];
            http_response_code(200);
            exit;
        } else {
            http_response_code(403);
            exit;
        }
    }
}

if (!$is_enabled) {
    http_response_code(200);
    exit;
}

// Handle Incoming Webhook Events
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['object']) && $data['object'] === 'page') {
        foreach ($data['entry'] as $entry) {
            
            // 1. Handle Messenger Inbox Messages
            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $messaging_event) {
                    if (isset($messaging_event['message']) && !isset($messaging_event['message']['is_echo'])) {
                        $sender_id = $messaging_event['sender']['id'];
                        $message_text = strtolower($messaging_event['message']['text'] ?? '');
                        $attachments = $messaging_event['message']['attachments'] ?? [];
                        
                        $reply = $settings['fbInboxReply'] ?? 'Hello! How can we help you today?';
                        
                        $imageUrl = null;
                        foreach ($attachments as $att) {
                            if ($att['type'] === 'image' && isset($att['payload']['url'])) {
                                $imageUrl = $att['payload']['url'];
                                break;
                            }
                        }

                        // Smart Vision AI: Analyze image to find product
                        if ($imageUrl) {
                            $aiKeywords = identifyProductFromImage($imageUrl, $settings);
                            if (!empty($aiKeywords)) {
                                $message_text .= ' ' . strtolower(trim($aiKeywords));
                            }
                        }

                        // Generate Smart Multilingual Reply via AI
                        $foundProduct = findProductFromText($pdo, $message_text);
                        $hasAiKey = ($settings['activeAiProvider'] === 'openai' && !empty($settings['openaiApiKey'])) || ($settings['activeAiProvider'] !== 'openai' && !empty($settings['geminiApiKey']));
                        
                        if ($hasAiKey) {
                            $basePrompt = $settings['fbBotPrompt'] ?? "You are a friendly customer service assistant. Reply in pure Bengali if asked in Bengali/Banglish, else reply in pure English. Use emojis.";
                            $prompt = $basePrompt . "\n\nCustomer Message: \"$message_text\".\nContext: This is a direct inbox message.\n";
                            
                            if ($foundProduct) {
                                $price = $foundProduct['isFlashDeal'] && $foundProduct['flashDealPrice'] ? $foundProduct['flashDealPrice'] : $foundProduct['basePrice'];
                                $link = "https://" . $_SERVER['HTTP_HOST'] . "/p/" . $foundProduct['slug'];
                                $prompt .= "Relevant Product Found: {$foundProduct['name']}. Price is ৳{$price}. Order link: {$link}. Provide this info to the customer.\n";
                            } else {
                                $prompt .= "If they are asking a general question, answer politely. If they want to browse products, provide our website link: https://" . $_SERVER['HTTP_HOST'] . ".\n";
                            }
                            
                            // AI Cross-Selling feature for Inbox
                            try {
                                $crossSellStmt = $pdo->query("SELECT name, basePrice, slug FROM Product ORDER BY RAND() LIMIT 3");
                                $crossSellProducts = $crossSellStmt->fetchAll(PDO::FETCH_ASSOC);
                                $crossSellText = "";
                                foreach($crossSellProducts as $p) {
                                    $crossSellText .= "- {$p['name']} (৳{$p['basePrice']}) - Link: https://{$_SERVER['HTTP_HOST']}/p/{$p['slug']}\n";
                                }
                                $prompt .= "\nCross-Selling Options (Recommend ONLY ONE of these products IF it naturally fits the conversation):\n$crossSellText";
                            } catch (Exception $e) {}

                            $aiReply = callAIText($prompt, $settings);
                            if ($aiReply) $reply = $aiReply;
                        } elseif ($foundProduct) {
                            // Static Fallback if API fails
                            $price = $foundProduct['isFlashDeal'] && $foundProduct['flashDealPrice'] ? $foundProduct['flashDealPrice'] : $foundProduct['basePrice'];
                            $reply = "Here are the details for " . $foundProduct['name'] . " 🛍️\nPrice: ৳" . number_format($price) . "\nOrder here: https://" . $_SERVER['HTTP_HOST'] . "/p/" . $foundProduct['slug'];
                        }

                        sendFbRequest("me/messages", ['recipient' => ['id' => $sender_id], 'message' => ['text' => $reply], 'messaging_type' => 'RESPONSE'], $page_access_token);
                    }
                }
            }

            // 2. Handle Feed Comments
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if ($change['field'] === 'feed' && $change['value']['item'] === 'comment' && $change['value']['verb'] === 'add') {
                        $comment_id = $change['value']['comment_id'];
                        $sender_name = $change['value']['from']['name'] ?? 'there';
                        $message_text = strtolower($change['value']['message'] ?? '');
                        $photo_url = $change['value']['photo'] ?? null;
                        
                        $hasPricingIntent = preg_match('/price|details|dam|koto|chai|nibo|how much|available|ki/i', $message_text);

                        // Smart Vision AI Salesman for Comments
                        if ($photo_url) {
                            $aiKeywords = identifyProductFromImage($photo_url, $settings);
                            if (!empty($aiKeywords)) {
                                $message_text .= ' ' . strtolower(trim($aiKeywords));
                                $hasPricingIntent = true;
                            }
                        }
                        
                        // Respond to intent comments via AI
                        if ($hasPricingIntent) {
                            $foundProduct = findProductFromText($pdo, $message_text);
                            $hasAiKey = ($settings['activeAiProvider'] === 'openai' && !empty($settings['openaiApiKey'])) || ($settings['activeAiProvider'] !== 'openai' && !empty($settings['geminiApiKey']));
                            
                            $reply_msg = $settings['fbCommentReply'] ?? 'Check your inbox for details!';
                            
                            if ($hasAiKey) {
                                $basePrompt = $settings['fbBotPrompt'] ?? "You are a friendly customer service assistant. Reply in pure Bengali if asked in Bengali/Banglish, else reply in pure English. Use emojis.";
                                $prompt = $basePrompt . "\n\nCustomer Commented: \"$message_text\".\nContext: This is a public Facebook comment.\n\nIMPORTANT INSTRUCTION:\n1. Sentiment Analysis: If the comment contains severe abuse, extreme negativity, foul language, or spam, output exactly [HIDE] and nothing else.\n2. Otherwise, write a very short, friendly comment reply (1-2 sentences).\n";
                                
                                if ($foundProduct) {
                                    $price = $foundProduct['isFlashDeal'] && $foundProduct['flashDealPrice'] ? $foundProduct['flashDealPrice'] : $foundProduct['basePrice'];
                                    $link = "https://" . $_SERVER['HTTP_HOST'] . "/p/" . $foundProduct['slug'];
                                    $prompt .= "Relevant Product: {$foundProduct['name']}. Price is ৳{$price}. Order link: {$link}.\n";
                                }

                                $aiReply = callAIText($prompt, $settings);
                                if ($aiReply) {
                                    $aiReply = trim($aiReply);
                                    if (strpos($aiReply, '[HIDE]') === 0 || strpos($aiReply, '[HIDE]') !== false) {
                                        // Auto-Hide the Negative/Abusive Comment
                                        sendFbRequest($comment_id, ['is_hidden' => true], $page_access_token);
                                        try {
                                            $notifStmt = $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Negative Comment Hidden', ?, 'alert')");
                                            $notifStmt->execute(["An abusive/negative comment by $sender_name was hidden automatically. Comment: \"$message_text\""]);
                                        } catch (Exception $e) {}
                                        
                                        continue; // Skip the rest, don't reply
                                    } else {
                                        $reply_msg = $aiReply;
                                    }
                                }
                            }
                            
                            sendFbRequest("{$comment_id}/comments", ['message' => "Hi {$sender_name}, {$reply_msg}"], $page_access_token);
                        }
                    }
                }
            }
        }
        http_response_code(200);
        echo 'EVENT_RECEIVED';
    } else {
        http_response_code(404);
    }
}

function sendFbRequest($endpoint, $data, $token) {
    if (empty($token)) return;
    $url = "https://graph.facebook.com/v19.0/{$endpoint}?access_token=" . $token;
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-type: application/json', 'content' => json_encode($data)]]);
    @file_get_contents($url, false, $context);
}

function findProductFromText($pdo, $text) {
    $text = strtolower($text);
    $stmt = $pdo->query("SELECT id, name, basePrice, flashDealPrice, isFlashDeal, slug FROM Product");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 1. Try an exact string match first
    foreach ($products as $p) {
        if (strpos($text, strtolower($p['name'])) !== false) {
            return $p;
        }
    }
    
    // 2. Smart partial word match (AI logic simulation)
    $words = str_word_count($text, 1);
    $ignore = ['price', 'details', 'dam', 'koto', 'er', 'the', 'of', 'for', 'a', 'an', 'is', 'what', 'inbox'];
    $words = array_diff($words, $ignore);
    
    if (count($words) > 0) {
        foreach ($products as $p) {
            $productWords = array_diff(str_word_count(strtolower($p['name']), 1), $ignore);
            if (empty($productWords)) continue;
            $intersect = array_intersect($words, $productWords);
            // If at least 2 words match, or the product only has 1 word and it matches
            if (count($intersect) >= min(2, count($productWords))) return $p;
        }
    }
    return null;
}

function identifyProductFromImage($imageUrl, $settings) {
    $provider = $settings['activeAiProvider'] ?? 'gemini';
    $prompt = "Identify the main e-commerce product in this image. Reply with ONLY the most likely product name or 2-3 highly relevant search keywords. Do not use full sentences.";
    
    if ($provider === 'openai') {
        $apiKey = $settings['openaiApiKey'] ?? '';
        if (empty($apiKey)) return '';
        
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]]
                    ]
                ]
            ],
            'max_tokens' => 50
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $res = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($res, true);
        return $json['choices'][0]['message']['content'] ?? '';
        
    } else {
        $apiKeys = array_filter(array_map('trim', explode(',', $settings['geminiApiKey'] ?? '')));
        if (empty($apiKeys)) return '';
        
        $imageData = @file_get_contents($imageUrl);
        if (!$imageData) return '';
        $base64 = base64_encode($imageData);
        $modelVersion = !empty($settings['geminiModelVersion']) ? $settings['geminiModelVersion'] : 'gemini-1.5-flash';
        
        foreach ($apiKeys as $apiKey) {
            $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelVersion . ':generateContent?key=' . $apiKey;
            $postData = [
                'contents' => [[ 'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $base64]]
                ]]]
            ];
            $ch = curl_init($geminiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            curl_close($ch);
            $resData = json_decode($response, true);
            if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) return $resData['candidates'][0]['content']['parts'][0]['text'];
        }
        return '';
    }
}
?>