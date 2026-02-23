<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Initialize session
\App\Utils\SessionManager::start();

// Page setup
$pageTitle = 'Platform Settings - ' . \App\Config\Config::getSiteName();
$currentPage = 'settings';

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    $settingsModel = new \App\Models\AdminSettings($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Check if admin is logged in
if (!$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentAdmin = $adminController->getCurrentAdmin();
$currentPage = 'settings';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'update_settings':
            $result = handleSettingsUpdate($settingsModel);
            echo json_encode($result);
            exit;

        case 'update_deposit_method':
            $result = handleDepositMethodUpdate($settingsModel);
            echo json_encode($result);
            exit;

        case 'create_deposit_method':
            $result = handleDepositMethodCreate($settingsModel);
            echo json_encode($result);
            exit;

        case 'delete_deposit_method':
            $result = handleDepositMethodDelete($settingsModel);
            echo json_encode($result);
            exit;
    }
}

function handleSettingsUpdate($settingsModel)
{
    try {
        $settings = [];

        // Platform Settings
        if (isset($_POST['site_name']))
            $settings['site_name'] = ['value' => $_POST['site_name'], 'type' => 'string'];
        if (isset($_POST['site_email']))
            $settings['site_email'] = ['value' => $_POST['site_email'], 'type' => 'string'];
        if (isset($_POST['support_email']))
            $settings['support_email'] = ['value' => $_POST['support_email'], 'type' => 'string'];
        if (isset($_POST['currency_symbol']))
            $settings['currency_symbol'] = ['value' => $_POST['currency_symbol'], 'type' => 'string'];

        // Financial Settings
        if (isset($_POST['signup_bonus']))
            $settings['signup_bonus'] = ['value' => (int) $_POST['signup_bonus'], 'type' => 'integer'];
        if (isset($_POST['referral_bonus_rate']))
            $settings['referral_bonus_rate'] = ['value' => (int) $_POST['referral_bonus_rate'], 'type' => 'integer'];
        if (isset($_POST['withdrawal_fee_rate']))
            $settings['withdrawal_fee_rate'] = ['value' => (int) $_POST['withdrawal_fee_rate'], 'type' => 'integer'];
        if (isset($_POST['platform_fee_rate']))
            $settings['platform_fee_rate'] = ['value' => (int) $_POST['platform_fee_rate'], 'type' => 'integer'];
        if (isset($_POST['min_withdrawal_amount']))
            $settings['min_withdrawal_amount'] = ['value' => (int) $_POST['min_withdrawal_amount'], 'type' => 'integer'];
        if (isset($_POST['max_withdrawal_amount']))
            $settings['max_withdrawal_amount'] = ['value' => (int) $_POST['max_withdrawal_amount'], 'type' => 'integer'];

        // System Settings
        $settings['deposit_auto_approval'] = ['value' => isset($_POST['deposit_auto_approval']) ? 1 : 0, 'type' => 'boolean'];
        $settings['withdrawal_auto_approval'] = ['value' => isset($_POST['withdrawal_auto_approval']) ? 1 : 0, 'type' => 'boolean'];
        $settings['maintenance_mode'] = ['value' => isset($_POST['maintenance_mode']) ? 1 : 0, 'type' => 'boolean'];
        $settings['email_notifications'] = ['value' => isset($_POST['email_notifications']) ? 1 : 0, 'type' => 'boolean'];

        // Profit Distribution Settings
        $settings['profit_distribution_locked'] = ['value' => isset($_POST['profit_distribution_locked']) ? 1 : 0, 'type' => 'boolean'];
        $settings['show_profit_calculations'] = ['value' => isset($_POST['show_profit_calculations']) ? 1 : 0, 'type' => 'boolean'];

        $result = $settingsModel->updateMultipleSettings($settings);

        return $result
            ? ['success' => true, 'message' => 'Settings updated successfully']
            : ['success' => false, 'message' => 'Failed to update settings'];

    } catch (Exception $e) {
        error_log("Settings update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

function handleDepositMethodUpdate($settingsModel)
{
    try {
        $id = (int) $_POST['method_id'];
        $data = [
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'minimum_deposit' => (float) $_POST['minimum_deposit'],
            'maximum_deposit' => (float) $_POST['maximum_deposit'],
            'charge' => (float) $_POST['charge'],
            'charge_type' => $_POST['charge_type'],
            'status' => isset($_POST['status']) ? 1 : 0
        ];

        $result = $settingsModel->updateDepositMethod($id, $data);

        return $result
            ? ['success' => true, 'message' => 'Deposit method updated successfully']
            : ['success' => false, 'message' => 'Failed to update deposit method'];

    } catch (Exception $e) {
        error_log("Deposit method update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Update failed'];
    }
}

function handleDepositMethodCreate($settingsModel)
{
    try {
        $data = [
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'gateway_code' => strtolower(str_replace(' ', '_', $_POST['name'])),
            'minimum_deposit' => (float) $_POST['minimum_deposit'],
            'maximum_deposit' => (float) $_POST['maximum_deposit'],
            'charge' => (float) $_POST['charge'],
            'charge_type' => $_POST['charge_type'],
            'currency' => 'USD',
            'currency_symbol' => '$',
            'status' => isset($_POST['status']) ? 1 : 0
        ];

        $result = $settingsModel->createDepositMethod($data);

        return $result
            ? ['success' => true, 'message' => 'Deposit method created successfully']
            : ['success' => false, 'message' => 'Failed to create deposit method'];

    } catch (Exception $e) {
        error_log("Deposit method create error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Creation failed'];
    }
}

function handleDepositMethodDelete($settingsModel)
{
    try {
        $id = (int) $_POST['method_id'];
        $result = $settingsModel->deleteDepositMethod($id);

        return $result
            ? ['success' => true, 'message' => 'Deposit method deleted successfully']
            : ['success' => false, 'message' => 'Failed to delete deposit method'];

    } catch (Exception $e) {
        error_log("Deposit method delete error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Delete failed'];
    }
}

// Get data for the page
$allSettings = $settingsModel->getAllSettings();
$depositMethods = $settingsModel->getDepositMethods();
$platformStats = $settingsModel->getPlatformStats();

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Page Content -->
<div class="space-y-6">
    <!-- Platform Statistics Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-users"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($platformStats['total_users'] ?? 0) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($platformStats['total_invested'] ?? 0, 2) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invested</div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($platformStats['total_user_balance'] ?? 0, 2) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">User Balances</div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($platformStats['total_profits'] ?? 0, 2) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Profits Distributed</div>
        </div>
    </div>

    <!-- Maintenance Mode Alert -->
    <?php if ($allSettings['maintenance_mode']['value'] ?? false): ?>
        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 text-sm" >
            <div class="flex">
                <div>
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                </div>
                <div>
                    <strong>Maintenance Mode Active!</strong> The platform is currently in maintenance mode. Users cannot access the frontend.
                </div>
            </div>
            <a class="text-gray-400 hover:text-gray-600 dark:hover:text-white"  aria-label="close"></a>
        </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Platform Settings</h3>
        </div>
        <div class="p-6">
            <div class="flex gap-2 mb-6" id="settingsTabs">
                <button class="settings-tab px-4 py-2 text-sm font-medium rounded-full cursor-pointer bg-[#1e0e62] text-white" data-target="platform" type="button">Platform</button>
                <button class="settings-tab px-4 py-2 text-sm font-medium rounded-full cursor-pointer text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e]" data-target="financial" type="button">Financial</button>
                <button class="settings-tab px-4 py-2 text-sm font-medium rounded-full cursor-pointer text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e]" data-target="system" type="button">System</button>
                <button class="settings-tab px-4 py-2 text-sm font-medium rounded-full cursor-pointer text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#0f0a2e]" data-target="deposit-methods" type="button">Deposit Methods</button>
            </div>

            <div class="tab-content mt-3" id="settingsTabContent">
                <!-- Platform Settings Tab -->
                <div class="settings-pane" id="platform" >
                    <form id="platformSettingsForm">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Site Name</label>
                                    <input type="text" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="site_name" value="<?= htmlspecialchars($allSettings['site_name']['value'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Site Email</label>
                                    <input type="email" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="site_email" value="<?= htmlspecialchars($allSettings['site_email']['value'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Support Email</label>
                                    <input type="email" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="support_email" value="<?= htmlspecialchars($allSettings['support_email']['value'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Currency Symbol</label>
                                    <input type="text" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="currency_symbol" value="<?= htmlspecialchars($allSettings['currency_symbol']['value'] ?? '$') ?>" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="mt-6 mb-0 px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Platform Settings</button>
                    </form>
                </div>

                <!-- Financial Settings Tab -->
                <div class="settings-pane hidden" id="financial" >
                    <form id="financialSettingsForm">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Signup Bonus (<?= \App\Config\Config::getCurrencySymbol() ?>)</label>
                                    <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="signup_bonus" value="<?= $allSettings['signup_bonus']['value'] ?? 0 ?>" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Referral Bonus Rate (%)</label>
                                    <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="referral_bonus_rate" value="<?= $allSettings['referral_bonus_rate']['value'] ?? 0 ?>" min="0" max="100" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Withdrawal Fee Rate (%)</label>
                                    <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="withdrawal_fee_rate" value="<?= $allSettings['withdrawal_fee_rate']['value'] ?? 0 ?>" min="0" max="100" step="0.01">
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Platform Fee Rate (%)</label>
                                    <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="platform_fee_rate" value="<?= $allSettings['platform_fee_rate']['value'] ?? 0 ?>" min="0" max="100" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Min Withdrawal Amount (<?= \App\Config\Config::getCurrencySymbol() ?>)</label>
                                    <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="min_withdrawal_amount" value="<?= $allSettings['min_withdrawal_amount']['value'] ?? 0 ?>" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Max Withdrawal Amount (<?= \App\Config\Config::getCurrencySymbol() ?>)</label>
                                    <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="max_withdrawal_amount" value="<?= $allSettings['max_withdrawal_amount']['value'] ?? 0 ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="mt-6 mb-0 px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save Financial Settings</button>
                    </form>
                </div>

                <!-- System Settings Tab -->
                <div class="settings-pane hidden" id="system" >
                    <form id="systemSettingsForm">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <div class="flex items-center gap-2">
                                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="deposit_auto_approval" <?= ($allSettings['deposit_auto_approval']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Auto-approve Deposits</label>
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <div class="flex items-center gap-2">
                                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="withdrawal_auto_approval" <?= ($allSettings['withdrawal_auto_approval']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Auto-approve Withdrawals</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <div class="flex items-center gap-2">
                                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="maintenance_mode" <?= ($allSettings['maintenance_mode']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Maintenance Mode</label>
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <div class="flex items-center gap-2">
                                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="email_notifications" <?= ($allSettings['email_notifications']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Email Notifications</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="mb-3">
                                    <div class="flex items-center gap-2">
                                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="profit_distribution_locked" <?= ($allSettings['profit_distribution_locked']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Lock Profit Distribution</label>
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <div class="mb-3">
                                    <div class="flex items-center gap-2">
                                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="show_profit_calculations" <?= ($allSettings['show_profit_calculations']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="text-sm text-gray-600 dark:text-gray-400">Show Profit Calculations</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="mt-6 mb-0 px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Save System Settings</button>
                    </form>
                </div>

                <!-- Deposit Methods Tab -->
                <div class="settings-pane hidden" id="deposit-methods" >
                    <div class="flex justify-content-between align-items-center mb-3">
                        <h5>Deposit Methods</h5>
                        <button class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="showModal(this.getAttribute('data-target'))" data-target="addDepositMethodModal">
                            <i class="fas fa-plus mr-2"></i>Add Method
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-white/5">
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Min Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Max Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Charge</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($depositMethods as $method): ?>
                                <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($method['name']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($method['type']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($method['minimum_deposit'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($method['maximum_deposit'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= $method['charge_type'] === 'percentage' ? $method['charge'] . '%' : \App\Config\Config::getCurrencySymbol() . number_format($method['charge'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block <?= $method['status'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' ?>">
                                            <?= $method['status'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="flex gap-1">
                                            <button class="px-3 py-1 text-xs font-medium rounded-full border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 hover:border-[#1e0e62] transition-colors" onclick="editDepositMethod(<?= $method['id'] ?>)">
                                                Edit
                                            </button>
                                            <button class="px-3 py-1 text-xs font-medium rounded-full text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" onclick="deleteDepositMethod(<?= $method['id'] ?>)">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Deposit Method Modal -->
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="addDepositMethodModal" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Add Deposit Method</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" ></button>
            </div>
            <form id="addDepositMethodForm">
                <div class="p-6">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Method Name</label>
                        <input type="text" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Type</label>
                        <select class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" name="type" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="crypto">Cryptocurrency</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="ewallet">E-Wallet</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Minimum Amount</label>
                                <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="minimum_deposit" step="0.01" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Maximum Amount</label>
                                <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="maximum_deposit" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Charge</label>
                                <input type="number" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="charge" step="0.01" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Charge Type</label>
                                <select class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" name="charge_type" required>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percentage">Percentage</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" name="status" checked>
                            <label class="text-sm text-gray-600 dark:text-gray-400">Active</label>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                    <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full transition-colors" >Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Add Method</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// tab switching for settings page
document.querySelectorAll('.settings-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        var target = this.getAttribute('data-target');

        // hide all panes
        document.querySelectorAll('.settings-pane').forEach(function(pane) {
            pane.classList.add('hidden');
        });

        // deactivate all tabs
        document.querySelectorAll('.settings-tab').forEach(function(t) {
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

// Form submission handlers
document.getElementById('platformSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitSettingsForm(this, 'update_settings');
});

document.getElementById('financialSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitSettingsForm(this, 'update_settings');
});

document.getElementById('systemSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitSettingsForm(this, 'update_settings');
});

document.getElementById('addDepositMethodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitDepositMethodForm(this, 'create_deposit_method');
});

function submitSettingsForm(form, action) {
    const formData = new FormData(form);
    formData.append('action', action);
    formData.append('ajax', '1');

    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while saving settings', 'error');
    });
}

function submitDepositMethodForm(form, action) {
    const formData = new FormData(form);
    formData.append('action', action);
    formData.append('ajax', '1');

    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Close modal and reload page
            hideModal('addDepositMethodModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while adding deposit method', 'error');
    });
}

function editDepositMethod(id) {
    // Implementation for editing deposit method
    showNotification('Edit functionality coming soon', 'info');
}

function deleteDepositMethod(id) {
    if (confirm('Are you sure you want to delete this deposit method?')) {
        const formData = new FormData();
        formData.append('action', 'delete_deposit_method');
        formData.append('method_id', id);
        formData.append('ajax', '1');

        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while deleting deposit method', 'error');
        });
    }
}
</script>
