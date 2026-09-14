<?php
session_start();
error_reporting(0); // Error বা Warning হাইড করার জন্য যাতে JSON নষ্ট না হয়

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/mailer_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Valid email address is required.']);
        exit;
    }

    $subject = "SMTP Configuration Test";
    $htmlContent = "<div style='font-family:sans-serif;padding:20px;border:1px solid #e2e8f0;border-radius:10px;'><h2 style='color:#0f172a;'>Congratulations! 🎉</h2><p>If you are receiving this email, your SMTP configuration is working perfectly.</p></div>";

    $result = sendDynamicEmail($pdo, $email, $subject, $htmlContent);

    if (ob_get_length()) ob_clean(); // আগের কোনো হোয়াইট স্পেস বা ক্যারেক্টার ক্লিন করা
    echo json_encode($result);
}