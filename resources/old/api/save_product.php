<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Unauthorized');
}

ini_set('memory_limit', '512M'); // Increase memory limit for large images
ini_set('max_execution_time', '300');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $sku = $_POST['sku'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $description = $_POST['description'] ?? '';
    $basePrice = $_POST['basePrice'] ?? 0;
    $flashDealPrice = !empty($_POST['flashDealPrice']) ? $_POST['flashDealPrice'] : null;
    $isFlashDeal = isset($_POST['isFlashDeal']) ? 1 : 0;
    
    $mainCategoryId = !empty($_POST['mainCategoryId']) ? $_POST['mainCategoryId'] : null;
    $subCategoryId = !empty($_POST['subCategoryId']) ? $_POST['subCategoryId'] : null;
    $categoryId = $subCategoryId ?: $mainCategoryId;

    $variants = $_POST['variants'] ?? [];
    $mainStock = $_POST['stock'] ?? 0;

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }

    // If slug is still empty after processing
    if (empty($slug)) {
        $slug = 'p-' . substr(time(), -6);
    }

    // Ensure slug is unique to prevent 1062 Duplicate entry error
    $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE slug = ? AND id != ?");
    $slugCheck->execute([$slug, $id ?: 0]);
    if ($slugCheck->fetchColumn() > 0) {
        $slug .= '-' . substr(uniqid(), -5);
    }

    // Ensure columns exist safely
    try { $pdo->exec("ALTER TABLE `Product` ADD COLUMN `sku` VARCHAR(100) NULL AFTER `slug`"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `Product` ADD COLUMN `serial_number` VARCHAR(255) NULL AFTER `sku`"); } catch (Exception $e) {}
    
    // Safe patch to change imageUrl column to TEXT so multiple image paths won't be truncated (prevents broken images)
    try { $pdo->exec("ALTER TABLE `Product` MODIFY `imageUrl` TEXT NULL"); } catch (Exception $e) {}

    // Fetch Settings for Watermark
    $logoUrl = null;
    $storeName = '';
    $fbPageName = '';
    try {
        $logoStmt = $pdo->query("SELECT logoUrl, storeName, facebookUrl FROM SettingGeneral LIMIT 1");
        $genData = $logoStmt->fetch(PDO::FETCH_ASSOC);
        if ($genData) {
            $logoUrl = $genData['logoUrl'];
            $storeName = $genData['storeName'];
            $fbUrl = $genData['facebookUrl'];
            if (!empty($fbUrl)) {
                $parts = explode('/', rtrim(str_replace(['http://', 'https://', 'www.'], '', $fbUrl), '/'));
                $fbPageName = end($parts);
            }
            if (empty($fbPageName)) $fbPageName = $storeName;
        }
    } catch (Exception $e) {}

    $imageUrls = [];

    if (!empty($id)) {
        $stmt = $pdo->prepare("SELECT imageUrl FROM Product WHERE id = ?");
        $stmt->execute([$id]);
        $oldImageUrl = $stmt->fetchColumn();
        if (!empty($oldImageUrl)) {
            $imageUrls = explode(',', $oldImageUrl);
        }
    }

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $newUploaded = [];
        $fileCount = count($_FILES['images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                
                $fileName = time() . '_' . $i . '_prod_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $slug) . '.webp';
                $targetPath = '../uploads/' . $fileName;
                $converted = false;
                
                if (function_exists('imagewebp') && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $img = null;
                    if ($ext === 'jpg' || $ext === 'jpeg') $img = @imagecreatefromjpeg($tmpName);
                    elseif ($ext === 'png') {
                        $img = @imagecreatefrompng($tmpName);
                    }
                    elseif ($ext === 'webp') $img = @imagecreatefromwebp($tmpName);
                    elseif ($ext === 'gif') $img = @imagecreatefromgif($tmpName);
                    
                    if ($img) {
                        $width = imagesx($img);
                        $height = imagesy($img);
                        
                        // Uniform 1080x1080 HD Square Canvas (Prevents UI cropping and standardizes low-res images)
                        $targetSize = 1080;
                        $newImg = imagecreatetruecolor($targetSize, $targetSize);
                        
                        if (in_array($ext, ['png', 'webp', 'gif'])) {
                            imagealphablending($newImg, false);
                            imagesavealpha($newImg, true);
                            $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
                            imagefilledrectangle($newImg, 0, 0, $targetSize, $targetSize, $transparent);
                            imagealphablending($newImg, true); // re-enable for watermarks
                        } else {
                            $whiteBg = imagecolorallocate($newImg, 255, 255, 255);
                            imagefilledrectangle($newImg, 0, 0, $targetSize, $targetSize, $whiteBg);
                        }
                        
                        $ratio = $width / $height;
                        if ($ratio > 1) {
                            $newWidth = $targetSize;
                            $newHeight = round($targetSize / $ratio);
                        } else {
                            $newHeight = $targetSize;
                            $newWidth = round($targetSize * $ratio);
                        }
                        
                        $dstX = round(($targetSize - $newWidth) / 2);
                        $dstY = round(($targetSize - $newHeight) / 2);
                        
                        imagecopyresampled($newImg, $img, $dstX, $dstY, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagedestroy($img);
                        $img = $newImg;
                        
                        // Apply Watermark
                        if (!empty($logoUrl)) {
                            $logoPath = realpath(__DIR__ . '/..' . $logoUrl);
                            if ($logoPath && file_exists($logoPath)) {
                                $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                                $watermark = null;
                                if ($logoExt === 'png') $watermark = @imagecreatefrompng($logoPath);
                                elseif ($logoExt === 'jpg' || $logoExt === 'jpeg') $watermark = @imagecreatefromjpeg($logoPath);
                                elseif ($logoExt === 'webp') $watermark = @imagecreatefromwebp($logoPath);
                                
                                if ($watermark) {
                                    $imgWidth = imagesx($img);
                                    $imgHeight = imagesy($img);
                                    $wmWidth = imagesx($watermark);
                                    $wmHeight = imagesy($watermark);
                                    
                                    // Scale watermark to 15% of image width (Professional smaller logo)
                                    $newWmWidth = max(50, min($imgWidth * 0.15, $wmWidth));
                                    $newWmHeight = ($newWmWidth / $wmWidth) * $wmHeight;
                                    
                                    $scaledWm = imagecreatetruecolor($newWmWidth, $newWmHeight);
                                    imagealphablending($scaledWm, false); imagesavealpha($scaledWm, true);
                                    $transparent = imagecolorallocatealpha($scaledWm, 255, 255, 255, 127);
                                    imagefilledrectangle($scaledWm, 0, 0, $newWmWidth, $newWmHeight, $transparent);
                                    imagecopyresampled($scaledWm, $watermark, 0, 0, 0, 0, $newWmWidth, $newWmHeight, $wmWidth, $wmHeight);
                                    
                                    // Position: Top-Right
                                    $padding = 20;
                                    $destX = $imgWidth - $newWmWidth - $padding;
                                    $destY = $padding;
                                    
                                    imagealphablending($img, true);
                                    imagecopy($img, $scaledWm, $destX, $destY, 0, 0, $newWmWidth, $newWmHeight);
                                    
                                    imagedestroy($watermark);
                                    imagedestroy($scaledWm);
                                }
                            }
                        }

                        // Apply Text Watermark at Bottom Center (Professional, No Box, Arial / Clean Font)
                        if (!empty($storeName)) {
                            $imgWidth = imagesx($img);
                            $imgHeight = imagesy($img);
                            
                            $line1 = "Web: " . $storeName;
                            $line2 = "FB: " . $fbPageName;
                            
                            $textColor = imagecolorallocate($img, 140, 140, 140); // Professional Dark Gray
                            $shadowColor = imagecolorallocatealpha($img, 255, 255, 255, 40); // White glow for visibility
                            
                            $fontPath = __DIR__ . '/arial.ttf';
                            
                            if (file_exists($fontPath)) {
                                $fontSize = 16;
                                $bbox1 = imagettfbbox($fontSize, 0, $fontPath, $line1);
                                $textWidth1 = $bbox1[2] - $bbox1[0];
                                $bbox2 = imagettfbbox($fontSize, 0, $fontPath, $line2);
                                $textWidth2 = $bbox2[2] - $bbox2[0];
                                
                                $x1 = ($targetSize - $textWidth1) / 2;
                                $x2 = ($targetSize - $textWidth2) / 2;
                                $y1 = $targetSize - 50;
                                $y2 = $y1 + 28;
                                
                                imagettftext($img, $fontSize, 0, $x1+1, $y1+1, $shadowColor, $fontPath, $line1);
                                imagettftext($img, $fontSize, 0, $x1, $y1, $textColor, $fontPath, $line1);
                                imagettftext($img, $fontSize, 0, $x2+1, $y2+1, $shadowColor, $fontPath, $line2);
                                imagettftext($img, $fontSize, 0, $x2, $y2, $textColor, $fontPath, $line2);
                            } else {
                                // Fallback to clean built-in font if arial.ttf is missing
                                $font = 4;
                                $charWidth = imagefontwidth($font);
                                $charHeight = imagefontheight($font);
                                
                                $textWidth1 = strlen($line1) * $charWidth;
                                $textWidth2 = strlen($line2) * $charWidth;
                                
                                $x1 = ($targetSize - $textWidth1) / 2;
                                $x2 = ($targetSize - $textWidth2) / 2;
                                $y1 = $targetSize - ($charHeight * 2) - 40;
                                $y2 = $y1 + $charHeight + 8;
                                
                                imagestring($img, $font, $x1 + 1, $y1 + 1, $line1, $shadowColor);
                                imagestring($img, $font, $x1 - 1, $y1 - 1, $line1, $shadowColor);
                                imagestring($img, $font, $x1, $y1, $line1, $textColor);
                                
                                imagestring($img, $font, $x2 + 1, $y2 + 1, $line2, $shadowColor);
                                imagestring($img, $font, $x2 - 1, $y2 - 1, $line2, $shadowColor);
                                imagestring($img, $font, $x2, $y2, $line2, $textColor);
                            }
                        }

                        if (imagewebp($img, $targetPath, 80)) { $converted = true; }
                        imagedestroy($img);
                    }
                }
                
                if (!$converted) {
                    $fileName = time() . '_' . $i . '_prod_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $slug) . '.' . $ext;
                    move_uploaded_file($tmpName, '../uploads/' . $fileName);
                }
                
                $newUploaded[] = '/uploads/' . $fileName;
            }
        }

        if (!empty($newUploaded)) {
            if (!empty($oldImageUrl)) {
                foreach (explode(',', $oldImageUrl) as $oldPath) {
                    if (file_exists(__DIR__ . '/..' . $oldPath)) {
                        @unlink(__DIR__ . '/..' . $oldPath);
                    }
                }
            }
            $imageUrls = $newUploaded;
        }
    }

    $finalImageUrl = !empty($imageUrls) ? implode(',', $imageUrls) : null;
    $firstImage = !empty($imageUrls) ? $imageUrls[0] : null;

    if (!empty($id)) {
        $stmt = $pdo->prepare("UPDATE Product SET name = ?, slug = ?, sku = ?, serial_number = ?, description = ?, basePrice = ?, flashDealPrice = ?, isFlashDeal = ?, categoryId = ?, imageUrl = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $sku, $serial_number, $description, $basePrice, $flashDealPrice, $isFlashDeal, $categoryId, $finalImageUrl, $id]);
        
        $pdo->prepare("DELETE FROM Variant WHERE productId = ?")->execute([$id]);
        if (empty($variants)) {
            $pdo->prepare("INSERT INTO Variant (productId, size, color, stock, initialStock, imageUrl) VALUES (?, 'Standard', 'Default', ?, ?, ?)")->execute([$id, $mainStock, $mainStock, $firstImage]);
        } else {
            foreach ($variants as $v) {
                $vSize = !empty($v['size']) ? $v['size'] : 'Standard';
                $vColor = !empty($v['color']) ? $v['color'] : 'Default';
                $vStock = !empty($v['stock']) ? $v['stock'] : 0;
                $pdo->prepare("INSERT INTO Variant (productId, size, color, stock, initialStock, imageUrl) VALUES (?, ?, ?, ?, ?, ?)")->execute([$id, $vSize, $vColor, $vStock, $vStock, $firstImage]);
            }
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO Product (name, slug, sku, serial_number, description, basePrice, flashDealPrice, isFlashDeal, categoryId, imageUrl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $sku, $serial_number, $description, $basePrice, $flashDealPrice, $isFlashDeal, $categoryId, $finalImageUrl]);
        $id = $pdo->lastInsertId();
        
        if (empty($variants)) {
            $pdo->prepare("INSERT INTO Variant (productId, size, color, stock, initialStock, imageUrl) VALUES (?, 'Standard', 'Default', ?, ?, ?)")->execute([$id, $mainStock, $mainStock, $firstImage]);
        } else {
            foreach ($variants as $v) {
                $vSize = !empty($v['size']) ? $v['size'] : 'Standard';
                $vColor = !empty($v['color']) ? $v['color'] : 'Default';
                $vStock = !empty($v['stock']) ? $v['stock'] : 0;
                $pdo->prepare("INSERT INTO Variant (productId, size, color, stock, initialStock, imageUrl) VALUES (?, ?, ?, ?, ?, ?)")->execute([$id, $vSize, $vColor, $vStock, $vStock, $firstImage]);
            }
        }
    }
    
    header("Location: ../admin/products");
    exit;
}