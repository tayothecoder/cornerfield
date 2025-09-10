<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

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

<!-- Page header -->
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">User Management</h2>
                <div class="text-secondary">Manage user accounts, balances, and permissions</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create-user">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="m0 0h24v24H0z" fill="none"/>
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add User
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page body -->
<div class="page-body">
    <div class="container-xl">
        
        <!-- Statistics Cards -->
        <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Users</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($stats['total_users']) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-primary" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">All</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Active Users</div>
                        </div>
                        <div class="h1 mb-3 text-success"><?= number_format($stats['active_users']) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-success" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Live</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">New This Month</div>
                        </div>
                        <div class="h1 mb-3 text-primary"><?= number_format($stats['new_users_this_month']) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-primary" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Recent</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Verified Users</div>
                        </div>
                        <div class="h1 mb-3 text-info"><?= number_format($stats['verified_users']) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-info" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Verified</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Users</h3>
                        <div class="card-actions">
                            <form method="GET" class="d-flex gap-2">
                                <input type="text" name="search" class="form-control form-control-sm" 
                                       placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                                    <option value="unverified" <?= $status === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-mobile-md card-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Balance</th>
                                    <th>Investments</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th class="w-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No users found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex py-1 align-items-center">
                                                    <div class="avatar me-3 gradient-bg-1 text-white">
                                                        <?= strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)) ?>
                                                    </div>
                                                    <div class="flex-fill">
                                                        <div class="font-weight-medium"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                                        <div class="text-muted">@<?= htmlspecialchars($user['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($user['email']) ?></div>
                                                <?php if ($user['phone']): ?>
                                                    <div class="text-muted"><?= htmlspecialchars($user['phone']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="amount-display <?= $user['balance'] > 0 ? 'text-success' : 'text-muted' ?>">
                                                    <span class="currency-symbol"><?= htmlspecialchars($currencySymbol) ?></span><?= number_format($user['balance'], 2) ?>
                                                </div>
                                                <?php if ($user['bonus_balance'] > 0): ?>
                                                    <div class="text-muted small">Bonus: <?= htmlspecialchars($currencySymbol) ?><?= number_format($user['bonus_balance'], 2) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= $user['total_investments'] ?> investments</div>
                                                <div class="text-muted small"><?= htmlspecialchars($currencySymbol) ?><?= number_format($user['total_invested_amount'], 2) ?> total</div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                    <?php if ($user['email_verified']): ?>
                                                        <span class="badge bg-success-lt">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-lt">Unverified</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                                <div class="text-muted small"><?= date('g:i A', strtotime($user['created_at'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="btn-list flex-nowrap">
                                                    <button class="btn btn-sm btn-primary" onclick="editUser(<?= $user['id'] ?>)">
                                                        Edit
                                                    </button>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-secondary dropdown-toggle" 
                                                                type="button" data-bs-toggle="dropdown">
                                                            More
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="viewUserDetails(<?= $user['id'] ?>)">View Details</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="manageBalance(<?= $user['id'] ?>)">Manage Balance</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-primary" href="#" onclick="impersonateUser(<?= $user['id'] ?>)">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                                    <circle cx="9" cy="7" r="4"/>
                                                                    <path d="m22 2-5 10-5-5 10-5z"/>
                                                                </svg>
                                                                Login as User
                                                            </a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $user['id'] ?>)">
                                                                <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer d-flex align-items-center">
                            <p class="m-0 text-muted">
                                Showing <?= ($page - 1) * $limit + 1 ?> to <?= min($page * $limit, $totalUsers) ?> of <?= $totalUsers ?> entries
                            </p>
                            <ul class="pagination m-0 ms-auto">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="m0 0h24v24H0z" fill="none"/><polyline points="15,6 9,12 15,18" /></svg>
                                            prev
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">
                                            next
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="m0 0h24v24H0z" fill="none"/><polyline points="9,6 15,12 9,18" /></svg>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal modal-blur fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm">
                <div class="modal-body">
                    <?= \App\Utils\CSRFProtection::getTokenField() ?>
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit_phone">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" id="edit_country">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Password (optional)</label>
                                <input type="password" class="form-control" name="password" id="edit_password">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                                    <span class="form-check-label">Active User</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-check">
                                    <input type="checkbox" class="form-check-input" name="email_verified" id="edit_email_verified">
                                    <span class="form-check-label">Email Verified</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Balance Management Modal -->
<div class="modal modal-blur fade" id="balanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage User Balance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="balanceForm">
                <div class="modal-body">
                    <?= \App\Utils\CSRFProtection::getTokenField() ?>
                    <input type="hidden" id="balance_user_id" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <div class="h3 text-primary" id="current_balance"><?= htmlspecialchars($currencySymbol) ?>0.00</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select class="form-select" name="type" id="balance_type">
                            <option value="add">Add funds</option>
                            <option value="subtract">Subtract funds</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= htmlspecialchars($currencySymbol) ?></span>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Reason for balance adjustment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal modal-blur fade" id="userDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript for this page -->
<?php
$pageSpecificJS = '
<script>
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
                    if (typeof bootstrap !== "undefined") {
                        const modal = new bootstrap.Modal(document.getElementById("editUserModal"));
                        modal.show();
                    } else {
                        // Fallback: show modal manually
                        const modal = document.getElementById("editUserModal");
                        modal.style.display = "block";
                        modal.classList.add("show");
                        document.body.classList.add("modal-open");
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
                   if (typeof bootstrap !== "undefined") {
                       const modal = new bootstrap.Modal(document.getElementById("balanceModal"));
                       modal.show();
                   } else {
                       // Fallback
                       const modal = document.getElementById("balanceModal");
                       modal.style.display = "block";
                       modal.classList.add("show");
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
       if (typeof bootstrap !== "undefined") {
           const modal = new bootstrap.Modal(document.getElementById("userDetailsModal"));
           modal.show();
       } else {
           // Fallback
           const modal = document.getElementById("userDetailsModal");
           modal.style.display = "block";
           modal.classList.add("show");
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
       // Show loading state
       const button = event.target;
       const originalText = button.innerHTML;
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