<?php
/**
 * ONE-CLICK MAGIC INSTALLER FOR XAMPP
 * This file will automatically create the database, tables, and fill them with dummy data.
 */

// XAMPP (Localhost) এর জন্য ডাটাবেস এর তথ্য
$host = 'localhost';
$username = 'root'; // XAMPP এর ডিফল্ট ইউজারনেম
$password = ''; // XAMPP এর ডিফল্ট পাসওয়ার্ড ব্ল্যাংক থাকে
$dbname = 'ideamart_v2'; // Corrupted ফোল্ডার এড়াতে নতুন নাম দেওয়া হলো

$messages = [];
$isSuccess = false;

try {
    // XAMPP এ ডাটাবেস তৈরি করার জন্য প্রথমে শুধু MySQL এ কানেক্ট করা
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ডাটাবেস তৈরি এবং সিলেক্ট করা
    // Corrupted tablespace এরর এড়াতে পুরো ডাটাবেস ড্রপ করে নতুন করে বানানো হচ্ছে
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    
    $messages[] = "✓ Connected to Database '$dbname' successfully.";

    // Create db.php config file
    $dbConfig = "<?php\n\$host = '$host';\n\$username = '$username';\n\$password = '$password';\n\$dbname = '$dbname';\n\ntry {\n    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$username, \$password);\n    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n} catch (PDOException \$e) {\n    die(\"Database connection failed: \" . \$e->getMessage());\n}\n?>";
    file_put_contents(__DIR__ . '/db.php', $dbConfig);
    $messages[] = "✓ Configuration file 'db.php' generated.";

    // CREATE TABLES (Exact schema from install.php)
    $pdo->exec("CREATE TABLE `User` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL, `email` VARCHAR(100) UNIQUE DEFAULT NULL, `phone` VARCHAR(20) UNIQUE DEFAULT NULL, `password` VARCHAR(255) NOT NULL, `role` ENUM('admin', 'customer') DEFAULT 'customer', `otpCode` VARCHAR(10) NULL, `otpExpiresAt` DATETIME NULL, `cartData` TEXT NULL, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE `Category` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `slug` VARCHAR(255) NOT NULL UNIQUE, `imageUrl` VARCHAR(255) DEFAULT NULL, `parentId` INT DEFAULT NULL, `status` BOOLEAN DEFAULT TRUE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE `Product` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `slug` VARCHAR(255) NOT NULL UNIQUE, `description` TEXT, `basePrice` DECIMAL(10,2) NOT NULL, `flashDealPrice` DECIMAL(10,2) DEFAULT NULL, `isFlashDeal` BOOLEAN DEFAULT FALSE, `imageUrl` VARCHAR(255) DEFAULT NULL, `categoryId` INT DEFAULT NULL, `sku` VARCHAR(100) DEFAULT NULL, `serial_number` VARCHAR(100) DEFAULT NULL, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE `Banner` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `targetUrl` VARCHAR(255) DEFAULT NULL, `imageUrl` VARCHAR(255) NOT NULL, `resourceType` VARCHAR(50) DEFAULT 'campaign', `active` BOOLEAN DEFAULT TRUE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE `Variant` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `productId` INT NOT NULL, `size` VARCHAR(50) DEFAULT NULL, `color` VARCHAR(50) DEFAULT NULL, `stock` INT DEFAULT 0, `initialStock` INT DEFAULT 0, `imageUrl` VARCHAR(255) DEFAULT NULL, FOREIGN KEY (`productId`) REFERENCES `Product`(`id`) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE `Order` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `userId` INT NOT NULL, `total` DECIMAL(10,2) NOT NULL, `shippingCharge` DECIMAL(10,2) DEFAULT 0.00, `discountAmount` DECIMAL(10,2) DEFAULT 0.00, `status` VARCHAR(50) DEFAULT 'PENDING', `cancelReason` TEXT DEFAULT NULL, `courierName` VARCHAR(255) DEFAULT NULL, `trackingLink` VARCHAR(255) DEFAULT NULL, `fraudCheckData` LONGTEXT NULL, `couponCode` VARCHAR(50) NULL, `deliveryAddress` TEXT DEFAULT NULL, `city` VARCHAR(100) DEFAULT NULL, `paymentMethod` VARCHAR(50) DEFAULT 'CASH ON DELIVERY', `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE `OrderItem` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `orderId` INT NOT NULL, `productId` INT NOT NULL, `variantId` INT DEFAULT NULL, `quantity` INT NOT NULL DEFAULT 1, `price` DECIMAL(10,2) NOT NULL, FOREIGN KEY (`orderId`) REFERENCES `Order`(`id`) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE `Coupon` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(50) NOT NULL UNIQUE, `discountType` ENUM('PERCENTAGE', 'FIXED') DEFAULT 'FIXED', `discountAmount` DECIMAL(10,2) NOT NULL, `minSpend` DECIMAL(10,2) DEFAULT 0, `expiryDate` DATETIME NULL, `usageLimit` INT DEFAULT NULL, `usedCount` INT DEFAULT 0, `status` BOOLEAN DEFAULT TRUE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE `SettingGeneral` (`id` INT AUTO_INCREMENT PRIMARY KEY, `storeName` VARCHAR(255) DEFAULT '', `brandColor` VARCHAR(20) DEFAULT '#003e86', `logoUrl` VARCHAR(255) DEFAULT NULL, `faviconUrl` VARCHAR(255) DEFAULT NULL, `metaTitle` VARCHAR(255) NULL, `metaDescription` TEXT DEFAULT NULL, `metaImage` VARCHAR(255) NULL, `supportEmail` VARCHAR(100) DEFAULT NULL, `supportPhone` VARCHAR(20) DEFAULT NULL, `whatsappNumber` VARCHAR(20) NULL, `currency` VARCHAR(10) DEFAULT 'BDT', `currencySymbol` VARCHAR(10) DEFAULT '৳', `facebookUrl` VARCHAR(255) DEFAULT NULL, `instagramUrl` VARCHAR(255) DEFAULT NULL, `twitterUrl` VARCHAR(255) DEFAULT NULL, `youtubeUrl` VARCHAR(255) DEFAULT NULL, `flashDealEndTime` DATETIME DEFAULT NULL, `guestCheckoutEnabled` BOOLEAN DEFAULT TRUE, `otpSystemEnabled` BOOLEAN DEFAULT FALSE, `otpLoginEnabled` BOOLEAN DEFAULT FALSE, `otpRegistrationEnabled` BOOLEAN DEFAULT FALSE, `footerDescription` TEXT NULL, `footerCopyright` VARCHAR(255) NULL, `footerBottomText` VARCHAR(255) NULL, `fbPixelId` VARCHAR(255) NULL, `googleAnalyticsId` VARCHAR(255) NULL, `storeFeaturesEnabled` BOOLEAN DEFAULT TRUE, `aiChatbotEnabled` BOOLEAN DEFAULT TRUE, `cartSyncEnabled` BOOLEAN DEFAULT TRUE, `fbtEnabled` BOOLEAN DEFAULT TRUE)");
    $pdo->exec("CREATE TABLE `SettingPayment` (`id` INT AUTO_INCREMENT PRIMARY KEY, `codEnabled` BOOLEAN DEFAULT TRUE, `bkashEnabled` BOOLEAN DEFAULT FALSE, `bkashAppKey` VARCHAR(255) DEFAULT NULL, `bkashAppSecret` VARCHAR(255) DEFAULT NULL, `bkashUsername` VARCHAR(255) DEFAULT NULL, `bkashPassword` VARCHAR(255) DEFAULT NULL, `bkashBaseUrl` VARCHAR(255) DEFAULT NULL)");
    $pdo->exec("CREATE TABLE `SettingDelivery` (`id` INT AUTO_INCREMENT PRIMARY KEY, `deliveryInsideDhaka` DECIMAL(10,2) DEFAULT 60.00, `deliveryOutsideDhaka` DECIMAL(10,2) DEFAULT 120.00)");
    $pdo->exec("CREATE TABLE `SettingNotification` (`id` INT AUTO_INCREMENT PRIMARY KEY, `emailEnabled` BOOLEAN DEFAULT FALSE, `smtpHost` VARCHAR(255) DEFAULT NULL, `smtpUser` VARCHAR(255) DEFAULT NULL, `smtpPass` VARCHAR(255) DEFAULT NULL, `smtpPort` INT DEFAULT 465, `orderPlacedTemplate` TEXT DEFAULT NULL, `orderProcessingTemplate` TEXT DEFAULT NULL, `orderShippedTemplate` TEXT DEFAULT NULL, `orderDeliveredTemplate` TEXT DEFAULT NULL, `orderCancelledTemplate` TEXT DEFAULT NULL, `welcomeEmailTemplate` TEXT DEFAULT NULL, `otpEmailTemplate` TEXT DEFAULT NULL, `smsEnabled` BOOLEAN DEFAULT FALSE, `smsApiUrl` VARCHAR(255) DEFAULT NULL, `smsSenderId` VARCHAR(255) DEFAULT NULL, `orderPlacedSmsTemplate` TEXT DEFAULT NULL, `orderProcessingSmsTemplate` TEXT DEFAULT NULL, `orderShippedSmsTemplate` TEXT DEFAULT NULL, `orderDeliveredSmsTemplate` TEXT DEFAULT NULL, `orderCancelledSmsTemplate` TEXT DEFAULT NULL, `welcomeSmsTemplate` TEXT DEFAULT NULL, `otpSmsTemplate` TEXT DEFAULT NULL, `fbAutoReplyEnabled` BOOLEAN DEFAULT FALSE, `fbPageId` VARCHAR(255) DEFAULT NULL, `fbPageAccessToken` TEXT DEFAULT NULL, `fbVerifyToken` VARCHAR(255) DEFAULT NULL, `fbCommentReply` TEXT DEFAULT NULL, `fbInboxReply` TEXT DEFAULT NULL, `fbBotPrompt` TEXT NULL, `activeAiProvider` VARCHAR(50) DEFAULT 'gemini', `geminiApiKey` VARCHAR(255) NULL, `geminiModelVersion` VARCHAR(50) DEFAULT 'gemini-1.5-flash', `openaiApiKey` VARCHAR(255) NULL, `geminiPrompt` TEXT NULL, `imagePrompt` TEXT NULL)");
    $pdo->exec("CREATE TABLE `Notification` (`id` INT AUTO_INCREMENT PRIMARY KEY, `userId` INT NULL, `title` VARCHAR(255) NOT NULL, `message` TEXT, `type` VARCHAR(50) DEFAULT 'info', `isRead` BOOLEAN DEFAULT FALSE, `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE CASCADE)");
    
    $messages[] = "✓ All 13 tables generated successfully.";

    // INSERT SETTINGS
    $flashEndTime = date('Y-m-d H:i:s', strtotime('+3 days')); // 3 Days Flash Deal
    $pdo->exec("INSERT INTO `SettingGeneral` (`id`, `storeName`, `flashDealEndTime`, `currencySymbol`) VALUES (1, 'Idea Mart', '$flashEndTime', '৳')");
    $pdo->exec("INSERT INTO `SettingPayment` (`id`, `codEnabled`, `bkashEnabled`) VALUES (1, 1, 1)");
    $pdo->exec("INSERT INTO `SettingDelivery` (`id`) VALUES (1)");

    $defaultOrderHtml = '<div style="font-family: \'Segoe UI\', Arial, sans-serif; color: #333333; max-width: 600px; margin: 0 auto; padding: 40px 30px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #2563eb; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">{{store_name}}</h1>
    </div>
    <div style="text-align: center; margin-bottom: 30px;">
        <span style="font-size: 48px;">🎉</span>
        <h2 style="color: #0f172a; margin-top: 10px; margin-bottom: 5px; font-size: 24px; font-weight: 700;">Order Confirmed!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Order ID: <strong style="color: #2563eb;">#{{order_id}}</strong></p>
    </div>
    <p style="color: #475569; font-size: 16px; margin-bottom: 10px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6; margin-top: 0;">
        Thank you for shopping with us! We have successfully received your order and our team is already working hard to get it ready for shipment.
    </p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">
        You will receive another update via email or SMS with a tracking link once your package is on its way.
    </p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <h3 style="color: #0f172a; font-size: 16px; margin-top: 0; margin-bottom: 15px;">Order Summary</h3>
    <div style="background-color: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 15px; color: #475569;">
            {{order_items_html}}
            <tr style="border-top: 1px solid #e2e8f0;">
                <td style="padding-top: 15px; font-weight: 700; color: #0f172a;">Total Paid</td>
                <td style="text-align: right; padding-top: 15px; font-weight: 700; color: #2563eb; font-size: 18px;">{{total_amount}}</td>
            </tr>
        </table>
    </div>
    <h3 style="color: #0f172a; font-size: 16px; margin-top: 0; margin-bottom: 10px;">Shipping Address</h3>
    <p style="color: #64748b; font-size: 14px; line-height: 1.5; margin-top: 0; background-color: #f8fafc; border-radius: 12px; padding: 15px;">
        <strong>{{customer_name}}</strong><br>
        {{shipping_address}}<br>
        Phone: {{customer_phone}}
    </p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <div style="text-align: center;">
        <p style="color: #475569; font-size: 15px; margin-bottom: 5px;">Need help with your order?</p>
        <p style="margin-top: 0; margin-bottom: 25px;"><a href="mailto:{{support_email}}" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Contact Support Team →</a></p>
        <p style="color: #94a3b8; font-size: 13px; margin: 0;">Best regards,</p>
        <p style="color: #0f172a; font-size: 15px; font-weight: 700; margin-top: 5px; margin-bottom: 0;">{{store_name}} Team</p>
    </div>
</div>';
    $defaultProcessingHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">⚙️</span></div>
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px; text-align: center;">Order is Processing!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Great news! We are currently processing your order <strong style="color:#f59e0b;">#{{order_id}}</strong>. Our team is carefully packing your items and preparing them for shipment.</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">We will notify you again once your package is out for delivery.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';
    $defaultShippedHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">🚚</span></div>
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px; text-align: center;">Your Order has Shipped!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Great news! Your order <strong style="color:#2563eb;">#{{order_id}}</strong> has been handed over to our delivery partner and is on its way to you.</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">You can track your package using the link below:</p>
    <div style="text-align: center; margin: 30px 0;"><a href="{{tracking_link}}" style="background: #0f172a; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;">Track Order</a></div>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';
    $defaultDeliveredHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">📦</span></div>
    <h2 style="color: #10b981; margin-top:0; font-size: 24px; text-align: center;">Order Delivered Successfully!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Your order <strong style="color:#10b981;">#{{order_id}}</strong> has been successfully delivered. We hope you love your purchase!</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">If you have any issues or feedback, please feel free to reach out to our support team.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';
    $defaultCancelledHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">❌</span></div>
    <h2 style="color: #e11d48; margin-top:0; font-size: 24px; text-align: center;">Order Cancelled</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">We regret to inform you that your order <strong style="color:#e11d48;">#{{order_id}}</strong> has been cancelled.</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">If you have already paid for this order, the refund process will be initiated shortly according to our standard policy.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';
    $defaultWelcomeHtml = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <div style="text-align: center; margin-bottom: 20px;"><span style="font-size: 48px;">👋</span></div>
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px; text-align: center;">Welcome to {{store_name}}!</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">We are thrilled to have you here! Your account has been successfully created. You can now track your orders, save your favorite products, and enjoy a seamless shopping experience.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
    <p style="color: #64748b; font-size: 14px; text-align: center;">Best regards,<br><strong style="color: #0f172a;">{{store_name}} Team</strong></p>
</div>';
    $defaultOtpEmail = '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;">
    <h2 style="color: #0f172a; margin-top:0; font-size: 24px;">Security Verification 🔒</h2>
    <p style="color: #475569; font-size: 16px;">Hi <strong>{{customer_name}}</strong>,</p>
    <p style="color: #475569; font-size: 16px; line-height: 1.6;">Your One-Time Password (OTP) for verification is:</p>
    <div style="font-size: 32px; font-weight: 900; background: #f8fafc; padding: 20px; text-align: center; letter-spacing: 8px; border-radius: 12px; margin: 30px 0; color: #0f172a; border: 1px dashed #cbd5e1;">
        {{otp_code}}
    </div>
    <p style="font-size: 13px; color: #94a3b8; line-height: 1.5;">This code is valid for the next 10 minutes. For your security, please do not share this code with anyone.</p>
</div>';
    $defaultOrderSms = 'Hi {{customer_name}}, your order #{{order_id}} has been confirmed successfully.';
    $defaultProcessingSms = 'Your order #{{order_id}} is now processing. We will notify you once shipped.';
    $defaultShippedSms = 'Your order #{{order_id}} has been shipped! Track it here: {{tracking_link}}';
    $defaultDeliveredSms = 'Your order #{{order_id}} has been successfully delivered. Thank you for shopping with us!';
    $defaultCancelledSms = 'Your order #{{order_id}} has been cancelled. Contact support for more info.';
    $defaultWelcomeSms = 'Welcome to {{store_name}}, {{customer_name}}! We are excited to have you on board.';
    $defaultOtpSms = 'Your {{store_name}} verification code is: {{otp_code}}. Do not share this code with anyone.';

    $pdo->prepare("INSERT INTO `SettingNotification` (`id`, `orderPlacedTemplate`, `orderProcessingTemplate`, `orderShippedTemplate`, `orderDeliveredTemplate`, `orderCancelledTemplate`, `welcomeEmailTemplate`, `otpEmailTemplate`, `orderPlacedSmsTemplate`, `orderProcessingSmsTemplate`, `orderShippedSmsTemplate`, `orderDeliveredSmsTemplate`, `orderCancelledSmsTemplate`, `welcomeSmsTemplate`, `otpSmsTemplate`) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $defaultOrderHtml, $defaultProcessingHtml, $defaultShippedHtml, $defaultDeliveredHtml, $defaultCancelledHtml, $defaultWelcomeHtml, $defaultOtpEmail, 
        $defaultOrderSms, $defaultProcessingSms, $defaultShippedSms, $defaultDeliveredSms, $defaultCancelledSms, $defaultWelcomeSms, $defaultOtpSms
    ]);

    // INSERT DUMMY ADMIN
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO `User` (`name`, `email`, `password`, `role`) VALUES ('Super Admin', 'admin@store.com', '$adminPass', 'admin')");
    $messages[] = "✓ Admin account created (admin@store.com / admin123).";

    // INSERT DUMMY CATEGORIES
    $pdo->exec("INSERT INTO `Category` (`id`, `name`, `slug`, `imageUrl`) VALUES 
        (1, 'Electronics', 'electronics', 'https://placehold.co/400x400/003e86/ffffff?text=Electronics'),
        (2, 'Men\'s Fashion', 'mens-fashion', 'https://placehold.co/400x400/10b981/ffffff?text=Mens+Fashion'),
        (3, 'Women\'s Fashion', 'womens-fashion', 'https://placehold.co/400x400/e11d48/ffffff?text=Womens+Fashion'),
        (4, 'Home & Living', 'home-living', 'https://placehold.co/400x400/f59e0b/ffffff?text=Home+Living')
    ");

    // INSERT DUMMY PRODUCTS
    $pdo->exec("INSERT INTO `Product` (`id`, `name`, `slug`, `description`, `basePrice`, `flashDealPrice`, `isFlashDeal`, `categoryId`, `sku`) VALUES 
        (1, 'Premium Wireless Headphones', 'wireless-headphones', 'Immersive sound with active noise cancellation. 30 hours of battery life.', 5500, 3999, 1, 1, 'ELEC-001'),
        (2, 'Smart Fitness Watch Series 7', 'smart-fitness-watch', 'Track your health, heart rate, and notifications on the go.', 3500, 2450, 1, 1, 'ELEC-002'),
        (3, 'Men\'s Premium Cotton Shirt', 'mens-cotton-shirt', 'Comfortable and breathable casual shirt for everyday wear.', 1200, NULL, 0, 2, 'MENS-001'),
        (4, 'Designer Leather Handbag', 'designer-leather-handbag', 'Elegant and spacious leather handbag for women.', 2800, NULL, 0, 3, 'WMN-001'),
        (5, 'Portable Bluetooth Speaker', 'portable-bluetooth-speaker', 'Waterproof portable speaker with deep bass.', 1800, 1499, 1, 1, 'ELEC-003'),
        (6, 'Modern Desk Lamp', 'modern-desk-lamp', 'Adjustable LED desk lamp with touch controls.', 950, NULL, 0, 4, 'HOME-001')
    ");

    // INSERT DUMMY VARIANTS & IMAGES
    $pdo->exec("INSERT INTO `Variant` (`productId`, `size`, `color`, `stock`, `initialStock`, `imageUrl`) VALUES 
        (1, 'Standard', 'Black', 50, 50, 'https://placehold.co/600x600/000000/ffffff?text=Headphone+Black'),
        (1, 'Standard', 'White', 30, 30, 'https://placehold.co/600x600/f8fafc/000000?text=Headphone+White'),
        (2, '44mm', 'Midnight', 100, 100, 'https://placehold.co/600x600/1e293b/ffffff?text=SmartWatch'),
        (3, 'M', 'Navy Blue', 40, 40, 'https://placehold.co/600x600/1e3a8a/ffffff?text=Navy+Shirt'),
        (3, 'L', 'Navy Blue', 25, 25, 'https://placehold.co/600x600/1e3a8a/ffffff?text=Navy+Shirt'),
        (4, 'Standard', 'Red', 15, 15, 'https://placehold.co/600x600/9f1239/ffffff?text=Red+Handbag'),
        (5, 'Standard', 'Blue', 60, 60, 'https://placehold.co/600x600/2563eb/ffffff?text=Speaker'),
        (6, 'Standard', 'White', 45, 45, 'https://placehold.co/600x600/f1f5f9/000000?text=Desk+Lamp')
    ");

    // Set Product Images from their first variant
    $pdo->exec("UPDATE `Product` p SET `imageUrl` = (SELECT `imageUrl` FROM `Variant` v WHERE v.productId = p.id LIMIT 1)");

    // INSERT DUMMY BANNERS
    $pdo->exec("INSERT INTO `Banner` (`title`, `targetUrl`, `imageUrl`, `resourceType`) VALUES 
        ('Mega Summer Sale', 'shop?category=mens-fashion', 'https://placehold.co/1200x400/003e86/ffffff?text=Summer+Mega+Sale', 'campaign'),
        ('Tech Gadgets Offer', 'shop?category=electronics', 'https://placehold.co/1200x400/0f172a/ffffff?text=Tech+Gadgets+Up+To+50%25+Off', 'campaign'),
        ('Welcome Discount', 'shop', 'https://placehold.co/600x600/e11d48/ffffff?text=Welcome+Voucher', 'popup')
    ");

    // Add a Notification
    $pdo->exec("INSERT INTO `Notification` (`title`, `message`, `type`) VALUES ('System Initialized', 'Welcome to your new ecommerce dashboard! Everything is set up and ready to go.', 'info')");

    $messages[] = "✓ Dummy Products, Categories, and Banners injected successfully.";
    $isSuccess = true;
    
    // @unlink(__DIR__ . '/install.php');
    // @unlink(__FILE__); // ফাইলগুলো স্বয়ংক্রিয়ভাবে ডিলিট হওয়া বন্ধ করা হলো

} catch (PDOException $e) {
    $messages[] = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Setup Complete</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen p-6 font-sans">
    <div class="bg-white max-w-2xl w-full rounded-3xl shadow-xl overflow-hidden border border-slate-200">
        <?php if ($isSuccess): ?>
        <div class="bg-emerald-500 p-8 text-center text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiNmZmYiLz48L3N2Zz4=')] opacity-20"></div>
            <svg class="w-20 h-20 mx-auto mb-4 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <h1 class="text-3xl font-black uppercase tracking-widest relative z-10">Setup Successful!</h1>
            <p class="mt-2 font-medium opacity-90 relative z-10">Your store is now populated with dummy data and ready to test.</p>
        </div>
        <div class="p-8">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-8 text-sm text-slate-600 space-y-2">
                <?php foreach($messages as $msg): ?>
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <?php echo htmlspecialchars($msg); ?>
                    </p>
                <?php endforeach; ?>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 mb-8">
                <h3 class="font-bold text-blue-900 mb-2">Admin Login Credentials:</h3>
                <p class="text-blue-800 text-sm"><strong>Email:</strong> admin@store.com</p>
                <p class="text-blue-800 text-sm"><strong>Password:</strong> admin123</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="index.php" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl text-center shadow-lg transition-all text-sm uppercase tracking-wider">
                    Visit Storefront
                </a>
                <a href="admin/login.php" class="flex-1 bg-[#003e86] hover:bg-blue-800 text-white font-bold py-4 rounded-xl text-center shadow-lg transition-all text-sm uppercase tracking-wider">
                    Go to Admin Panel
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-rose-500 p-8 text-center text-white">
            <svg class="w-20 h-20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <h1 class="text-3xl font-black uppercase tracking-widest">Setup Failed</h1>
        </div>
        <div class="p-8">
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-5 text-sm text-rose-600">
                <?php foreach($messages as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>