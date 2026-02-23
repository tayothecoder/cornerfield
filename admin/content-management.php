<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

use App\Config\Database;
use App\Models\SiteSettings;
use App\Utils\SessionManager;

// Start session and check admin authentication
SessionManager::start();

if (!SessionManager::get('admin_logged_in')) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$siteSettings = new SiteSettings($database);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_settings':
                $settings = $_POST['settings'] ?? [];
                $siteSettings->updateMultiple($settings);
                $success_message = 'Settings updated successfully!';
                break;
                
            case 'upload_logo':
                $uploaded = handleLogoUpload();
                if ($uploaded) {
                    $success_message = 'Logo uploaded successfully!';
                } else {
                    $error_message = 'Failed to upload logo.';
                }
                break;
                
            case 'reset_settings':
                // Reset to defaults
                $defaults = getDefaultSettings();
                $siteSettings->updateMultiple($defaults);
                $success_message = 'Settings reset to defaults!';
                break;
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get all settings grouped by category
$settingsByCategory = $siteSettings->getAllByCategory();

function handleLogoUpload() {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $uploadDir = '../assets/uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml'];
    $fileType = $_FILES['logo']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }
    
    $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
        $database = new Database();
        $siteSettings = new SiteSettings($database);
        $siteSettings->set('site_logo', 'assets/uploads/logos/' . $filename);
        return true;
    }
    
    return false;
}

function getDefaultSettings() {
    return [
        'site_name' => ['value' => 'CornerField', 'type' => 'text', 'category' => 'general'],
        'site_tagline' => ['value' => 'Your Gateway to Financial Freedom', 'type' => 'text', 'category' => 'general'],
        'primary_color' => ['value' => '#667eea', 'type' => 'color', 'category' => 'theme'],
        'secondary_color' => ['value' => '#764ba2', 'type' => 'color', 'category' => 'theme'],
        'success_color' => ['value' => '#10b981', 'type' => 'color', 'category' => 'theme'],
        'warning_color' => ['value' => '#f59e0b', 'type' => 'color', 'category' => 'theme'],
        'danger_color' => ['value' => '#ef4444', 'type' => 'color', 'category' => 'theme']
    ];
}

$pageTitle = 'Content Management';
include __DIR__ . '/includes/header.php';
?>

