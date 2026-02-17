<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}


require_once dirname(__DIR__) . '/vendor/autoload.php';

// Page setup
$pageTitle = 'Profits Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'profits';

// Initialize session
\App\Utils\SessionManager::start();

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    $transactionModel = new \App\Models\Transaction($database);
    $userModel = new \App\Models\User($database);
    
    // Get admin settings
    $adminSettingsModel = new \App\Models\AdminSettings($database);
    $currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$success = '';
$error = '';

// Get current admin data
$currentAdmin = $adminController->getCurrentAdmin();
if (!$currentAdmin) {
    header('Location: login.php');
    exit;
}

// Include header
include __DIR__ . '/includes/header.php';

// Handle profit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    \App\Utils\CSRFProtection::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'manual_profit':
            $userId = (int)($_POST['user_id'] ?? 0);
            $investmentId = !empty($_POST['investment_id']) ? (int)$_POST['investment_id'] : null;
            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? 'Manual profit by admin');
            $profitType = trim($_POST['profit_type'] ?? 'manual');
            
            // Validate inputs
            if ($userId <= 0 || $amount <= 0) {
                $error = "Invalid input parameters provided.";
                break;
            }
            
            // Sanitize inputs
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            $profitType = htmlspecialchars($profitType, ENT_QUOTES, 'UTF-8');
            
            try {
                $database->beginTransaction();
                
                // Create transaction record
                $transactionData = [
                    'user_id' => $userId,
                    'type' => 'profit',
                    'amount' => $amount,
                    'fee' => 0,
                    'net_amount' => $amount,
                    'status' => 'completed',
                    'payment_method' => 'system',
                    'description' => $description,
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_by_type' => 'admin',
                    'processed_at' => date('Y-m-d H:i:s')
                ];
                
                $transactionId = $database->insert('transactions', $transactionData);
                
                // Create profit record if we have detailed tracking
                if ($investmentId) {
                    $investment = $database->fetchOne("SELECT i.id, i.schema_id, i.invest_amount, s.daily_rate FROM investments i JOIN investment_schemas s ON i.schema_id = s.id WHERE i.id = ?", [$investmentId]);
                    if ($investment) {
                        $profitData = [
                            'transaction_id' => $transactionId,
                            'user_id' => $userId,
                            'investment_id' => $investmentId,
                            'schema_id' => $investment['schema_id'],
                            'profit_amount' => $amount,
                            'profit_rate' => $investment['daily_rate'],
                            'investment_amount' => $investment['invest_amount'],
                            'profit_day' => 0, // Manual profit
                            'profit_type' => $profitType,
                            'calculation_date' => date('Y-m-d'),
                            'distribution_method' => 'manual',
                            'status' => 'distributed',
                            'admin_processed_by' => $currentAdmin['id'],
                            'processed_at' => date('Y-m-d H:i:s'),
                            'processing_notes' => 'Manual profit added by admin: ' . $currentAdmin['username']
                        ];
                        
                        $database->insert('profits', $profitData);
                    }
                }
                
                // Add to user balance
                $userModel->addToBalance($userId, $amount);
                
                // Update user's total earned
                $userModel->addToTotalEarned($userId, $amount);
                
                $database->commit();
                $success = "Manual profit of " . $currencySymbol . number_format($amount, 2) . " added successfully.";
                
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to add manual profit: " . $e->getMessage();
            }
            break;
            
        case 'distribute_signup_bonus':
            $userId = (int)($_POST['user_id'] ?? 0);
            $bonusAmount = (float)($_POST['bonus_amount'] ?? 0);
            
            // Validate inputs
            if ($userId <= 0 || $bonusAmount <= 0) {
                $error = "Invalid input parameters provided.";
                break;
            }
            
            try {
                $database->beginTransaction();
                
                // Check if user already received signup bonus
                $existingBonus = $database->fetchOne("SELECT id FROM transactions WHERE user_id = ? AND type = 'bonus' AND description LIKE '%signup%'", [$userId]);
                if ($existingBonus) {
                    throw new Exception("User has already received signup bonus");
                }
                
                // Create transaction record
                $transactionData = [
                    'user_id' => $userId,
                    'type' => 'bonus',
                    'amount' => $bonusAmount,
                    'fee' => 0,
                    'net_amount' => $bonusAmount,
                    'status' => 'completed',
                    'payment_method' => 'system',
                    'description' => 'Signup bonus',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_by_type' => 'admin',
                    'processed_at' => date('Y-m-d H:i:s')
                ];
                
                $transactionId = $database->insert('transactions', $transactionData);
                
                // Add to user bonus balance safely
                $currentBonusBalance = $database->fetchOne("SELECT bonus_balance FROM users WHERE id = ?", [$userId])['bonus_balance'] ?? 0;
                $newBonusBalance = $currentBonusBalance + $bonusAmount;
                
                $database->update('users', [
                    'bonus_balance' => $newBonusBalance
                ], 'id = ?', [$userId]);
                
                $database->commit();
                $success = "Signup bonus of " . $currencySymbol . number_format($bonusAmount, 2) . " distributed successfully.";
                
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to distribute signup bonus: " . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$profit_type = $_GET['profit_type'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;

// Build WHERE conditions
$whereConditions = [];
$params = [];

// Apply quick filters
switch ($filter) {
    case 'daily':
        $whereConditions[] = "p.profit_type = 'daily'";
        break;
    case 'bonus':
        $whereConditions[] = "p.profit_type = 'bonus'";
        break;
    case 'manual':
        $whereConditions[] = "p.profit_type = 'manual'";
        break;
    case 'completion':
        $whereConditions[] = "p.profit_type = 'completion'";
        break;
    case 'today':
        $whereConditions[] = "DATE(p.created_at) = CURDATE()";
        break;
    case 'this_week':
        $whereConditions[] = "WEEK(p.created_at) = WEEK(NOW())";
        break;
}

// Additional filters
if ($profit_type) {
    $whereConditions[] = "p.profit_type = ?";
    $params[] = $profit_type;
}

if ($user_id) {
    $whereConditions[] = "p.user_id = ?";
    $params[] = $user_id;
}

// Get profits with user and investment info
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
$offset = ($page - 1) * $limit;

$profitsQuery = "
    SELECT p.*, 
           u.username, u.email, u.first_name, u.last_name,
           i.invest_amount, s.name as plan_name,
           t.description as transaction_description,
           a.username as admin_username
    FROM profits p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN investments i ON p.investment_id = i.id
    LEFT JOIN investment_schemas s ON p.schema_id = s.id
    JOIN transactions t ON p.transaction_id = t.id
    LEFT JOIN admins a ON p.admin_processed_by = a.id
    " . (!empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '') . "
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$queryParams = array_merge($params, [$limit, $offset]);
$profits = $database->fetchAll($profitsQuery, $queryParams);

// Get profit statistics
$stats = $database->fetchOne("
    SELECT 
        COUNT(*) as total_profits,
        SUM(profit_amount) as total_profit_amount,
        SUM(CASE WHEN profit_type = 'daily' THEN profit_amount ELSE 0 END) as daily_profits_total,
        SUM(CASE WHEN profit_type = 'bonus' THEN profit_amount ELSE 0 END) as bonus_profits_total,
        SUM(CASE WHEN profit_type = 'manual' THEN profit_amount ELSE 0 END) as manual_profits_total,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_profits,
        COUNT(DISTINCT user_id) as users_with_profits
    FROM profits
");

// Get users for manual profit dropdown
$users = $userModel->getAllUsers(1, 100);

// Get active investments for manual profit
$activeInvestments = $database->fetchAll("
    SELECT i.id, i.user_id, i.invest_amount, u.username, s.name as plan_name
    FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN investment_schemas s ON i.schema_id = s.id
    WHERE i.status = 'active'
    ORDER BY u.username, s.name
");

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Page header -->
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Profits Management</h2>
                <div class="text-secondary">Monitor profit distributions and manage user earnings</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-manual-profit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="m0 0h24v24H0z" fill="none"/>
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Manual Profit
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-signup-bonus">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="m0 0h24v24H0z" fill="none"/>
                            <path d="M7 10v4h3v7a1 1 0 0 0 1 1h3a1 1 0 0 0 1 -1v-7h3v-4l-1 -1h-9z"/>
                            <circle cx="12" cy="6" r="2"/>
                        </svg>
                        Signup Bonus
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page body -->
<div class="page-body">
    <div class="container-xl">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
                <div class="d-flex">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="m0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>
                    </div>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible" role="alert">
                <div class="d-flex">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="m0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Profits</div>
                        </div>
                        <div class="h1 mb-3 text-success"><?= $currencySymbol ?><?= number_format($stats['total_profit_amount'], 2) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-success" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">All time</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Daily Profits</div>
                        </div>
                        <div class="h1 mb-3 text-primary"><?= $currencySymbol ?><?= number_format($stats['daily_profits_total'], 2) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-primary" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Investment</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Bonuses</div>
                        </div>
                        <div class="h1 mb-3 text-info"><?= $currencySymbol ?><?= number_format($stats['bonus_profits_total'], 2) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-info" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Signup & Referral</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Users Earning</div>
                        </div>
                        <div class="h1 mb-3 text-warning"><?= $stats['users_with_profits'] ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-warning" style="width: 100%" role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Active</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Filter Profits</h3>
                    </div>
                    <div class="card-body">
                        <div class="btn-list">
                            <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                                All Profits
                            </a>
                            <a href="?filter=daily" class="btn btn-outline-success <?= $filter === 'daily' ? 'active' : '' ?>">
                                Daily Profits
                            </a>
                            <a href="?filter=bonus" class="btn btn-outline-info <?= $filter === 'bonus' ? 'active' : '' ?>">
                                Bonuses
                            </a>
                            <a href="?filter=manual" class="btn btn-outline-warning <?= $filter === 'manual' ? 'active' : '' ?>">
                                Manual
                            </a>
                            <a href="?filter=completion" class="btn btn-outline-purple <?= $filter === 'completion' ? 'active' : '' ?>">
                                Completion
                            </a>
                            <a href="?filter=today" class="btn btn-outline-secondary <?= $filter === 'today' ? 'active' : '' ?>">
                                Today
                            </a>
                            <a href="?filter=this_week" class="btn btn-outline-secondary <?= $filter === 'this_week' ? 'active' : '' ?>">
                                This Week
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profits Table -->
        <div class="row row-cards mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Profit Distributions</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Investment</th>
                                        <th>Profit Details</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($profits)): ?>
                                        <?php foreach ($profits as $profit): ?>
                                            <tr class="activity-item">
                                                <td>
                                                    <div class="text-secondary">#<?= $profit['id'] ?></div>
                                                    <div class="text-muted small">TX: #<?= $profit['transaction_id'] ?></div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm me-2 gradient-bg-2 text-white"><?= strtoupper(substr($profit['username'], 0, 2)) ?></span>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($profit['username']) ?></div>
                                                            <div class="text-secondary td-truncate"><?= htmlspecialchars($profit['email']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($profit['plan_name']): ?>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($profit['plan_name']) ?></div>
                                                            <div class="text-secondary">Investment: <?= $currencySymbol ?><?= number_format($profit['invest_amount'], 2) ?></div>
                                                            <?php if ($profit['profit_day'] > 0): ?>
                                                                <div class="text-muted small">Day <?= $profit['profit_day'] ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-semibold text-success amount-display">
                                                            <span class="currency-symbol"><?= $currencySymbol ?></span><?= number_format($profit['profit_amount'], 2) ?>
                                                        </div>
                                                        <?php if ($profit['profit_rate'] > 0): ?>
                                                            <div class="text-secondary"><?= $profit['profit_rate'] ?>% rate</div>
                                                        <?php endif; ?>
                                                        <div class="text-muted small"><?= ucfirst($profit['distribution_method']) ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?=
                                                        $profit['profit_type'] === 'daily' ? 'success' :
                                                        ($profit['profit_type'] === 'bonus' ? 'info' :
                                                            ($profit['profit_type'] === 'manual' ? 'warning' :
                                                                ($profit['profit_type'] === 'completion' ? 'purple' : 'secondary')))
                                                        ?>">
                                                        <?= ucfirst($profit['profit_type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div><?= date('M j, Y', strtotime($profit['created_at'])) ?></div>
                                                    <div class="text-secondary"><?= date('H:i', strtotime($profit['created_at'])) ?></div>
                                                    <?php if ($profit['processed_at']): ?>
                                                        <div class="text-muted small">Processed: <?= date('M j, H:i', strtotime($profit['processed_at'])) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?=
                                                        $profit['status'] === 'distributed' ? 'success' :
                                                        ($profit['status'] === 'pending' ? 'warning' :
                                                           ($profit['status'] === 'failed' ? 'danger' : 'secondary'))
                                                       ?>">
                                                       <?= ucfirst($profit['status']) ?>
                                                   </span>
                                                   <?php if ($profit['admin_username']): ?>
                                                       <div class="text-muted small">By: <?= htmlspecialchars($profit['admin_username']) ?></div>
                                                   <?php endif; ?>
                                               </td>
                                           </tr>
                                       <?php endforeach; ?>
                                   <?php else: ?>
                                       <tr>
                                           <td colspan="7" class="text-center text-muted py-4">
                                               No profits found
                                           </td>
                                       </tr>
                                   <?php endif; ?>
                               </tbody>
                           </table>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>
</div>

<!-- Manual Profit Modal -->
<div class="modal modal-blur fade" id="modal-manual-profit" tabindex="-1" role="dialog" aria-hidden="true">
   <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
       <div class="modal-content">
           <div class="modal-header">
               <h5 class="modal-title">Add Manual Profit</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
           </div>
           <form method="POST">
               <div class="modal-body">
                   <input type="hidden" name="action" value="manual_profit">

                   <div class="row">
                       <div class="col-md-6">
                           <div class="mb-3">
                               <label class="form-label">Select User</label>
                               <select name="user_id" id="manual_user_id" class="form-select" required onchange="loadUserInvestments()">
                                   <option value="">Choose user...</option>
                                   <?php foreach ($users as $user): ?>
                                       <option value="<?= $user['id'] ?>">
                                           <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                       </option>
                                   <?php endforeach; ?>
                               </select>
                           </div>
                       </div>
                       <div class="col-md-6">
                           <div class="mb-3">
                               <label class="form-label">Related Investment (Optional)</label>
                               <select name="investment_id" id="user_investments" class="form-select">
                                   <option value="">Not related to specific investment</option>
                               </select>
                           </div>
                       </div>
                   </div>

                   <div class="row">
                       <div class="col-md-6">
                           <div class="mb-3">
                               <label class="form-label">Profit Amount</label>
                               <div class="input-group">
                                   <span class="input-group-text"><?= $currencySymbol ?></span>
                                   <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                               </div>
                           </div>
                       </div>
                       <div class="col-md-6">
                           <div class="mb-3">
                               <label class="form-label">Profit Type</label>
                               <select name="profit_type" class="form-select">
                                   <option value="manual">Manual Profit</option>
                                   <option value="bonus">Bonus Profit</option>
                                   <option value="completion">Completion Bonus</option>
                               </select>
                           </div>
                       </div>
                   </div>

                   <div class="mb-3">
                       <label class="form-label">Description</label>
                       <textarea name="description" class="form-control" rows="3" placeholder="Reason for manual profit distribution..."></textarea>
                   </div>
               </div>
               <div class="modal-footer">
                   <a href="#" class="btn me-auto" data-bs-dismiss="modal">Cancel</a>
                   <button type="submit" class="btn btn-primary">Add Profit</button>
               </div>
           </form>
       </div>
   </div>
</div>

<!-- Signup Bonus Modal -->
<div class="modal modal-blur fade" id="modal-signup-bonus" tabindex="-1" role="dialog" aria-hidden="true">
   <div class="modal-dialog modal-dialog-centered" role="document">
       <div class="modal-content">
           <div class="modal-header">
               <h5 class="modal-title">Distribute Signup Bonus</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
           </div>
           <form method="POST">
               <div class="modal-body">
                   <input type="hidden" name="action" value="distribute_signup_bonus">

                   <div class="alert alert-info">
                       <div class="d-flex">
                           <div>
                               <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="m0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                           </div>
                           <div>
                               <h4 class="alert-title">Signup Bonus</h4>
                               <div class="text-muted">This will add the bonus to the user\'s bonus balance. Users can only receive this once.</div>
                           </div>
                       </div>
                   </div>

                   <div class="mb-3">
                       <label class="form-label">Select User</label>
                       <select name="user_id" class="form-select" required>
                           <option value="">Choose user...</option>
                           <?php foreach ($users as $user): ?>
                               <option value="<?= $user['id'] ?>">
                                   <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>) - Balance: <?= $currencySymbol ?><?= number_format($user['balance'], 2) ?>
                               </option>
                           <?php endforeach; ?>
                       </select>
                   </div>

                   <div class="mb-3">
                       <label class="form-label">Bonus Amount</label>
                       <div class="input-group">
                           <span class="input-group-text"><?= $currencySymbol ?></span>
                           <input type="number" name="bonus_amount" class="form-control" step="0.01" min="1" value="25" required>
                       </div>
                       <small class="form-hint">Default signup bonus is <?= $currencySymbol ?>25</small>
                   </div>
               </div>
               <div class="modal-footer">
                   <a href="#" class="btn me-auto" data-bs-dismiss="modal">Cancel</a>
                   <button type="submit" class="btn btn-success">Distribute Bonus</button>
               </div>
           </form>
       </div>
   </div>
</div>

<!-- Custom JavaScript for this page -->
<?php
$pageSpecificJS = '
<script>
// Load user investments when user is selected
function loadUserInvestments() {
   const userId = document.getElementById("manual_user_id").value;
   const investmentSelect = document.getElementById("user_investments");
   
   // Clear existing options
   investmentSelect.innerHTML = "<option value=\"\">Not related to specific investment</option>";
   
   if (userId) {
       // Filter investments for the selected user
       const userInvestments = ' . json_encode($activeInvestments) . '.filter(inv => inv.user_id == userId);
       
       userInvestments.forEach(investment => {
           const option = document.createElement("option");
           option.value = investment.id;
           option.textContent = `${investment.plan_name} - ' . $currencySymbol . '${parseFloat(investment.invest_amount).toLocaleString()}`;
           investmentSelect.appendChild(option);
       });
   }
}
</script>
';

include __DIR__ . '/includes/footer.php';
?>