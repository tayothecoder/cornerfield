<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

use App\Config\Database;
use App\Models\SiteSettings;
use App\Utils\SessionManager;

// start session and check admin authentication
SessionManager::start();

if (!SessionManager::get('admin_logged_in')) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$siteSettings = new SiteSettings($database);

// handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_settings':
                $settings = $_POST['settings'] ?? [];
                $siteSettings->updateMultiple($settings);
                $success_message = 'Settings updated successfully.';
                break;
                
            case 'upload_logo':
                $uploaded = handleLogoUpload();
                if ($uploaded) {
                    $success_message = 'Logo uploaded successfully.';
                } else {
                    $error_message = 'Failed to upload logo.';
                }
                break;
                
            case 'reset_settings':
                $defaults = getDefaultSettings();
                $siteSettings->updateMultiple($defaults);
                $success_message = 'Settings reset to defaults.';
                break;
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// get all settings grouped by category
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
$currentPage = 'content-management';
include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- page header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Content Management</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage site content, branding, and configuration</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] transition-colors" onclick="resetSettings()">Reset to Defaults</button>
            <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveAllSettings()">Save All Changes</button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-sm"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- tab navigation -->
    <div class="flex flex-wrap gap-2" id="cmTabs">
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full bg-[#1e0e62] text-white cursor-pointer" data-target="general" type="button">General</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="branding" type="button">Branding</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="theme" type="button">Theme</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="company" type="button">Company</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="content" type="button">Content</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="social" type="button">Social Media</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="seo" type="button">SEO</button>
        <button class="cm-tab px-4 py-2 text-sm font-medium rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e] cursor-pointer" data-target="system" type="button">System</button>
    </div>

    <form id="settingsForm" method="POST">
        <input type="hidden" name="action" value="update_settings">

        <!-- general tab -->
        <div class="cm-pane" id="general">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">General Settings</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Basic site information and configuration</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['general'] ?? [] as $setting): ?>
                            <div class="<?= $setting['setting_type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <?php if ($setting['setting_type'] === 'textarea'): ?>
                                    <textarea name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] min-h-[100px] resize-vertical text-sm" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                                <?php endif; ?>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save General Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- branding tab -->
        <div class="cm-pane hidden" id="branding">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Branding</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Logo and brand identity settings</p>
                </div>
                <div class="p-6">
                    <!-- logo upload -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Site Logo</label>
                        <?php $currentLogo = $siteSettings->get('site_logo', ''); if ($currentLogo): ?>
                            <div class="mb-3">
                                <img src="../<?= htmlspecialchars($currentLogo) ?>" alt="Current Logo" class="max-h-[60px] rounded-lg">
                            </div>
                        <?php endif; ?>
                        <div class="border-2 border-dashed border-gray-200 dark:border-[#2d1b6e] rounded-xl p-8 text-center cursor-pointer hover:border-[#1e0e62] transition-colors" onclick="document.getElementById('logoUpload').click()">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Click to upload or drag and drop</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">PNG, JPG, SVG up to 2MB</p>
                        </div>
                        <input type="file" id="logoUpload" name="logo" accept="image/*" class="hidden" onchange="uploadLogo()">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['branding'] ?? [] as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <input type="text" name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Branding Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- theme tab -->
        <div class="cm-pane hidden" id="theme">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Theme Colors</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Customize the color scheme of your platform</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['theme'] ?? [] as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <div class="flex items-center gap-3">
                                    <input type="color" class="w-10 h-10 border border-gray-200 dark:border-[#2d1b6e] rounded-lg cursor-pointer p-0.5" value="<?= htmlspecialchars($setting['setting_value']) ?>" data-sync="<?= $setting['setting_key'] ?>">
                                    <input type="text" name="settings[<?= $setting['setting_key'] ?>][value]" class="flex-1 px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="#000000" data-sync-text="<?= $setting['setting_key'] ?>">
                                </div>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Theme Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- company tab -->
        <div class="cm-pane hidden" id="company">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Company Information</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Legal and business details</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['company'] ?? [] as $setting): ?>
                            <div class="<?= $setting['setting_type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <?php if ($setting['setting_type'] === 'textarea'): ?>
                                    <textarea name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] min-h-[100px] resize-vertical text-sm" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                                <?php endif; ?>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Company Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- content tab -->
        <div class="cm-pane hidden" id="content">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Page Content</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Manage text content displayed on your site</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($settingsByCategory['content'] ?? [] as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <textarea name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] min-h-[100px] resize-vertical text-sm" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Content Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- social media tab -->
        <div class="cm-pane hidden" id="social">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Social Media Links</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Connect your social media profiles</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['social'] ?? [] as $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <input type="url" name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="https://...">
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Social Media Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- seo tab -->
        <div class="cm-pane hidden" id="seo">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">SEO Settings</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Search engine optimization configuration</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['seo'] ?? [] as $setting): ?>
                            <div class="<?= $setting['setting_type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <?php if ($setting['setting_type'] === 'textarea'): ?>
                                    <textarea name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] min-h-[100px] resize-vertical text-sm" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                                <?php endif; ?>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save SEO Settings</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- system tab -->
        <div class="cm-pane hidden" id="system">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">System Settings</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Advanced system configuration</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($settingsByCategory['system'] ?? [] as $setting): ?>
                            <div class="<?= $setting['setting_type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))) ?></label>
                                <?php if ($setting['setting_type'] === 'boolean'): ?>
                                    <select name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm">
                                        <option value="0" <?= $setting['setting_value'] === '0' ? 'selected' : '' ?>>Disabled</option>
                                        <option value="1" <?= $setting['setting_value'] === '1' ? 'selected' : '' ?>>Enabled</option>
                                    </select>
                                <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                    <textarea name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] min-h-[100px] resize-vertical text-sm" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="settings[<?= $setting['setting_key'] ?>][value]" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" value="<?= htmlspecialchars($setting['setting_value']) ?>" placeholder="Enter <?= str_replace('_', ' ', $setting['setting_key']) ?>">
                                <?php endif; ?>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($setting['description']) ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][type]" value="<?= $setting['setting_type'] ?>">
                                <input type="hidden" name="settings[<?= $setting['setting_key'] ?>][category]" value="<?= $setting['category'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                        <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save System Settings</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// tab switching
