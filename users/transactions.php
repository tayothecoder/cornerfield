<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\DashboardController;

// auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

// initialize controller and get data
try {
    $transactionModel = new \App\Models\TransactionModel();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $rawTransactions = $transactionModel->findByUserId($userId);
    $stats = $transactionModel->getStats($userId);
    // map created_at to date for template compatibility
    $transactions = array_map(function ($tx) {
        $tx['date'] = $tx['created_at'] ?? '';
        $tx['description'] = !empty($tx['description']) ? $tx['description'] : ucwords(str_replace('_', ' ', $tx['type'] ?? 'Transaction'));
        return $tx;
    }, $rawTransactions);
    // compute per-type totals from the by_type breakdown
    $typeMap = [];
    foreach (($stats['by_type'] ?? []) as $row) {
        $typeMap[$row['type']] = (float)($row['total_amount'] ?? 0);
    }
    $data = [
        'transactions' => $transactions,
        'stats' => [
            'total_deposits' => $typeMap['deposit'] ?? 0.0,
            'total_withdrawals' => abs($typeMap['withdrawal'] ?? 0.0),
            'total_earnings' => ($typeMap['profit'] ?? 0.0) + ($typeMap['bonus'] ?? 0.0) + ($typeMap['referral'] ?? 0.0),
            'total_investments' => abs($typeMap['investment'] ?? 0.0),
        ]
    ];
} catch (\Throwable $e) {
    // fallback demo data for preview
    $data = [
        'transactions' => [
            ['id' => 'TXN001', 'type' => 'deposit', 'amount' => 500.00, 'status' => 'completed', 'date' => '2024-02-10 14:30:00', 'description' => 'Bitcoin Deposit', 'hash' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'],
            ['id' => 'TXN002', 'type' => 'investment', 'amount' => -1000.00, 'status' => 'active', 'date' => '2024-02-09 10:15:00', 'description' => 'Premium Plan Investment', 'hash' => ''],
            ['id' => 'TXN003', 'type' => 'earning', 'amount' => 125.50, 'status' => 'completed', 'date' => '2024-02-08 16:45:00', 'description' => 'Daily ROI Payout', 'hash' => ''],
            ['id' => 'TXN004', 'type' => 'withdrawal', 'amount' => -250.00, 'status' => 'pending', 'date' => '2024-02-07 11:20:00', 'description' => 'USDT Withdrawal', 'hash' => '0x742d35cc6c3c0532925a3b8d0ec4f84ec3b8b78e'],
            ['id' => 'TXN005', 'type' => 'referral', 'amount' => 50.00, 'status' => 'completed', 'date' => '2024-02-06 09:10:00', 'description' => 'Referral Commission', 'hash' => ''],
            ['id' => 'TXN006', 'type' => 'deposit', 'amount' => 2000.00, 'status' => 'completed', 'date' => '2024-02-05 08:30:00', 'description' => 'Ethereum Deposit', 'hash' => '0x123...abc'],
            ['id' => 'TXN007', 'type' => 'investment', 'amount' => -1500.00, 'status' => 'completed', 'date' => '2024-02-04 14:20:00', 'description' => 'VIP Plan Investment', 'hash' => ''],
            ['id' => 'TXN008', 'type' => 'earning', 'amount' => 87.50, 'status' => 'completed', 'date' => '2024-02-03 12:00:00', 'description' => 'Daily ROI Payout', 'hash' => ''],
            ['id' => 'TXN009', 'type' => 'withdrawal', 'amount' => -300.00, 'status' => 'completed', 'date' => '2024-02-02 16:15:00', 'description' => 'Bitcoin Withdrawal', 'hash' => 'bc1qrandomhashexample'],
            ['id' => 'TXN010', 'type' => 'transfer', 'amount' => -100.00, 'status' => 'completed', 'date' => '2024-02-01 10:45:00', 'description' => 'Transfer to user@example.com', 'hash' => ''],
        ],
        'stats' => [
            'total_deposits' => 2500.00,
            'total_withdrawals' => 550.00,
            'total_earnings' => 213.00,
            'total_investments' => 2500.00
        ]
    ];
}

$pageTitle = 'Transaction History';
$currentPage = 'transactions';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- stats overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- total deposits -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Deposits</p>
                    <p class="text-2xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_deposits'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- total withdrawals -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Withdrawals</p>
                    <p class="text-2xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_withdrawals'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- total earnings -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Earnings</p>
                    <p class="text-2xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_earnings'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- total investments -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl mr-4">
                    <svg class="w-6 h-6 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invested</p>
                    <p class="text-2xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_investments'], 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- filters and search -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4 flex-1">
                <!-- search -->
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" id="search" placeholder="Search transactions..." 
                               class="block w-full pl-10 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                    </div>
                </div>

                <!-- type filter -->
                <div class="sm:w-48">
                    <select id="typeFilter" class="w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                        <option value="">All Types</option>
                        <option value="deposit">Deposits</option>
                        <option value="withdrawal">Withdrawals</option>
                        <option value="investment">Investments</option>
                        <option value="earning">Earnings</option>
                        <option value="referral">Referrals</option>
                        <option value="transfer">Transfers</option>
                    </select>
                </div>

                <!-- status filter -->
                <div class="sm:w-48">
                    <select id="statusFilter" class="w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>

            <!-- date range -->
            <div class="flex space-x-3">
                <input type="date" id="startDate" class="py-2 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                <input type="date" id="endDate" class="py-2 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
            </div>
        </div>
    </div>

    <!-- transactions table -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-6">All Transactions</h3>

        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="transactionsTable">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaction</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-[#2d1b6e]" id="transactionsBody">
                        <?php foreach ($data['transactions'] as $transaction): ?>
                        <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors transaction-row" 
                            data-type="<?= $transaction['type'] ?>" 
                            data-status="<?= $transaction['status'] ?>"
                            data-date="<?= $transaction['date'] ?>"
                            data-description="<?= strtolower($transaction['description']) ?>">
                            
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mr-3 <?php
                                        switch ($transaction['type']) {
                                            case 'deposit': echo 'bg-emerald-100 dark:bg-emerald-900/30'; break;
                                            case 'withdrawal': echo 'bg-red-100 dark:bg-red-900/30'; break;
                                            case 'investment': echo 'bg-blue-100 dark:bg-blue-900/30'; break;
                                            case 'earning': echo 'bg-amber-100 dark:bg-amber-900/30'; break;
                                            case 'referral': echo 'bg-purple-100 dark:bg-purple-900/30'; break;
                                            case 'transfer': echo 'bg-orange-100 dark:bg-orange-900/30'; break;
                                            default: echo 'bg-gray-100 dark:bg-gray-700';
                                        }
                                    ?>">
                                        <svg class="w-5 h-5 <?php
                                            switch ($transaction['type']) {
                                                case 'deposit': echo 'text-emerald-600 dark:text-emerald-400'; break;
                                                case 'withdrawal': echo 'text-red-600 dark:text-red-400'; break;
                                                case 'investment': echo 'text-blue-600 dark:text-blue-400'; break;
                                                case 'earning': echo 'text-amber-600 dark:text-amber-400'; break;
                                                case 'referral': echo 'text-purple-600 dark:text-purple-400'; break;
                                                case 'transfer': echo 'text-orange-600 dark:text-orange-400'; break;
                                                default: echo 'text-gray-600 dark:text-gray-400';
                                            }
                                        ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <?php
                                            switch ($transaction['type']) {
                                                case 'deposit':
                                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"></path>';
                                                    break;
                                                case 'withdrawal':
                                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"></path>';
                                                    break;
                                                case 'investment':
                                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>';
                                                    break;
                                                case 'earning':
                                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path>';
                                                    break;
                                                case 'transfer':
                                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>';
                                                    break;
                                                default:
                                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>';
                                            }
                                            ?>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars((string)($transaction['description'] ?? '')) ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">ID: <?= htmlspecialchars((string)$transaction['id']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span class="inline-block px-2.5 py-1 text-xs font-medium rounded-full <?php
                                    switch ($transaction['type']) {
                                        case 'deposit': echo 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'; break;
                                        case 'withdrawal': echo 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'; break;
                                        case 'investment': echo 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'; break;
                                        case 'earning': echo 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'; break;
                                        case 'referral': echo 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'; break;
                                        case 'transfer': echo 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'; break;
                                        default: echo 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-400';
                                    }
                                ?>">
                                    <?= ucwords(str_replace('_', ' ', $transaction['type'])) ?>
                                </span>
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php
                                // determine sign and color based on transaction type
                                $txAmount = abs((float)$transaction['amount']);
                                $txType = $transaction['type'] ?? '';
                                $isNegative = in_array($txType, ['withdrawal', 'investment']) || ($txType === 'transfer' && (float)$transaction['amount'] < 0);
                                $isPositive = in_array($txType, ['deposit', 'earning', 'profit', 'referral', 'bonus', 'principal_return']) || ($txType === 'transfer' && (float)$transaction['amount'] > 0);
                                if ($isNegative) {
                                    $amountClass = 'text-red-600 dark:text-red-400';
                                    $amountPrefix = '-';
                                } elseif ($isPositive) {
                                    $amountClass = 'text-emerald-600 dark:text-emerald-400';
                                    $amountPrefix = '+';
                                } else {
                                    $amountClass = (float)$transaction['amount'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
                                    $amountPrefix = (float)$transaction['amount'] >= 0 ? '+' : '-';
                                }
                                ?>
                                <div class="text-sm font-medium <?= $amountClass ?>">
                                    <?= $amountPrefix ?>$<?= number_format($txAmount, 2) ?>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span class="inline-block px-2.5 py-1 text-xs font-medium rounded-full <?php
                                    switch ($transaction['status']) {
                                        case 'completed': echo 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'; break;
                                        case 'pending': echo 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'; break;
                                        case 'active': echo 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'; break;
                                        case 'failed': echo 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'; break;
                                        default: echo 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-400';
                                    }
                                ?>">
                                    <?= ucfirst($transaction['status']) ?>
                                </span>
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="text-sm text-gray-900 dark:text-white"><?= date('M j, Y', strtotime($transaction['date'])) ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?= date('H:i', strtotime($transaction['date'])) ?></div>
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <button onclick="viewTransaction('<?= htmlspecialchars((string)$transaction['id']) ?>')" 
                                        class="text-[#1e0e62] dark:text-indigo-400 hover:text-[#2d1b8a] dark:hover:text-indigo-300 text-sm font-medium">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- no results message -->
        <div id="noResults" class="hidden text-center py-12">
            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-1">No transactions found</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Try adjusting your search criteria or filters</p>
        </div>

        <!-- simple pagination info -->
        <div class="mt-6 flex justify-between items-center text-sm text-gray-500 dark:text-gray-400">
            <div>
                Showing <span class="font-medium" id="showingStart">1</span> to <span class="font-medium" id="showingEnd">10</span> of <span class="font-medium" id="totalCount">10</span> results
            </div>
            <div class="text-xs">
                Use filters to refine results
            </div>
        </div>
    </div>
</div>

<script>
// filter and search functionality
function filterTransactions() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    const rows = document.querySelectorAll('.transaction-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const description = row.dataset.description;
        const type = row.dataset.type;
        const status = row.dataset.status;
        const date = new Date(row.dataset.date);
        
        // search filter
        const matchesSearch = !searchTerm || description.includes(searchTerm) || 
                            row.querySelector('td div div').textContent.toLowerCase().includes(searchTerm);
        
        // type filter
        const matchesType = !typeFilter || type === typeFilter;
        
        // status filter
        const matchesStatus = !statusFilter || status === statusFilter;
        
        // date range filter
        let matchesDate = true;
        if (startDate) {
            matchesDate = matchesDate && date >= new Date(startDate);
        }
        if (endDate) {
            matchesDate = matchesDate && date <= new Date(endDate + 'T23:59:59');
        }
        
        const isVisible = matchesSearch && matchesType && matchesStatus && matchesDate;
        
        if (isVisible) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // update pagination info
    document.getElementById('showingEnd').textContent = visibleCount;
    document.getElementById('totalCount').textContent = visibleCount;
    
    // show/hide no results message
    const noResults = document.getElementById('noResults');
    const table = document.getElementById('transactionsTable');
    
    if (visibleCount === 0) {
        noResults.classList.remove('hidden');
        table.style.display = 'none';
    } else {
        noResults.classList.add('hidden');
        table.style.display = '';
    }
}

// event listeners for filters
document.getElementById('search').addEventListener('input', filterTransactions);
document.getElementById('typeFilter').addEventListener('change', filterTransactions);
document.getElementById('statusFilter').addEventListener('change', filterTransactions);
document.getElementById('startDate').addEventListener('change', filterTransactions);
document.getElementById('endDate').addEventListener('change', filterTransactions);

// view transaction details
function viewTransaction(transactionId) {
    // simple alert for now - could be enhanced with a modal
    alert('Transaction details for ID: ' + transactionId + ' would be shown in a modal');
}

// set default date range and initialize
document.addEventListener('DOMContentLoaded', function() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(endDate.getDate() - 30);
    
    document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
    document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
    
    // initial count
    const totalRows = document.querySelectorAll('.transaction-row').length;
    document.getElementById('totalCount').textContent = totalRows;
    document.getElementById('showingEnd').textContent = totalRows;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>