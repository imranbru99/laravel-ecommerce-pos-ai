<?php
/**
 * Scheduled Facebook Auto-Poster
 * This script should be triggered by a Cron Job every 5 minutes.
 */
require_once __DIR__ . '/../db.php';

// Fetch pending posts that are due for publishing
$stmt = $pdo->query("SELECT * FROM ScheduledPost WHERE status = 'PENDING' AND publishAt <= NOW() LIMIT 2"); // Limit to 2 per run to avoid timeout
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($posts)) {
    echo "No pending posts to publish at this time.\n";
    exit;
}

$stmt = $pdo->query("SELECT * FROM SettingNotification LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($settings['fbAutoPosterEnabled']) && empty($settings['fbAutoPosterEnabled'])) {
    echo "Facebook Auto-Poster is globally disabled in settings.\n";
    exit;
}

if (empty($settings['fbPageId']) || empty($settings['fbPageAccessToken'])) {
    echo "Facebook API credentials missing.\n";
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost'; 

foreach ($posts as $post) {
    $prodStmt = $pdo->prepare("SELECT name, description, basePrice, imageUrl, slug FROM Product WHERE id = ?");
    $prodStmt->execute([$post['productId']]);
    $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || empty($product['imageUrl'])) {
        $pdo->prepare("UPDATE ScheduledPost SET status = 'FAILED' WHERE id = ?")->execute([$post['id']]);
        
        $productName = $product['name'] ?? 'Unknown Product';
        $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Auto-Poster Failed', ?, 'error')")->execute(["Post for '$productName' failed because the product or its image is missing."]);
        continue;
    }

    $caption = $post['caption'];
    if (empty($caption)) {
        $caption = $product['name'] . "\nPrice: ৳" . $product['basePrice'] . "\n\n🛍️ Order yours here: " . $protocol . $host . "/product?slug=" . $product['slug'];
    }

    $imageUrls = explode(',', $product['imageUrl']);
    $finalImagesToPost = [];

    if (!empty($post['imageUrl'])) {
        $finalImagesToPost[] = $protocol . $host . $post['imageUrl'];
    } else {
        for ($i = 0; $i < min(4, count($imageUrls)); $i++) {
            $finalImagesToPost[] = $protocol . $host . $imageUrls[$i];
        }
    }

    // 3. Post to Facebook
    $pageId = $settings['fbPageId'];
    $accessToken = $settings['fbPageAccessToken'];

    if (count($finalImagesToPost) > 1) {
        // Multi-photo post
        $attachedMedia = [];
        foreach ($finalImagesToPost as $imgUrl) {
            // Upload unpublished photo
            $uploadUrl = "https://graph.facebook.com/v19.0/{$pageId}/photos";
            $uploadData = [
                'url' => $imgUrl,
                'published' => 'false',
                'access_token' => $accessToken
            ];
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($uploadData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            curl_close($ch);
            $photoData = json_decode($res, true);
            if (isset($photoData['id'])) {
                $attachedMedia[] = ['media_fbid' => $photoData['id']];
            }
        }

        if (!empty($attachedMedia)) {
            $feedUrl = "https://graph.facebook.com/v19.0/{$pageId}/feed";
            $feedData = [
                'message' => $caption,
                'attached_media' => json_encode($attachedMedia),
                'access_token' => $accessToken
            ];
            $ch = curl_init($feedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($feedData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $resData = json_decode($response, true);
            curl_close($ch);
        }
    } else {
        // Single photo post
        $url = "https://graph.facebook.com/v19.0/{$pageId}/photos";
        $data = ['url' => $finalImagesToPost[0], 'message' => $caption, 'access_token' => $accessToken];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $resData = json_decode($response, true);
        curl_close($ch);
    }

    if (isset($resData['id']) || isset($resData['post_id'])) {
        $pdo->prepare("UPDATE ScheduledPost SET status = 'PUBLISHED', fbPostId = ? WHERE id = ?")->execute([$resData['id'] ?? $resData['post_id'], $post['id']]);
        echo "Successfully published post ID: {$post['id']}\n";
    } else {
        $errorMsg = $resData['error']['message'] ?? 'Unknown API Error';
        $pdo->prepare("UPDATE ScheduledPost SET status = 'FAILED' WHERE id = ?")->execute([$post['id']]);
        $pdo->prepare("INSERT INTO Notification (userId, title, message, type) VALUES (NULL, 'Auto-Poster API Error', ?, 'error')")->execute(["Failed to publish '{$product['name']}' to Facebook. Error: " . substr($errorMsg, 0, 150)]);
        echo "Failed to publish post ID: {$post['id']} - " . $errorMsg . "\n";
    }
}
?>