<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

ini_set('memory_limit', '512M'); // Increase memory limit for large images
ini_set('max_execution_time', '300');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $parentId = !empty($_POST['parentId']) ? $_POST['parentId'] : null;
    
    if (empty($name) || empty($slug)) {
        echo json_encode(['success' => false, 'error' => 'Name and slug are required']);
        exit;
    }
    
    $imageUrl = null;
    
    // এডিট করার ক্ষেত্রে আগের ছবিটা ফেচ করা
    $oldImageUrl = null;
    if (!empty($id)) {
        $stmt = $pdo->prepare("SELECT imageUrl FROM Category WHERE id = ?");
        $stmt->execute([$id]);
        $oldImageUrl = $stmt->fetchColumn();
    }

    if (isset($_FILES['image'])) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
            $tmpName = $_FILES['image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            $fileName = time() . '_' . $slug . '.webp';
            $targetPath = '../uploads/' . $fileName;
            $converted = false;
            
            $fileSize = filesize($tmpName);
            if ($fileSize <= 4 * 1024 * 1024 && function_exists('imagewebp') && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $img = null;
                if ($ext === 'jpg' || $ext === 'jpeg') $img = @imagecreatefromjpeg($tmpName);
                elseif ($ext === 'png') {
                    $img = @imagecreatefrompng($tmpName);
                    if ($img) { imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); }
                }
                elseif ($ext === 'webp') $img = @imagecreatefromwebp($tmpName);
                elseif ($ext === 'gif') $img = @imagecreatefromgif($tmpName);
                
                if ($img) {
                    if (imagewebp($img, $targetPath, 80)) { $converted = true; }
                    imagedestroy($img);
                }
            }
            
            if (!$converted) {
                $fileName = time() . '_' . $slug . '.' . $ext;
                move_uploaded_file($tmpName, '../uploads/' . $fileName);
            }
            
            $imageUrl = '/uploads/' . $fileName;

            // আগের ইমেজ সার্ভার থেকে ডিলিট করা
            if (!empty($oldImageUrl)) {
                $oldPath = __DIR__ . '/..' . $oldImageUrl;
                if (file_exists($oldPath) && is_file($oldPath)) {
                    unlink($oldPath);
                }
            }
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'error' => 'Image upload failed. Error code: ' . $_FILES['image']['error']]);
            exit;
        }
    }
    
    try {
        if (!empty($id)) {
            if ($imageUrl) {
                $stmt = $pdo->prepare("UPDATE Category SET name = ?, slug = ?, parentId = ?, imageUrl = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $parentId, $imageUrl, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE Category SET name = ?, slug = ?, parentId = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $parentId, $id]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO Category (name, slug, parentId, imageUrl) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $parentId, $imageUrl]);
       }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}