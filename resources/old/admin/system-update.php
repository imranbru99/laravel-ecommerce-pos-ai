<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
ob_start();
?>

<div class="max-w-4xl mx-auto pb-24 font-sans">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="refresh-cw" class="w-6 h-6 text-blue-600"></i> System Update
                </h2>
                <p class="text-sm text-slate-500 mt-1">Upload a ZIP file to patch or update your storefront.</p>
            </div>
            <button 
                id="install-btn"
                onclick="installUpdate()"
                disabled
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
            >
                <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                <span id="btn-text">Install Update</span>
            </button>
        </div>

        <div class="p-6 sm:p-8 space-y-8">
            <!-- Upload Box -->
            <label id="drop-area" class="relative flex flex-col items-center justify-center w-full h-64 border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl cursor-pointer transition-all duration-300 hover:bg-slate-100 hover:border-slate-300 overflow-hidden">
                <div class="flex flex-col items-center justify-center pt-5 pb-6" id="upload-content">
                    <div id="icon-container">
                        <i data-lucide="upload-cloud" class="w-12 h-12 mb-4 text-slate-400"></i>
                    </div>
                    <p class="mb-2 text-sm font-bold text-slate-700" id="file-status">
                        Click to upload or drag and drop
                    </p>
                    <p class="text-xs text-slate-500 font-mono" id="file-info">
                        ZIP Archive (MAX. 50MB)
                    </p>
                </div>
                <input type="file" id="update-file" class="hidden" accept=".zip" onchange="handleFileSelect(this)">
            </label>
        </div>
    </div>
</div>

<script>
    let selectedFile = null;

    function handleFileSelect(input) {
        const file = input.files[0];
        const statusText = document.getElementById('file-status');
        const infoText = document.getElementById('file-info');
        const dropArea = document.getElementById('drop-area');
        const installBtn = document.getElementById('install-btn');
        const iconContainer = document.getElementById('icon-container');

        if (file && file.name.endsWith('.zip')) {
            selectedFile = file;
            statusText.innerText = file.name;
            statusText.classList.add('text-blue-600');
            infoText.innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            dropArea.classList.add('border-blue-500', 'bg-blue-50/50');
            installBtn.disabled = false;
            
            // Change Icon to Archive
            iconContainer.innerHTML = '<i data-lucide="archive" class="w-12 h-12 mb-4 text-blue-500"></i>';
            lucide.createIcons();
        } else {
            selectedFile = null;
            installBtn.disabled = true;
            showToast("Please select a valid .zip file", "error");
        }
    }

    async function installUpdate() {
        if (!selectedFile) return;

        const btn = document.getElementById('install-btn');
        const btnText = document.getElementById('btn-text');
        
        // UI Loading State
        btn.disabled = true;
        btnText.innerText = "Extracting...";
        btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-5 h-5"></i> ' + btnText.innerText;
        lucide.createIcons();

        const formData = new FormData();
        formData.append("update_file", selectedFile);

        try {
            const response = await fetch('../api/process_update', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast(result.message || "System updated successfully!", "success");
                resetForm();
            } else {
                showToast(result.error || "Failed to update system.", "error");
            }
        } catch (error) {
            showToast("Something went wrong during the update process.", "error");
        } finally {
            btn.disabled = true;
            btnText.innerText = "Install Update";
            btn.innerHTML = '<i data-lucide="upload-cloud" class="w-5 h-5"></i> ' + btnText.innerText;
            lucide.createIcons();
        }
    }

    function resetForm() {
        selectedFile = null;
        document.getElementById('update-file').value = '';
        document.getElementById('file-status').innerText = "Click to upload or drag and drop";
        document.getElementById('file-status').classList.remove('text-blue-600');
        document.getElementById('file-info').innerText = "ZIP Archive (MAX. 50MB)";
        document.getElementById('drop-area').classList.remove('border-blue-500', 'bg-blue-50/50');
        document.getElementById('icon-container').innerHTML = '<i data-lucide="upload-cloud" class="w-12 h-12 mb-4 text-slate-400"></i>';
        document.getElementById('install-btn').disabled = true;
        lucide.createIcons();
    }
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>