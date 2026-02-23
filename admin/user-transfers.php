<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Start session
\App\Utils\SessionManager::start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'User Transfers Management';
$currentPage = 'user-transfers';

// Initialize database and services
/** @var \App\Config\Database $database */
$database = new \App\Config\Database();
/** @var \App\Services\UserTransferService $transferService */
$transferService = new \App\Services\UserTransferService($database);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'cancel_transfer':
            $transferId = (int)($_POST['transfer_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin cancelled');
            
            $result = $transferService->cancelTransfer($transferId, $_SESSION['admin_id'], $reason);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
    }
}

// Get transfers and stats
$transfers = $transferService->getTransferHistory();
$stats = $transferService->getTransferStats();

include __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <div class="">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">User Transfers Management</h2>
                <div class="text-gray-400 dark:text-gray-500 mt-1">Monitor and manage user-to-user balance transfers</div>
            </div>
        </div>
    </div>
</div>

<div class="space-y-6">
    <div class="">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Transfers</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-3"><?= number_format($stats['total'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-emerald-600 dark:text-emerald-400 mb-3">$<?= number_format($stats['total_amount'] ?? 0, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Transfers</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-blue-600 dark:text-blue-400 mb-3"><?= number_format($stats['today'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-amber-600 dark:text-amber-400 mb-3"><?= number_format($stats['pending'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfers Table -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">User Transfers</h3>
                <div class="flex items-center gap-2">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" >
                            Filter
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" data-filter="all">All Transfers</a>
                            <a class="dropdown-item" href="#" data-filter="completed">Completed</a>
                            <a class="dropdown-item" href="#" data-filter="pending">Pending</a>
                            <a class="dropdown-item" href="#" data-filter="cancelled">Cancelled</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">From User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">To User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $transfer): ?>
                        <tr data-status="<?= $transfer['status'] ?>" class="border-b border-gray-50 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($transfer['transaction_id']) ?></div>
                                <div class="text-gray-400 dark:text-gray-500">#<?= $transfer['id'] ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex py-1 align-items-center">
                                    <div class="flex-fill">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($transfer['from_email'] ?? 'Unknown') ?></div>
                                        <div class="text-gray-400 dark:text-gray-500"><?= htmlspecialchars(($transfer['from_first_name'] ?? '') . ' ' . ($transfer['from_last_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex py-1 align-items-center">
                                    <div class="flex-fill">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($transfer['to_email'] ?? 'Unknown') ?></div>
                                        <div class="text-gray-400 dark:text-gray-500"><?= htmlspecialchars(($transfer['to_first_name'] ?? '') . ' ' . ($transfer['to_last_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="font-medium text-emerald-600 dark:text-emerald-400">$<?= number_format($transfer['amount'], 2) ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="text-gray-400 dark:text-gray-500"><?= htmlspecialchars($transfer['description'] ?: 'No description') ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php $tsc = match($transfer['status']) { 'completed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400', 'pending' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400', 'cancelled' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400', 'failed' => 'bg-gray-100 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400', default => 'bg-gray-100 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400' }; ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $tsc ?>"><?= ucfirst(htmlspecialchars($transfer['status'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="text-gray-400 dark:text-gray-500"><?= date('M j, Y', strtotime($transfer['created_at'])) ?></div>
                                <div class="text-gray-400 dark:text-gray-500"><?= date('g:i A', strtotime($transfer['created_at'])) ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex gap-1">
                                    <?php if ($transfer['status'] === 'pending'): ?>
                                    <button class="px-3 py-1 bg-red-600 text-white text-xs font-medium rounded-full hover:bg-red-700 transition-colors" onclick="cancelTransfer(<?= $transfer['id'] ?>)">
                                        Cancel
                                    </button>
                                    <?php endif; ?>
                                    <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 text-xs font-medium rounded-full" onclick="viewTransfer(<?= $transfer['id'] ?>)">
                                        View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Transfer Modal -->
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="cancelTransferModal" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Cancel Transfer</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" ></button>
            </div>
            <div class="p-6">
                <form id="cancelTransferForm">
                    <input type="hidden" id="cancelTransferId" name="transfer_id">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Reason for Cancellation</label>
                        <textarea class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" id="cancelReason" name="reason" rows="3" placeholder="Enter reason for cancellation..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full transition-colors" >Cancel</button>
                <button type="button" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-full hover:bg-red-700 transition-colors" onclick="confirmCancelTransfer()">Cancel Transfer</button>
            </div>
        </div>
    </div>
</div>

<script>
function cancelTransfer(transferId) {
    document.getElementById('cancelTransferId').value = transferId;
    document.getElementById('cancelReason').value = '';
    showModal('cancelTransferModal');
}

function confirmCancelTransfer() {
    const formData = new FormData(document.getElementById('cancelTransferForm'));
    formData.append('action', 'cancel_transfer');
    
    fetch('user-transfers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function viewTransfer(transferId) {
    // This would show transfer details in a modal
    alert('View transfer details for ID: ' + transferId);
}

// Filter functionality
document.querySelectorAll('[data-filter]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const filter = this.dataset.filter;
        
        if (filter === 'all') {
            document.querySelectorAll('tbody tr').forEach(row => row.style.display = '');
        } else {
            document.querySelectorAll('tbody tr').forEach(row => {
                if (row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
