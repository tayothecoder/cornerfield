<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}


require_once dirname(__DIR__) . '/vendor/autoload.php';

// Page setup
$pageTitle = 'Transaction Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'transactions';

// Initialize session
\App\Utils\SessionManager::start();

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    $transactionModel = new \App\Models\Transaction($database);
    $userModel = new \App\Models\User($database);
    $adminSettingsModel = new \App\Models\AdminSettings($database);
    $systemHealth = new \App\Utils\SystemHealth($database);
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
$success = '';
$error = '';

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Handle transaction actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    \App\Utils\CSRFProtection::validateRequest();
    
    $action = $_POST['action'] ?? '';
    $transactionId = $_POST['transaction_id'] ?? '';

    switch ($action) {
        case 'approve_deposit':
            $amount = floatval($_POST['amount']);
            $userId = $_POST['user_id'];

            try {
                $database->beginTransaction();

                $transaction = $transactionModel->getTransactionById($transactionId);

                if (!$transaction) {
                    throw new Exception("Transaction not found");
                }

                if ($transaction['status'] !== 'pending') {
                    $error = "Deposit has already been processed (status: {$transaction['status']})";
                    $database->rollback();
                    break;
                }

                $deposit = $database->fetchOne("SELECT status FROM deposits WHERE transaction_id = ?", [$transactionId]);
                if ($deposit && $deposit['status'] !== 'pending') {
                    $error = "Deposit has already been processed (deposit status: {$deposit['status']})";
                    $database->rollback();
                    break;
                }

                // Update transaction status
                $transactionModel->updateTransactionStatus($transactionId, 'completed', $currentAdmin['id'], true);

                // Add amount to user balance
                $userModel->addToBalance($userId, $amount);

                if ($deposit) {
                    $database->update('deposits', [
                        'status' => 'completed',
                        'verification_status' => 'verified',
                        'admin_processed_by' => $currentAdmin['id'],
                        'processed_at' => date('Y-m-d H:i:s'),
                        'admin_notes' => 'Approved by admin'
                    ], 'transaction_id = ?', [$transactionId]);
                }

                $database->commit();

                header('Location: transactions.php?success=' . urlencode("Deposit approved successfully. User balance updated."));
                exit;

            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to approve deposit: " . $e->getMessage();
            }
            break;

        case 'approve_withdrawal':
            try {
                $database->beginTransaction();

                $transaction = $transactionModel->getTransactionById($transactionId);

                if (!$transaction) {
                    throw new Exception("Transaction not found");
                }

                if ($transaction['status'] !== 'pending') {
                    $error = "Withdrawal has already been processed (status: {$transaction['status']})";
                    $database->rollback();
                    break;
                }

                $withdrawal = $database->fetchOne("SELECT status FROM withdrawals WHERE transaction_id = ?", [$transactionId]);
                if ($withdrawal && $withdrawal['status'] !== 'pending') {
                    $error = "Withdrawal has already been processed (withdrawal status: {$withdrawal['status']})";
                    $database->rollback();
                    break;
                }

                // Update transaction status
                $transactionModel->updateTransactionStatus($transactionId, 'completed', $currentAdmin['id'], true);

                if ($transaction) {
                    $userModel->addToTotalWithdrawn($transaction['user_id'], $transaction['amount']);
                }

                if ($withdrawal) {
                    $database->update('withdrawals', [
                        'status' => 'completed',
                        'admin_processed_by' => $currentAdmin['id'],
                        'processed_at' => date('Y-m-d H:i:s'),
                        'processing_notes' => 'Approved by admin'
                    ], 'transaction_id = ?', [$transactionId]);
                }

                $database->commit();

                header('Location: transactions.php?success=' . urlencode("Withdrawal approved and processed successfully."));
                exit;

            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to approve withdrawal: " . $e->getMessage();
            }
            break;

        case 'reject_transaction':
            $reason = $_POST['rejection_reason'] ?? 'Transaction rejected by admin';

            try {
                $database->beginTransaction();

                $transaction = $transactionModel->getTransactionById($transactionId);

                if (!$transaction) {
                    throw new Exception("Transaction not found");
                }

                if ($transaction['status'] !== 'pending') {
                    $error = "Transaction has already been processed (status: {$transaction['status']})";
                    $database->rollback();
                    break;
                }

                if ($transaction['type'] === 'withdrawal') {
                    // For withdrawals, also check withdrawal table status
                    $withdrawal = $database->fetchOne("SELECT status FROM withdrawals WHERE transaction_id = ?", [$transactionId]);

                    if ($withdrawal && $withdrawal['status'] !== 'pending') {
                        $error = "Withdrawal has already been processed (status: {$withdrawal['status']})";
                        $database->rollback();
                        break;
                    }

                    // Refund amount + fee to user balance
                    $refundAmount = $transaction['amount'] + $transaction['fee'];
                    $userModel->addToBalance($transaction['user_id'], $refundAmount);

                    // Update withdrawal status if exists
                    if ($withdrawal) {
                        $database->update('withdrawals', [
                            'status' => 'failed',
                            'admin_processed_by' => $currentAdmin['id'],
                            'processed_at' => date('Y-m-d H:i:s'),
                            'rejection_reason' => $reason,
                            'processing_notes' => 'Rejected: ' . $reason
                        ], 'transaction_id = ?', [$transactionId]);
                    }
                }

                // Update transaction status
                $transactionModel->updateTransactionStatus($transactionId, 'failed', $currentAdmin['id'], true);
                $transactionModel->addAdminNote($transactionId, $reason, $currentAdmin['id']);

                $database->commit();

                $successMsg = "Transaction rejected successfully." . ($transaction['type'] === 'withdrawal' ? " Amount refunded to user." : "");
                header('Location: transactions.php?success=' . urlencode($successMsg));
                exit;

            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to reject transaction: " . $e->getMessage();
            }
            break;

        case 'manual_deposit':
            $userId = $_POST['user_id'];
            $amount = floatval($_POST['amount']);
            $description = $_POST['description'] ?? 'Manual deposit by admin';

            try {
                $database->beginTransaction();

                // Create transaction record
                $transactionId = $transactionModel->createDepositTransaction($userId, $amount, [
                    'payment_method' => 'manual',
                    'status' => 'completed',
                    'gateway_transaction_id' => 'MANUAL_' . time(),
                    'description' => $description
                ]);

                // Add to user balance
                $userModel->addToBalance($userId, $amount);

                $database->commit();
                $success = "Manual deposit of " . $adminSettingsModel->getSetting('currency_symbol', '$') . number_format($amount, 2) . " added successfully.";

            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to add manual deposit: " . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;

// Build filters array
$filters = [];
if ($type)
    $filters['type'] = $type;
if ($status)
    $filters['status'] = $status;

// Apply quick filters
switch ($filter) {
    case 'pending':
        $filters['status'] = 'pending';
        break;
    case 'deposits':
        $filters['type'] = 'deposit';
        break;
    case 'withdrawals':
        $filters['type'] = 'withdrawal';
        break;
    case 'today':
        $filters['date_from'] = date('Y-m-d');
        $filters['date_to'] = date('Y-m-d');
        break;
}

// Get transactions
$transactions = $transactionModel->getAllTransactions($page, $limit, $filters);

// Get transaction statistics
$stats = $transactionModel->getTransactionStatistics();

// Get users for manual deposit dropdown
$users = $userModel->getAllUsers(1, 100);

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Page header -->
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Transaction Management</h2>
                <div class="text-secondary">
                    Manage deposits, withdrawals, and other financial transactions
                </div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#modal-manual-deposit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Manual Deposit
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M5 12l5 5l10 -10" />
                        </svg>
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <circle cx="12" cy="12" r="9" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                    </div>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row row-deck row-cards">
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Pending Deposits</div>
                            <?php if ($pendingDeposits > 0): ?>
                                <div class="ms-auto">
                                    <span class="badge bg-warning"><?= $pendingDeposits ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="h1 mb-3"><?= $pendingDeposits ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-warning" style="width: 100%"
                                        role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Waiting</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Pending Withdrawals</div>
                            <?php if ($pendingWithdrawals > 0): ?>
                                <div class="ms-auto">
                                    <span class="badge bg-danger"><?= $pendingWithdrawals ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="h1 mb-3"><?= $pendingWithdrawals ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-danger" style="width: 100%"
                                        role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">Waiting</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Deposited</div>
                        </div>
                        <div class="h1 mb-3">
                            <?= $currencySymbol ?><?= number_format($stats['total_deposited'], 2) ?>
                        </div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-success" style="width: 100%"
                                        role="progressbar"></div>
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
                            <div class="subheader">Total Withdrawn</div>
                        </div>
                        <div class="h1 mb-3">
                            <?= $currencySymbol ?><?= number_format($stats['total_withdrawn'], 2) ?>
                        </div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="progress progress-sm flex-fill">
                                    <div class="progress-bar bg-info" style="width: 100%"
                                        role="progressbar"></div>
                                </div>
                                <div class="text-secondary ms-2">All time</div>
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
                        <h3 class="card-title">Filter Transactions</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="btn-list">
                                    <a href="?filter=all"
                                        class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                                        All Transactions
                                    </a>
                                    <a href="?filter=pending"
                                        class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">
                                        Pending
                                        <?php if ($pendingDeposits + $pendingWithdrawals > 0): ?>
                                            <span
                                                class="badge bg-red ms-2"><?= $pendingDeposits + $pendingWithdrawals ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?filter=deposits"
                                        class="btn btn-outline-success <?= $filter === 'deposits' ? 'active' : '' ?>">
                                        Deposits
                                    </a>
                                    <a href="?filter=withdrawals"
                                        class="btn btn-outline-danger <?= $filter === 'withdrawals' ? 'active' : '' ?>">
                                        Withdrawals
                                    </a>
                                    <a href="?filter=today"
                                        class="btn btn-outline-info <?= $filter === 'today' ? 'active' : '' ?>">
                                        Today
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="row row-cards mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Transactions</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <div class="text-secondary">#<?= $transaction['id'] ?></div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span
                                                            class="avatar avatar-sm me-2"><?= strtoupper(substr($transaction['username'], 0, 2)) ?></span>
                                                        <div>
                                                            <div class="fw-semibold">
                                                                <?= htmlspecialchars($transaction['username']) ?>
                                                            </div>
                                                            <div class="text-secondary">
                                                                <?= htmlspecialchars($transaction['email']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= $transaction['type'] === 'deposit' ? 'success' : ($transaction['type'] === 'withdrawal' ? 'danger' : 'info') ?>">
                                                        <?= ucfirst($transaction['type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-semibold amount-display">
                                                            <span class="currency-symbol"><?= $currencySymbol ?></span>
                                                            <?= number_format($transaction['amount'], 2) ?>
                                                        </div>
                                                        <?php if ($transaction['fee'] > 0): ?>
                                                            <div class="text-secondary">Fee:
                                                                <?= $currencySymbol ?>
                                                                <?= number_format($transaction['fee'], 2) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?=
                                                        $transaction['status'] === 'completed' ? 'success' :
                                                        ($transaction['status'] === 'pending' ? 'warning' :
                                                            ($transaction['status'] === 'failed' ? 'danger' : 'secondary'))
                                                        ?>">
                                                        <?= ucfirst($transaction['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="text-secondary"><?= ucfirst($transaction['payment_method']) ?></span>
                                                    <?php if ($transaction['wallet_address']): ?>
                                                        <div class="text-secondary small td-truncate">
                                                            <?= substr($transaction['wallet_address'], 0, 15) ?>...
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?= date('M j, Y', strtotime($transaction['created_at'])) ?>
                                                   </div>
                                                   <div class="text-secondary">
                                                       <?= date('H:i', strtotime($transaction['created_at'])) ?>
                                                   </div>
                                               </td>
                                               <td>
                                                   <?php if ($transaction['status'] === 'pending'): ?>
                                                       <div class="btn-list flex-nowrap">
                                                           <?php if ($transaction['type'] === 'deposit'): ?>
                                                               <button class="btn btn-sm btn-success"
                                                                   onclick="approveDeposit(<?= $transaction['id'] ?>, <?= $transaction['user_id'] ?>, <?= $transaction['amount'] ?>)">
                                                                   Approve
                                                               </button>
                                                           <?php elseif ($transaction['type'] === 'withdrawal'): ?>
                                                               <button class="btn btn-sm btn-success"
                                                                   onclick="approveWithdrawal(<?= $transaction['id'] ?>)">
                                                                   Approve
                                                               </button>
                                                           <?php endif; ?>
                                                           <button class="btn btn-sm btn-danger"
                                                               onclick="rejectTransaction(<?= $transaction['id'] ?>)">
                                                               Reject
                                                           </button>
                                                       </div>
                                                   <?php else: ?>
                                                       <span class="text-secondary">—</span>
                                                   <?php endif; ?>
                                               </td>
                                           </tr>
                                       <?php endforeach; ?>
                                   <?php else: ?>
                                       <tr>
                                           <td colspan="8" class="text-center text-muted py-4">
                                               No transactions found
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

<!-- Manual Deposit Modal -->
<div class="modal modal-blur fade" id="modal-manual-deposit" tabindex="-1" role="dialog" aria-hidden="true">
   <div class="modal-dialog modal-dialog-centered" role="document">
       <div class="modal-content">
           <div class="modal-header">
               <h5 class="modal-title">Manual Deposit</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
           </div>
           <form method="POST">
               <div class="modal-body">
                   <input type="hidden" name="action" value="manual_deposit">

                   <div class="mb-3">
                       <label class="form-label">Select User</label>
                       <select name="user_id" class="form-select" required>
                           <option value="">Choose user...</option>
                           <?php foreach ($users as $user): ?>
                               <option value="<?= $user['id'] ?>">
                                   <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                               </option>
                           <?php endforeach; ?>
                       </select>
                   </div>

                   <div class="mb-3">
                       <label class="form-label">Deposit Amount</label>
                       <div class="input-group">
                           <span class="input-group-text"><?= $currencySymbol ?></span>
                           <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
                       </div>
                   </div>

                   <div class="mb-3">
                       <label class="form-label">Description (Optional)</label>
                       <input type="text" name="description" class="form-control"
                           placeholder="Manual deposit by admin">
                   </div>
               </div>
               <div class="modal-footer">
                   <a href="#" class="btn me-auto" data-bs-dismiss="modal">Cancel</a>
                   <button type="submit" class="btn btn-primary">Add Deposit</button>
               </div>
           </form>
       </div>
   </div>
</div>

<?php
$pageSpecificJS = '
<script>
function approveDeposit(transactionId, userId, amount) {
   if (confirm("Approve this deposit for ' . $currencySymbol . '" + amount.toFixed(2) + "?\\n\\nThis will add the amount to the user\'s balance.")) {
       var form = document.createElement("form");
       form.method = "POST";
       form.innerHTML = "<input type=\"hidden\" name=\"action\" value=\"approve_deposit\">" +
           "<input type=\"hidden\" name=\"transaction_id\" value=\"" + transactionId + "\">" +
           "<input type=\"hidden\" name=\"user_id\" value=\"" + userId + "\">" +
           "<input type=\"hidden\" name=\"amount\" value=\"" + amount + "\">";
       document.body.appendChild(form);
       form.submit();
   }
}

function approveWithdrawal(transactionId) {
   if (confirm("Approve this withdrawal?\\n\\nThis will mark it as completed. Make sure you have processed the payment externally.")) {
       var form = document.createElement("form");
       form.method = "POST";
       form.innerHTML = "<input type=\"hidden\" name=\"action\" value=\"approve_withdrawal\">" +
           "<input type=\"hidden\" name=\"transaction_id\" value=\"" + transactionId + "\">";
       document.body.appendChild(form);
       form.submit();
   }
}

function rejectTransaction(transactionId) {
   var reason = prompt("Enter rejection reason (optional):");
   if (reason !== null) {
       var form = document.createElement("form");
       form.method = "POST";
       form.innerHTML = "<input type=\"hidden\" name=\"action\" value=\"reject_transaction\">" +
           "<input type=\"hidden\" name=\"transaction_id\" value=\"" + transactionId + "\">" +
           "<input type=\"hidden\" name=\"rejection_reason\" value=\"" + reason + "\">";
       document.body.appendChild(form);
       form.submit();
   }
}
</script>
';

include __DIR__ . '/includes/footer.php';
?>