<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize session
\App\Utils\SessionManager::start();

// Page setup
$pageTitle = 'Deposits Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'deposits';

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

// Auto-approve deposits if enabled
$autoApproveDeposits = $adminSettingsModel->getSetting('deposit_auto_approval', 0);
$autoApprovalEnabled = $autoApproveDeposits;
if ($autoApproveDeposits) {
    $pendingDeposits = $database->fetchAll("
        SELECT d.*, t.id as transaction_id 
        FROM deposits d 
        JOIN transactions t ON d.transaction_id = t.id 
        WHERE d.status = 'pending' AND d.verification_status = 'pending'
    ");

    foreach ($pendingDeposits as $deposit) {
        try {
            $database->beginTransaction();

            // Auto-approve logic same as manual approve
            $database->update('transactions', [
                'status' => 'completed',
                'admin_processed_by' => $currentAdmin['id'],
                'processed_by_type' => 'admin',
                'processed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$deposit['transaction_id']]);

            $database->update('deposits', [
                'status' => 'completed',
                'verification_status' => 'verified',
                'admin_processed_by' => $currentAdmin['id'],
                'processed_at' => date('Y-m-d H:i:s'),
                'admin_notes' => 'Auto-approved by system'
            ], 'id = ?', [$deposit['id']]);

            $database->query('UPDATE users SET balance = balance + ? WHERE id = ?', [
                $deposit['requested_amount'], $deposit['user_id']
            ]);

            $database->commit();
        } catch (Exception $e) {
            $database->rollback();
            error_log('Auto-approval failed for deposit ' . $deposit['id'] . ': ' . $e->getMessage());
        }
    }

    if (!empty($pendingDeposits)) {
        $success = count($pendingDeposits) . " deposits auto-approved successfully.";
    }
}

// Handle deposit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'approve_deposit':
            $depositId = $_POST['deposit_id'];
            $transactionId = $_POST['transaction_id'];
            $userId = $_POST['user_id'];
            $amount = floatval($_POST['amount']);

            try {
                $database->beginTransaction();

                // Update transaction status
                $database->update('transactions', [
                    'status' => 'completed',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_by_type' => 'admin',
                    'processed_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$transactionId]);

                // Update deposit status
                $database->update('deposits', [
                    'status' => 'completed',
                    'verification_status' => 'verified',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_at' => date('Y-m-d H:i:s'),
                    'admin_notes' => $_POST['admin_notes'] ?? 'Approved by admin'
                ], 'id = ?', [$depositId]);

                // Update user balance
                $database->query('UPDATE users SET balance = balance + ? WHERE id = ?', [$amount, $userId]);

                $database->commit();
                $success = "Deposit approved successfully. User balance updated.";
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to approve deposit: " . $e->getMessage();
            }
            break;

        case 'reject_deposit':
            $depositId = $_POST['deposit_id'];
            $transactionId = $_POST['transaction_id'];

            try {
                $database->beginTransaction();

                // Update transaction status
                $database->update('transactions', [
                    'status' => 'rejected',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_by_type' => 'admin',
                    'processed_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$transactionId]);

                // Update deposit status
                $database->update('deposits', [
                    'status' => 'rejected',
                    'verification_status' => 'rejected',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_at' => date('Y-m-d H:i:s'),
                    'admin_notes' => $_POST['admin_notes'] ?? 'Rejected by admin'
                ], 'id = ?', [$depositId]);

                $database->commit();
                $success = "Deposit rejected successfully.";
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to reject deposit: " . $e->getMessage();
            }
            break;

        case 'manual_deposit':
            $userId = $_POST['user_id'];
            $amount = floatval($_POST['amount']);
            $paymentMethod = $_POST['payment_method'];
            $description = $_POST['description'] ?? 'Manual deposit by admin';

            try {
                $database->beginTransaction();

                // Create transaction
                $transactionData = [
                    'user_id' => $userId,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'fee' => 0,
                    'net_amount' => $amount,
                    'status' => 'completed',
                    'payment_method' => $paymentMethod,
                    'description' => $description,
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_by_type' => 'admin',
                    'processed_at' => date('Y-m-d H:i:s')
                ];

                $transactionId = $database->insert('transactions', $transactionData);

                // Create deposit record
                $depositData = [
                    'user_id' => $userId,
                    'transaction_id' => $transactionId,
                    'requested_amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'status' => 'completed',
                    'verification_status' => 'verified',
                    'admin_processed_by' => $currentAdmin['id'],
                    'processed_at' => date('Y-m-d H:i:s'),
                    'admin_notes' => 'Manual deposit by admin'
                ];

                $database->insert('deposits', $depositData);

                // Update user balance
                $database->query('UPDATE users SET balance = balance + ? WHERE id = ?', [$amount, $userId]);

                $database->commit();
                $success = "Manual deposit created successfully. User balance updated.";
            } catch (Exception $e) {
                $database->rollback();
                $error = "Failed to create manual deposit: " . $e->getMessage();
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
        $whereConditions[] = "d.status = 'pending'";
        break;
    case 'completed':
        $whereConditions[] = "d.status = 'completed'";
        break;
    case 'rejected':
        $whereConditions[] = "d.status = 'rejected'";
        break;
    case 'verification':
        $whereConditions[] = "d.verification_status = 'pending'";
        break;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get deposits with user info
$depositsQuery = "
    SELECT d.*, u.username, u.email, u.first_name, u.last_name, t.amount as transaction_amount
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN transactions t ON d.transaction_id = t.id
    $whereClause
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$limit, $offset]);
$deposits = $database->fetchAll($depositsQuery, $queryParams);

// Get deposit statistics
$stats = $database->fetchOne("
    SELECT 
        COUNT(*) as total_deposits,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_deposits,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_deposits,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_deposits,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_verification,
        SUM(CASE WHEN status = 'completed' THEN requested_amount ELSE 0 END) as total_amount
    FROM deposits
");

// Get total count for pagination
$totalQuery = "
    SELECT COUNT(*) as count
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN transactions t ON d.transaction_id = t.id
    $whereClause
";
$totalCount = $database->fetchOne($totalQuery, $params)['count'];
$totalPages = ceil($totalCount / $limit);

// Get users for manual deposit dropdown
$users = $userModel->getAllUsers(1, 100);

// Get payment methods
$paymentMethods = $database->fetchAll("SELECT * FROM payment_gateways WHERE is_active = 1 ORDER BY name");

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
            <div class="stats-icon deposits">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="stats-number"><?= number_format($stats['total_deposits']) ?></div>
            <div class="stats-label">Total Deposits</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-number"><?= number_format($stats['pending_deposits']) ?></div>
            <div class="stats-label">Pending Deposits</div>
            <?php if ($stats['pending_deposits'] > 0): ?>
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
            <div class="stats-number"><?= number_format($stats['completed_deposits']) ?></div>
            <div class="stats-label">Completed Deposits</div>
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
            <div class="alert <?= $autoApproveDeposits ? 'alert-success' : 'alert-warning' ?> mb-0">
                <i class="fas <?= $autoApproveDeposits ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
                <strong>Auto-Approval:</strong> <?= $autoApproveDeposits ? 'ENABLED' : 'DISABLED' ?>
                <?php if (!$autoApproveDeposits): ?>
                    <span class="text-muted ms-2">(Deposits require manual approval)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Filter Deposits</h3>
        </div>
        <div class="admin-card-body">
            <div class="btn-list">
                <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                    All Deposits
                </a>
                <a href="?filter=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">
                    Pending
                    <?php if ($stats['pending_deposits'] > 0): ?>
                        <span class="badge bg-warning ms-2"><?= $stats['pending_deposits'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="?filter=verification" class="btn btn-outline-danger <?= $filter === 'verification' ? 'active' : '' ?>">
                    Need Verification
                    <?php if ($stats['pending_verification'] > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $stats['pending_verification'] ?></span>
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

    <!-- Deposits Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Deposits List</h3>
            <div class="ms-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-manual-deposit">
                    <i class="fas fa-plus me-2"></i>
                    Manual Deposit
                </button>
            </div>
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
                            <th>Verification</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deposits)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-3"></i>
                                    <div>No deposits found</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($deposits as $deposit): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="admin-user-avatar me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($deposit['username']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($deposit['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($deposit['requested_amount'], 2) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($deposit['payment_method'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        switch($deposit['status']) {
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
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($deposit['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        switch($deposit['verification_status']) {
                                            case 'verified':
                                                $verificationClass = 'bg-success';
                                                break;
                                            case 'pending':
                                                $verificationClass = 'bg-warning';
                                                break;
                                            case 'rejected':
                                                $verificationClass = 'bg-danger';
                                                break;
                                            default:
                                                $verificationClass = 'bg-secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $verificationClass ?>"><?= ucfirst($deposit['verification_status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="text-muted small">
                                            <?= date('M j, Y', strtotime($deposit['created_at'])) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= date('g:i A', strtotime($deposit['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-list">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewDeposit(<?= $deposit['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($deposit['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveDeposit(<?= $deposit['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectDeposit(<?= $deposit['id'] ?>)">
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

<!-- Manual Deposit Modal -->
<div class="modal fade" id="modal-manual-deposit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Manual Deposit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">Select Payment Method</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= htmlspecialchars($method['name']) ?>"><?= htmlspecialchars($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="manual_deposit" class="btn btn-primary">Create Deposit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Deposit Details Modal -->
<div class="modal fade" id="modal-deposit-details" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deposit Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deposit-details-content">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Approve/Reject Modal -->
<div class="modal fade" id="modal-deposit-action" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-action-title">Action on Deposit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="deposit-action-form">
                <div class="modal-body">
                    <input type="hidden" name="deposit_id" id="action-deposit-id">
                    <input type="hidden" name="transaction_id" id="action-transaction-id">
                    <input type="hidden" name="user_id" id="action-user-id">
                    <input type="hidden" name="amount" id="action-amount">
                    <input type="hidden" name="action" id="action-type">
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3" placeholder="Optional notes about this action"></textarea>
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
function viewDeposit(depositId) {
    // Load deposit details via AJAX
    fetch(`get-deposit-details.php?id=${depositId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('deposit-details-content').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modal-deposit-details')).show();
        });
}

function approveDeposit(depositId) {
    // Set up approve action
    document.getElementById('modal-action-title').textContent = 'Approve Deposit';
    document.getElementById('action-type').value = 'approve_deposit';
    document.getElementById('action-submit-btn').className = 'btn btn-success';
    document.getElementById('action-submit-btn').textContent = 'Approve';
    
    // Get deposit data and populate form
    fetch(`get-deposit-data.php?id=${depositId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('action-deposit-id').value = data.deposit_id;
            document.getElementById('action-transaction-id').value = data.transaction_id;
            document.getElementById('action-user-id').value = data.user_id;
            document.getElementById('action-amount').value = data.amount;
            new bootstrap.Modal(document.getElementById('modal-deposit-action')).show();
        });
}

function rejectDeposit(depositId) {
    // Set up reject action
    document.getElementById('modal-action-title').textContent = 'Reject Deposit';
    document.getElementById('action-type').value = 'reject_deposit';
    document.getElementById('action-submit-btn').className = 'btn btn-danger';
    document.getElementById('action-submit-btn').textContent = 'Reject';
    
    // Get deposit data and populate form
    fetch(`get-deposit-data.php?id=${depositId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('action-deposit-id').value = data.deposit_id;
            document.getElementById('action-transaction-id').value = data.transaction_id;
            document.getElementById('action-user-id').value = data.user_id;
            document.getElementById('action-amount').value = data.amount;
            new bootstrap.Modal(document.getElementById('modal-deposit-action')).show();
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>