<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('findPHPMailerPath')) {
    function findPHPMailerPath($dir, $depth = 0) {
        if ($depth > 5) return false;
        
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'PHPMailer.php') && 
            file_exists($dir . DIRECTORY_SEPARATOR . 'Exception.php') && 
            file_exists($dir . DIRECTORY_SEPARATOR . 'SMTP.php')) {
            return $dir;
        }
        
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PHPMailer.php') && 
            file_exists($dir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php') && 
            file_exists($dir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'SMTP.php')) {
            return $dir . DIRECTORY_SEPARATOR . 'src';
        }
        
        $items = @scandir($dir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $found = findPHPMailerPath($path, $depth + 1);
                    if ($found) return $found;
                }
            }
        }
        return false;
    }
}

// Check if Composer autoload exists first
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
} else {
    $phpmailerSrc = false;
    
    // Check standard installation paths first for performance and reliability
    $directPaths = [
        __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src',
        __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer'
    ];
    foreach ($directPaths as $path) {
        if (file_exists($path . DIRECTORY_SEPARATOR . 'PHPMailer.php')) {
            $phpmailerSrc = $path;
            break;
        }
    }
    
    if (!$phpmailerSrc) {
        $phpmailerSrc = findPHPMailerPath(dirname(__DIR__));
    }

    if ($phpmailerSrc) {
        require_once $phpmailerSrc . DIRECTORY_SEPARATOR . 'Exception.php';
        require_once $phpmailerSrc . DIRECTORY_SEPARATOR . 'PHPMailer.php';
        require_once $phpmailerSrc . DIRECTORY_SEPARATOR . 'SMTP.php';
    }
}

function sendDynamicEmail($pdo, $toEmail, $subject, $htmlContent) {
    global $phpmailerSrc;
    if (!class_exists(PHPMailer::class)) {
        return ['success' => false, 'error' => 'PHPMailer files not found! Searched in ' . dirname(__DIR__) . '. Please install PHPMailer via Composer or run setup_mail.php from your browser.'];
    }

    $stmt = $pdo->query("SELECT n.*, g.storeName FROM SettingNotification n LEFT JOIN SettingGeneral g ON n.id = g.id LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($settings['emailEnabled']) || empty($settings['smtpHost']) || empty($settings['smtpUser'])) {
        return ['success' => false, 'error' => 'Email sending is disabled or SMTP not configured.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $settings['smtpHost'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtpUser'];
        $mail->Password   = $settings['smtpPass'];
        
        // Dynamic Port from settings (Default to 465)
        $mail->Port       = !empty($settings['smtpPort']) ? (int)$settings['smtpPort'] : 465;
        
        // Auto-configure encryption based on Port (465 = SSL, 587 = TLS)
        $mail->SMTPSecure = ($mail->Port === 587) ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS; 

        // Bypass SSL verification for Localhost (XAMPP/WAMP)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->CharSet    = 'UTF-8';

        $storeName = !empty($settings['storeName']) ? $settings['storeName'] : '';
        $mail->setFrom($settings['smtpUser'], $storeName);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>