<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Page setup
$pageTitle = 'User Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'users';

// Initialize session
\App\Utils\SessionManager::start();

// Initialize database and models
try {
    $database = new \App\Config\Database();
    $userManagement = new \App\Models\UserManagement($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    \App\Utils\CSRFProtection::validateRequest();
    
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'toggle_status':
            $userId = (int)$_POST['user_id'];
            if ($userManagement->toggleUserStatus($userId)) {
                $response = ['success' => true, 'message' => 'User status updated successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update user status'];
            }
            break;
            
        case 'update_balance':
            $userId = (int)($_POST['user_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $type = trim($_POST['type'] ?? ''); // 'add' or 'subtract'
            $description = trim($_POST['description'] ?? '');
            $adminId = \App\Utils\SessionManager::get('admin_id');
            
            // Validate inputs
            if ($userId <= 0 || $amount <= 0 || !in_array($type, ['add', 'subtract'])) {
                $response = ['success' => false, 'message' => 'Invalid input parameters provided.'];
                break;
            }
            
            // Sanitize description
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            
            try {
                $database->beginTransaction();
                
                if ($type === 'add') {
                    // Create transaction record for deposit
                    $transactionData = [
                        'user_id' => $userId,
                        'type' => 'deposit',
                        'amount' => $amount,
                        'fee' => 0,
                        'net_amount' => $amount,
                        'status' => 'completed',
                        'payment_method' => 'manual',
                        'gateway_transaction_id' => \App\Utils\ReferenceGenerator::generateAdminId(),
                        'description' => $description ?: 'Manual balance addition by admin',
                        'admin_processed_by' => $adminId,
                        'processed_by_type' => 'admin',
                        'processed_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $transactionId = $database->insert('transactions', $transactionData);
                    
                    // Create deposit record for consistency with deposits.php
                    // Get or create admin deposit method
                    $adminDepositMethod = $database->fetchOne("SELECT id FROM deposit_methods WHERE name LIKE '%admin%' OR name LIKE '%manual%' LIMIT 1");
                    $depositMethodId = $adminDepositMethod ? $adminDepositMethod['id'] : 1; // Fallback to first method
                    
                    $depositData = [
                        'transaction_id' => $transactionId,
                        'user_id' => $userId,
                        'deposit_method_id' => $depositMethodId,
                        'requested_amount' => $amount,
                        'fee_amount' => 0,
                        'status' => 'completed',
                        'verification_status' => 'verified',
                        'admin_processed_by' => $adminId,
                        'processed_at' => date('Y-m-d H:i:s'),
                        'admin_notes' => 'Balance addition via user management: ' . ($description ?: 'No description provided')
                    ];
                    
                    $database->insert('deposits', $depositData);
                    
                    // Update user balance safely
                    $currentBalance = $database->fetchOne("SELECT balance FROM users WHERE id = ?", [$userId])['balance'] ?? 0;
                    $newBalance = $currentBalance + $amount;
                    
                    $database->update('users', [
                        'balance' => $newBalance
                    ], 'id = ?', [$userId]);
                    
                } else {
                    // For subtract, use existing UserManagement method
                    if (!$userManagement->updateUserBalance($userId, $amount, $type, $adminId, $description)) {
                        throw new Exception('Failed to update balance via UserManagement');
                    }
                }
                
                $database->commit();
                $response = ['success' => true, 'message' => 'Balance updated successfully'];
                
            } catch (Exception $e) {
                $database->rollback();
                error_log('Balance update error: ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'Failed to update balance: ' . $e->getMessage()];
            }
            break;
            
        case 'update_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            
            // Validate user ID
            if ($userId <= 0) {
                $response = ['success' => false, 'message' => 'Invalid user ID provided.'];
                break;
            }
            
            $updateData = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'email_verified' => isset($_POST['email_verified']) ? 1 : 0
            ];
            
            // Sanitize inputs
            $updateData['first_name'] = htmlspecialchars($updateData['first_name'], ENT_QUOTES, 'UTF-8');
            $updateData['last_name'] = htmlspecialchars($updateData['last_name'], ENT_QUOTES, 'UTF-8');
            $updateData['email'] = filter_var($updateData['email'], FILTER_SANITIZE_EMAIL);
            $updateData['phone'] = htmlspecialchars($updateData['phone'], ENT_QUOTES, 'UTF-8');
            $updateData['country'] = htmlspecialchars($updateData['country'], ENT_QUOTES, 'UTF-8');
            
            if (!empty($_POST['password'])) {
                $updateData['password'] = $_POST['password'];
            }
            
            if ($userManagement->updateUser($userId, $updateData)) {
                $response = ['success' => true, 'message' => 'User updated successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update user'];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Get filter parameters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$limit = 20;

// Get users and pagination info
$users = $userManagement->getAllUsers($page, $limit, $search, $status);
$totalUsers = $userManagement->getTotalUsersCount($search, $status);
$totalPages = ceil($totalUsers / $limit);

// Get user statistics
$stats = $userManagement->getUserStatistics();

// Get currency symbol from admin settings
try {
    $adminSettingsModel = new \App\Models\AdminSettings($database);
    $currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');
} catch (Exception $e) {
    $currencySymbol = '$'; // Fallback to default
}

// Create admin controller for header
$adminController = new \App\Controllers\AdminController($database);

// Get current admin data for header
$currentAdmin = $adminController->getCurrentAdmin();

// Include header
include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Users</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($stats['total_users']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Active Users</p>
            <p class="text-3xl font-light tracking-tighter text-emerald-600 dark:text-emerald-400"><?= number_format($stats['active_users']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">New This Month</p>
            <p class="text-3xl font-light tracking-tighter text-[#1e0e62] dark:text-indigo-400"><?= number_format($stats['new_users_this_month']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Verified Users</p>
            <p class="text-3xl font-light tracking-tighter text-blue-600 dark:text-blue-400"><?= number_format($stats['verified_users']) ?></p>
        </div>
    </div>

    <!-- filters + table -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">All Users</h3>
                <form method="GET" class="flex flex-wrap gap-2">
                    <input type="text" name="search" class="px-3 py-1.5 text-sm bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status" class="px-3 py-1.5 text-sm bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="unverified" <?= $status === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                    </select>
                    <button type="submit" class="px-4 py-1.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Filter</button>
                </form>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-white/5">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Investments</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No users found</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#1e0e62]/10 dark:bg-white/10 flex items-center justify-center text-[#1e0e62] dark:text-white text-xs font-medium"><?= strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)) ?></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                                        <p class="text-xs text-gray-400">@<?= htmlspecialchars($user['username']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <p class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($user['email']) ?></p>
                                <?php if ($user['phone']): ?><p class="text-xs text-gray-400"><?= htmlspecialchars($user['phone']) ?></p><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <p class="text-sm font-medium <?= $user['balance'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' ?>"><?= htmlspecialchars($currencySymbol) ?><?= number_format($user['balance'], 2) ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <p class="text-sm text-gray-900 dark:text-white"><?= $user['total_investments'] ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($currencySymbol) ?><?= number_format($user['total_invested_amount'], 2) ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex flex-col gap-1">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block w-fit <?= $user['is_active'] ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block w-fit <?= $user['email_verified'] ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400' ?>"><?= $user['email_verified'] ? 'Verified' : 'Unverified' ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <p class="text-sm text-gray-900 dark:text-white"><?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center gap-1">
                                    <button onclick="editUser(<?= $user['id'] ?>)" class="px-3 py-1 bg-[#1e0e62] text-white text-xs font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Edit</button>
                                    <button onclick="manageBalance(<?= $user['id'] ?>)" class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-xs font-medium rounded-full hover:border-[#1e0e62] transition-colors">Balance</button>
                                    <button onclick="impersonateUser(<?= $user['id'] ?>)" class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-xs font-medium rounded-full hover:border-[#1e0e62] transition-colors">Login as</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-6 border-t border-gray-100 dark:border-[#2d1b6e] flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">Showing <?= ($page - 1) * $limit + 1 ?> to <?= min($page * $limit, $totalUsers) ?> of <?= $totalUsers ?></p>
            <div class="flex gap-1">
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" class="px-3 py-1 text-sm rounded-full <?= $i === $page ? 'bg-[#1e0e62] text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/10' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- edit user modal -->
<div id="editUserModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit User</h3>
            <button onclick="hideModal('editUserModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-white"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="editUserForm" class="p-6 space-y-4">
            <?= \App\Utils\CSRFProtection::getTokenField() ?>
            <input type="hidden" id="edit_user_id" name="user_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">First name</label><input type="text" name="first_name" id="edit_first_name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]"></div>
                <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Last name</label><input type="text" name="last_name" id="edit_last_name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]"></div>
                <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Email</label><input type="email" name="email" id="edit_email" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]"></div>
                <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Phone</label><input type="text" name="phone" id="edit_phone" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]"></div>
                <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Country</label><input type="text" name="country" id="edit_country" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]"></div>
                <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">New password (optional)</label><input type="password" name="password" id="edit_password" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]"></div>
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_active" id="edit_is_active" class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]"><span class="text-sm text-gray-600 dark:text-gray-400">Active</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="email_verified" id="edit_email_verified" class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]"><span class="text-sm text-gray-600 dark:text-gray-400">Email verified</span></label>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('editUserModal')" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-full hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- balance modal -->
<div id="balanceModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Manage Balance</h3>
            <button onclick="hideModal('balanceModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-white"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="balanceForm" class="p-6 space-y-4">
            <?= \App\Utils\CSRFProtection::getTokenField() ?>
            <input type="hidden" id="balance_user_id" name="user_id">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Current balance</p>
                <p class="text-2xl font-light tracking-tighter text-[#1e0e62] dark:text-indigo-400" id="current_balance"><?= htmlspecialchars($currencySymbol) ?>0.00</p>
            </div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Action</label><select name="type" id="balance_type" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none"><option value="add">Add funds</option><option value="subtract">Subtract funds</option></select></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Amount</label><input type="number" name="amount" step="0.01" min="0" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" required></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label><textarea name="description" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="Reason for adjustment..."></textarea></div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('balanceModal')" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-full">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Update Balance</button>
            </div>
        </form>
    </div>
</div>

<!-- user details modal -->
<div id="userDetailsModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">User Details</h3>
            <button onclick="hideModal('userDetailsModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-white"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6" id="userDetailsContent"></div>
    </div>
</div>

<?php
$pageSpecificJS = '
<script>
// modal helpers
function showModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove("hidden");
        modal.style.display = "flex";
        document.body.classList.add("modal-open");
    }
}

function hideModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add("hidden");
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
    }
}

// Edit User Function
function editUser(userId) {
    console.log("Edit user called for ID:", userId);
    
    // Load user data via AJAX first
    fetch(`get-user-data.php?user_id=${userId}`)
        .then(response => {
            console.log("Response status:", response.status);
            return response.json();
        })
        .then(data => {
            console.log("User data received:", data);
            if (data.success) {
                const user = data.user;
                
                // Populate the form
                document.getElementById("edit_user_id").value = user.id;
                document.getElementById("edit_first_name").value = user.first_name || "";
                document.getElementById("edit_last_name").value = user.last_name || "";
                document.getElementById("edit_email").value = user.email || "";
                document.getElementById("edit_phone").value = user.phone || "";
                document.getElementById("edit_country").value = user.country || "";
                document.getElementById("edit_password").value = "";
                document.getElementById("edit_is_active").checked = user.is_active == 1;
                document.getElementById("edit_email_verified").checked = user.email_verified == 1;
                
                // Show the modal using Tabler method or fallback
                try {
                    if (false && typeof bootstrap !== "undefined") {
                        const modal = new bootstrap.Modal(document.getElementById("editUserModal"));
                        modal.show();
                    } else {
                        // Fallback: show modal manually
                        const modal = document.getElementById("editUserModal");
                        showModal(modal.id);
                    }
                } catch (error) {
                    console.error("Error showing modal:", error);
                    alert("Modal error, but form is populated. Check console.");
                }
            } else {
                alert("Error loading user data: " + data.error);
            }
        })
        .catch(error => {
           console.error("Fetch error:", error);
           alert("Error loading user data: " + error.message);
       });
}

// Toggle User Status
function toggleStatus(userId) {
   if (confirm("Are you sure you want to change this user\'s status?")) {
       fetch("users.php", {
           method: "POST",
           headers: {
               "Content-Type": "application/x-www-form-urlencoded",
           },
           body: `action=toggle_status&user_id=${userId}`
       })
       .then(response => response.json())
       .then(data => {
           if (data.success) {
               location.reload();
           } else {
               alert("Error: " + data.message);
           }
       })
       .catch(error => {
           alert("An error occurred");
           console.error("Error:", error);
       });
   }
}

// Manage Balance
function manageBalance(userId) {
   console.log("Manage balance called for ID:", userId);
   
   // Load current user balance first
   fetch(`get-user-data.php?user_id=${userId}`)
       .then(response => response.json())
       .then(data => {
           if (data.success) {
               document.getElementById("balance_user_id").value = userId;
               document.getElementById("current_balance").textContent = "' . addslashes($currencySymbol) . '" + parseFloat(data.user.balance).toLocaleString("en-US", {
                   minimumFractionDigits: 2, 
                   maximumFractionDigits: 2
               });
               
               // Show modal
               try {
                   if (false && typeof bootstrap !== "undefined") {
                       const modal = new bootstrap.Modal(document.getElementById("balanceModal"));
                       modal.show();
                   } else {
                       // Fallback
                       const modal = document.getElementById("balanceModal");
                       modal.classList.remove("hidden");
                       modal.style.display = "flex";
                       document.body.classList.add("modal-open");
                   }
               } catch (error) {
                   console.error("Error showing balance modal:", error);
                   alert("Modal error. Check console.");
               }
           } else {
               alert("Error loading user balance: " + data.error);
           }
       })
       .catch(error => {
           console.error("Balance fetch error:", error);
           alert("Error loading user balance: " + error.message);
       });
}

// View User Details
function viewUserDetails(userId) {
   console.log("View details called for ID:", userId);
   
   try {
       if (false && typeof bootstrap !== "undefined") {
           const modal = new bootstrap.Modal(document.getElementById("userDetailsModal"));
           modal.show();
       } else {
           // Fallback
           const modal = document.getElementById("userDetailsModal");
           modal.classList.remove("hidden");
           modal.style.display = "flex";
           document.body.classList.add("modal-open");
       }
   } catch (error) {
       console.error("Error showing details modal:", error);
   }
   
   document.getElementById("userDetailsContent").innerHTML = "<div class=\"text-center py-4\"><div class=\"spinner-border\" role=\"status\"></div><div class=\"mt-2\">Loading user details...</div></div>";
   
   // Load user details via AJAX
   fetch(`user-details.php?user_id=${userId}`)
       .then(response => {
           console.log("Details response status:", response.status);
           if (!response.ok) {
               throw new Error(`HTTP error! status: ${response.status}`);
           }
           return response.text();
       })
       .then(html => {
           document.getElementById("userDetailsContent").innerHTML = html;
       })
       .catch(error => {
           console.error("Details fetch error:", error);
           document.getElementById("userDetailsContent").innerHTML = "<div class=\"alert alert-danger\">Failed to load user details: " + error.message + "</div>";
       });
}

// Impersonate User
function impersonateUser(userId) {
   if (confirm("Are you sure you want to login as this user?\\n\\nThis action will be logged for security purposes.")) {
       // redirect to impersonation handler
       window.location.href = `impersonate.php?user_id=${userId}`;
       return;
       // legacy loading state code below
       const button = event ? event.target : null;
       const originalText = button ? button.innerHTML : \'\';
       button.innerHTML = "<span class=\"spinner-border spinner-border-sm me-1\"></span>Logging in...";
       button.disabled = true;
       
       // Redirect to impersonation handler
       window.location.href = `impersonate.php?user_id=${userId}`;
   }
}

// Form handlers
document.addEventListener("DOMContentLoaded", function() {
   console.log("DOM loaded, Bootstrap available:", typeof bootstrap !== "undefined");
   
   // Handle Edit User Form
   const editUserForm = document.getElementById("editUserForm");
   if (editUserForm) {
       editUserForm.addEventListener("submit", function(e) {
           e.preventDefault();
           
           const formData = new FormData(this);
           formData.append("action", "update_user");
           formData.append("csrf_token", document.querySelector(\'input[name="csrf_token"]\').value);
           
           // Show loading state
           const submitBtn = this.querySelector("button[type=\"submit\"]");
           const originalText = submitBtn.textContent;
           submitBtn.disabled = true;
           submitBtn.textContent = "Updating...";
           
           fetch("users.php", {
               method: "POST",
               body: formData
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   location.reload();
               } else {
                   alert("Error: " + data.message);
                   submitBtn.disabled = false;
                   submitBtn.textContent = originalText;
               }
           })
           .catch(error => {
               alert("An error occurred");
               console.error("Error:", error);
               submitBtn.disabled = false;
               submitBtn.textContent = originalText;
           });
       });
   }

   // Handle Balance Form
   const balanceForm = document.getElementById("balanceForm");
   if (balanceForm) {
       balanceForm.addEventListener("submit", function(e) {
           e.preventDefault();
           
           const formData = new FormData(this);
           formData.append("action", "update_balance");
           formData.append("csrf_token", document.querySelector(\'input[name="csrf_token"]\').value);
           
           // Show loading state
           const submitBtn = this.querySelector("button[type=\"submit\"]");
           const originalText = submitBtn.textContent;
           submitBtn.disabled = true;
           submitBtn.textContent = "Updating...";
           
           fetch("users.php", {
               method: "POST",
               body: formData
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   location.reload();
               } else {
                   alert("Error: " + data.message);
                   submitBtn.disabled = false;
                   submitBtn.textContent = originalText;
               }
           })
           .catch(error => {
               alert("An error occurred");
               console.error("Error:", error);
               submitBtn.disabled = false;
               submitBtn.textContent = originalText;
           });
       });
   }

   // Modal close handlers
   document.querySelectorAll("[data-bs-dismiss=\"modal\"]").forEach(button => {
       button.addEventListener("click", function() {
           const modal = this.closest(".modal");
           if (modal) {
               modal.style.display = "none";
               modal.classList.remove("show");
               document.body.classList.remove("modal-open");
               
               // Remove backdrop
               const backdrop = document.querySelector(".modal-backdrop");
               if (backdrop) {
                   backdrop.remove();
               }
           }
       });
   });
});
</script>
';

include __DIR__ . '/includes/footer.php';
?>