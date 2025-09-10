<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize session
\App\Utils\SessionManager::start();

// Page setup
$pageTitle = 'Withdrawals Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'withdrawals';

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    $transactionModel = new \App\Models\Transaction($database);
    $userModel = new \App\Models\User($database);
    $adminSettingsModel = new \App\Models\AdminSettings($database);
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

// Auto-approve withdrawals if enabled
$autoApproveWithdrawals = $adminSettingsModel->getSetting('withdrawal_auto_approval', 0);
$autoApprovalEnabled = $autoApproveWithdrawals;
if ($autoApproveWithdrawals) {
    $pendingWithdrawals = $database->fetchAll("
        SELECT w.*, t.id as transaction_id 
        FROM withdrawals w 
        JOIN transactions t ON w.transaction_id = t.id 
        WHERE w.status = 'pending'
    ");

    foreach ($pendingWithdrawals as $withdrawal) {
        try {
            $database->beginTransaction();

            $transactionModel->updateTransactionStatus($withdrawal['transaction_id'], 'completed', $currentAdmin['id'], true);

            $database->update('withdrawals', [
                'status' => 'completed',
                'admin_processed_by' => $currentAdmin['id'],
                'processed_at' => date('Y-m-d H:i:s'),
                'processing_notes' => 'Auto-approved by system'
            ], 'id = ?', [$withdrawal['id']]);

            // Update user's total withdrawn
            $transaction = $transactionModel->getTransactionById($withdrawal['transaction_id']);
            if ($transaction) {
                $userModel->addToTotalWithdrawn($transaction['user_id'], $transaction['amount']);
            }

            $database->commit();
        } catch (Exception $e) {
            $database->rollback();
            error_log('Auto-approval failed for withdrawal ' . $withdrawal['id'] . ': ' . $e->getMessage());
        }
    }

    if (!empty($pendingWithdrawals)) {
        $success = count($pendingWithdrawals) . " withdrawals auto-approved successfully.";
    }
}

// Handle withdrawal actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'approve_withdrawal':
            $withdrawalId = $_POST['withdrawal_id'];
            $transactionId = $_POST['transaction_id'];
            $withdrawalHash = $_POST['withdrawal_hash'] ?? '';
            $processingNotes = $_POST['processing_notes'] ?? '';

            try {
                $database->beginTransaction();

                // Update transaction status
                $transactionModel->updateTransactionStatus($transactionId, 'completed', $currentAdmin['id'], true);

                // Update withdrawal status
                $database->update('withdrawals', [
                    'status' => 'completed',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_at' => date('Y-m-d H:i:s'),
                    'processing_notes' => $processingNotes,
                    'withdrawal_hash' => $withdrawalHash
                ], 'id = ?', [$withdrawalId]);

                // Update user's total withdrawn
                $transaction = $transactionModel->getTransactionById($transactionId);
                if ($transaction) {
                    $userModel->addToTotalWithdrawn($transaction['user_id'], $transaction['amount']);
                }

                $database->commit();
                $success = "Withdrawal approved successfully.";
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to approve withdrawal: " . $e->getMessage();
            }
            break;

        case 'reject_withdrawal':
            $withdrawalId = $_POST['withdrawal_id'];
            $transactionId = $_POST['transaction_id'];
            $userId = $_POST['user_id'];
            $amount = floatval($_POST['amount']);
            $rejectionReason = $_POST['rejection_reason'] ?? '';

            try {
                $database->beginTransaction();

                // Update transaction status
                $transactionModel->updateTransactionStatus($transactionId, 'rejected', $currentAdmin['id'], true);

                // Update withdrawal status
                $database->update('withdrawals', [
                    'status' => 'rejected',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_at' => date('Y-m-d H:i:s'),
                    'processing_notes' => $rejectionReason
                ], 'id = ?', [$withdrawalId]);

                // Refund user balance
                $database->update('users', [
                    'balance' => $database->raw('balance + ' . $amount)
                ], 'id = ?', [$userId]);

                $database->commit();
                $success = "Withdrawal rejected and user balance refunded.";
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to reject withdrawal: " . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = [];
$params = [];

switch ($filter) {
    case 'pending':
        $whereConditions[] = "w.status = 'pending'";
        break;
    case 'completed':
        $whereConditions[] = "w.status = 'completed'";
        break;
    case 'rejected':
        $whereConditions[] = "w.status = 'rejected'";
        break;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get withdrawals with user info
$withdrawalsQuery = "
    SELECT w.*, u.username, u.email, u.first_name, u.last_name, t.amount as transaction_amount
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    JOIN transactions t ON w.transaction_id = t.id
    $whereClause
    ORDER BY w.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$limit, $offset]);
$withdrawals = $database->fetchAll($withdrawalsQuery, $queryParams);

// Get withdrawal statistics
$stats = $database->fetchOne("
    SELECT 
        COUNT(*) as total_withdrawals,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_withdrawals,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_withdrawals,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_withdrawals,
        SUM(CASE WHEN status = 'completed' THEN requested_amount ELSE 0 END) as total_amount
    FROM withdrawals
");

// Get total count for pagination
$totalQuery = "
    SELECT COUNT(*) as count
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    JOIN transactions t ON w.transaction_id = t.id
    $whereClause
";
$totalCount = $database->fetchOne($totalQuery, $params)['count'];
$totalPages = ceil($totalCount / $limit);

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Page Content -->
<div class="admin-content">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    <i class="fas fa-check-circle me-2"></i>
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
                    <i class="fas fa-exclamation-circle me-2"></i>
                </div>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-danger);">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="stats-number"><?= number_format($stats['total_withdrawals']) ?></div>
            <div class="stats-label">Total Withdrawals</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-number"><?= number_format($stats['pending_withdrawals']) ?></div>
            <div class="stats-label">Pending Withdrawals</div>
            <?php if ($stats['pending_withdrawals'] > 0): ?>
                <div class="stats-change" style="background: rgba(255, 193, 7, 0.1); color: var(--admin-warning);">
                    <i class="fas fa-exclamation-triangle"></i>
                    Requires Action
                </div>
            <?php endif; ?>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-number"><?= number_format($stats['completed_withdrawals']) ?></div>
            <div class="stats-label">Completed Withdrawals</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-primary);">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stats-number"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($stats['total_amount'], 2) ?></div>
            <div class="stats-label">Total Amount</div>
        </div>
    </div>

    <!-- Auto-Approval Status -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Auto-Approval Status</h3>
        </div>
        <div class="admin-card-body">
            <div class="alert <?= $autoApprovalEnabled ? 'alert-success' : 'alert-warning' ?> mb-0">
                <i class="fas <?= $autoApprovalEnabled ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
                <strong>Auto-Approval:</strong> <?= $autoApprovalEnabled ? 'ENABLED' : 'DISABLED' ?>
                <?php if (!$autoApprovalEnabled): ?>
                    <span class="text-muted ms-2">(Withdrawals require manual approval)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Filter Withdrawals</h3>
        </div>
        <div class="admin-card-body">
            <div class="btn-list">
                <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                    All Withdrawals
                </a>
                <a href="?filter=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">
                    Pending
                    <?php if ($stats['pending_withdrawals'] > 0): ?>
                        <span class="badge bg-warning ms-2"><?= $stats['pending_withdrawals'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="?filter=completed" class="btn btn-outline-success <?= $filter === 'completed' ? 'active' : '' ?>">
                    Completed
                </a>
                <a href="?filter=rejected" class="btn btn-outline-secondary <?= $filter === 'rejected' ? 'active' : '' ?>">
                    Rejected
                </a>
            </div>
        </div>
    </div>

    <!-- Withdrawals Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Withdrawals List</h3>
        </div>
        <div class="admin-card-body">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($withdrawals)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-3"></i>
                                    <div>No withdrawals found</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="admin-user-avatar me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($withdrawal['username']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($withdrawal['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($withdrawal['requested_amount'], 2) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($withdrawal['payment_method']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        switch($withdrawal['status']) {
                                            case 'completed':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'pending':
                                                $statusClass = 'bg-warning';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'bg-danger';
                                                break;
                                            default:
                                                $statusClass = 'bg-secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($withdrawal['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="text-muted small">
                                            <?= date('M j, Y', strtotime($withdrawal['created_at'])) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= date('g:i A', strtotime($withdrawal['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-list">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewWithdrawal(<?= $withdrawal['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($withdrawal['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveWithdrawal(<?= $withdrawal['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectWithdrawal(<?= $withdrawal['id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
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
                <div class="d-flex justify-content-center mt-4">
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Withdrawal Details Modal -->
<div class="modal fade" id="modal-withdrawal-details" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Withdrawal Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="withdrawal-details-content">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Approve/Reject Modal -->
<div class="modal fade" id="modal-withdrawal-action" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-action-title">Action on Withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="withdrawal-action-form">
                <div class="modal-body">
                    <input type="hidden" name="withdrawal_id" id="action-withdrawal-id">
                    <input type="hidden" name="transaction_id" id="action-transaction-id">
                    <input type="hidden" name="user_id" id="action-user-id">
                    <input type="hidden" name="amount" id="action-amount">
                    <input type="hidden" name="action" id="action-type">
                    
                    <div class="mb-3" id="withdrawal-hash-group" style="display: none;">
                        <label class="form-label">Transaction Hash</label>
                        <input type="text" name="withdrawal_hash" class="form-control" placeholder="Enter transaction hash">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" id="notes-label">Notes</label>
                        <textarea name="processing_notes" class="form-control" rows="3" placeholder="Optional notes about this action"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="action-submit-btn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewWithdrawal(withdrawalId) {
    // Load withdrawal details via AJAX
    fetch(`get-withdrawal-details.php?id=${withdrawalId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('withdrawal-details-content').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modal-withdrawal-details')).show();
        });
}

function approveWithdrawal(withdrawalId) {
    // Set up approve action
    document.getElementById('modal-action-title').textContent = 'Approve Withdrawal';
    document.getElementById('action-type').value = 'approve_withdrawal';
    document.getElementById('action-submit-btn').className = 'btn btn-success';
    document.getElementById('action-submit-btn').textContent = 'Approve';
    document.getElementById('notes-label').textContent = 'Processing Notes';
    document.getElementById('withdrawal-hash-group').style.display = 'block';
    
    // Get withdrawal data and populate form
    fetch(`get-withdrawal-data.php?id=${withdrawalId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('action-withdrawal-id').value = data.withdrawal_id;
            document.getElementById('action-transaction-id').value = data.transaction_id;
            new bootstrap.Modal(document.getElementById('modal-withdrawal-action')).show();
        });
}

function rejectWithdrawal(withdrawalId) {
    // Set up reject action
    document.getElementById('modal-action-title').textContent = 'Reject Withdrawal';
    document.getElementById('action-type').value = 'reject_withdrawal';
    document.getElementById('action-submit-btn').className = 'btn btn-danger';
    document.getElementById('action-submit-btn').textContent = 'Reject';
    document.getElementById('notes-label').textContent = 'Rejection Reason';
    document.getElementById('withdrawal-hash-group').style.display = 'none';
    
    // Get withdrawal data and populate form
    fetch(`get-withdrawal-data.php?id=${withdrawalId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('action-withdrawal-id').value = data.withdrawal_id;
            document.getElementById('action-transaction-id').value = data.transaction_id;
            document.getElementById('action-user-id').value = data.user_id;
            document.getElementById('action-amount').value = data.amount;
            new bootstrap.Modal(document.getElementById('modal-withdrawal-action')).show();
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>