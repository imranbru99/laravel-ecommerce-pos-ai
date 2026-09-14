<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? null;
$caption = $_POST['caption'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Post ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ScheduledPost WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post not found.']);
        exit;
    }

    $imageUrl = $post['imageUrl'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $fileName = time() . '_custom_post_' . uniqid() . '.' . $ext;
        $targetPath = '../uploads/' . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imageUrl = '/uploads/' . $fileName;
            // Delete old generated images to save space
            if (!empty($post['imageUrl']) && (strpos($post['imageUrl'], '_ai_post_') !== false || strpos($post['imageUrl'], '_custom_post_') !== false)) {
                @unlink(__DIR__ . '/..' . $post['imageUrl']);
            }
        }
    }

    $pdo->prepare("UPDATE ScheduledPost SET caption = ?, imageUrl = ? WHERE id = ?")->execute([$caption, $imageUrl, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>