<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

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
    SELECT d.*, u.username, u.email, u.first_name, u.last_name, t.amount as transaction_amount, t.payment_method
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
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Deposits</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($stats['total_deposits']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Pending</p>
            <p class="text-3xl font-light tracking-tighter text-amber-600 dark:text-amber-400"><?= number_format($stats['pending_deposits']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Completed</p>
            <p class="text-3xl font-light tracking-tighter text-emerald-600 dark:text-emerald-400"><?= number_format($stats['completed_deposits']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Amount</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($stats['total_amount'], 2) ?></p>
        </div>
    </div>

    <!-- auto-approval notice -->
    <div class="p-4 rounded-xl text-sm <?= $autoApproveDeposits ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400' ?>">
        Auto-approval: <?= $autoApproveDeposits ? 'enabled' : 'disabled - deposits require manual approval' ?>
    </div>

    <!-- filters -->
    <div class="flex flex-wrap gap-2">
        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'verification' => 'Verification', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $fk => $fl): ?>
        <a href="?filter=<?= $fk ?>" class="px-4 py-2 text-sm font-medium rounded-full transition-colors <?= $filter === $fk ? 'bg-[#1e0e62] text-white' : 'border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 hover:border-[#1e0e62]' ?>">
            <?= $fl ?>
            <?php if ($fk === 'pending' && $stats['pending_deposits'] > 0): ?>
                <span class="ml-1 bg-white/20 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $stats['pending_deposits'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <button onclick="showModal('modal-manual-deposit')" class="ml-auto px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
            Manual Deposit
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
                    <?php if (empty($deposits)): ?>
                    <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No deposits found</td></tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $deposit): ?>
                        <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($deposit['username']) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($deposit['email']) ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-medium"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($deposit['requested_amount'], 2) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400"><?= htmlspecialchars($deposit['payment_method'] ?? 'N/A') ?></span></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php $sc = match($deposit['status']) { 'completed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400', 'pending' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400', 'rejected' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400', default => 'bg-gray-100 dark:bg-gray-800 text-gray-500' }; ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $sc ?>"><?= ucfirst($deposit['status']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y', strtotime($deposit['created_at'])) ?><br><span class="text-xs"><?= date('g:i A', strtotime($deposit['created_at'])) ?></span></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center gap-1">
                                    <button onclick="viewDeposit(<?= $deposit['id'] ?>)" class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-xs font-medium rounded-full hover:border-[#1e0e62] transition-colors">View</button>
                                    <?php if ($deposit['status'] === 'pending'): ?>
                                    <button onclick="approveDeposit(<?= $deposit['id'] ?>)" class="px-3 py-1 bg-emerald-600 text-white text-xs font-medium rounded-full hover:bg-emerald-700 transition-colors">Approve</button>
                                    <button onclick="rejectDeposit(<?= $deposit['id'] ?>)" class="px-3 py-1 bg-red-600 text-white text-xs font-medium rounded-full hover:bg-red-700 transition-colors">Reject</button>
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

<!-- manual deposit modal -->
<div id="modal-manual-deposit" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Manual Deposit</h3>
            <button onclick="hideModal('modal-manual-deposit')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">User</label><select name="user_id" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required><option value="">Select user</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Amount</label><input type="number" name="amount" step="0.01" min="0.01" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Payment method</label><select name="payment_method" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required><option value="">Select method</option><?php foreach ($paymentMethods as $pm): ?><option value="<?= htmlspecialchars($pm['name']) ?>"><?= htmlspecialchars($pm['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label><textarea name="description" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" placeholder="Optional description"></textarea></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('modal-manual-deposit')" class="px-4 py-2 text-sm font-medium text-gray-600 rounded-full">Cancel</button>
                <button type="submit" name="action" value="manual_deposit" class="px-6 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a]">Create Deposit</button>
            </div>
        </form>
    </div>
</div>

<!-- deposit details modal -->
<div id="modal-deposit-details" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Deposit Details</h3>
            <button onclick="hideModal('modal-deposit-details')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6" id="deposit-details-content"></div>
    </div>
</div>

<!-- approve/reject modal -->
<div id="modal-deposit-action" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-action-title">Action</h3>
            <button onclick="hideModal('modal-deposit-action')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" id="deposit-action-form" class="p-6 space-y-4">
            <input type="hidden" name="deposit_id" id="action-deposit-id">
            <input type="hidden" name="transaction_id" id="action-transaction-id">
            <input type="hidden" name="user_id" id="action-user-id">
            <input type="hidden" name="amount" id="action-amount">
            <input type="hidden" name="action" id="action-type">
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Admin notes</label><textarea name="admin_notes" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" placeholder="Optional notes"></textarea></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('modal-deposit-action')" class="px-4 py-2 text-sm font-medium text-gray-600 rounded-full">Cancel</button>
                <button type="submit" class="px-6 py-2 text-sm font-medium rounded-full text-white" id="action-submit-btn">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewDeposit(id) {
    fetch('get-deposit-details.php?id=' + id).then(r => r.text()).then(html => {
        document.getElementById('deposit-details-content').innerHTML = html;
        showModal('modal-deposit-details');
    });
}
function approveDeposit(id) {
    document.getElementById('modal-action-title').textContent = 'Approve Deposit';
    document.getElementById('action-type').value = 'approve_deposit';
    document.getElementById('action-submit-btn').className = 'px-6 py-2 text-sm font-medium rounded-full text-white bg-emerald-600 hover:bg-emerald-700';
    document.getElementById('action-submit-btn').textContent = 'Approve';
    fetch('get-deposit-data.php?id=' + id).then(r => r.json()).then(data => {
        document.getElementById('action-deposit-id').value = data.deposit_id;
        document.getElementById('action-transaction-id').value = data.transaction_id;
        document.getElementById('action-user-id').value = data.user_id;
        document.getElementById('action-amount').value = data.amount;
        showModal('modal-deposit-action');
    });
}
function rejectDeposit(id) {
    document.getElementById('modal-action-title').textContent = 'Reject Deposit';
    document.getElementById('action-type').value = 'reject_deposit';
    document.getElementById('action-submit-btn').className = 'px-6 py-2 text-sm font-medium rounded-full text-white bg-red-600 hover:bg-red-700';
    document.getElementById('action-submit-btn').textContent = 'Reject';
    fetch('get-deposit-data.php?id=' + id).then(r => r.json()).then(data => {
        document.getElementById('action-deposit-id').value = data.deposit_id;
        document.getElementById('action-transaction-id').value = data.transaction_id;
        document.getElementById('action-user-id').value = data.user_id;
        document.getElementById('action-amount').value = data.amount;
        showModal('modal-deposit-action');
    });
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>