<?php
session_start();
error_reporting(0);

// Check if already installed
if (file_exists(__DIR__ . '/db.php')) {
    $dbContent = file_get_contents(__DIR__ . '/db.php');
    if (preg_match('/\$host\s*=\s*[\'"](.*?)[\'"]/', $dbContent, $mHost) &&
        preg_match('/\$username\s*=\s*[\'"](.*?)[\'"]/', $dbContent, $mUser) &&
        preg_match('/\$password\s*=\s*[\'"](.*?)[\'"]/', $dbContent, $mPass) &&
        preg_match('/\$dbname\s*=\s*[\'"](.*?)[\'"]/', $dbContent, $mDbname)) {
        try {
            $testPdo = new PDO("mysql:host={$mHost[1]};dbname={$mDbname[1]};charset=utf8mb4", $mUser[1], $mPass[1]);
            $check = $testPdo->query("SHOW TABLES LIKE 'SettingGeneral'");
            if ($check && $check->rowCount() > 0) {
                die('<div style="text-align:center; padding: 50px; font-family: sans-serif;"><h2>System is already installed!</h2><p>If you want to reinstall, please delete db.php first.</p><a href="./admin/login.php" style="padding: 10px 20px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 5px; display: inline-block; mt-4">Go to Admin Panel</a></div>');
            }
        } catch (Exception $e) {}
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // 1. Connection Test
    if ($_POST['action'] === 'test_db') {
        $host = $_POST['db_host'] ?? 'localhost';
        $name = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';

        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (!empty($name)) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. Full Installation
    if ($_POST['action'] === 'install') {
        $host = $_POST['db_host'] ?? 'localhost';
        $name = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';
        
        $adminName = $_POST['admin_name'] ?? 'Admin';
        $adminEmail = $_POST['admin_email'] ?? 'admin@store.com';
        $adminPass = $_POST['admin_pass'] ?? '123456';

        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }

        // Step 1: Create db.php
        $dbContent = "<?php\n\$host = '$host';\n\$username = '$user';\n\$password = '$pass';\n\$dbname = '$name';\n\ntry {\n    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$username, \$password);\n    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n} catch (PDOException \$e) {\n    die(\"Database connection failed: \" . \$e->getMessage());\n}\n?>";
        if (file_put_contents(__DIR__ . '/db.php', $dbContent) === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to write db.php. Check folder permissions.']);
            exit;
        }

        try {
            // Step 2: Create All Database Tables (Quick Setup)
            $queries = [
                "CREATE TABLE IF NOT EXISTS `User` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `email` VARCHAR(255) NULL UNIQUE, `phone` VARCHAR(50) NULL UNIQUE, `password` VARCHAR(255) NOT NULL, `role` ENUM('admin', 'customer') DEFAULT 'customer', `otpCode` VARCHAR(10) NULL, `otpExpiresAt` DATETIME NULL, `address` TEXT NULL, `status` ENUM('active', 'blocked') DEFAULT 'active', `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                "CREATE TABLE IF NOT EXISTS `Category` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `slug` VARCHAR(255) NOT NULL UNIQUE, `parentId` INT NULL, `imageUrl` VARCHAR(255) NULL, `status` BOOLEAN DEFAULT TRUE)",
                "CREATE TABLE IF NOT EXISTS `Product` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `slug` VARCHAR(255) NOT NULL UNIQUE, `sku` VARCHAR(100) NULL, `serial_number` VARCHAR(255) NULL, `description` TEXT NULL, `basePrice` DECIMAL(10, 2) DEFAULT 0.00, `flashDealPrice` DECIMAL(10, 2) NULL, `isFlashDeal` BOOLEAN DEFAULT FALSE, `categoryId` INT NULL, `imageUrl` TEXT NULL, `seoTitle` VARCHAR(255) NULL, `seoDescription` TEXT NULL, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                "CREATE TABLE IF NOT EXISTS `Variant` (`id` INT AUTO_INCREMENT PRIMARY KEY, `productId` INT NOT NULL, `size` VARCHAR(50) DEFAULT 'Standard', `color` VARCHAR(50) DEFAULT 'Default', `stock` INT DEFAULT 0, `initialStock` INT DEFAULT 0, `imageUrl` VARCHAR(255) NULL, FOREIGN KEY (`productId`) REFERENCES `Product`(`id`) ON DELETE CASCADE)",
                "CREATE TABLE IF NOT EXISTS `Coupon` (`id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(50) NOT NULL UNIQUE, `discountType` ENUM('PERCENTAGE', 'FIXED') DEFAULT 'FIXED', `discountAmount` DECIMAL(10,2) NOT NULL, `minSpend` DECIMAL(10,2) DEFAULT 0, `expiryDate` DATETIME NULL, `usageLimit` INT DEFAULT NULL, `usedCount` INT DEFAULT 0, `status` BOOLEAN DEFAULT TRUE, `couponType` VARCHAR(50) DEFAULT 'GENERAL', `usagePerUser` INT DEFAULT 0, `applicableData` TEXT NULL, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                "CREATE TABLE IF NOT EXISTS `Order` (`id` INT AUTO_INCREMENT PRIMARY KEY, `userId` INT NOT NULL, `total` DECIMAL(10, 2) NOT NULL, `shippingCharge` DECIMAL(10, 2) DEFAULT 0.00, `discountAmount` DECIMAL(10, 2) DEFAULT 0.00, `couponCode` VARCHAR(50) NULL, `paymentMethod` VARCHAR(50) DEFAULT 'CASH ON DELIVERY', `status` ENUM('PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED') DEFAULT 'PENDING', `courierName` VARCHAR(255) NULL, `trackingLink` VARCHAR(255) NULL, `fraudCheckData` TEXT NULL, `cancelReason` TEXT NULL, `city` VARCHAR(100) NULL, `deliveryAddress` TEXT NULL, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE CASCADE)",
                "CREATE TABLE IF NOT EXISTS `OrderItem` (`id` INT AUTO_INCREMENT PRIMARY KEY, `orderId` INT NOT NULL, `productId` INT NOT NULL, `variantId` INT NULL, `quantity` INT DEFAULT 1, `price` DECIMAL(10, 2) NOT NULL, FOREIGN KEY (`orderId`) REFERENCES `Order`(`id`) ON DELETE CASCADE)",
                "CREATE TABLE IF NOT EXISTS `Banner` (`id` INT AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `targetUrl` VARCHAR(255) DEFAULT NULL, `imageUrl` VARCHAR(255) NOT NULL, `resourceType` VARCHAR(50) DEFAULT 'campaign', `active` BOOLEAN DEFAULT TRUE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                "CREATE TABLE IF NOT EXISTS `ScheduledPost` (`id` INT AUTO_INCREMENT PRIMARY KEY, `productId` INT NOT NULL, `caption` TEXT NULL, `imageUrl` VARCHAR(255) NULL, `publishAt` DATETIME NULL, `status` ENUM('PENDING', 'PUBLISHED', 'FAILED', 'DRAFT') DEFAULT 'PENDING', `fbPostId` VARCHAR(255) NULL, `useAiImage` BOOLEAN DEFAULT FALSE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`productId`) REFERENCES `Product`(`id`) ON DELETE CASCADE)",
                "CREATE TABLE IF NOT EXISTS `Notification` (`id` INT AUTO_INCREMENT PRIMARY KEY, `userId` INT NULL, `title` VARCHAR(255) NOT NULL, `message` TEXT NOT NULL, `type` VARCHAR(50) DEFAULT 'info', `isRead` BOOLEAN DEFAULT FALSE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                "CREATE TABLE IF NOT EXISTS `SettingGeneral` (`id` INT PRIMARY KEY DEFAULT 1, `storeName` VARCHAR(255) DEFAULT 'Idea Mart', `currencySymbol` VARCHAR(10) DEFAULT '৳', `supportEmail` VARCHAR(255) NULL, `supportPhone` VARCHAR(50) NULL, `whatsappNumber` VARCHAR(50) NULL, `logoUrl` VARCHAR(255) NULL, `faviconUrl` VARCHAR(255) NULL, `metaImage` VARCHAR(255) NULL, `flashDealEndTime` DATETIME NULL, `footerDescription` TEXT NULL, `footerCopyright` VARCHAR(255) NULL, `footerBottomText` VARCHAR(255) NULL, `facebookUrl` VARCHAR(255) NULL, `instagramUrl` VARCHAR(255) NULL, `twitterUrl` VARCHAR(255) NULL, `youtubeUrl` VARCHAR(255) NULL, `aiChatbotEnabled` BOOLEAN DEFAULT TRUE, `cartSyncEnabled` BOOLEAN DEFAULT TRUE, `fbtEnabled` BOOLEAN DEFAULT TRUE, `storeFeaturesEnabled` BOOLEAN DEFAULT TRUE, `otpSystemEnabled` BOOLEAN DEFAULT TRUE)",
                "CREATE TABLE IF NOT EXISTS `SettingNotification` (`id` INT PRIMARY KEY DEFAULT 1, `emailEnabled` BOOLEAN DEFAULT FALSE, `smtpHost` VARCHAR(255) NULL, `smtpPort` VARCHAR(10) NULL, `smtpUser` VARCHAR(255) NULL, `smtpPass` VARCHAR(255) NULL, `orderPlacedTemplate` TEXT NULL, `orderProcessingTemplate` TEXT NULL, `orderShippedTemplate` TEXT NULL, `orderDeliveredTemplate` TEXT NULL, `orderCancelledTemplate` TEXT NULL, `welcomeEmailTemplate` TEXT NULL, `otpEmailTemplate` TEXT NULL, `smsEnabled` BOOLEAN DEFAULT FALSE, `smsApiUrl` VARCHAR(255) NULL, `orderPlacedSmsTemplate` TEXT NULL, `orderProcessingSmsTemplate` TEXT NULL, `orderShippedSmsTemplate` TEXT NULL, `orderDeliveredSmsTemplate` TEXT NULL, `orderCancelledSmsTemplate` TEXT NULL, `welcomeSmsTemplate` TEXT NULL, `otpSmsTemplate` TEXT NULL, `fbAutoReplyEnabled` BOOLEAN DEFAULT FALSE, `fbAutoPosterEnabled` BOOLEAN DEFAULT TRUE, `fbPageId` VARCHAR(255) NULL, `fbPageAccessToken` TEXT NULL, `fbVerifyToken` VARCHAR(255) NULL, `fbCommentReply` TEXT NULL, `fbInboxReply` TEXT NULL, `fbBotPrompt` TEXT NULL, `activeAiProvider` VARCHAR(50) DEFAULT 'gemini', `geminiApiKey` VARCHAR(255) NULL, `geminiModelVersion` VARCHAR(50) DEFAULT 'gemini-1.5-flash', `openaiApiKey` VARCHAR(255) NULL, `geminiPrompt` TEXT NULL, `imagePrompt` TEXT NULL)",
                "CREATE TABLE IF NOT EXISTS `SettingApi` (`id` INT PRIMARY KEY DEFAULT 1, `fraudCheckerEnabled` BOOLEAN DEFAULT FALSE, `fraudCheckerApiKey` VARCHAR(255) NULL, `fraudCheckerMinRate` INT DEFAULT 50, `fraudCheckerAutoBlock` BOOLEAN DEFAULT FALSE, `steadfastEnabled` BOOLEAN DEFAULT FALSE, `steadfastApiKey` VARCHAR(255) NULL, `steadfastSecretKey` VARCHAR(255) NULL)",
                "CREATE TABLE IF NOT EXISTS `SettingPayment` (`id` INT PRIMARY KEY DEFAULT 1, `codEnabled` BOOLEAN DEFAULT TRUE, `bkashEnabled` BOOLEAN DEFAULT FALSE)",
                "CREATE TABLE IF NOT EXISTS `SettingDelivery` (`id` INT PRIMARY KEY DEFAULT 1, `insideCity` DECIMAL(10,2) DEFAULT 60.00, `outsideCity` DECIMAL(10,2) DEFAULT 120.00)",
                
                "INSERT IGNORE INTO `Category` (`id`, `name`, `slug`, `imageUrl`) VALUES (1, 'Electronics', 'electronics', 'https://placehold.co/400x400/003e86/ffffff?text=Electronics'), (2, 'Men\'s Fashion', 'mens-fashion', 'https://placehold.co/400x400/10b981/ffffff?text=Mens+Fashion')",
                "INSERT IGNORE INTO `Product` (`id`, `name`, `slug`, `description`, `basePrice`, `flashDealPrice`, `isFlashDeal`, `categoryId`, `sku`) VALUES (1, 'Premium Wireless Headphones', 'wireless-headphones', 'Immersive sound with active noise cancellation.', 5500, 3999, 1, 1, 'ELEC-001'), (2, 'Smart Fitness Watch', 'smart-fitness-watch', 'Track your health on the go.', 3500, 2450, 1, 1, 'ELEC-002')",
                "INSERT IGNORE INTO `Variant` (`productId`, `size`, `color`, `stock`, `initialStock`, `imageUrl`) VALUES (1, 'Standard', 'Black', 50, 50, 'https://placehold.co/600x600/000000/ffffff?text=Headphone+Black'), (2, '44mm', 'Midnight', 100, 100, 'https://placehold.co/600x600/1e293b/ffffff?text=SmartWatch')",
                "UPDATE `Product` p SET `imageUrl` = (SELECT `imageUrl` FROM `Variant` v WHERE v.productId = p.id LIMIT 1) WHERE `imageUrl` IS NULL",
                "INSERT IGNORE INTO `Banner` (`id`, `title`, `targetUrl`, `imageUrl`, `resourceType`) VALUES (1, 'Mega Summer Sale', 'shop', 'https://placehold.co/1200x400/003e86/ffffff?text=Summer+Sale', 'campaign')"
            ];
            foreach ($queries as $sql) { $pdo->exec($sql); }

            // Step 3: Insert Admin Account
            $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT id FROM `User` WHERE email = ?");
            $stmt->execute([$adminEmail]);
            if (!$stmt->fetch()) {
                $pdo->prepare("INSERT INTO `User` (name, email, password, role) VALUES (?, ?, ?, 'admin')")->execute([$adminName, $adminEmail, $hashedPass]);
            } else {
                $pdo->prepare("UPDATE `User` SET name = ?, password = ?, role = 'admin' WHERE email = ?")->execute([$adminName, $hashedPass, $adminEmail]);
            }

            // Initialize default row settings
            $pdo->exec("INSERT IGNORE INTO `SettingGeneral` (id, storeName) VALUES (1, 'Smart E-Commerce')");
            $pdo->exec("INSERT IGNORE INTO `SettingNotification` (id) VALUES (1)");
            $pdo->exec("INSERT IGNORE INTO `SettingApi` (id) VALUES (1)");
            $pdo->exec("INSERT IGNORE INTO `SettingPayment` (id) VALUES (1)");
            $pdo->exec("INSERT IGNORE INTO `SettingDelivery` (id) VALUES (1)");

            // Step 4: Run Auto Mail Setup (PHPMailer)
            $zipFile = __DIR__ . '/phpmailer.zip';
            $targetDir = __DIR__ . '/PHPMailer';
            $opts = ["http" => ["method" => "GET", "header" => "User-Agent: Mozilla/5.0\r\n"], "ssl" => ["verify_peer" => false, "verify_peer_name" => false]];
            $context = stream_context_create($opts);
            $data = @file_get_contents('https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip', false, $context);
            
            if ($data !== false && !empty($data)) {
                if (file_put_contents($zipFile, $data) !== false) {
                    $zip = new ZipArchive;
                    if ($zip->open($zipFile) === TRUE) {
                        $zip->extractTo(__DIR__);
                        $zip->close();
                        @unlink($zipFile);
                        
                        $dirs = scandir(__DIR__);
                        foreach ($dirs as $dir) {
                            if ($dir !== '.' && $dir !== '..' && is_dir(__DIR__ . '/' . $dir)) {
                                if (file_exists(__DIR__ . '/' . $dir . '/src/PHPMailer.php') || file_exists(__DIR__ . '/' . $dir . '/PHPMailer.php')) {
                                    @rename(__DIR__ . '/' . $dir, $targetDir);
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // Create Uploads directories
            if (!is_dir(__DIR__ . '/uploads')) @mkdir(__DIR__ . '/uploads', 0777, true);
            if (!is_dir(__DIR__ . '/uploads/banners')) @mkdir(__DIR__ . '/uploads/banners', 0777, true);

            // Lock Installation for safety
            @rename(__FILE__, __DIR__ . '/install.lock');

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Installer & Quick Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .step-hidden { display: none; }
        .step-active { display: block; animation: slideUp 0.4s ease-out forwards; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 selection:bg-indigo-100 selection:text-indigo-900">

    <div class="w-full max-w-4xl bg-white rounded-3xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.1)] border border-slate-100 flex flex-col md:flex-row overflow-hidden">
        
        <!-- Left Sidebar (Steps) -->
        <div class="md:w-1/3 bg-slate-900 text-white p-8 lg:p-10 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 to-transparent"></div>
            
            <div class="relative z-10">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center mb-8 shadow-lg">
                    <i data-lucide="rocket" class="w-6 h-6 text-indigo-600"></i>
                </div>
                <h2 class="text-2xl font-extrabold mb-2 tracking-tight">Installation</h2>
                <p class="text-slate-400 text-sm mb-10 leading-relaxed">Complete these 3 simple steps to get your powerful e-commerce store running.</p>
                
                <div class="space-y-8 relative before:absolute before:inset-0 before:ml-3 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-700 before:to-transparent">
                    
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active" id="nav-step-1">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 bg-indigo-600 border-indigo-600 text-white shadow shadow-indigo-600/50 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 transition-all indicator-icon"><i data-lucide="database" class="w-3 h-3"></i></div>
                        <div class="w-[calc(100%-3rem)] md:w-[calc(50%-1.5rem)] text-left md:group-odd:text-right">
                            <h4 class="text-sm font-bold indicator-text text-white">Database</h4>
                        </div>
                    </div>

                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group" id="nav-step-2">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 bg-slate-900 border-slate-700 text-slate-500 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 transition-all indicator-icon"><i data-lucide="user" class="w-3 h-3"></i></div>
                        <div class="w-[calc(100%-3rem)] md:w-[calc(50%-1.5rem)] text-left md:group-even:text-left">
                            <h4 class="text-sm font-bold indicator-text text-slate-500">Admin</h4>
                        </div>
                    </div>

                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group" id="nav-step-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 bg-slate-900 border-slate-700 text-slate-500 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 transition-all indicator-icon"><i data-lucide="check" class="w-3 h-3"></i></div>
                        <div class="w-[calc(100%-3rem)] md:w-[calc(50%-1.5rem)] text-left md:group-odd:text-right">
                            <h4 class="text-sm font-bold indicator-text text-slate-500">Finish</h4>
                        </div>
                    </div>

                </div>
            </div>
            
            <div class="relative z-10 mt-12 pt-6 border-t border-slate-700/50">
                <p class="text-xs text-slate-500 flex items-center gap-1"><i data-lucide="shield-check" class="w-3 h-3"></i> Secure Installation Process</p>
            </div>
        </div>

        <!-- Right Content Area -->
        <div class="md:w-2/3 p-8 lg:p-12 relative bg-white min-h-[500px] flex flex-col justify-center">
            
            <form id="install-form" onsubmit="return false;" autocomplete="off">
                
                <!-- Step 1: Database -->
                <div id="step-1" class="step-active">
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">Database Configuration</h2>
                        <p class="text-sm text-slate-500 mt-1">Enter your MySQL database connection details.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">Database Host</label>
                            <input type="text" id="db_host" value="localhost" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">Database Name</label>
                            <input type="text" id="db_name" placeholder="e.g. store_db" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">DB Username</label>
                                <input type="text" id="db_user" placeholder="root" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">DB Password</label>
                                <input type="password" id="db_pass" placeholder="••••••••" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                            </div>
                        </div>
                    </div>
                    
                    <div id="db-error" class="hidden mt-4 p-3 bg-rose-50 text-rose-600 text-sm font-semibold border border-rose-100 rounded-xl flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4"></i> <span></span>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button type="button" onclick="validateStep1()" id="btn-next-1" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3.5 rounded-xl font-bold transition-all shadow-md flex items-center gap-2">
                            Test Connection & Next <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Admin Account -->
                <div id="step-2" class="step-hidden">
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">Admin Account Setup</h2>
                        <p class="text-sm text-slate-500 mt-1">Create the owner account for the dashboard.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">Full Name</label>
                            <input type="text" id="admin_name" placeholder="John Doe" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">Email Address (Login ID)</label>
                            <input type="email" id="admin_email" placeholder="admin@store.com" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-widest mb-1.5 ml-1">Password</label>
                            <input type="password" id="admin_pass" placeholder="••••••••" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex justify-between">
                        <button type="button" onclick="goToStep(1)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-6 py-3.5 rounded-xl font-bold transition-all flex items-center gap-2">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                        </button>
                        <button type="button" onclick="startInstallation()" id="btn-install" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3.5 rounded-xl font-bold transition-all shadow-md shadow-indigo-600/30 flex items-center gap-2">
                            <i data-lucide="zap" class="w-4 h-4"></i> Install System
                        </button>
                    </div>
                </div>

                <!-- Step 3: Loading / Success -->
                <div id="step-3" class="step-hidden text-center py-10">
                    
                    <!-- Processing State -->
                    <div id="install-processing">
                        <div class="relative w-24 h-24 mx-auto mb-6">
                            <svg class="animate-spin text-indigo-100 w-full h-full" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <i data-lucide="loader" class="w-8 h-8 text-indigo-600 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-slate-900 mb-2">Installing System...</h2>
                        <p class="text-sm text-slate-500 font-medium animate-pulse" id="loading-text">Configuring database and background services...</p>
                        
                        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-8 overflow-hidden">
                            <div id="progress-bar" class="bg-indigo-600 h-1.5 rounded-full" style="width: 10%; transition: width 0.5s ease-out;"></div>
                        </div>
                    </div>

                    <!-- Success State -->
                    <div id="install-success" class="hidden">
                        <div class="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner border-4 border-white outline outline-1 outline-emerald-200">
                            <i data-lucide="check-circle" class="w-12 h-12 text-emerald-600"></i>
                        </div>
                        <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Installation Complete! 🎉</h2>
                        <p class="text-sm text-slate-500 font-medium mb-8 leading-relaxed max-w-sm mx-auto">Database, admin account, and mail services have been successfully configured. Your store is ready to launch.</p>
                        
                        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-left max-w-sm mx-auto mb-8 relative">
                            <p class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-2">Admin Login Details</p>
                            <p class="text-sm text-indigo-900 font-semibold mb-1"><span class="text-indigo-500 w-16 inline-block">URL:</span> /admin/login.php</p>
                            <p class="text-sm text-indigo-900 font-semibold mb-1"><span class="text-indigo-500 w-16 inline-block">Email:</span> <span id="res-email"></span></p>
                            <p class="text-sm text-indigo-900 font-semibold"><span class="text-indigo-500 w-16 inline-block">Pass:</span> <span id="res-pass">••••••</span></p>
                        </div>

                        <a href="./admin/login.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3.5 rounded-xl font-bold transition-all shadow-lg inline-flex items-center gap-2">
                            Go to Admin Dashboard <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                    <!-- Error State -->
                    <div id="install-failed" class="hidden">
                        <div class="w-20 h-20 bg-rose-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i data-lucide="x-circle" class="w-10 h-10 text-rose-600"></i>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2">Installation Failed</h2>
                        <p class="text-sm text-rose-600 font-semibold mb-8" id="fail-reason">Something went wrong.</p>
                        
                        <button type="button" onclick="goToStep(1)" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-bold transition-all inline-flex items-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i> Try Again
                        </button>
                    </div>

                </div>

            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function goToStep(step) {
            document.querySelectorAll('[id^="step-"]').forEach(el => {
                el.classList.remove('step-active');
                el.classList.add('step-hidden');
            });
            document.getElementById('step-' + step).classList.remove('step-hidden');
            document.getElementById('step-' + step).classList.add('step-active');

            // Update sidebar indicators
            updateIndicators(step);
        }

        function updateIndicators(activeStep) {
            [1, 2, 3].forEach(step => {
                const nav = document.getElementById('nav-step-' + step);
                const icon = nav.querySelector('.indicator-icon');
                const text = nav.querySelector('.indicator-text');
                
                if (step < activeStep) {
                    // Completed
                    icon.className = 'flex items-center justify-center w-6 h-6 rounded-full border-2 bg-indigo-600 border-indigo-600 text-white shrink-0 z-10 transition-all indicator-icon';
                    icon.innerHTML = '<svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    text.className = 'text-sm font-bold indicator-text text-white';
                } else if (step === activeStep) {
                    // Active
                    icon.className = 'flex items-center justify-center w-6 h-6 rounded-full border-2 bg-indigo-600 border-indigo-600 text-white shadow shadow-indigo-600/50 shrink-0 z-10 transition-all indicator-icon';
                    text.className = 'text-sm font-bold indicator-text text-white';
                } else {
                    // Pending
                    icon.className = 'flex items-center justify-center w-6 h-6 rounded-full border-2 bg-slate-900 border-slate-700 text-slate-500 shrink-0 z-10 transition-all indicator-icon';
                    text.className = 'text-sm font-bold indicator-text text-slate-500';
                }
            });
        }

        async function validateStep1() {
            const btn = document.getElementById('btn-next-1');
            const errorBox = document.getElementById('db-error');
            const host = document.getElementById('db_host').value;
            const name = document.getElementById('db_name').value;
            const user = document.getElementById('db_user').value;
            const pass = document.getElementById('db_pass').value;

            if (!host || !name || !user) {
                errorBox.querySelector('span').innerText = 'Please fill all required database fields.';
                errorBox.classList.remove('hidden');
                return;
            }

            errorBox.classList.add('hidden');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Connecting...';

            try {
                const fd = new FormData();
                fd.append('action', 'test_db');
                fd.append('db_host', host);
                fd.append('db_name', name);
                fd.append('db_user', user);
                fd.append('db_pass', pass);

                const res = await fetch('', { method: 'POST', body: fd });
                const result = await res.json();

                if (result.success) {
                    goToStep(2);
                } else {
                    errorBox.querySelector('span').innerText = result.error;
                    errorBox.classList.remove('hidden');
                }
            } catch (e) {
                errorBox.querySelector('span').innerText = 'Network error occurred.';
                errorBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        async function startInstallation() {
            const adminName = document.getElementById('admin_name').value;
            const adminEmail = document.getElementById('admin_email').value;
            const adminPass = document.getElementById('admin_pass').value;

            if (!adminName || !adminEmail || !adminPass) {
                alert("Please fill all admin details!");
                return;
            }

            goToStep(3);
            
            const progress = document.getElementById('progress-bar');
            const loadText = document.getElementById('loading-text');
            
            setTimeout(() => { progress.style.width = '40%'; loadText.innerText = 'Creating database schema...'; }, 500);
            setTimeout(() => { progress.style.width = '70%'; loadText.innerText = 'Setting up Admin & downloading PHPMailer...'; }, 1500);

            try {
                const fd = new FormData();
                fd.append('action', 'install');
                fd.append('db_host', document.getElementById('db_host').value);
                fd.append('db_name', document.getElementById('db_name').value);
                fd.append('db_user', document.getElementById('db_user').value);
                fd.append('db_pass', document.getElementById('db_pass').value);
                fd.append('admin_name', adminName);
                fd.append('admin_email', adminEmail);
                fd.append('admin_pass', adminPass);

                const res = await fetch('', { method: 'POST', body: fd });
                const result = await res.json();

                progress.style.width = '100%';
                
                setTimeout(() => {
                    document.getElementById('install-processing').classList.add('hidden');
                    if (result.success) {
                        document.getElementById('res-email').innerText = adminEmail;
                        document.getElementById('install-success').classList.remove('hidden');
                    } else {
                        document.getElementById('fail-reason').innerText = result.error;
                        document.getElementById('install-failed').classList.remove('hidden');
                    }
                }, 500);

            } catch (e) {
                document.getElementById('install-processing').classList.add('hidden');
                document.getElementById('fail-reason').innerText = 'Fatal network or server error.';
                document.getElementById('install-failed').classList.remove('hidden');
            }
        }
    </script>
</body>
</html>