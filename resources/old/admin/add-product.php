<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

// Fetch all categories
$catStmt = $pdo->query("SELECT * FROM Category ORDER BY name ASC");
$allCats = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$mainCats = [];
$subCats = [];
foreach ($allCats as $cat) {
    if (empty($cat['parentId'])) {
        $mainCats[] = $cat;
    } else {
        $subCats[] = $cat;
    }
}

ob_start();
?>

<div class="max-w-7xl mx-auto font-sans pb-12" id="add-product-app">
    <form action="../api/save_product.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="package-plus" class="w-6 h-6 text-blue-600"></i> New Product
                </h2>
                <p class="text-sm text-slate-500 mt-1">Add a new product to your catalog.</p>
            </div>
            <div class="flex gap-3">
                <a href="products" class="px-6 py-3 font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all shadow-sm flex items-center justify-center">Discard</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save Product</span>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 sm:p-8 grid grid-cols-1 lg:grid-cols-12 gap-8 bg-slate-50/30">
            <!-- Left Column -->
            <div class="lg:col-span-8 space-y-8">
                <!-- General -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-5 pb-3 border-b border-slate-100">General Information</h3>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Product Title</label>
                            <input type="text" name="name" id="prod-name" onkeyup="generateSlug()" placeholder="e.g. Premium Quality T-Shirt" class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" required>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between ml-1">
                                <label class="text-xs font-bold text-slate-600 uppercase tracking-widest">Product Description</label>
                                <button type="button" onclick="generateAiDescription()" class="text-[10px] font-bold bg-indigo-50 text-indigo-600 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition-colors flex items-center gap-1 shadow-sm">
                                    <i data-lucide="sparkles" class="w-3 h-3"></i> AI Write
                                </button>
                            </div>
                            <textarea name="description" rows="5" placeholder="Describe your product in detail..." class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Media -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-5 pb-3 border-b border-slate-100">Product Media</h3>
                    <div class="border-2 border-dashed border-blue-200 bg-blue-50/30 rounded-2xl p-10 flex flex-col items-center justify-center cursor-pointer hover:bg-blue-50 transition-all group relative overflow-hidden">
                        <div class="w-14 h-14 rounded-full bg-white flex items-center justify-center shadow-sm mb-3 group-hover:scale-110 transition-transform">
                            <i data-lucide="cloud-upload" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <p class="font-bold text-slate-700">Click to add images (One by one or multiple)</p>
                        <p class="text-xs text-slate-500 mt-1 bg-slate-100 px-3 py-1 rounded-full mt-2 font-semibold text-center">Images will stack as you select them</p>
                        <input type="file" name="images[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*" id="fileInput">
                    </div>
                    <div class="grid grid-cols-4 md:grid-cols-5 gap-4 mt-5" id="image-preview-container">
                        <div class="aspect-square bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center relative overflow-hidden">
                            <i data-lucide="image" class="text-slate-300"></i>
                        </div>
                    </div>
                </div>

                <!-- Variations -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">Variations (Size & Color)</h3>
                        <button type="button" onclick="addVariantRow()" class="text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                            <i data-lucide="plus" class="w-3 h-3"></i> Add Variant
                        </button>
                    </div>
                    <div id="variants-container" class="space-y-3">
                        <!-- Variant rows will go here -->
                    </div>
                    <p id="no-variants-msg" class="text-xs text-slate-500 italic mt-3">No variations added. The main stock quantity will be used.</p>
                </div>
            </div>

            <!-- Right Column -->
            <div class="lg:col-span-4 space-y-8">
                <!-- Pricing -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-5 pb-3 border-b border-slate-100"><i data-lucide="tag" class="w-4 h-4 text-blue-600"></i> Pricing (BDT)</h3>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Base Price</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-blue-600">৳</span>
                                <input type="number" name="basePrice" placeholder="0.00" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-bold outline-none transition-all">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Flash Deal Price</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-rose-500">৳</span>
                                <input type="number" name="flashDealPrice" placeholder="0.00" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-bold text-rose-600 outline-none transition-all">
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1 italic pl-1">*Leave empty if no discount applies.</p>
                        </div>
                    </div>
                </div>

                <!-- Inventory & Org -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-5 pb-3 border-b border-slate-100"><i data-lucide="package" class="w-4 h-4 text-blue-600"></i> Inventory & Details</h3>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">SKU</label>
                            <div class="relative">
                                <input type="text" name="sku" id="sku-input" placeholder="PROD-001" class="w-full px-4 py-3 pr-24 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-bold outline-none transition-all uppercase">
                                <button type="button" onclick="generateSKU()" class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-1.5 rounded-lg hover:bg-blue-200 transition-colors uppercase tracking-widest">Generate</button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Product Slug</label>
                            <input type="text" name="slug" id="slug-input" placeholder="product-slug" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-mono text-sm text-slate-500 outline-none transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Serial / Barcode (Optional)</label>
                            <input type="text" name="serial_number" placeholder="Enter Serial No." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-mono text-sm text-slate-500 outline-none transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Stock Quantity</label>
                            <input type="number" name="stock" placeholder="0" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-bold outline-none transition-all">
                        </div>
                        <div class="space-y-2 pt-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Main Category</label>
                            <select name="mainCategoryId" id="mainCategory" onchange="updateSubCategories()" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-bold outline-none transition-all cursor-pointer">
                                <option value="">Select Main Category</option>
                                <?php foreach($mainCats as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2 pt-2" id="subCategoryContainer" style="display: none;">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-widest ml-1">Sub Category</label>
                            <select name="subCategoryId" id="subCategory" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 font-bold outline-none transition-all cursor-pointer">
                                <option value="">Select Sub Category</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Publish Status -->
                <div class="bg-blue-50/50 rounded-2xl border border-blue-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-blue-900">Live Status</h3>
                            <p class="text-[11px] text-blue-600/70 font-medium">Make product public now</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Image Preview & File Stacking Logic
    let selectedFiles = [];

    document.getElementById('fileInput').addEventListener('change', function(e) {
        if(this.files && this.files.length > 0) {
            Array.from(this.files).forEach(file => {
                selectedFiles.push(file);
            });
            updateFileInputAndPreview();
        }
    });

    function removeImage(index) {
        selectedFiles.splice(index, 1);
        updateFileInputAndPreview();
    }

    function updateFileInputAndPreview() {
        const container = document.getElementById('image-preview-container');
        container.innerHTML = '';
        
        const dt = new DataTransfer();
        
        if (selectedFiles.length > 0) {
            selectedFiles.forEach((file, index) => {
                dt.items.add(file);
                const objectUrl = URL.createObjectURL(file);
                container.innerHTML += `
                    <div class="aspect-square bg-slate-100 rounded-xl border ${index === 0 ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200'} flex items-center justify-center relative group overflow-hidden shadow-sm">
                        ${index === 0 ? '<span class="absolute top-2 left-2 bg-blue-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-lg z-10 shadow-sm">Main</span>' : ''}
                        <img src="${objectUrl}" class="w-full h-full object-cover">
                        <button type="button" onclick="removeImage(${index})" class="absolute top-2 right-2 bg-rose-500 text-white p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-rose-600 shadow-md">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
                    </div>
                `;
            });
            lucide.createIcons();
        } else {
            container.innerHTML = `
                <div class="aspect-square bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center relative overflow-hidden">
                    <i data-lucide="image" class="text-slate-300"></i>
                </div>
            `;
            lucide.createIcons();
        }
        
        document.getElementById('fileInput').files = dt.files;
    }

    function generateSKU() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let sku = 'PROD-';
        for(let i=0; i<6; i++) {
            sku += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('sku-input').value = sku;
    }
    
    function generateSlug() {
        const name = document.getElementById('prod-name').value;
        const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
        document.getElementById('slug-input').value = slug;
    }

    // Variants logic
    let variantIndex = 0;
    function addVariantRow() {
        document.getElementById('no-variants-msg').style.display = 'none';
        const container = document.getElementById('variants-container');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200';
        row.innerHTML = `
            <div class="flex-1">
                <input type="text" name="variants[${variantIndex}][size]" placeholder="Size (e.g. XL)" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-500">
            </div>
            <div class="flex-1">
                <input type="text" name="variants[${variantIndex}][color]" placeholder="Color (e.g. Red)" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-500">
            </div>
            <div class="w-24">
                <input type="number" name="variants[${variantIndex}][stock]" placeholder="Stock" value="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-blue-500" required>
            </div>
            <button type="button" onclick="this.parentElement.remove(); checkVariants();" class="text-rose-500 hover:text-rose-700 p-2"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        `;
        container.appendChild(row);
        variantIndex++;
        lucide.createIcons();
    }
    function checkVariants() {
        if (document.getElementById('variants-container').children.length === 0) {
            document.getElementById('no-variants-msg').style.display = 'block';
        }
    }

    // Categories Logic
    const subCategories = <?php echo json_encode($subCats); ?>;
    function updateSubCategories() {
        const mainId = document.getElementById('mainCategory').value;
        const subSelect = document.getElementById('subCategory');
        const container = document.getElementById('subCategoryContainer');
        
        subSelect.innerHTML = '<option value="">Select Sub Category</option>';
        const filteredSubs = subCategories.filter(sub => sub.parentId == mainId);
        if (filteredSubs.length > 0) {
            filteredSubs.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = sub.name;
                subSelect.appendChild(opt);
            });
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
    
    async function generateAiDescription() {
        const nameInput = document.getElementById('prod-name');
        const descInput = document.querySelector('textarea[name="description"]');
        const name = nameInput.value.trim();
        
        if (!name) {
            showToast("Please enter a product title first.", "error");
            return;
        }
        
        const originalText = descInput.value;
        descInput.value = "🤖 AI is writing description... Please wait.";
        descInput.disabled = true;

        try {
            const formData = new FormData();
            formData.append('name', name);
            
            const response = await fetch('../api/generate_description.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                descInput.value = result.description;
                showToast("Description generated successfully!", "success");
            } else { descInput.value = originalText; showToast(result.error || "Failed to generate.", "error"); }
        } catch (e) {
            descInput.value = originalText; showToast("Network error.", "error");
        } finally { descInput.disabled = false; }
    }
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>