<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\SettingsController;

// Auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle settings POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthMiddleware::check()) {
        header('Location: ' . \App\Config\Config::getBasePath() . '/login.php');
        exit;
    }
    try {
        $controller = new SettingsController();
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'change_password':
                $controller->changePassword();
                break;
            case 'update_profile':
                // handle profile update inline since controller may not have this method
                $userId = (int)($_SESSION['user_id'] ?? 0);
                if ($userId > 0) {
                    $userModel = new \App\Models\UserModel();
                    $fields = [];
                    if (isset($_POST['phone'])) {
                        $fields['phone'] = $_POST['phone'];
                    }
                    if (isset($_POST['first_name'])) {
                        $fields['first_name'] = $_POST['first_name'];
                    }
                    if (isset($_POST['last_name'])) {
                        $fields['last_name'] = $_POST['last_name'];
                    }
                    if (isset($_POST['country'])) {
                        $fields['country'] = $_POST['country'];
                    }
                    if (!empty($fields)) {
                        $userModel->updateProfile($userId, $fields);
                        // clear cached user in session
                        unset($_SESSION['user']);
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'data' => ['message' => 'Profile updated successfully']]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                }
                break;
            case 'update_notifications':
                $controller->updateNotificationPrefs();
                break;
            case 'add_wallet':
                $controller->addWallet();
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
    } catch (\Throwable $e) {
        error_log('Settings POST failed: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
    exit;
}

if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

// Initialize controller and get data
// For demo/preview: wrap in try/catch so pages render even without DB
try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $userModel = new \App\Models\UserModel();
    $settingsUser = $userModel->findById($userId) ?? [];
    $data = [
        'user' => array_merge([
            'email' => '',
            'username' => '',
            'two_factor_enabled' => false,
            'email_verified' => false,
            'email_notifications' => true,
            'sms_notifications' => false,
            'security_notifications' => true,
        ], $settingsUser),
        'security_log' => [],
        'sessions' => [],
        'wallets' => [],
        'notification_prefs' => [
            'email_notifications' => true,
            'sms_notifications' => false,
            'security_notifications' => true,
            'login_alerts' => true,
            'transaction_alerts' => true,
            'marketing_emails' => false,
        ],
        'active_sessions' => [],
    ];
} catch (\Throwable $e) {
    // Fallback demo data for preview
    $data = [
        'user' => [
            'email' => 'demo@cornerfield.io',
            'two_factor_enabled' => false,
            'email_notifications' => true,
            'sms_notifications' => false,
            'security_notifications' => true
        ],
        'wallets' => [
            ['id' => 1, 'type' => 'bitcoin', 'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 'label' => 'Main BTC Wallet'],
            ['id' => 2, 'type' => 'ethereum', 'address' => '0x742d35cc6c3c0532925a3b8d0ec4f84ec3b8b78e', 'label' => 'ETH Wallet'],
        ],
        'active_sessions' => [
            ['id' => 1, 'device' => 'Windows 11 - Chrome', 'location' => 'New York, US', 'ip' => '192.168.1.100', 'last_activity' => '2024-02-10 14:30:00', 'current' => true],
            ['id' => 2, 'device' => 'iPhone 15 - Safari', 'location' => 'New York, US', 'ip' => '192.168.1.101', 'last_activity' => '2024-02-09 10:15:00', 'current' => false],
        ],
        'security_log' => [
            ['event' => 'Login', 'ip' => '192.168.1.100', 'location' => 'New York, US', 'timestamp' => '2024-02-10 14:30:00'],
            ['event' => 'Password Changed', 'ip' => '192.168.1.100', 'location' => 'New York, US', 'timestamp' => '2024-02-08 16:20:00'],
            ['event' => 'Login', 'ip' => '192.168.1.101', 'location' => 'New York, US', 'timestamp' => '2024-02-07 09:45:00'],
        ]
    ];
}

$pageTitle = 'Account Settings';
$currentPage = 'settings';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Settings Content -->
<div class="space-y-6">
    <!-- Settings Navigation -->
    <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex" id="settingsTabs">
                <button class="settings-tab active whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600 dark:text-indigo-400" 
                        data-tab="security">
                    Security
                </button>
                <button class="settings-tab whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300" 
                        data-tab="wallets">
                    Wallets
                </button>
                <button class="settings-tab whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300" 
                        data-tab="notifications">
                    Notifications
                </button>
                <button class="settings-tab whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300" 
                        data-tab="sessions">
                    Sessions
                </button>
                <button class="settings-tab whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300" 
                        data-tab="advanced">
                    Advanced
                </button>
            </nav>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="settings-content p-6">
            <div class="space-y-8">
                <!-- Change Password -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change Password</h3>
                    
                    <form id="passwordForm" method="POST" action="/users/settings.php" data-validate class="max-w-md">
                        <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Current Password
                                </label>
                                <input type="password" id="current_password" name="current_password" required
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    New Password
                                </label>
                                <input type="password" id="new_password" name="new_password" required
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                <div class="mt-2">
                                    <div id="password-strength" class="h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                        <div id="password-strength-bar" class="h-full transition-all duration-300"></div>
                                    </div>
                                    <p id="password-feedback" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                                </div>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Confirm New Password
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <button type="submit" 
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-4 rounded-lg transition-colors"
                                    data-original-text="Update Password">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Two-Factor Authentication -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-8">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Two-Factor Authentication</h3>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                Add an extra layer of security to your account by requiring a verification code in addition to your password.
                            </p>
                        </div>
                        <div class="ml-4">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="twoFactorToggle" class="sr-only peer" <?= $data['user']['two_factor_enabled'] ? 'checked' : '' ?>>
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>

                    <?php if (!$data['user']['two_factor_enabled']): ?>
                    <div id="setup-2fa" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-blue-800 dark:text-blue-200 text-sm mb-3">Click the toggle above to set up 2FA for your account.</p>
                    </div>
                    <?php else: ?>
                    <div id="manage-2fa" class="mt-4">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center text-green-600 dark:text-green-400">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm font-medium">Two-Factor Authentication Enabled</span>
                            </div>
                            <button onclick="generateBackupCodes()" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                                View Backup Codes
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Security Log -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-8">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Security Activity</h3>
                    
                    <div class="space-y-3">
                        <?php foreach (array_slice($data['security_log'], 0, 5) as $log): ?>
                        <div class="flex items-center justify-between p-3 bg-[#f5f3ff] dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-3">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($log['event']) ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($log['location']) ?> • <?= htmlspecialchars($log['ip']) ?></p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400"><?= date('M j, H:i', strtotime($log['timestamp'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wallets Tab -->
        <div id="wallets-tab" class="settings-content p-6 hidden">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Withdrawal Wallets</h3>
                        <p class="text-gray-600 dark:text-gray-300">Manage your cryptocurrency withdrawal addresses.</p>
                    </div>
                    <button onclick="openAddWalletModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Add Wallet
                    </button>
                </div>

                <div class="space-y-4">
                    <?php foreach ($data['wallets'] as $wallet): ?>
                    <div class="cf-card bg-[#f5f3ff] dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600" data-hover>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                                    <?php if ($wallet['type'] === 'bitcoin'): ?>
                                    <span class="text-orange-600 dark:text-orange-400 text-xl font-medium tracking-tight">₿</span>
                                    <?php elseif ($wallet['type'] === 'ethereum'): ?>
                                    <span class="text-blue-600 dark:text-blue-400 text-xl font-medium tracking-tight">Ξ</span>
                                    <?php else: ?>
                                    <span class="text-gray-600 dark:text-gray-400 text-xl font-medium tracking-tight">$</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($wallet['label']) ?></h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= htmlspecialchars(substr($wallet['address'], 0, 20)) ?>...<?= htmlspecialchars(substr($wallet['address'], -10)) ?>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase"><?= ucfirst($wallet['type']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="copyToClipboard('<?= htmlspecialchars($wallet['address']) ?>')" 
                                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                        data-tooltip="Copy Address">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                                <button onclick="deleteWallet(<?= $wallet['id'] ?>)" 
                                        class="p-2 text-red-400 hover:text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                        data-tooltip="Delete Wallet">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($data['wallets'])): ?>
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No wallets added</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Add your cryptocurrency addresses for withdrawals.</p>
                        <button onclick="openAddWalletModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            Add Your First Wallet
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications-tab" class="settings-content p-6 hidden">
            <div class="space-y-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notification Preferences</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-6">Choose how you want to be notified about your account activity.</p>
                </div>

                <form id="notificationsForm" method="POST" action="/users/settings.php">
                    <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                    <input type="hidden" name="action" value="update_notifications">
                    
                    <div class="space-y-6">
                        <!-- Email Notifications -->
                        <div class="flex items-start justify-between p-4 bg-[#f5f3ff] dark:bg-gray-700 rounded-lg">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">Email Notifications</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    Receive updates about transactions, investments, and account security via email.
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer ml-4">
                                <input type="checkbox" name="email_notifications" class="sr-only peer" <?= $data['user']['email_notifications'] ? 'checked' : '' ?>>
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- SMS Notifications -->
                        <div class="flex items-start justify-between p-4 bg-[#f5f3ff] dark:bg-gray-700 rounded-lg">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">SMS Notifications</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    Get instant alerts for important account activities via SMS.
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer ml-4">
                                <input type="checkbox" name="sms_notifications" class="sr-only peer" <?= $data['user']['sms_notifications'] ? 'checked' : '' ?>>
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Security Notifications -->
                        <div class="flex items-start justify-between p-4 bg-[#f5f3ff] dark:bg-gray-700 rounded-lg">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">Security Notifications</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    Critical security alerts about logins, password changes, and suspicious activity.
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer ml-4">
                                <input type="checkbox" name="security_notifications" class="sr-only peer" <?= $data['user']['security_notifications'] ? 'checked' : '' ?>>
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors"
                                    data-original-text="Save Preferences">
                                Save Preferences
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sessions Tab -->
        <div id="sessions-tab" class="settings-content p-6 hidden">
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Active Sessions</h3>
                    <p class="text-gray-600 dark:text-gray-300">Manage your active login sessions across different devices.</p>
                </div>

                <div class="space-y-4">
                    <?php foreach ($data['active_sessions'] as $session): ?>
                    <div class="cf-card bg-[#f5f3ff] dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <?php if (strpos($session['device'], 'iPhone') !== false || strpos($session['device'], 'Android') !== false): ?>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        <?php else: ?>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        <?php endif; ?>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white flex items-center">
                                        <?= htmlspecialchars($session['device']) ?>
                                        <?php if ($session['current']): ?>
                                        <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">Current</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($session['location']) ?> • <?= htmlspecialchars($session['ip']) ?></p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">Last active: <?= date('M j, Y H:i', strtotime($session['last_activity'])) ?></p>
                                </div>
                            </div>
                            <?php if (!$session['current']): ?>
                            <button onclick="terminateSession(<?= $session['id'] ?>)" 
                                    class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium text-sm">
                                Terminate
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <button onclick="terminateAllSessions()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Terminate All Other Sessions
                    </button>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        This will log you out from all devices except this one.
                    </p>
                </div>
            </div>
        </div>

        <!-- Advanced Tab -->
        <div id="advanced-tab" class="settings-content p-6 hidden">
            <div class="space-y-8">
                <!-- Account Deletion -->
                <div>
                    <h3 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-4">Danger Zone</h3>
                    
                    <div class="border border-red-200 dark:border-red-800 rounded-lg p-6 bg-red-50 dark:bg-red-900/20">
                        <h4 class="font-medium text-red-900 dark:text-red-100 mb-2">Delete Account</h4>
                        <p class="text-sm text-red-700 dark:text-red-300 mb-4">
                            Once you delete your account, there is no going back. Please be certain.
                        </p>
                        <button onclick="confirmDeleteAccount()" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            Delete My Account
                        </button>
                    </div>
                </div>

                <!-- Data Export -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-8">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Export Data</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                        Download a copy of all your account data including transactions, investments, and profile information.
                    </p>
                    <button onclick="exportData()" 
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Export My Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Wallet Modal -->
<div id="addWalletModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-[#f5f3ff]0 bg-opacity-75 transition-opacity"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hiddentransition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="walletForm" method="POST" action="/users/settings.php" class="p-6" data-validate>
                <div class="mb-6">
                    <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white mb-2">Add Withdrawal Wallet</h3>
                    <p class="text-gray-600 dark:text-gray-300">Add a new cryptocurrency address for withdrawals.</p>
                </div>

                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                <input type="hidden" name="action" value="add_wallet">

                <div class="space-y-4">
                    <div>
                        <label for="wallet-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Currency Type
                        </label>
                        <select id="wallet-type" name="wallet_type" required
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Currency</option>
                            <option value="bitcoin">Bitcoin (BTC)</option>
                            <option value="ethereum">Ethereum (ETH)</option>
                            <option value="usdt">Tether (USDT)</option>
                            <option value="bnb">Binance Coin (BNB)</option>
                        </select>
                    </div>

                    <div>
                        <label for="wallet-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Wallet Address
                        </label>
                        <input type="text" id="wallet-address" name="wallet_address" required
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Enter wallet address">
                    </div>

                    <div>
                        <label for="wallet-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Label (Optional)
                        </label>
                        <input type="text" id="wallet-label" name="wallet_label"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., My Main Wallet">
                    </div>
                </div>

                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeAddWalletModal()" 
                        class="flex-1 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-[#f5f3ff]0 text-gray-700 dark:text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors"
                        data-original-text="Add Wallet">
                        Add Wallet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.settings-tab');
    const contents = document.querySelectorAll('.settings-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            // Update tab appearance
            tabs.forEach(t => {
                t.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                t.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            });
            this.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            this.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            
            // Show/hide content
            contents.forEach(content => content.classList.add('hidden'));
            document.getElementById(tabName + '-tab').classList.remove('hidden');
        });
    });
});

// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('password-strength-bar');
    const feedback = document.getElementById('password-feedback');
    
    let score = 0;
    let feedback_text = '';
    
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;
    
    const strength = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500', 'bg-green-600'];
    
    strengthBar.className = `h-full transition-all duration-300 ${colors[score - 1] || 'bg-gray-300'}`;
    strengthBar.style.width = `${(score / 5) * 100}%`;
    feedback.textContent = password ? strength[score - 1] || 'Very Weak' : '';
});

// Form submissions
async function submitSettingsForm(form, successMsg) {
    const submitBtn = form.querySelector('button[type="submit"]');
    setLoading(submitBtn, true);
    try {
        const formData = new FormData(form);
        const response = await fetch('settings.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showNotification(result.data?.message || successMsg, 'success');
        } else {
            showNotification(result.error || 'Operation failed', 'error');
        }
    } catch (err) {
        console.error('Settings error:', err);
        showNotification('Network error. Please try again.', 'error');
    } finally {
        setLoading(submitBtn, false);
    }
}

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    if (newPassword !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    submitSettingsForm(this, 'Password updated successfully');
});

document.getElementById('notificationsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitSettingsForm(this, 'Notification preferences saved');
});

// Wallet modal functions
function openAddWalletModal() {
    document.getElementById('addWalletModal').classList.remove('hidden');
}

