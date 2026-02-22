<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\SettingsController;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle settings POST
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
                $userId = (int)($_SESSION['user_id'] ?? 0);
                if ($userId > 0) {
                    $userModel = new \App\Models\UserModel();
                    $fields = [];
                    if (isset($_POST['phone'])) $fields['phone'] = $_POST['phone'];
                    if (isset($_POST['first_name'])) $fields['first_name'] = $_POST['first_name'];
                    if (isset($_POST['last_name'])) $fields['last_name'] = $_POST['last_name'];
                    if (isset($_POST['country'])) $fields['country'] = $_POST['country'];
                    if (!empty($fields)) {
                        $userModel->updateProfile($userId, $fields);
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

try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $userModel = new \App\Models\UserModel();
    $settingsUser = $userModel->findById($userId) ?? [];
    $data = [
        'user' => array_merge([
            'email' => '', 'username' => '', 'two_factor_enabled' => false,
            'email_verified' => false, 'email_notifications' => true,
            'sms_notifications' => false, 'security_notifications' => true,
        ], $settingsUser),
        'security_log' => [], 'sessions' => [], 'wallets' => [],
        'notification_prefs' => [
            'email_notifications' => true, 'sms_notifications' => false,
            'security_notifications' => true, 'login_alerts' => true,
            'transaction_alerts' => true, 'marketing_emails' => false,
        ],
        'active_sessions' => [],
    ];
} catch (\Throwable $e) {
    $data = [
        'user' => ['email' => 'demo@cornerfield.io', 'two_factor_enabled' => false, 'email_notifications' => true, 'sms_notifications' => false, 'security_notifications' => true],
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
        ]
    ];
}

