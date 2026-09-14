<?php
ob_start(); // Prevent hidden warnings from breaking JSON
session_start();
error_reporting(E_ALL);
@ini_set('display_errors', 0);
@set_time_limit(300);
@ini_set('memory_limit', '512M');

function sendResponse($success, $message) {
    $output = ob_get_clean();
    http_response_code(200); // Ensure frontend receives JSON properly instead of an HTTP error status
    header('Content-Type: application/json; charset=utf-8');
    if (!empty(trim($output)) && !$success) {
        $message .= ' [Server log: ' . trim(strip_tags($output)) . ']';
    }
    
    $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
    
    $response = ['success' => $success];
    if ($success) { $response['message'] = $message; } 
    else { $response['error'] = $message; }
    echo json_encode($response);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        sendResponse(false, 'Fatal Server Error: ' . $error['message']);
    }
});

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendResponse(false, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

try {
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $sizeMB = round($_SERVER['CONTENT_LENGTH'] / 1024 / 1024, 2);
        sendResponse(false, "The uploaded file is too large ({$sizeMB}MB). Your server restricts upload_max_filesize or post_max_size.");
    }

    function recursiveCopyUpdate($src, $dst, $blocked) {
        $dir = @opendir($src);
        if (!$dir) { throw new Exception("Could not open source directory for reading: $src"); }
        if (!is_dir($dst)) { if (!@mkdir($dst, 0755, true)) { throw new Exception("Could not create destination directory: $dst. Check permissions."); } }
        $count = 0;
        while (($f = readdir($dir)) !== false) {
            if ($f === '.' || $f === '..' || $f === '__MACOSX' || $f === '.DS_Store') continue;
            if (in_array($f, $blocked)) continue;
            $srcPath = $src . '/' . $f;
            $dstPath = $dst . '/' . $f;
            if (is_dir($srcPath)) {
                $count += recursiveCopyUpdate($srcPath, $dstPath, []);
            } else {
                if (!copy($srcPath, $dstPath)) {
                    $error = error_get_last();
                    throw new Exception("Failed to copy '$f'. Reason: " . ($error['message'] ?? 'Permission Denied'));
                }
                $count++;
            }
        }
        closedir($dir);
        return $count;
    }

    if (isset($_FILES['update_file'])) {
        $file = $_FILES['update_file'];
    } elseif (!empty($_FILES)) {
        $file = reset($_FILES);
    } else {
        sendResponse(false, 'No file was uploaded to the server. Check your upload limits or Cloudflare restrictions.');
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        sendResponse(false, $uploadErrors[$file['error']] ?? 'Unknown upload error code: ' . $file['error']);
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (strtolower($ext) !== 'zip') {
        sendResponse(false, "Only ZIP files are allowed. You uploaded a .$ext file.");
    }
    
    $zipPath = $file['tmp_name'];
    
    if (!class_exists('ZipArchive')) {
        sendResponse(false, 'ZipArchive PHP extension is missing! Please enable the "zip" extension in your server PHP settings.');
    }

    $zip = new ZipArchive;
    if ($zip->open($zipPath) !== TRUE) {
        sendResponse(false, 'Cannot open the ZIP archive. File might be corrupted.');
    }

    // Extract to a temporary safe folder first
    $tempDir = dirname(__DIR__) . '/uploads/temp_update_' . time() . '_' . rand(100, 999);
    if (!@mkdir($tempDir, 0755, true)) {
        $zip->close();
        sendResponse(false, 'Failed to create temporary folder. Check permissions for /uploads/ directory.');
    }

    if (!$zip->extractTo($tempDir)) {
        $zip->close();
        sendResponse(false, 'Failed to extract ZIP to temporary folder.');
    }
    $zip->close();

    // Find the actual content root if wrapped in a single folder
    $sourceDir = $tempDir;
    $scan = @scandir($tempDir);
    if ($scan !== false) {
        $items = array_values(array_filter($scan, function($item) {
            return !in_array($item, ['.', '..', '__MACOSX', '.DS_Store']);
        }));
        
        if (count($items) === 1 && is_dir($tempDir . '/' . $items[0])) {
            $sourceDir = $tempDir . '/' . $items[0];
        }
    }

    // Cleanup function
    function deleteTempDir($dirPath) {
        if (!is_dir($dirPath)) return;
        $scan = @scandir($dirPath);
        if ($scan !== false) {
            $files = array_diff($scan, ['.', '..']);
            foreach ($files as $file) {
                $path = $dirPath . '/' . $file;
                is_dir($path) ? deleteTempDir($path) : @unlink($path);
            }
        }
        @rmdir($dirPath);
    }

    $blockedFiles = ['db.php', 'install.php','quick_setup.php', 'install.lock', 'patch_db.php', 'demo_data.php', 'admin/reset.php', '.env', 'cleanup.php', 'manual_update.php', '.htaccess'];
    $targetDir = dirname(__DIR__);

    $copiedCount = recursiveCopyUpdate($sourceDir, $targetDir, $blockedFiles);
    deleteTempDir($tempDir);

    if ($copiedCount > 0) {
        sendResponse(true, $copiedCount . ' files updated successfully!');
    } else {
        sendResponse(false, 'No files were updated. The update might be empty or folder write permissions are incorrect.');
    }
} catch (Exception $e) {
    if (isset($tempDir) && is_dir($tempDir)) {
        deleteTempDir($tempDir);
    }
    sendResponse(false, $e->getMessage());
}