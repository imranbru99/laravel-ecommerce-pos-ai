<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('<div style="text-align:center; padding: 50px; font-family: sans-serif; color: red;"><h2>Unauthorized!</h2><p>Please log in to your Admin Panel first.</p></div>');
}

$zipFile = __DIR__ . '/phpmailer.zip';
$targetDir = __DIR__ . '/PHPMailer';

echo "<div style='font-family: sans-serif; padding: 30px; max-width: 600px; margin: 0 auto;'>";
echo "<h3>Starting PHPMailer Setup...</h3>";

echo "<p>Downloading PHPMailer from GitHub...</p>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$data = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($data === false || empty($data)) {
    echo "<p>cURL failed, trying fallback method...</p>";
    $opts = [
        "http" => ["method" => "GET", "header" => "User-Agent: Mozilla/5.0\r\n"],
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ];
    $context = stream_context_create($opts);
    $data = @file_get_contents('https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip', false, $context);
    
    if ($data === false || empty($data)) {
        die("<h2 style='color:red'>❌ Failed to download PHPMailer. Please check your internet connection or server firewall.</h2></div>");
    }
}

if (file_put_contents($zipFile, $data) === false) {
    die("<h2 style='color:red'>❌ Failed to save zip file to " . htmlspecialchars($zipFile) . ". Check folder permissions.</h2></div>");
}

echo "<p>Download complete. File size: " . round(filesize($zipFile) / 1024) . " KB.</p>";

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    echo "<p>Extracting zip file...</p>";
    $zip->extractTo(__DIR__);
    $zip->close();
    @unlink($zipFile);
    
    $extractedFolder = '';
    $dirs = scandir(__DIR__);
    foreach ($dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir(__DIR__ . '/' . $dir)) {
            if (file_exists(__DIR__ . '/' . $dir . '/src/PHPMailer.php') || file_exists(__DIR__ . '/' . $dir . '/PHPMailer.php')) {
                $extractedFolder = $dir;
                break;
            }
        }
    }
    
    if ($extractedFolder) {
        echo "<p>Found PHPMailer source in folder: <b>$extractedFolder</b></p>";
        if ($extractedFolder !== 'PHPMailer') {
            @rename(__DIR__ . '/' . $extractedFolder, $targetDir);
        }
        echo "<h2 style='color:green'>✅ PHPMailer installed successfully!</h2><p>You can now close this tab, go back to Notification Settings and click the Test button.</p>";
    } else {
        echo "<h2 style='color:red'>❌ Extraction succeeded, but could not find PHPMailer files inside.</h2>";
    }
} else {
    echo "<h2 style='color:red'>❌ Failed to extract zip file. It might be corrupted.</h2>";
}
echo "</div>";
?>