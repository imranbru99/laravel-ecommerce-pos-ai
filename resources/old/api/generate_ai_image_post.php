<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = $_POST['productId'] ?? null;

    if (!$productId) {
        echo json_encode(['success' => false, 'error' => 'Product ID is required.']);
        exit;
    }

    try {
        $stmt = $pdo->query("SELECT activeAiProvider, openaiApiKey, imagePrompt FROM SettingNotification LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $provider = $settings['activeAiProvider'] ?? 'gemini';
        $bgPrompt = $settings['imagePrompt'] ?? 'placed on a clean surface with studio lighting';

        $stmt = $pdo->prepare("SELECT name, description FROM Product WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'error' => 'Product not found.']);
            exit;
        }

        $shortDesc = mb_substr(strip_tags($product['description'] ?? ''), 0, 150);
        $fullPrompt = "High quality realistic e-commerce product photography of: " . $product['name'] . ". Details: " . $shortDesc . ". Style and background: " . $bgPrompt;
        $generatedImageUrl = '';

        if ($provider === 'openai') {
            $apiKey = $settings['openaiApiKey'] ?? '';
            if (empty($apiKey)) {
                echo json_encode(['success' => false, 'error' => 'OpenAI API key is missing.']);
                exit;
            }
            $ch = curl_init('https://api.openai.com/v1/images/generations');
            $postData = ['model' => 'dall-e-3', 'prompt' => $fullPrompt, 'n' => 1, 'size' => '1024x1024'];
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $resData = json_decode($response, true);
            if ($httpcode !== 200 || !isset($resData['data'][0]['url'])) {
                echo json_encode(['success' => false, 'error' => $resData['error']['message'] ?? 'OpenAI API Error']);
                exit;
            }
            $generatedImageUrl = $resData['data'][0]['url'];
        } else {
            $generatedImageUrl = 'https://image.pollinations.ai/prompt/' . urlencode($fullPrompt) . '?width=1024&height=1024&nologo=true';
        }

        if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
        $fileName = time() . '_ai_post_' . uniqid() . '.jpg';
        $targetPath = '../uploads/' . $fileName;

        $imgResponse = @file_get_contents($generatedImageUrl);
        if ($imgResponse && file_put_contents($targetPath, $imgResponse)) {
            echo json_encode(['success' => true, 'imageUrl' => '/uploads/' . $fileName]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save the generated image on server.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>