<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}


require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

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

<div class="space-y-6">
    <?php if ($success): ?><div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-sm"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Pending Deposits</p>
            <p class="text-3xl font-light tracking-tighter text-amber-600 dark:text-amber-400"><?= $pendingDeposits ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Pending Withdrawals</p>
            <p class="text-3xl font-light tracking-tighter text-red-600 dark:text-red-400"><?= $pendingWithdrawals ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Deposited</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= $currencySymbol ?><?= number_format($stats['total_deposited'], 2) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Withdrawn</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= $currencySymbol ?><?= number_format($stats['total_withdrawn'], 2) ?></p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'deposits' => 'Deposits', 'withdrawals' => 'Withdrawals', 'today' => 'Today'] as $fk => $fl): ?>
        <a href="?filter=<?= $fk ?>" class="px-4 py-2 text-sm font-medium rounded-full transition-colors <?= $filter === $fk ? 'bg-[#1e0e62] text-white' : 'border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 hover:border-[#1e0e62]' ?>"><?= $fl ?></a>
        <?php endforeach; ?>
        <button onclick="showModal('modal-manual-deposit')" class="ml-auto px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a]">Manual Deposit</button>
    </div>

    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/50 dark:bg-white/5">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th><th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">#<?= $tx['id'] ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($tx['username']) ?></p><p class="text-xs text-gray-400"><?= htmlspecialchars($tx['email']) ?></p></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php $tc = match($tx['type']) { 'deposit' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400', 'withdrawal' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400', default => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' }; ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $tc ?>"><?= ucfirst($tx['type']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-medium"><?= $currencySymbol ?><?= number_format($tx['amount'], 2) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php $sc = match($tx['status']) { 'completed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400', 'pending' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400', 'failed' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400', default => 'bg-gray-100 dark:bg-gray-800 text-gray-500' }; ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $sc ?>"><?= ucfirst($tx['status']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= ucfirst($tx['payment_method']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y', strtotime($tx['created_at'])) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php if ($tx['status'] === 'pending'): ?>
                                <div class="flex gap-1">
                                    <?php if ($tx['type'] === 'deposit'): ?>
                                    <button onclick="approveDeposit(<?= $tx['id'] ?>, <?= $tx['user_id'] ?>, <?= $tx['amount'] ?>)" class="px-3 py-1 bg-emerald-600 text-white text-xs font-medium rounded-full">Approve</button>
                                    <?php elseif ($tx['type'] === 'withdrawal'): ?>
                                    <button onclick="approveWithdrawal(<?= $tx['id'] ?>)" class="px-3 py-1 bg-emerald-600 text-white text-xs font-medium rounded-full">Approve</button>
                                    <?php endif; ?>
                                    <button onclick="rejectTransaction(<?= $tx['id'] ?>)" class="px-3 py-1 bg-red-600 text-white text-xs font-medium rounded-full">Reject</button>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-300">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">No transactions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- manual deposit modal -->
<div id="modal-manual-deposit" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]"><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Manual Deposit</h3><button onclick="hideModal('modal-manual-deposit')" class="text-gray-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="manual_deposit">
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">User</label><select name="user_id" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required><option value="">Choose user...</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Amount</label><input type="number" name="amount" step="0.01" min="1" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required></div>
            <div><label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label><input type="text" name="description" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" placeholder="Manual deposit by admin"></div>
            <div class="flex justify-end gap-3"><button type="button" onclick="hideModal('modal-manual-deposit')" class="px-4 py-2 text-sm text-gray-600 rounded-full">Cancel</button><button type="submit" class="px-6 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full">Add Deposit</button></div>
        </form>
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