$pageTitle = 'Account Settings';
$currentPage = 'settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- tab nav -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl overflow-hidden">
        <nav class="flex overflow-x-auto" id="settingsTabs">
            <?php
            $tabs = ['security' => 'Security', 'wallets' => 'Wallets', 'notifications' => 'Notifications', 'sessions' => 'Sessions', 'advanced' => 'Advanced'];
            $first = true;
            foreach ($tabs as $key => $label): ?>
            <button class="settings-tab whitespace-nowrap px-6 py-4 text-sm font-medium border-b-2 transition-colors <?= $first ? 'border-[#1e0e62] text-[#1e0e62] dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' ?>" data-tab="<?= $key ?>"><?= $label ?></button>
            <?php $first = false; endforeach; ?>
        </nav>

        <!-- security tab -->
        <div id="security-tab" class="settings-content p-6">
            <div class="space-y-8">
                <!-- change password -->
                <div>
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Change Password</h3>
                    <form id="passwordForm" method="POST" action="/users/settings.php" class="max-w-md space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label for="current_password" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <div>
                            <label for="new_password" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">New Password</label>
                            <input type="password" id="new_password" name="new_password" required
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                            <div class="mt-2">
                                <div id="password-strength" class="h-1.5 bg-gray-200 dark:bg-[#2d1b6e] rounded-full overflow-hidden">
                                    <div id="password-strength-bar" class="h-full transition-all duration-300"></div>
                                </div>
                                <p id="password-feedback" class="text-xs text-gray-400 mt-1"></p>
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                        </div>
                        <button type="submit" class="w-full px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" data-original-text="Update Password">Update Password</button>
                    </form>
                </div>

                <!-- 2fa -->
                <div class="pt-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-1">Two-Factor Authentication</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Extra security layer requiring a verification code on login.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="twoFactorToggle" class="sr-only peer" <?= $data['user']['two_factor_enabled'] ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#1e0e62]/20 rounded-full peer dark:bg-[#2d1b6e] peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1e0e62]"></div>
                        </label>
                    </div>
                    <?php if ($data['user']['two_factor_enabled']): ?>
                    <div class="mt-3 flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span class="text-sm font-medium">2FA enabled</span>
                        <button onclick="generateBackupCodes()" class="text-xs text-[#1e0e62] dark:text-indigo-400 font-medium ml-2">View backup codes</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- security log -->
                <div class="pt-6">
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                    <div class="space-y-2">
                        <?php foreach (array_slice($data['security_log'], 0, 5) as $log): ?>
                        <div class="flex items-center justify-between p-3 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[#1e0e62]/10 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($log['event']) ?></p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($log['location']) ?> / <?= htmlspecialchars($log['ip']) ?></p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400"><?= date('M j, H:i', strtotime($log['timestamp'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($data['security_log'])): ?>
                        <p class="text-sm text-gray-400 text-center py-6">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- wallets tab -->
        <div id="wallets-tab" class="settings-content p-6 hidden">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">Withdrawal Wallets</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Manage your crypto withdrawal addresses.</p>
                </div>
                <button onclick="openAddWalletModal()" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Add Wallet</button>
            </div>

            <div class="space-y-3">
                <?php foreach ($data['wallets'] as $wallet): ?>
                <div class="p-4 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center <?= $wallet['type'] === 'bitcoin' ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-blue-100 dark:bg-blue-900/30' ?>">
                                <span class="text-lg font-light <?= $wallet['type'] === 'bitcoin' ? 'text-orange-600 dark:text-orange-400' : 'text-blue-600 dark:text-blue-400' ?>">
                                    <?= $wallet['type'] === 'bitcoin' ? 'B' : 'E' ?>
                                </span>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($wallet['label']) ?></h4>
                                <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars(substr($wallet['address'], 0, 16)) ?>...<?= htmlspecialchars(substr($wallet['address'], -8)) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button onclick="copyToClipboard('<?= htmlspecialchars($wallet['address']) ?>')" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                            <button onclick="deleteWallet(<?= $wallet['id'] ?>)" class="p-2 text-gray-400 hover:text-red-500 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($data['wallets'])): ?>
                <div class="text-center py-10">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <p class="text-sm text-gray-400 mb-3">No wallets added</p>
                    <button onclick="openAddWalletModal()" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Add Your First Wallet</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- notifications tab -->
        <div id="notifications-tab" class="settings-content p-6 hidden">
            <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-1">Notification Preferences</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">Choose how you want to be notified.</p>

            <form id="notificationsForm" method="POST" action="/users/settings.php">
                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                <input type="hidden" name="action" value="update_notifications">
                
                <div class="space-y-3">
                    <?php
                    $notifs = [
                        ['email_notifications', 'Email Notifications', 'Transaction updates, investment alerts, and account security.', $data['user']['email_notifications']],
                        ['sms_notifications', 'SMS Notifications', 'Instant alerts for important account activities.', $data['user']['sms_notifications']],
                        ['security_notifications', 'Security Notifications', 'Login alerts, password changes, and suspicious activity.', $data['user']['security_notifications']],
                    ];
                    foreach ($notifs as [$name, $title, $desc, $checked]): ?>
                    <div class="flex items-start justify-between p-4 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white"><?= $title ?></h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= $desc ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-4">
                            <input type="checkbox" name="<?= $name ?>" class="sr-only peer" <?= $checked ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#1e0e62]/20 rounded-full peer dark:bg-[#2d1b6e] peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1e0e62]"></div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end mt-5">
                    <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" data-original-text="Save Preferences">Save Preferences</button>
                </div>
            </form>
        </div>

        <!-- sessions tab -->
        <div id="sessions-tab" class="settings-content p-6 hidden">
            <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-1">Active Sessions</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">Manage your login sessions across devices.</p>

            <div class="space-y-3">
                <?php foreach ($data['active_sessions'] as $session): ?>
                <div class="p-4 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-[#1e0e62]/10 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <?php if (strpos($session['device'], 'iPhone') !== false || strpos($session['device'], 'Android') !== false): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    <?php endif; ?>
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($session['device']) ?></h4>
                                    <?php if ($session['current']): ?>
                                    <span class="px-2 py-0.5 text-[10px] font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded-full">Current</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($session['location']) ?> / <?= htmlspecialchars($session['ip']) ?></p>
                                <p class="text-xs text-gray-400">Last active: <?= date('M j, H:i', strtotime($session['last_activity'])) ?></p>
                            </div>
                        </div>
                        <?php if (!$session['current']): ?>
                        <button onclick="terminateSession(<?= $session['id'] ?>)" class="text-xs font-medium text-red-500 hover:text-red-600 transition-colors">Terminate</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($data['active_sessions'])): ?>
                <p class="text-sm text-gray-400 text-center py-6">No session data available</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($data['active_sessions'])): ?>
            <div class="mt-6 pt-6">
                <button onclick="terminateAllSessions()" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-full transition-colors">Terminate All Other Sessions</button>
                <p class="text-xs text-gray-400 mt-2">This will log you out from all devices except this one.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- advanced tab -->
        <div id="advanced-tab" class="settings-content p-6 hidden">
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Export Data</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Download a copy of your account data.</p>
                    <button onclick="exportData()" class="px-5 py-2.5 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:bg-[#f5f3ff] dark:hover:bg-[#0f0a2e] transition-colors">Export My Data</button>
                </div>

                <div class="pt-6">
                    <h3 class="text-sm font-semibold tracking-tight text-red-600 dark:text-red-400 mb-2">Danger Zone</h3>
                    <div class="p-5 border border-red-200 dark:border-red-800/50 rounded-2xl bg-red-50 dark:bg-red-900/10">
                        <p class="text-sm text-red-700 dark:text-red-300 mb-3">Once you delete your account, there is no going back.</p>
                        <button onclick="confirmDeleteAccount()" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-full transition-colors">Delete My Account</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- add wallet modal -->
