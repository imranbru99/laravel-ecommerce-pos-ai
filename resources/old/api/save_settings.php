<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $htmlFields = [
        'orderPlacedTemplate', 'orderProcessingTemplate', 'orderShippedTemplate', 
        'orderDeliveredTemplate', 'orderCancelledTemplate', 'welcomeEmailTemplate', 'otpEmailTemplate'
    ];

    // Map tables and columns
    $tables = ['SettingGeneral', 'SettingPayment', 'SettingDelivery', 'SettingNotification'];
    $tableColumns = [];
    foreach($tables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $tableColumns[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec("INSERT IGNORE INTO `$table` (id) VALUES (1)");
    }

    $updatesByTable = [];
    $paramsByTable = [];

    // Parse standard POST fields
    foreach ($_POST as $key => $value) {
        if (in_array($key, $htmlFields)) {
            $value = base64_decode($value);
        }
        
        foreach ($tables as $table) {
            if (in_array($key, $tableColumns[$table])) {
                $updatesByTable[$table][] = "`$key` = :$key";
                $paramsByTable[$table][$key] = $value;
                break; // stop searching if found
            }
        }
    }

    // বর্তমান সেটিংস নিয়ে আসা (যাতে আগের ছবির লিংক পাওয়া যায়)
    $stmt = $pdo->query("SELECT logoUrl, faviconUrl, metaImage FROM SettingGeneral LIMIT 1");
    $currentSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure upload directory exists
    if (!is_dir('../uploads')) { mkdir('../uploads', 0755, true); }

    // Logo File Upload
    if (isset($_FILES['logoFile']) && $_FILES['logoFile']['error'] === UPLOAD_ERR_OK) {
        $logoName = time() . '_logo_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['logoFile']['name']);
        if (move_uploaded_file($_FILES['logoFile']['tmp_name'], '../uploads/' . $logoName)) {
            $updatesByTable['SettingGeneral'][] = "`logoUrl` = :logoUrl";
            $paramsByTable['SettingGeneral']['logoUrl'] = '/uploads/' . $logoName;
    
            // আগের লোগো ফাইল সার্ভার থেকে ডিলিট করা
            if (!empty($currentSettings['logoUrl'])) {
                $oldPath = __DIR__ . '/..' . $currentSettings['logoUrl'];
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }
    }

    // Favicon Upload
    if (isset($_FILES['faviconFile']) && $_FILES['faviconFile']['error'] === UPLOAD_ERR_OK) {
        $faviconName = time() . '_fav_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['faviconFile']['name']);
        if (move_uploaded_file($_FILES['faviconFile']['tmp_name'], '../uploads/' . $faviconName)) {
            $updatesByTable['SettingGeneral'][] = "`faviconUrl` = :faviconUrl";
            $paramsByTable['SettingGeneral']['faviconUrl'] = '/uploads/' . $faviconName;
    
            // আগের ফ্যাভিকন ফাইল সার্ভার থেকে ডিলিট করা
            if (!empty($currentSettings['faviconUrl'])) {
                $oldPath = __DIR__ . '/..' . $currentSettings['faviconUrl'];
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }
    }

    // Meta Image Upload
    if (isset($_FILES['metaImageFile']) && $_FILES['metaImageFile']['error'] === UPLOAD_ERR_OK) {
        $metaImageName = time() . '_meta_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['metaImageFile']['name']);
        if (move_uploaded_file($_FILES['metaImageFile']['tmp_name'], '../uploads/' . $metaImageName)) {
            $updatesByTable['SettingGeneral'][] = "`metaImage` = :metaImage";
            $paramsByTable['SettingGeneral']['metaImage'] = '/uploads/' . $metaImageName;
    
            if (!empty($currentSettings['metaImage'])) {
                $oldPath = __DIR__ . '/..' . $currentSettings['metaImage'];
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }
    }

    try {
        foreach ($tables as $table) {
            if (!empty($updatesByTable[$table])) {
                $sql = "UPDATE `$table` SET " . implode(', ', $updatesByTable[$table]) . " WHERE id = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($paramsByTable[$table]);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}