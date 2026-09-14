<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Auto-create Banner table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `Banner` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `targetUrl` VARCHAR(255) DEFAULT NULL,
        `imageUrl` VARCHAR(255) NOT NULL,
        `resourceType` VARCHAR(50) DEFAULT 'campaign',
        `active` BOOLEAN DEFAULT TRUE,
        `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Handle POST request for Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    ini_set('memory_limit', '512M'); // Increase memory limit for large images
    ini_set('max_execution_time', '300');

    if (empty($_POST) && empty($_FILES)) {
        echo json_encode(['success' => false, 'error' => 'File size limit exceeded based on your server settings.']);
        exit;
    }

    $id = $_POST['id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $targetUrl = trim($_POST['targetUrl'] ?? '');
    $resourceType = $_POST['resourceType'] ?? 'campaign';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Campaign title is required.']);
        exit;
    }

    $imageUrl = null;
    $oldImageUrl = null;
    
    if (!empty($id)) {
        $stmt = $pdo->prepare("SELECT imageUrl FROM Banner WHERE id = ?");
        $stmt->execute([$id]);
        $oldImageUrl = $stmt->fetchColumn();
    }

    if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir('../uploads/banners')) { mkdir('../uploads/banners', 0777, true); }
        $tmpName = $_FILES['banner_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
        
        $fileName = time() . '_' . uniqid() . '.webp';
        $targetPath = '../uploads/banners/' . $fileName;
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
            $fileName = time() . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($tmpName, '../uploads/banners/' . $fileName)) {
                echo json_encode(['success' => false, 'error' => 'Failed to upload image.']);
                exit;
            }
        }
        
        $imageUrl = '/uploads/banners/' . $fileName;
        if (!empty($oldImageUrl)) {
            $oldPath = __DIR__ . '/..' . $oldImageUrl;
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    if (empty($id) && empty($imageUrl)) {
        echo json_encode(['success' => false, 'error' => 'Banner image is required for a new campaign.']);
        exit;
    }
    
    try {
        if (!empty($id)) {
            if ($imageUrl) {
                $stmt = $pdo->prepare("UPDATE Banner SET title=?, targetUrl=?, imageUrl=?, resourceType=? WHERE id=?");
                $stmt->execute([$title, $targetUrl, $imageUrl, $resourceType, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE Banner SET title=?, targetUrl=?, resourceType=? WHERE id=?");
                $stmt->execute([$title, $targetUrl, $resourceType, $id]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO Banner (title, targetUrl, imageUrl, resourceType) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $targetUrl, $imageUrl, $resourceType]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle GET requests for toggle/delete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $id = $_GET['id'] ?? '';
    if ($id) {
        try {
            if ($_GET['action'] === 'toggle') {
                $pdo->prepare("UPDATE Banner SET active = NOT active WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
            } elseif ($_GET['action'] === 'delete') {
                $stmt = $pdo->prepare("SELECT imageUrl FROM Banner WHERE id = ?");
                $stmt->execute([$id]);
                $img = $stmt->fetchColumn();
                if ($img) {
                    $oldPath = __DIR__ . '/..' . $img;
                    if (file_exists($oldPath) && is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $pdo->prepare("DELETE FROM Banner WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    exit;
}

$stmt = $pdo->query("SELECT * FROM Banner ORDER BY id DESC");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="banners-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="layout-template" class="w-6 h-6 text-blue-600"></i> Campaigns & Banners
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage your promotional assets.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="relative group w-full md:w-64">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                    <input 
                        type="text" 
                        id="banner-search"
                        placeholder="Search..." 
                        class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-blue-500 outline-none w-full transition-all shadow-sm"
                    />
                </div>
                <button 
                    onclick="openBannerModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg transition-all flex items-center gap-2 text-sm"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i> 
                    <span id="btn-text">New Campaign</span>
                </button>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 bg-slate-50/30" id="banner-grid">
            <?php foreach ($banners as $banner): ?>
                <div class="banner-card group bg-white rounded-3xl border border-slate-200 overflow-hidden hover:shadow-2xl hover:shadow-blue-500/10 transition-all duration-300 transform hover:-translate-y-1" data-title="<?php echo strtolower($banner['title']); ?>">
                    <!-- Image Preview -->
                    <div class="aspect-[21/9] relative overflow-hidden bg-slate-100 border-b border-slate-100">
                        <img src="<?php echo $banner['imageUrl'] ? '..' . $banner['imageUrl'] : 'https://placehold.co/800x400'; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-in-out" />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">
                            <div class="flex gap-2 justify-end translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                                <button onclick='editBanner(<?php echo json_encode($banner); ?>)' class="p-2.5 bg-white/20 hover:bg-white text-white hover:text-blue-600 rounded-xl backdrop-blur-md transition-all" title="Edit Campaign">
                                    <i data-lucide="edit-3" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="confirmDelete('<?php echo $banner['id']; ?>')" class="p-2.5 bg-white/20 hover:bg-rose-500 text-white rounded-xl backdrop-blur-md transition-all" title="Delete Campaign">
                                    <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-5">
                        <div class="flex justify-between items-start gap-4 mb-4">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-slate-900 text-lg leading-tight line-clamp-2"><?php echo htmlspecialchars($banner['title']); ?></h3>
                                <span class="inline-block mt-1 px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-600 border border-blue-100">
                                    <?php echo $banner['resourceType'] === 'popup' ? 'Popup Banner' : 'Hero Slider'; ?>
                                </span>
                            </div>
                            <button onclick="toggleStatus('<?php echo $banner['id']; ?>')" class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-xl border <?php echo $banner['active'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100'; ?> transition-colors">
                                <div class="h-2 w-2 rounded-full <?php echo $banner['active'] ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'; ?>"></div>
                                <span class="text-[10px] font-black uppercase tracking-widest"><?php echo $banner['active'] ? 'Active' : 'Inactive'; ?></span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
                                <i data-lucide="link" class="w-4 h-4 text-blue-600"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Target Link</p>
                                <a href="<?php echo $banner['targetUrl'] ?: '#'; ?>" target="_blank" class="text-sm font-semibold text-blue-600 hover:text-blue-800 truncate block transition-colors">
                                    <?php echo $banner['targetUrl'] ?: 'No target link set'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($banners)): ?>
                <div class="col-span-full py-12 text-center">
                    <i data-lucide="image" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
                    <h3 class="text-lg font-bold text-slate-700">No campaigns found</h3>
                    <p class="text-slate-500 text-sm">Create a new banner campaign to display on your storefront.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="banner-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2" id="modal-title">
                <i data-lucide="image-plus" class="w-5 h-5 text-blue-600"></i> 
                <span>New Campaign</span>
            </h2>
            <button onclick="closeModal()" class="p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form id="banner-form" class="p-6 space-y-5" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="banner-id">
            
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Campaign Title <span class="text-rose-500">*</span></label>
                <input required type="text" name="title" id="form-title" placeholder="e.g. Summer Mega Sale 2026" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Banner Type</label>
                <select name="resourceType" id="form-type" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all cursor-pointer">
                    <option value="campaign">Hero Slider (Homepage Top)</option>
                    <option value="popup">Popup Banner (Shows Once)</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Target Link (Optional)</label>
                <input type="url" name="targetUrl" id="form-url" placeholder="https://yourstore.com/category/summer" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
                <p class="text-[10px] text-slate-400 ml-1">Customers will be redirected here when they click the banner.</p>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 flex items-center justify-between">
                    <span>Creative Asset <span class="text-rose-500">*</span></span>
                    <span class="text-[10px] text-blue-600 bg-blue-50 px-2 py-0.5 rounded font-bold">1920 × 600 px (Recommended)</span>
                </label>
                <div class="relative border-2 border-dashed border-blue-200 bg-blue-50/30 hover:bg-blue-50/80 rounded-2xl p-2 flex flex-col items-center justify-center cursor-pointer transition-colors group min-h-[220px] overflow-hidden">
                    <input type="file" name="banner_file" id="banner-file" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="previewBanner(this)">
                    
                    <div id="preview-container" class="hidden absolute inset-0 w-full h-full bg-slate-100">
                        <img id="image-preview" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                            <span class="bg-white/90 text-slate-900 px-4 py-2 rounded-xl font-bold text-sm shadow-lg flex items-center gap-2">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Change Image
                            </span>
                        </div>
                    </div>
                    
                    <div id="upload-placeholder" class="text-center pointer-events-none p-6">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                            <i data-lucide="cloud-upload" class="w-8 h-8 text-blue-500"></i>
                        </div>
                        <span class="block text-sm font-bold text-slate-700">Click or drag banner image to upload</span>
                        <span class="block text-xs text-slate-500 mt-1">Supports JPG, PNG, WEBP</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="flex-1 py-3.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" id="submit-btn" class="flex-[2] py-3.5 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-md hover:bg-blue-700 hover:shadow-lg transition-all flex justify-center items-center gap-2">
                    <i data-lucide="rocket" class="w-4 h-4"></i> <span id="submit-text">Launch Campaign</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search Functionality
document.getElementById('banner-search').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.banner-card').forEach(card => {
        const title = card.getAttribute('data-title');
        card.style.display = title.includes(term) ? 'block' : 'none';
    });
});

function openBannerModal() {
    document.getElementById('banner-form').reset();
    document.getElementById('banner-id').value = '';
    document.getElementById('image-preview').src = '';
    document.getElementById('preview-container').classList.add('hidden');
    document.getElementById('upload-placeholder').classList.remove('hidden');
    document.getElementById('modal-title').innerHTML = '<i data-lucide="image-plus" class="w-5 h-5 text-blue-600"></i> <span>New Campaign</span>';
    document.getElementById('submit-text').innerText = 'Launch Campaign';
    document.getElementById('banner-modal').classList.remove('hidden');
    document.getElementById('banner-modal').classList.add('flex');
    lucide.createIcons();
}

function editBanner(banner) {
    document.getElementById('banner-id').value = banner.id;
    document.getElementById('form-title').value = banner.title;
    document.getElementById('form-url').value = banner.targetUrl || '';
    document.getElementById('form-type').value = 'campaign';
    document.getElementById('form-type').value = banner.resourceType || 'campaign';
    if (banner.imageUrl) {
        document.getElementById('image-preview').src = '..' + banner.imageUrl;
        document.getElementById('preview-container').classList.remove('hidden');
        document.getElementById('upload-placeholder').classList.add('hidden');
    }
    document.getElementById('modal-title').innerHTML = '<i data-lucide="edit" class="w-5 h-5 text-blue-600"></i> <span>Update Campaign</span>';
    document.getElementById('submit-text').innerText = 'Save Changes';
    document.getElementById('banner-modal').classList.remove('hidden');
    document.getElementById('banner-modal').classList.add('flex');
    lucide.createIcons();
}

function closeModal() {
    document.getElementById('banner-modal').classList.add('hidden');
    document.getElementById('banner-modal').classList.remove('flex');
}

function previewBanner(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('image-preview').src = e.target.result;
            document.getElementById('preview-container').classList.remove('hidden');
            document.getElementById('upload-placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Form Submission (AJAX)
document.getElementById('banner-form').onsubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    const btnText = document.getElementById('submit-text');
    
    btn.disabled = true;
    btnText.innerText = 'Processing...';

    const formData = new FormData(this);
    try {
        const response = await fetch('banners.php', {
            method: 'POST',
            body: formData
        });
        const text = await response.text();
        try {
            const result = JSON.parse(text);
            if(result.success) {
                location.reload();
            } else {
                showToast(result.error || 'Failed to save banner.', "error");
            }
        } catch(e) {
            console.error("Server Error Response: ", text);
            showToast("Server returned an error. The file might be too large.", "error");
        }
    } catch (err) {
        console.error("Upload error: ", err);
        showToast("Network error! Your file exceeds server upload limits.", "error");
    } finally {
        btn.disabled = false;
        btnText.innerText = 'Launch Campaign';
    }
};

async function toggleStatus(id) {
    try {
        await fetch(`banners.php?action=toggle&id=${id}`);
        location.reload();
    } catch(e) { console.error(e); }
}

async function confirmDelete(id) {
    customConfirm("Are you sure you want to delete this campaign? This action cannot be undone.", async () => {
        try {
            const res = await fetch(`banners.php?action=delete&id=${id}`);
            const result = await res.json();
            if(result.success) {
                location.reload();
            } else {
                showToast(result.error, "error");
            }
        } catch(e) {
            showToast("Operation failed", "error");
        }
    });
}

lucide.createIcons();
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>