
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once __DIR__ . '/../db.php';

$session_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM `User` WHERE id = :id AND role = 'admin'");
$stmt->execute(['id' => $session_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Security Check: If user not found, clear session and redirect to login
if (!$user) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    header("Location: login");
    exit;
}
ob_start();
?>

<div class="max-w-4xl mx-auto font-sans" id="profile-app">
    <form id="profile-form" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        
        <!-- Header -->
        <div class="px-6 sm:px-8 py-6 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="user" class="w-6 h-6 text-blue-600"></i> Account Settings
                </h2>
                <p class="text-sm text-slate-500 mt-1">Manage your administrative identity and security.</p>
            </div>
            
            <button 
                type="button" 
                onclick="updateProfile()"
                id="update-btn"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition-all flex items-center justify-center gap-2"
            >
                <i data-lucide="save" class="w-5 h-5"></i> 
                <span id="btn-text">Update Profile</span>
            </button>
        </div>

        <div class="p-6 sm:p-8 space-y-10">
            <!-- Section 1: Personal Identity -->
            <div>
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4">Personal Identity</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" />
                    </div>
                    <div class="md:col-span-2 space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" />
                    </div>
                </div>
            </div>

            <!-- Section 2: Security -->
            <div class="pt-6 border-t border-slate-100">
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4">Security & Password</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">New Password</label>
                        <input type="password" name="newPassword" id="newPassword" placeholder="••••••••" class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Confirm New Password</label>
                        <input type="password" id="confirmPassword" placeholder="••••••••" class="w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl font-medium text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 outline-none transition-all" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
async function updateProfile() {
    const form = document.getElementById('profile-form');
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    const btn = document.getElementById('update-btn');
    const btnText = document.getElementById('btn-text');

    // পাসওয়ার্ড ম্যাচিং চেক
    if (newPass && newPass !== confirmPass) {
        showToast("Passwords do not match!", "error");
        return;
    }

    // UI Loading State
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Updating...';
    lucide.createIcons();

    const formData = new FormData(form);

    try {
        const response = await fetch('../api/update_admin_profile.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast("Profile updated successfully!", "success");
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            // পেজ রিফ্রেশ করার জন্য:
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.error || "Failed to update profile.", "error");
        }
    } catch (error) {
        showToast("An error occurred while updating.", "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Update Profile';
        lucide.createIcons();
    }
}

// আইকন ইনিশিয়ালাইজ
lucide.createIcons();
</script>

<?php
$content = ob_get_clean();
include 'admin_layout.php';
?>