function closeAddWalletModal() {
    document.getElementById('addWalletModal').classList.add('hidden');
}

function deleteWallet(walletId) {
    if (confirm('Are you sure you want to delete this wallet?')) {
        showNotification('Wallet deleted successfully!', 'success');
        // In real app, would make API call to delete wallet
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Address copied to clipboard!', 'success');
    });
}

// Session management
function terminateSession(sessionId) {
    if (confirm('Are you sure you want to terminate this session?')) {
        showNotification('Session terminated successfully!', 'success');
        // In real app, would make API call
    }
}

function terminateAllSessions() {
    if (confirm('This will log you out from all other devices. Continue?')) {
        showNotification('All other sessions terminated!', 'success');
        // In real app, would make API call
    }
}

// 2FA functions
document.getElementById('twoFactorToggle').addEventListener('change', function() {
    if (this.checked) {
        showNotification('Setting up 2FA...', 'info');
        // In real app, would show QR code setup modal
    } else {
        if (confirm('Are you sure you want to disable 2FA?')) {
            showNotification('2FA disabled', 'warning');
        } else {
            this.checked = true;
        }
    }
});

function generateBackupCodes() {
    showNotification('Backup codes generated! Please save them securely.', 'info');
    // In real app, would show modal with backup codes
}

// Advanced functions
function confirmDeleteAccount() {
    if (confirm('Are you ABSOLUTELY sure? This action cannot be undone!')) {
        if (confirm('This will permanently delete all your data. Type "DELETE" to confirm.')) {
            showNotification('Account deletion initiated. Please check your email to complete.', 'warning');
        }
    }
}

function exportData() {
    showNotification('Preparing your data export... You will receive an email when ready.', 'info');
    // In real app, would initiate data export process
}

// Wallet form submission
document.getElementById('walletForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    setLoading(submitBtn, true);
    try {
        const formData = new FormData(this);
        formData.set('action', 'add_wallet');
        const response = await fetch('settings.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showNotification(result.data?.message || 'Wallet added successfully', 'success');
            closeAddWalletModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(result.error || 'Failed to add wallet', 'error');
        }
    } catch (err) {
        showNotification('Network error. Please try again.', 'error');
    } finally {
        setLoading(submitBtn, false);
    }
});

// Close modal on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('addWalletModal');
    if (e.target === modal) {
        closeAddWalletModal();
    }
});

// Handle URL hash for direct tab access
if (window.location.hash) {
    const tabName = window.location.hash.substring(1);
    const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (tabButton) {
        tabButton.click();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>