<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

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
.content-management {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.settings-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e5e7eb;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.setting-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.setting-label {
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.setting-description {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.color-input {
    width: 60px;
    height: 40px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.textarea-control {
    min-height: 100px;
    resize: vertical;
}

.upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: border-color 0.2s;
    cursor: pointer;
}

.upload-area:hover {
    border-color: #667eea;
}

.upload-area.dragover {
    border-color: #667eea;
    background-color: #f8fafc;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #1e0e62;
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.current-logo {
    max-width: 200px;
    max-height: 100px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.tab-navigation {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid #e5e7eb;
}

.tab-button {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-button.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<div class="content-management">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">Content Management</h1>
        <div>
            <button type="button" class="btn btn-secondary me-2" onclick="resetSettings()">Reset to Defaults</button>
            <button type="button" class="btn btn-primary" onclick="saveAllSettings()">Save All Changes</button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button active" onclick="showTab('general')">General</button>
        <button class="tab-button" onclick="showTab('branding')">Branding</button>
        <button class="tab-button" onclick="showTab('theme')">Theme</button>
        <button class="tab-button" onclick="showTab('company')">Company</button>
        <button class="tab-button" onclick="showTab('content')">Content</button>
        <button class="tab-button" onclick="showTab('social')">Social Media</button>
        <button class="tab-button" onclick="showTab('seo')">SEO</button>
        <button class="tab-button" onclick="showTab('system')">System</button>
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
                                       class="form-control" 
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
        <div id="branding-tab" class="tab-content">
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
                        <p class="text-muted">PNG, JPG, SVG up to 2MB</p>
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
                                   class="form-control" 
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
        <div id="theme-tab" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">Theme Colors</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['theme'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="color-input" 
                                       value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                <input type="text" 
                                       name="settings[<?= $setting['setting_key'] ?>][value]" 
                                       class="form-control" 
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
        <div id="company-tab" class="tab-content">
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
                                       class="form-control" 
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
        <div id="content-tab" class="tab-content">
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
        <div id="social-tab" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">Social Media Links</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['social'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <input type="url" 
                                   name="settings[<?= $setting['setting_key'] ?>][value]" 
                                   class="form-control" 
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
        <div id="seo-tab" class="tab-content">
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
                                       class="form-control" 
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
        <div id="system-tab" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">System Settings</h3>
                <div class="settings-grid">
                    <?php foreach ($settingsByCategory['system'] ?? [] as $setting): ?>
                        <div class="setting-item">
                            <label class="setting-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                            <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                <select name="settings[<?= $setting['setting_key'] ?>][value]" class="form-control">
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
                                       class="form-control" 
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
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
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