<style>
/* content management styles */
.settings-section { margin-bottom: 1.5rem; }
.section-title { font-size: 0.875rem; font-weight: 600; margin-bottom: 1rem; }
.settings-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
@media (min-width: 768px) { .settings-grid { grid-template-columns: 1fr 1fr; } }
.setting-item { margin-bottom: 0.75rem; }
.setting-label { display: block; font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem; }
.setting-description { font-size: 0.75rem; color: #9ca3af; margin-bottom: 0.5rem; }
.textarea-control { width: 100%; min-height: 100px; padding: 0.625rem 1rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; outline: none; font-size: 0.875rem; background: white; color: #111827; resize: vertical; }
:is(.dark *) .textarea-control { background: #1a1145; border-color: #2d1b6e; color: white; }
.textarea-control:focus { border-color: #1e0e62; }
.upload-area { border: 2px dashed #e5e7eb; border-radius: 0.75rem; padding: 2rem; text-align: center; cursor: pointer; transition: border-color 0.2s; }
.upload-area:hover { border-color: #1e0e62; }
:is(.dark *) .upload-area { border-color: #2d1b6e; }
.current-logo { max-height: 60px; border-radius: 0.5rem; }
.color-input { width: 40px; height: 40px; border: 1px solid #e5e7eb; border-radius: 0.5rem; cursor: pointer; padding: 2px; }
:is(.dark *) .color-input { border-color: #2d1b6e; }
:is(.dark *) .section-title { color: white; }
:is(.dark *) .setting-label { color: #9ca3af; }
:is(.dark *) .setting-description { color: #6b7280; }
</style>

<div class="space-y-6">



<div class="content-management">
    <div class="flex justify-between items-center gap-4 mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Content Management</h1>
        <div class="flex items-center gap-2 flex-shrink-0">
            <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] transition-colors" onclick="resetSettings()">Reset to Defaults</button>
            <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveAllSettings()">Save All Changes</button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-sm"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm p-1 flex flex-wrap gap-1 mb-4">
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl bg-[#1e0e62] text-white" onclick="showTab('general')">General</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('branding')">Branding</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('theme')">Theme</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('company')">Company</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('content')">Content</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('social')">Social Media</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('seo')">SEO</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-2xl text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e]" onclick="showTab('system')">System</button>
    </div>

    <form id="settingsForm" method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <!-- General Settings Tab -->
        <div id="general-tab" class="tab-content active">
            <div class="settings-section">
                <h3 class="section-title">General Settings</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['general'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                <textarea name="settings[<?= $setting['setting_key'] ?>][value]" 
                                          class="form-control textarea-control" 
                                          placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                            <?php else: ?>
                                <input type="text" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                       placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                            <?php endif; ?>
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Branding Tab -->
        <div id="branding-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">Branding & Logo</h3>
                
                <!-- Logo Upload -->
                <div class="mb-4">
                    <label class="setting-label">Site Logo</label>
                    <div class="setting-description">Upload your site logo (PNG, JPG, or SVG)</div>
                    
                    <?php 
                    $currentLogo = $siteSettings->get('site_logo', '');
                    if ($currentLogo): ?>
                        <div class="mb-3">
                            <img src="../<?= htmlspecialchars($currentLogo) ?>" alt="Current Logo" class="current-logo">
                        </div>
                    <?php endif; ?>
                    
                    <div class="upload-area" onclick="document.getElementById('logoUpload').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: #6b7280;"></i>
                        <p>Click to upload or drag and drop</p>
                        <p class="text-gray-400 dark:text-gray-500">PNG, JPG, SVG up to 2MB</p>
                    </div>
                    <input type="file" id="logoUpload" name="logo" accept="image/*" style="display: none;" onchange="uploadLogo()">
                </div>

                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['branding'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <input type="text" 
                                   name="settings[<?= $setting['setting_key'] ?>][value]" 
                                   class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                   value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                   placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Theme Tab -->
        <div id="theme-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">Theme Colors</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['theme'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <div class="flex align-items-center gap-2">
                                <input type="color" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="color-input" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                <input type="text" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                       placeholder="#000000">
                            </div>
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Company Tab -->
        <div id="company-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">Company Information</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['company'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                <textarea name="settings[<?= $setting['setting_key'] ?>][value]" 
                                          class="form-control textarea-control" 
                                          placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                            <?php else: ?>
                                <input type="text" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                       placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                            <?php endif; ?>
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Content Tab -->
        <div id="content-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">Page Content</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['content'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <textarea name="settings[<?= $setting['setting_key'] ?>][value]" 
                                      class="form-control textarea-control" 
                                      placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Social Media Tab -->
        <div id="social-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">Social Media Links</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['social'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <input type="url" 
                                   name="settings[<?= $setting['setting_key'] ?>][value]" 
                                   class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                   value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                   placeholder="https://...">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- SEO Tab -->
        <div id="seo-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">SEO Settings</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['seo'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                <textarea name="settings[<?= $setting['setting_key'] ?>][value]" 
                                          class="form-control textarea-control" 
                                          placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                            <?php else: ?>
                                <input type="text" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                       placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                            <?php endif; ?>
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- System Tab -->
        <div id="system-tab" class="tab-content" style="display:none">
            <div class="settings-section">
                <h3 class="section-title">System Settings</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['system'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                <select name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]">
                                    <option value="0" <?= $setting['setting_value'] === '0' ? 'selected' : '' ?>>Disabled</option>
                                    <option value="1" <?= $setting['setting_value'] === '1' ? 'selected' : '' ?>>Enabled</option>
                                </select>
                            <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                <textarea name="settings[<?= $setting['setting_key'] ?>][value]" 
                                          class="form-control textarea-control" 
                                          placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                            <?php else: ?>
                                <input type="text" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                       placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                            <?php endif; ?>
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                            <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function showTab(tabName) {
    // hide all tab contents
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.style.display = 'none';
    });

    // deactivate all tab buttons
    document.querySelectorAll('.cm-tab').forEach(function(button) {
        button.classList.remove('bg-[#1e0e62]', 'text-white');
        button.classList.add('text-gray-500', 'dark:text-gray-400');
    });

    // show selected tab content
    var targetTab = document.getElementById(tabName + '-tab');
    if (targetTab) targetTab.style.display = 'block';

    // activate clicked button
    if (event && event.target) {
        event.target.classList.add('bg-[#1e0e62]', 'text-white');
        event.target.classList.remove('text-gray-500', 'dark:text-gray-400');
    }
}

function saveAllSettings() {
    document.getElementById('settingsForm').submit();
}

function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="reset_settings">';
        document.body.appendChild(form);
        form.submit();
    }
}

function uploadLogo() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.enctype = 'multipart/form-data';
    form.innerHTML = '<input type="hidden" name="action" value="upload_logo">';
    
    const fileInput = document.getElementById('logoUpload');
    const newFileInput = fileInput.cloneNode(true);
    form.appendChild(newFileInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Sync color inputs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="color"]').forEach(colorInput => {
        const textInput = colorInput.parentElement.querySelector('input[type="text"]');
        
        colorInput.addEventListener('input', function() {
            textInput.value = this.value;
        });
        
        textInput.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                colorInput.value = this.value;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
