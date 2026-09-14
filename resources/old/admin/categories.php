<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Auto-create Category table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `Category` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL UNIQUE,
        `imageUrl` VARCHAR(255) DEFAULT NULL,
        `status` BOOLEAN DEFAULT TRUE,
        `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Add parentId column for Sub-categories if it doesn't exist
    $pdo->exec("ALTER TABLE `Category` ADD COLUMN `parentId` INT NULL DEFAULT NULL AFTER `slug`");
} catch (Exception $e) {}

// Fetch all categories
$stmt = $pdo->query("
    SELECT c.*, p.name as parent_name 
    FROM Category c 
    LEFT JOIN Category p ON c.parentId = p.id 
    ORDER BY c.id DESC
");
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group categories (Main vs Sub)
$mainCategories = [];
$subCategories = [];

foreach ($allCategories as $cat) {
    if (empty($cat['parentId'])) {
        $mainCategories[$cat['id']] = $cat;
        $mainCategories[$cat['id']]['subItems'] = [];
    } else {
        $subCategories[] = $cat;
    }
}

foreach ($subCategories as $sub) {
    if (isset($mainCategories[$sub['parentId']])) {
        $mainCategories[$sub['parentId']]['subItems'][] = $sub;
    }
}
ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="categories-app">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="layers" class="w-6 h-6 text-blue-600"></i> Categories
                </h2>
                <p class="text-sm text-slate-500 mt-1">Organize your products into collections.</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative group w-full md:w-64">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4.5 h-4.5"></i>
                    <input type="text" id="category-search" placeholder="Search categories..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-blue-500 outline-none w-full transition-all shadow-sm" />
                </div>
                <button onclick="openCategoryModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg transition-all flex items-center gap-2 text-sm whitespace-nowrap">
                    <i data-lucide="plus" class="w-4 h-4"></i> 
                    <span>Add Category</span>
                </button>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-500">
                        <th class="p-5 font-bold w-16">SL</th>
                        <th class="p-5 sm:px-8 font-bold">Category</th>
                        <th class="p-5 font-bold">Slug</th>
                        <th class="p-5 font-bold">Status</th>
                        <th class="p-5 sm:px-8 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-700" id="categories-table-body">
                    <?php if (empty($mainCategories)): ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center">
                                <i data-lucide="layers" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                                <p class="text-slate-500 font-medium">No categories found.</p>
                                <button onclick="openCategoryModal()" class="text-blue-600 hover:underline font-bold mt-2 inline-block">Create your first category</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $serial = 1; ?>
                        <?php foreach ($mainCategories as $cat): ?>
                            <!-- Main Category Row -->
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors cat-row" data-search="<?php echo strtolower($cat['name']); ?>">
                                <td class="p-5 font-bold text-slate-500"><?php echo $serial++; ?></td>
                                <td class="p-5 sm:px-8">
                                    <div class="flex items-center gap-4">
                                        <?php if (count($cat['subItems']) > 0): ?>
                                            <button onclick="toggleSubCats(<?php echo $cat['id']; ?>, this)" class="w-6 h-6 flex items-center justify-center bg-slate-100 rounded hover:bg-slate-200 text-slate-500 transition-colors">
                                                <i data-lucide="chevron-right" class="w-4 h-4 transition-transform duration-200"></i>
                                            </button>
                                        <?php else: ?>
                                            <div class="w-6 h-6 shrink-0"></div> <!-- Spacer for alignment -->
                                        <?php endif; ?>
                                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0">
                                            <img src="<?php echo $cat['imageUrl'] ? '..' . $cat['imageUrl'] : 'https://placehold.co/100x100?text=No+Image'; ?>" class="w-full h-full object-cover">
                                        </div>
                                        <p class="font-bold text-slate-800"><?php echo htmlspecialchars($cat['name']); ?></p>
                                    </div>
                                </td>
                                <td class="p-5 font-mono text-xs text-slate-500">
                                    <?php echo htmlspecialchars($cat['slug']); ?>
                                </td>
                                <td class="p-5">
                                    <button onclick="toggleCatStatus(<?php echo $cat['id']; ?>)" class="px-3 py-1 rounded border <?php echo $cat['status'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'; ?> text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 w-fit transition-colors">
                                        <div class="w-1.5 h-1.5 rounded-full <?php echo $cat['status'] ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'; ?>"></div>
                                        <?php echo $cat['status'] ? 'Active' : 'Hidden'; ?>
                                    </button>
                                </td>
                                <td class="p-5 sm:px-8 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="openSubCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>')" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Add Sub Category">
                                            <i data-lucide="folder-plus" class="w-5 h-5"></i>
                                        </button>
                                        <button onclick='editCategory(<?php echo json_encode($cat); ?>, false, "")' class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                            <i data-lucide="edit-3" class="w-5 h-5"></i>
                                        </button>
                                        <button onclick="deleteCategory(<?php echo $cat['id']; ?>)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Sub Categories Rows -->
                            <?php foreach ($cat['subItems'] as $child): ?>
                                <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors cat-row bg-slate-50/30 sub-row-<?php echo $cat['id']; ?> hidden" data-search="<?php echo strtolower($child['name']); ?>">
                                    <td class="p-5 text-center text-slate-300">
                                        <i data-lucide="corner-down-right" class="w-4 h-4 mx-auto"></i>
                                    </td>
                                    <td class="p-5 sm:px-8">
                                        <div class="flex items-center gap-4 pl-8">
                                            <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0 opacity-80">
                                                <img src="<?php echo $child['imageUrl'] ? '..' . $child['imageUrl'] : 'https://placehold.co/100x100?text=No+Image'; ?>" class="w-full h-full object-cover">
                                            </div>
                                            <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($child['name']); ?></p>
                                        </div>
                                    </td>
                                    <td class="p-5 font-mono text-xs text-slate-500">
                                        <?php echo htmlspecialchars($child['slug']); ?>
                                    </td>
                                    <td class="p-5">
                                        <button onclick="toggleCatStatus(<?php echo $child['id']; ?>)" class="px-3 py-1 rounded border <?php echo $child['status'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'; ?> text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 w-fit transition-colors">
                                            <div class="w-1.5 h-1.5 rounded-full <?php echo $child['status'] ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'; ?>"></div>
                                            <?php echo $child['status'] ? 'Active' : 'Hidden'; ?>
                                        </button>
                                    </td>
                                    <td class="p-5 sm:px-8 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='editCategory(<?php echo json_encode($child); ?>, true, "<?php echo addslashes($cat['name']); ?>")' class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                                <i data-lucide="edit-3" class="w-5 h-5"></i>
                                            </button>
                                            <button onclick="deleteCategory(<?php echo $child['id']; ?>)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="category-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="text-lg font-bold text-slate-800" id="modal-title">New Category</h2>
            <button onclick="closeCategoryModal()" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form id="category-form" class="p-6 space-y-5" enctype="multipart/form-data">
            <input type="hidden" name="id" id="cat-id">
            <input type="hidden" name="parentId" id="cat-parent" value="">
            
            <div id="parent-badge" class="hidden mb-4 px-3 py-2 bg-blue-50 border border-blue-100 rounded-xl flex items-center gap-2 text-xs font-bold text-blue-700">
                <i data-lucide="corner-down-right" class="w-4 h-4"></i>
                <span id="parent-name-text"></span>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Category Name</label>
                <input required type="text" name="name" id="cat-name" onkeyup="generateSlug()" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">URL Slug</label>
                <input required type="text" name="slug" id="cat-slug" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-slate-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" placeholder="e.g. mens-fashion">
            </div>

            <div class="space-y-1.5" id="image-upload-section">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Category Image (Optional)</label>
                <div class="relative border-2 border-dashed border-slate-300 rounded-2xl p-4 flex flex-col items-center justify-center bg-slate-50/50 hover:bg-slate-50 cursor-pointer min-h-[140px]">
                    <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-50" onchange="previewCatImage(this)">
                    <div id="cat-preview-container" class="hidden w-full h-full">
                        <img id="cat-image-preview" class="max-h-24 w-full object-contain rounded-lg mx-auto">
                    </div>
                    <div id="cat-upload-placeholder" class="text-center pointer-events-none">
                        <i data-lucide="image-plus" class="w-8 h-8 mx-auto mb-2 text-slate-400"></i>
                        <span class="text-xs font-bold text-slate-600">Browse Image</span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Recommended size: 400x400px (Square JPG/PNG)</p>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeCategoryModal()" class="flex-1 py-3 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" id="cat-submit-btn" class="flex-[2] py-3 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-md hover:bg-blue-700 transition-colors flex justify-center items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> <span id="cat-btn-text">Save Category</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search
document.getElementById('category-search').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.cat-row').forEach(row => {
        const searchData = row.getAttribute('data-search');
        row.style.display = searchData.includes(term) ? '' : 'none';
    });
});

// Sub-Category Toggle Logic
function toggleSubCats(id, btn) {
    const rows = document.querySelectorAll('.sub-row-' + id);
    const icon = btn.querySelector('i');
    let isHidden = false;
    rows.forEach(row => {
        row.classList.toggle('hidden');
        isHidden = row.classList.contains('hidden');
    });
    if (!isHidden) {
        icon.style.transform = 'rotate(90deg)';
    } else {
        icon.style.transform = 'rotate(0deg)';
    }
}

function openCategoryModal() {
    document.getElementById('category-form').reset();
    document.getElementById('cat-id').value = '';
    document.getElementById('cat-image-preview').src = '';
    document.getElementById('cat-preview-container').classList.add('hidden');
    document.getElementById('cat-upload-placeholder').classList.remove('hidden');
    document.getElementById('cat-parent').value = '';
    document.getElementById('parent-badge').classList.add('hidden');
    document.getElementById('image-upload-section').classList.remove('hidden');
    
    document.getElementById('modal-title').innerText = 'New Main Category';
    document.getElementById('category-modal').classList.remove('hidden');
    document.getElementById('category-modal').classList.add('flex');
}

function openSubCategoryModal(parentId, parentName) {
    document.getElementById('category-form').reset();
    document.getElementById('cat-id').value = '';
    document.getElementById('cat-image-preview').src = '';
    document.getElementById('cat-preview-container').classList.add('hidden');
    document.getElementById('cat-upload-placeholder').classList.remove('hidden');
    document.getElementById('cat-parent').value = parentId;
    
    document.getElementById('parent-name-text').innerText = 'Adding under: ' + parentName;
    document.getElementById('parent-badge').classList.remove('hidden');
    document.getElementById('image-upload-section').classList.add('hidden');
    document.getElementById('modal-title').innerText = 'New Sub Category';
    document.getElementById('category-modal').classList.remove('hidden');
    document.getElementById('category-modal').classList.add('flex');
}

function closeCategoryModal() {
    document.getElementById('category-modal').classList.add('hidden');
    document.getElementById('category-modal').classList.remove('flex');
}

function editCategory(cat, isSub = false, parentName = '') {
    document.getElementById('cat-id').value = cat.id;
    document.getElementById('cat-name').value = cat.name;
    document.getElementById('cat-slug').value = cat.slug;
    document.getElementById('cat-parent').value = cat.parentId || '';
    
    if (isSub) {
        document.getElementById('parent-name-text').innerText = 'Sub-category of: ' + parentName;
        document.getElementById('parent-badge').classList.remove('hidden');
        document.getElementById('modal-title').innerText = 'Edit Sub Category';
        document.getElementById('image-upload-section').classList.add('hidden');
    } else {
        document.getElementById('parent-badge').classList.add('hidden');
        document.getElementById('modal-title').innerText = 'Edit Main Category';
        document.getElementById('image-upload-section').classList.remove('hidden');
    }

    if (cat.imageUrl) {
        document.getElementById('cat-image-preview').src = '..' + cat.imageUrl;
        document.getElementById('cat-preview-container').classList.remove('hidden');
        document.getElementById('cat-upload-placeholder').classList.add('hidden');
    } else {
        document.getElementById('cat-preview-container').classList.add('hidden');
        document.getElementById('cat-upload-placeholder').classList.remove('hidden');
    }
    document.getElementById('category-modal').classList.remove('hidden');
    document.getElementById('category-modal').classList.add('flex');
}

function generateSlug() {
    const name = document.getElementById('cat-name').value;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
    document.getElementById('cat-slug').value = slug;
}

function previewCatImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cat-image-preview').src = e.target.result;
            document.getElementById('cat-preview-container').classList.remove('hidden');
            document.getElementById('cat-upload-placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('category-form').onsubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('cat-submit-btn');
    const btnText = document.getElementById('cat-btn-text');
    
    btn.disabled = true;
    btnText.innerText = 'Saving...';
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/save_category.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        try {
            const result = JSON.parse(text);
            if (result.success) {
                location.reload();
            } else {
                showToast(result.error || "Failed to save category.", "error");
            }
        } catch (parseError) {
            console.error("Server returned:", text);
            showToast("Server error! Please check the console for details.", "error");
        }
    } catch (err) {
        console.error(err);
        showToast("Error: " + (err.message || "Network error"), "error");
    } finally {
        btn.disabled = false;
        btnText.innerText = 'Save Category';
    }
};

async function toggleCatStatus(id) {
    try {
        await fetch(`../api/toggle_category.php?id=${id}`);
        location.reload();
    } catch(e) { console.error(e); }
}

async function deleteCategory(id) {
    customConfirm("Are you sure you want to delete this category?", async () => {
        try {
            const res = await fetch(`../api/delete_category.php?id=${id}`, { method: 'DELETE' });
            const result = await res.json();
            if (result.success) {
                location.reload();
            } else {
                showToast(result.error, "error");
            }
        } catch(e) { 
            console.error(e);
            showToast("Error: " + (e.message || "Network error"), "error"); 
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>