<?php
session_start();

// List of files that are no longer needed
$filesToClean = [
    'install.php',
    'install.lock',
    'phpmailer.zip',
    'patch_db.php',
    'demo_data.php',
    'admin/reset.php',
    'cleanup.php' // Auto-delete this script itself at the end!
];

$deletedFiles = [];
foreach ($filesToClean as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        @unlink($filePath);
        $deletedFiles[] = $file;
    }
}

echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Cleanup Successful! 🎉</h2><p>The following unnecessary files have been securely removed from your server:</p><p style='color:green;'><b>" . implode(', ', $deletedFiles) . "</b></p><br><a href='./admin/dashboard.php' style='padding:10px 20px; background:#4f46e5; color:#fff; text-decoration:none; border-radius:5px;'>Go to Admin Dashboard</a></div>";
?>