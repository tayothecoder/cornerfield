<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Models\Transaction;
use App\Utils\SessionManager;

// Start session and check authentication
SessionManager::start();

if (!SessionManager::get('user_logged_in')) {
    header('Location: ../login.php');
    exit;
}

$user_id = SessionManager::get('user_id');

try {
    $database = new Database();
    $userModel = new User($database);
    $currentUser = $userModel->findById($user_id);

    if (!$currentUser) {
        header('Location: ../login.php');
        exit;
    }

    $transactionModel = new Transaction($database);
    
    // Get filter parameters
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Get transactions
    $transactions = $transactionModel->getUserTransactions($user_id, $type, $limit, $offset);
    $totalTransactions = count($transactionModel->getUserTransactions($user_id, $type));
    $totalPages = ceil($totalTransactions / $limit);

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Transactions';
$currentPage = 'transactions';

include __DIR__ . '/includes/header.php';
?>

<style>
    .transactions-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .filters-section {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    .filter-select {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 0.75rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        transition: all 0.3s ease;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .filter-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        height: fit-content;
    }

    .filter-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .transactions-table {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .table-header {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 0.5rem;
        color: var(--primary-color);
    }

    .table-content {
        overflow-x: auto;
    }

    .transactions-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .transactions-table th {
        background: var(--bg-tertiary);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
        border-bottom: 1px solid var(--border-color);
    }

    .transactions-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .transactions-table tr:hover {
        background: var(--bg-secondary);
    }

    .transaction-type {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .type-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        color: white;
    }

    .type-icon.deposit { background: var(--success-color); }
    .type-icon.withdrawal { background: var(--warning-color); }
    .type-icon.withdraw { background: var(--warning-color); }
    .type-icon.profit { background: var(--info-color); }
    .type-icon.bonus { background: var(--primary-color); }
    .type-icon.investment { background: var(--dark-color); }

    .type-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .amount {
        font-weight: 700;
        font-size: 1rem;
    }

    .amount.positive {
        color: var(--success-color);
    }

    .amount.negative {
        color: var(--danger-color);
    }

    .status {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status.completed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .status.pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    .status.failed {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .status.cancelled {
        background: rgba(107, 114, 128, 0.1);
        color: var(--text-secondary);
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        padding: 1.5rem;
        background: var(--bg-secondary);
    }

    .pagination-btn {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .pagination-btn:hover {
        background: var(--primary-color);
        color: white;
        text-decoration: none;
    }

    .pagination-btn.active {
        background: var(--primary-color);
        color: white;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--text-muted);
    }

    .transaction-details {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    @media (max-width: 768px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .transactions-table th,
        .transactions-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .table-content {
            font-size: 0.8rem;
        }
    }
</style>

<div class="transactions-container">
    <!-- Filters Section -->
    <div class="filters-section gradient-card">
        <form method="GET" class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">Transaction Type</label>
                <select name="type" class="filter-select">
                    <option value="">All Types</option>
                    <option value="deposit" <?= $type === 'deposit' ? 'selected' : '' ?>>Deposits</option>
                    <option value="withdrawal" <?= $type === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                    <option value="profit" <?= $type === 'profit' ? 'selected' : '' ?>>Profits</option>
                    <option value="bonus" <?= $type === 'bonus' ? 'selected' : '' ?>>Bonuses</option>
                    <option value="investment" <?= $type === 'investment' ? 'selected' : '' ?>>Investments</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter me-2"></i>
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="transactions-table gradient-card">
        <div class="table-header">
            <h2 class="section-title gradient-text">
                <i class="fas fa-history"></i>
                Transaction History
            </h2>
        </div>
        
        <div class="table-content">
            <?php if (!empty($transactions)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                            <th>Date</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <div class="transaction-type">
                                        <div class="type-icon <?= strtolower($transaction['type']) ?>">
                                            <?php
                                            switch (strtolower($transaction['type'])) {
                                                case 'deposit':
                                                    echo '<i class="fas fa-plus"></i>';
                                                    break;
                                                case 'withdrawal':
                                                case 'withdraw':
                                                    echo '<i class="fas fa-minus"></i>';
                                                    break;
                                                case 'profit':
                                                    echo '<i class="fas fa-chart-line"></i>';
                                                    break;
                                                case 'bonus':
                                                    echo '<i class="fas fa-gift"></i>';
                                                    break;
                                                case 'investment':
                                                    echo '<i class="fas fa-rocket"></i>';
                                                    break;
                                                default:
                                                    echo '<i class="fas fa-exchange-alt"></i>';
                                            }
                                            ?>
                                        </div>
                                        <div class="type-name"><?= htmlspecialchars(ucfirst($transaction['type'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="amount <?= $transaction['amount'] >= 0 ? 'positive' : 'negative' ?>">
                                        <?= ($transaction['amount'] >= 0 ? '+' : '') ?>$<?= number_format(abs($transaction['amount']), 2) ?>
                                    </div>
                                    <?php if (isset($transaction['net_amount']) && $transaction['net_amount'] != $transaction['amount']): ?>
                                        <div class="transaction-details">
                                            Net: $<?= number_format($transaction['net_amount'], 2) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status <?= strtolower($transaction['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($transaction['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars(ucfirst($transaction['payment_method'] ?? 'N/A')) ?>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($transaction['created_at'])) ?></div>
                                    <div class="transaction-details"><?= date('g:i A', strtotime($transaction['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($transaction['description'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No transactions found</h3>
                    <p>Your transaction history will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&type=<?= $type ?>&status=<?= $status ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&type=<?= $type ?>&status=<?= $status ?>" 
                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&type=<?= $type ?>&status=<?= $status ?>" class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>