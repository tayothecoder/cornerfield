<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

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

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">User Transfers Management</h2>
                <div class="text-muted mt-1">Monitor and manage user-to-user balance transfers</div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <!-- Statistics Cards -->
        <div class="row row-deck row-cards mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Transfers</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($stats['total'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Amount</div>
                        </div>
                        <div class="h1 mb-3 text-success">$<?= number_format($stats['total_amount'] ?? 0, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Today's Transfers</div>
                        </div>
                        <div class="h1 mb-3 text-info"><?= number_format($stats['today'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Pending</div>
                        </div>
                        <div class="h1 mb-3 text-warning"><?= number_format($stats['pending'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfers Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Transfers</h3>
                <div class="card-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
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
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>From User</th>
                            <th>To User</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $transfer): ?>
                        <tr data-status="<?= $transfer['status'] ?>">
                            <td>
                                <div class="font-weight-medium"><?= htmlspecialchars($transfer['transaction_id']) ?></div>
                                <div class="text-muted">#<?= $transfer['id'] ?></div>
                            </td>
                            <td>
                                <div class="d-flex py-1 align-items-center">
                                    <div class="flex-fill">
                                        <div class="font-weight-medium"><?= htmlspecialchars($transfer['from_email'] ?? 'Unknown') ?></div>
                                        <div class="text-muted"><?= htmlspecialchars(($transfer['from_first_name'] ?? '') . ' ' . ($transfer['from_last_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex py-1 align-items-center">
                                    <div class="flex-fill">
                                        <div class="font-weight-medium"><?= htmlspecialchars($transfer['to_email'] ?? 'Unknown') ?></div>
                                        <div class="text-muted"><?= htmlspecialchars(($transfer['to_first_name'] ?? '') . ' ' . ($transfer['to_last_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-medium text-success">$<?= number_format($transfer['amount'], 2) ?></div>
                            </td>
                            <td>
                                <div class="text-muted"><?= htmlspecialchars($transfer['description'] ?: 'No description') ?></div>
                            </td>
                            <td>
                                <?php
                                switch($transfer['status']) {
                                    case 'completed':
                                        $statusClass = 'bg-success';
                                        break;
                                    case 'pending':
                                        $statusClass = 'bg-warning';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'bg-danger';
                                        break;
                                    case 'failed':
                                        $statusClass = 'bg-secondary';
                                        break;
                                    default:
                                        $statusClass = 'bg-secondary';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($transfer['status']) ?></span>
                            </td>
                            <td>
                                <div class="text-muted"><?= date('M j, Y', strtotime($transfer['created_at'])) ?></div>
                                <div class="text-muted"><?= date('g:i A', strtotime($transfer['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <?php if ($transfer['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="cancelTransfer(<?= $transfer['id'] ?>)">
                                        Cancel
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTransfer(<?= $transfer['id'] ?>)">
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
<div class="modal" id="cancelTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cancelTransferForm">
                    <input type="hidden" id="cancelTransferId" name="transfer_id">
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" placeholder="Enter reason for cancellation..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmCancelTransfer()">Cancel Transfer</button>
            </div>
        </div>
    </div>
</div>

<script>
function cancelTransfer(transferId) {
    document.getElementById('cancelTransferId').value = transferId;
    document.getElementById('cancelReason').value = '';
    new bootstrap.Modal(document.getElementById('cancelTransferModal')).show();
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