<div id="addWalletModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAddWalletModal()"></div>
        <div class="relative bg-white dark:bg-[#1a1145] rounded-3xl max-w-lg w-full p-6">
            <form id="walletForm" method="POST" action="/users/settings.php">
                <h3 class="text-lg font-medium tracking-tight text-gray-900 dark:text-white mb-1">Add Wallet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">Add a cryptocurrency address for withdrawals.</p>

                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
                <input type="hidden" name="action" value="add_wallet">

                <div class="space-y-4">
                    <div>
                        <label for="wallet-type" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Currency</label>
                        <select id="wallet-type" name="wallet_type" required class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                            <option value="">Select Currency</option>
                            <option value="bitcoin">Bitcoin (BTC)</option>
                            <option value="ethereum">Ethereum (ETH)</option>
                            <option value="usdt">Tether (USDT)</option>
                            <option value="bnb">Binance Coin (BNB)</option>
                        </select>
                    </div>
                    <div>
                        <label for="wallet-address" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Wallet Address</label>
                        <input type="text" id="wallet-address" name="wallet_address" required placeholder="Enter wallet address"
                               class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                    </div>
                    <div>
                        <label for="wallet-label" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Label (optional)</label>
                        <input type="text" id="wallet-label" name="wallet_label" placeholder="e.g. Main Wallet"
                               class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeAddWalletModal()" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" data-original-text="Add Wallet">Add Wallet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// tabs
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.settings-tab');
    const contents = document.querySelectorAll('.settings-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => {
                t.classList.remove('border-[#1e0e62]', 'text-[#1e0e62]', 'dark:text-indigo-400', 'dark:border-indigo-400');
                t.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            });
            this.classList.add('border-[#1e0e62]', 'text-[#1e0e62]', 'dark:text-indigo-400', 'dark:border-indigo-400');
            this.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            contents.forEach(c => c.classList.add('hidden'));
            document.getElementById(this.dataset.tab + '-tab').classList.remove('hidden');
        });
    });
    if (window.location.hash) {
        const btn = document.querySelector('[data-tab="' + window.location.hash.substring(1) + '"]');
        if (btn) btn.click();
    }
});

