<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

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
<div class="admin-content">
    <!-- Platform Statistics Overview -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-primary);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-number"><?= number_format($platformStats['total_users'] ?? 0) ?></div>
            <div class="stats-label">Total Users</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-success);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stats-number"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($platformStats['total_invested'] ?? 0, 2) ?></div>
            <div class="stats-label">Total Invested</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-info);">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stats-number"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($platformStats['total_user_balance'] ?? 0, 2) ?></div>
            <div class="stats-label">User Balances</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-warning);">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stats-number"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($platformStats['total_profits'] ?? 0, 2) ?></div>
            <div class="stats-label">Profits Distributed</div>
        </div>
    </div>

    <!-- Maintenance Mode Alert -->
    <?php if ($allSettings['maintenance_mode']['value'] ?? false): ?>
        <div class="alert alert-warning alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                </div>
                <div>
                    <strong>Maintenance Mode Active!</strong> The platform is currently in maintenance mode. Users cannot access the frontend.
                </div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Platform Settings</h3>
        </div>
        <div class="admin-card-body">
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="platform-tab" data-bs-toggle="tab" data-bs-target="#platform" type="button" role="tab">
                        <i class="fas fa-cog me-2"></i>Platform
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button" role="tab">
                        <i class="fas fa-dollar-sign me-2"></i>Financial
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                        <i class="fas fa-server me-2"></i>System
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deposit-methods-tab" data-bs-toggle="tab" data-bs-target="#deposit-methods" type="button" role="tab">
                        <i class="fas fa-credit-card me-2"></i>Deposit Methods
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-3" id="settingsTabContent">
                <!-- Platform Settings Tab -->
                <div class="tab-pane fade show active" id="platform" role="tabpanel">
                    <form id="platformSettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($allSettings['site_name']['value'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Site Email</label>
                                    <input type="email" class="form-control" name="site_email" value="<?= htmlspecialchars($allSettings['site_email']['value'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Support Email</label>
                                    <input type="email" class="form-control" name="support_email" value="<?= htmlspecialchars($allSettings['support_email']['value'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" name="currency_symbol" value="<?= htmlspecialchars($allSettings['currency_symbol']['value'] ?? '$') ?>" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Platform Settings</button>
                    </form>
                </div>

                <!-- Financial Settings Tab -->
                <div class="tab-pane fade" id="financial" role="tabpanel">
                    <form id="financialSettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Signup Bonus (<?= \App\Config\Config::getCurrencySymbol() ?>)</label>
                                    <input type="number" class="form-control" name="signup_bonus" value="<?= $allSettings['signup_bonus']['value'] ?? 0 ?>" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Referral Bonus Rate (%)</label>
                                    <input type="number" class="form-control" name="referral_bonus_rate" value="<?= $allSettings['referral_bonus_rate']['value'] ?? 0 ?>" min="0" max="100" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Withdrawal Fee Rate (%)</label>
                                    <input type="number" class="form-control" name="withdrawal_fee_rate" value="<?= $allSettings['withdrawal_fee_rate']['value'] ?? 0 ?>" min="0" max="100" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Platform Fee Rate (%)</label>
                                    <input type="number" class="form-control" name="platform_fee_rate" value="<?= $allSettings['platform_fee_rate']['value'] ?? 0 ?>" min="0" max="100" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Min Withdrawal Amount (<?= \App\Config\Config::getCurrencySymbol() ?>)</label>
                                    <input type="number" class="form-control" name="min_withdrawal_amount" value="<?= $allSettings['min_withdrawal_amount']['value'] ?? 0 ?>" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Withdrawal Amount (<?= \App\Config\Config::getCurrencySymbol() ?>)</label>
                                    <input type="number" class="form-control" name="max_withdrawal_amount" value="<?= $allSettings['max_withdrawal_amount']['value'] ?? 0 ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Financial Settings</button>
                    </form>
                </div>

                <!-- System Settings Tab -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <form id="systemSettingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="deposit_auto_approval" <?= ($allSettings['deposit_auto_approval']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Auto-approve Deposits</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="withdrawal_auto_approval" <?= ($allSettings['withdrawal_auto_approval']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Auto-approve Withdrawals</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="maintenance_mode" <?= ($allSettings['maintenance_mode']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Maintenance Mode</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" <?= ($allSettings['email_notifications']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Email Notifications</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="profit_distribution_locked" <?= ($allSettings['profit_distribution_locked']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Lock Profit Distribution</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="show_profit_calculations" <?= ($allSettings['show_profit_calculations']['value'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Show Profit Calculations</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save System Settings</button>
                    </form>
                </div>

                <!-- Deposit Methods Tab -->
                <div class="tab-pane fade" id="deposit-methods" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Deposit Methods</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepositMethodModal">
                            <i class="fas fa-plus me-2"></i>Add Method
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Min Amount</th>
                                    <th>Max Amount</th>
                                    <th>Charge</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($depositMethods as $method): ?>
                                <tr>
                                    <td><?= htmlspecialchars($method['name']) ?></td>
                                    <td><?= htmlspecialchars($method['type']) ?></td>
                                    <td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($method['minimum_deposit'], 2) ?></td>
                                    <td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($method['maximum_deposit'], 2) ?></td>
                                    <td><?= $method['charge_type'] === 'percentage' ? $method['charge'] . '%' : \App\Config\Config::getCurrencySymbol() . number_format($method['charge'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $method['status'] ? 'success' : 'danger' ?>">
                                            <?= $method['status'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editDepositMethod(<?= $method['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDepositMethod(<?= $method['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
<div class="modal fade" id="addDepositMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Deposit Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDepositMethodForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Method Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="crypto">Cryptocurrency</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="ewallet">E-Wallet</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Amount</label>
                                <input type="number" class="form-control" name="minimum_deposit" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maximum Amount</label>
                                <input type="number" class="form-control" name="maximum_deposit" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Charge</label>
                                <input type="number" class="form-control" name="charge" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Charge Type</label>
                                <select class="form-select" name="charge_type" required>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percentage">Percentage</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Method</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
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
            const modal = bootstrap.Modal.getInstance(document.getElementById('addDepositMethodModal'));
            modal.hide();
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