document.querySelectorAll('.cm-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        var target = this.getAttribute('data-target');

        // hide all panes
        document.querySelectorAll('.cm-pane').forEach(function(pane) {
            pane.classList.add('hidden');
        });

        // deactivate all tabs
        document.querySelectorAll('.cm-tab').forEach(function(t) {
            t.classList.remove('bg-[#1e0e62]', 'text-white');
            t.classList.add('text-gray-600', 'dark:text-gray-400');
        });

        // show target pane
        var pane = document.getElementById(target);
        if (pane) pane.classList.remove('hidden');

        // activate clicked tab
        this.classList.remove('text-gray-600', 'dark:text-gray-400');
        this.classList.add('bg-[#1e0e62]', 'text-white');
    });
});

// color input sync
document.querySelectorAll('input[type="color"][data-sync]').forEach(function(colorInput) {
    var key = colorInput.getAttribute('data-sync');
    var textInput = document.querySelector('input[data-sync-text="' + key + '"]');
    if (textInput) {
        colorInput.addEventListener('input', function() {
            textInput.value = this.value;
        });
        textInput.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                colorInput.value = this.value;
            }
        });
    }
});

function saveAllSettings() {
    document.getElementById('settingsForm').submit();
}

function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="reset_settings">';
        document.body.appendChild(form);
        form.submit();
    }
}

function uploadLogo() {
    var form = document.createElement('form');
    form.method = 'POST';
    form.enctype = 'multipart/form-data';
    form.innerHTML = '<input type="hidden" name="action" value="upload_logo">';
    var fileInput = document.getElementById('logoUpload');
    var clone = fileInput.cloneNode(true);
    form.appendChild(clone);
    document.body.appendChild(form);
    form.submit();
}
</script>