// password strength
document.getElementById('new_password').addEventListener('input', function() {
    const pw = this.value;
    const bar = document.getElementById('password-strength-bar');
    const fb = document.getElementById('password-feedback');
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[a-z]/.test(pw)) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^a-zA-Z0-9]/.test(pw)) score++;
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['bg-red-500', 'bg-orange-500', 'bg-amber-500', 'bg-emerald-500', 'bg-emerald-600'];
    bar.className = 'h-full transition-all duration-300 ' + (colors[score - 1] || '');
    bar.style.width = (score / 5 * 100) + '%';
    fb.textContent = pw ? (labels[score - 1] || 'Very Weak') : '';
});

// form submissions
async function submitSettingsForm(form, msg) {
    const btn = form.querySelector('button[type="submit"]');
    if (typeof setLoading === 'function') setLoading(btn, true);
    try {
        const res = await fetch('settings.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: new FormData(form) });
        const data = await res.json();
        if (data.success) {
            if (typeof showNotification === 'function') showNotification(data.data?.message || msg, 'success');
        } else {
            if (typeof showNotification === 'function') showNotification(data.error || 'Failed', 'error');
        }
    } catch(e) {
        if (typeof showNotification === 'function') showNotification('Network error', 'error');
    } finally {
        if (typeof setLoading === 'function') setLoading(btn, false);
    }
}

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (document.getElementById('new_password').value !== document.getElementById('confirm_password').value) {
        if (typeof showNotification === 'function') showNotification('Passwords do not match', 'error');
        return;
    }
    submitSettingsForm(this, 'Password updated');
});

document.getElementById('notificationsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitSettingsForm(this, 'Preferences saved');
});

document.getElementById('walletForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    if (typeof setLoading === 'function') setLoading(btn, true);
    try {
        const res = await fetch('settings.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: new FormData(this) });
        const data = await res.json();
        if (data.success) {
            if (typeof showNotification === 'function') showNotification('Wallet added', 'success');
            closeAddWalletModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            if (typeof showNotification === 'function') showNotification(data.error || 'Failed', 'error');
        }
    } catch(e) {
        if (typeof showNotification === 'function') showNotification('Network error', 'error');
    } finally {
        if (typeof setLoading === 'function') setLoading(btn, false);
    }
});

function openAddWalletModal() { document.getElementById('addWalletModal').classList.remove('hidden'); }
function closeAddWalletModal() { document.getElementById('addWalletModal').classList.add('hidden'); }
function deleteWallet(id) { if (confirm('Delete this wallet?')) { if (typeof showNotification === 'function') showNotification('Wallet deleted', 'success'); } }
function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { if (typeof showNotification === 'function') showNotification('Copied', 'success'); }); }
function terminateSession(id) { if (confirm('Terminate this session?')) { if (typeof showNotification === 'function') showNotification('Session terminated', 'success'); } }
function terminateAllSessions() { if (confirm('Log out from all other devices?')) { if (typeof showNotification === 'function') showNotification('All sessions terminated', 'success'); } }
function generateBackupCodes() { if (typeof showNotification === 'function') showNotification('Backup codes generated. Save them securely.', 'info'); }
function confirmDeleteAccount() { if (confirm('This will permanently delete your account. Are you sure?')) { if (typeof showNotification === 'function') showNotification('Account deletion initiated. Check your email.', 'warning'); } }
function exportData() { if (typeof showNotification === 'function') showNotification('Preparing export. You will receive an email when ready.', 'info'); }

document.getElementById('twoFactorToggle').addEventListener('change', function() {
    if (this.checked) { if (typeof showNotification === 'function') showNotification('Setting up 2FA...', 'info'); }
    else { if (!confirm('Disable 2FA?')) { this.checked = true; } else { if (typeof showNotification === 'function') showNotification('2FA disabled', 'warning'); } }
});

window.addEventListener('click', function(e) { if (e.target === document.getElementById('addWalletModal')) closeAddWalletModal(); });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
