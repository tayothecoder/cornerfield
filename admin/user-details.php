<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize session
\App\Utils\SessionManager::start();

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    
    // Check if admin is logged in
    if (!$adminController->isLoggedIn()) {
        http_response_code(403);
        die('Unauthorized');
    }
    
    $userManagement = new \App\Models\UserManagement($database);
    
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        die('Invalid user ID');
    }
    
    // Get user details
    $user = $userManagement->getUserById($userId);
    if (!$user) {
        die('User not found');
    }
    
    // Get user statistics
    $userStats = [
        'total_investments' => $user['total_investments'] ?? 0,
        'total_invested_amount' => $user['total_invested_amount'] ?? 0,
        'active_investments' => $user['active_investments'] ?? 0,
        'total_transactions' => $user['total_transactions'] ?? 0,
        'total_profits_earned' => $user['total_profits_earned'] ?? 0,
        'total_referrals' => $user['total_referrals'] ?? 0
    ];
    
    // Get recent transactions
    $recentTransactions = $userManagement->getUserTransactions($userId, 5);
    
    // Get user investments
    $investments = $userManagement->getUserInvestments($userId);
    
} catch (Exception $e) {
    die('Error loading user details: ' . $e->getMessage());
}
?>

<!-- User Details Content -->
<h5 class="mb-4">User Details: <?= htmlspecialchars($user['username']) ?></h5>
    <!-- User Basic Info -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Basic Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Username:</strong></td><td><?= htmlspecialchars($user['username']) ?></td></tr>
                <tr><td><strong>Email:</strong></td><td><?= htmlspecialchars($user['email']) ?></td></tr>
                <tr><td><strong>Full Name:</strong></td><td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td></tr>
                <tr><td><strong>Phone:</strong></td><td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td></tr>
                <tr><td><strong>Country:</strong></td><td><?= htmlspecialchars($user['country'] ?? 'N/A') ?></td></tr>
                <tr><td><strong>Status:</strong></td><td>
                    <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td></tr>
                <tr><td><strong>Email Verified:</strong></td><td>
                    <span class="badge bg-<?= $user['email_verified'] ? 'success' : 'warning' ?>">
                        <?= $user['email_verified'] ? 'Yes' : 'No' ?>
                    </span>
                </td></tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Financial Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Balance:</strong></td><td>$<?= number_format($user['balance'], 2) ?></td></tr>
                <tr><td><strong>Locked Balance:</strong></td><td>$<?= number_format($user['locked_balance'], 2) ?></td></tr>
                <tr><td><strong>Bonus Balance:</strong></td><td>$<?= number_format($user['bonus_balance'], 2) ?></td></tr>
                <tr><td><strong>Total Invested:</strong></td><td>$<?= number_format($user['total_invested'], 2) ?></td></tr>
                <tr><td><strong>Total Withdrawn:</strong></td><td>$<?= number_format($user['total_withdrawn'], 2) ?></td></tr>
                <tr><td><strong>Total Earned:</strong></td><td>$<?= number_format($user['total_earned'], 2) ?></td></tr>
            </table>
        </div>
    </div>
    
    <!-- User Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="text-muted mb-3">Investment Statistics</h6>
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body p-2">
                            <div class="h4 mb-0"><?= $userStats['total_investments'] ?></div>
                            <small class="text-muted">Total Investments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body p-2">
                            <div class="h4 mb-0">$<?= number_format($userStats['total_invested_amount'], 2) ?></div>
                            <small class="text-muted">Total Invested</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body p-2">
                            <div class="h4 mb-0"><?= $userStats['active_investments'] ?></div>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body p-2">
                            <div class="h4 mb-0"><?= $userStats['total_transactions'] ?></div>
                            <small class="text-muted">Transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body p-2">
                            <div class="h4 mb-0">$<?= number_format($userStats['total_profits_earned'], 2) ?></div>
                            <small class="text-muted">Profits Earned</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body p-2">
                            <div class="h4 mb-0"><?= $userStats['total_referrals'] ?></div>
                            <small class="text-muted">Referrals</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <?php if (!empty($recentTransactions)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="text-muted mb-3">Recent Transactions</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?= $transaction['type'] === 'deposit' ? 'success' : ($transaction['type'] === 'withdrawal' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($transaction['type']) ?>
                                </span>
                            </td>
                            <td>$<?= number_format($transaction['amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($transaction['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($transaction['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- User Investments -->
    <?php if (!empty($investments)): ?>
    <div class="row">
        <div class="col-12">
            <h6 class="text-muted mb-3">Current Investments</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Daily Rate</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $investment): ?>
                        <tr>
                            <td><?= htmlspecialchars($investment['plan_name']) ?></td>
                            <td>$<?= number_format($investment['invest_amount'], 2) ?></td>
                            <td><?= $investment['daily_rate'] ?>%</td>
                            <td>
                                <span class="badge bg-<?= $investment['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($investment['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($investment['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

<!-- Action Buttons -->
<div class="row mt-4">
    <div class="col-12 text-center">
        <button type="button" class="btn btn-primary me-2" onclick="editUser(<?= $user['id'] ?>)">Edit User</button>
        <button type="button" class="btn btn-info" onclick="impersonateUser(<?= $user['id'] ?>)">Impersonate</button>
    </div>
</div>