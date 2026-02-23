<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

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
                $database->query('UPDATE users SET balance = balance + ? WHERE id = ?', [$amount, $userId]);

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

<div class="space-y-6">
    <?php if ($success): ?>
    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Withdrawals</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($stats['total_withdrawals']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Pending</p>
            <p class="text-3xl font-light tracking-tighter text-amber-600 dark:text-amber-400"><?= number_format($stats['pending_withdrawals']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Completed</p>
            <p class="text-3xl font-light tracking-tighter text-emerald-600 dark:text-emerald-400"><?= number_format($stats['completed_withdrawals']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Amount</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($stats['total_amount'], 2) ?></p>
        </div>
    </div>

    <!-- auto-approval notice -->
    <div class="p-4 rounded-xl text-sm <?= $autoApproveWithdrawals ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400' ?>">
        Auto-approval: <?= $autoApproveWithdrawals ? 'enabled' : 'disabled - withdrawals require manual approval' ?>
    </div>

    <!-- filters -->
    <div class="flex flex-wrap gap-2">
        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $fk => $fl): ?>
        <a href="?filter=<?= $fk ?>" class="px-4 py-2 text-sm font-medium rounded-full transition-colors <?= $filter === $fk ? 'bg-[#1e0e62] text-white' : 'border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 hover:border-[#1e0e62]' ?>">
            <?= $fl ?>
            <?php if ($fk === 'pending' && $stats['pending_withdrawals'] > 0): ?>
                <span class="ml-1 bg-white/20 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $stats['pending_withdrawals'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <button onclick="showModal('modal-manual-withdrawal')" class="ml-auto px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
            Manual Withdrawal
        </button>
    </div>

    <!-- table -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-white/5">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php if (empty($withdrawals)): ?>
                    <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No withdrawals found</td></tr>
                    <?php else: ?>
                        <?php foreach ($withdrawals as $withdrawal): ?>
                        <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($withdrawal['username']) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($withdrawal['email']) ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-medium"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($withdrawal['requested_amount'], 2) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400"><?= htmlspecialchars($withdrawal['payment_method'] ?? 'N/A') ?></span></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php $sc = match($withdrawal['status']) { 'completed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400', 'pending' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400', 'rejected' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400', default => 'bg-gray-100 dark:bg-gray-800 text-gray-500' }; ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $sc ?>"><?= ucfirst($withdrawal['status']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y', strtotime($withdrawal['created_at'])) ?><br><span class="text-xs"><?= date('g:i A', strtotime($withdrawal['created_at'])) ?></span></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center gap-1">
                                    <button onclick="viewWithdrawal(<?= $withdrawal['id'] ?>)" class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-xs font-medium rounded-full hover:border-[#1e0e62] transition-colors">View</button>
                                    <?php if ($withdrawal['status'] === 'pending'): ?>
                                    <button onclick="approveWithdrawal(<?= $withdrawal['id'] ?>)" class="px-3 py-1 bg-emerald-600 text-white text-xs font-medium rounded-full hover:bg-emerald-700 transition-colors">Approve</button>
                                    <button onclick="rejectWithdrawal(<?= $withdrawal['id'] ?>)" class="px-3 py-1 bg-red-600 text-white text-xs font-medium rounded-full hover:bg-red-700 transition-colors">Reject</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-6 border-t border-gray-100 dark:border-[#2d1b6e] flex justify-center">
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $i ?>" class="px-3 py-1 text-sm rounded-full <?= $i === $page ? 'bg-[#1e0e62] text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/10' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- manual withdrawal modal -->
<div id="modal-manual-withdrawal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Manual Withdrawal</h3>
            <button onclick="hideModal('modal-manual-withdrawal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">User</label><select name="user_id" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required><option value="">Select user</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Amount</label><input type="number" name="amount" step="0.01" min="0.01" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Payment method</label><select name="payment_method" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required><option value="">Select method</option><?php foreach ($paymentMethods as $pm): ?><option value="<?= htmlspecialchars($pm['name']) ?>"><?= htmlspecialchars($pm['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label><textarea name="description" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" placeholder="Optional description"></textarea></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('modal-manual-withdrawal')" class="px-4 py-2 text-sm font-medium text-gray-600 rounded-full">Cancel</button>
                <button type="submit" name="action" value="manual_withdrawal" class="px-6 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a]">Create Withdrawal</button>
            </div>
        </form>
    </div>
</div>

<!-- withdrawal details modal -->
<div id="modal-withdrawal-details" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Withdrawal Details</h3>
            <button onclick="hideModal('modal-withdrawal-details')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6" id="withdrawal-details-content"></div>
    </div>
</div>

<!-- approve/reject modal -->
<div id="modal-withdrawal-action" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-action-title">Action</h3>
            <button onclick="hideModal('modal-withdrawal-action')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" id="withdrawal-action-form" class="p-6 space-y-4">
            <input type="hidden" name="withdrawal_id" id="action-withdrawal-id">
            <input type="hidden" name="transaction_id" id="action-transaction-id">
            <input type="hidden" name="user_id" id="action-user-id">
            <input type="hidden" name="amount" id="action-amount">
            <input type="hidden" name="action" id="action-type">
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Admin notes</label><textarea name="admin_notes" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" placeholder="Optional notes"></textarea></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('modal-withdrawal-action')" class="px-4 py-2 text-sm font-medium text-gray-600 rounded-full">Cancel</button>
                <button type="submit" class="px-6 py-2 text-sm font-medium rounded-full text-white" id="action-submit-btn">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewWithdrawal(id) {
    fetch('get-withdrawal-details.php?id=' + id).then(r => r.text()).then(html => {
        document.getElementById('withdrawal-details-content').innerHTML = html;
        showModal('modal-withdrawal-details');
    });
}
function approveWithdrawal(id) {
    document.getElementById('modal-action-title').textContent = 'Approve Withdrawal';
    document.getElementById('action-type').value = 'approve_withdrawal';
    document.getElementById('action-submit-btn').className = 'px-6 py-2 text-sm font-medium rounded-full text-white bg-emerald-600 hover:bg-emerald-700';
    document.getElementById('action-submit-btn').textContent = 'Approve';
    fetch('get-withdrawal-data.php?id=' + id).then(r => r.json()).then(data => {
        document.getElementById('action-withdrawal-id').value = data.withdrawal_id;
        document.getElementById('action-transaction-id').value = data.transaction_id;
        document.getElementById('action-user-id').value = data.user_id;
        document.getElementById('action-amount').value = data.amount;
        showModal('modal-withdrawal-action');
    });
}
function rejectWithdrawal(id) {
    document.getElementById('modal-action-title').textContent = 'Reject Withdrawal';
    document.getElementById('action-type').value = 'reject_withdrawal';
    document.getElementById('action-submit-btn').className = 'px-6 py-2 text-sm font-medium rounded-full text-white bg-red-600 hover:bg-red-700';
    document.getElementById('action-submit-btn').textContent = 'Reject';
    fetch('get-withdrawal-data.php?id=' + id).then(r => r.json()).then(data => {
        document.getElementById('action-withdrawal-id').value = data.withdrawal_id;
        document.getElementById('action-transaction-id').value = data.transaction_id;
        document.getElementById('action-user-id').value = data.user_id;
        document.getElementById('action-amount').value = data.amount;
        showModal('modal-withdrawal-action');
    });
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>