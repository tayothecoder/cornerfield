<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\DashboardController;

// Auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

// Initialize controller and get data
// For demo/preview: wrap in try/catch so pages render even without DB
try {
    $transactionModel = new \App\Models\TransactionModel();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $rawTransactions = $transactionModel->findByUserId($userId);
    $stats = $transactionModel->getStats($userId);
    // map created_at to date for template compatibility
    $transactions = array_map(function ($tx) {
        $tx['date'] = $tx['created_at'] ?? '';
        $tx['description'] = $tx['description'] ?? ucwords(str_replace('_', ' ', $tx['type'] ?? ''));
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
    // Fallback demo data for preview
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

<!-- Transaction History Content -->
<div class="space-y-6">
    <!-- Header with Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Deposits -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center">
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Deposits</p>
                    <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_deposits'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Total Withdrawals -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center">
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Withdrawals</p>
                    <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_withdrawals'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Total Earnings -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Earnings</p>
                    <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_earnings'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Total Investments -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg mr-4">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Invested</p>
                    <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">$<?= number_format($data['stats']['total_investments'], 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4 flex-1">
                <!-- Search -->
                <div class="flex-1">
                    <label for="search" class="sr-only">Search transactions</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" id="search" placeholder="Search transactions..." 
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                    </div>
                </div>

                <!-- Type Filter -->
                <div class="sm:w-48">
                    <select id="typeFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        <option value="">All Types</option>
                        <option value="deposit">Deposits</option>
                        <option value="withdrawal">Withdrawals</option>
                        <option value="investment">Investments</option>
                        <option value="earning">Earnings</option>
                        <option value="referral">Referrals</option>
                        <option value="transfer">Transfers</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="sm:w-48">
                    <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>

            <!-- Date Range -->
            <div class="flex space-x-4">
                <input type="date" id="startDate" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                <input type="date" id="endDate" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">All Transactions</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="transactionsTable">
                <thead class="bg-[#f5f3ff] dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Transaction
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Amount
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="transactionsBody">
                    <?php foreach ($data['transactions'] as $transaction): ?>
                    <tr class="hover:bg-[#f5f3ff] dark:hover:bg-gray-700 transition-colors transaction-row" 
                        data-type="<?= $transaction['type'] ?>" 
                        data-status="<?= $transaction['status'] ?>"
                        data-date="<?= $transaction['date'] ?>"
                        data-description="<?= strtolower($transaction['description']) ?>">
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="p-2 rounded-lg mr-3 <?php
                                    switch ($transaction['type']) {
                                        case 'deposit': echo 'bg-green-100 dark:bg-green-900'; break;
                                        case 'withdrawal': echo 'bg-red-100 dark:bg-red-900'; break;
                                        case 'investment': echo 'bg-blue-100 dark:bg-blue-900'; break;
                                        case 'earning': echo 'bg-yellow-100 dark:bg-yellow-900'; break;
                                        case 'referral': echo 'bg-purple-100 dark:bg-purple-900'; break;
                                        case 'transfer': echo 'bg-orange-100 dark:bg-orange-900'; break;
                                        default: echo 'bg-gray-100 dark:bg-gray-700';
                                    }
                                ?>">
                                    <svg class="w-4 h-4 <?php
                                        switch ($transaction['type']) {
                                            case 'deposit': echo 'text-green-600 dark:text-green-400'; break;
                                            case 'withdrawal': echo 'text-red-600 dark:text-red-400'; break;
                                            case 'investment': echo 'text-blue-600 dark:text-blue-400'; break;
                                            case 'earning': echo 'text-yellow-600 dark:text-yellow-400'; break;
                                            case 'referral': echo 'text-purple-600 dark:text-purple-400'; break;
                                            case 'transfer': echo 'text-orange-600 dark:text-orange-400'; break;
                                            default: echo 'text-gray-600 dark:text-gray-400';
                                        }
                                    ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <?php
                                        switch ($transaction['type']) {
                                            case 'deposit':
                                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>';
                                                break;
                                            case 'withdrawal':
                                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>';
                                                break;
                                            case 'investment':
                                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>';
                                                break;
                                            case 'earning':
                                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>';
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

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php
                                switch ($transaction['type']) {
                                    case 'deposit': echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; break;
                                    case 'withdrawal': echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; break;
                                    case 'investment': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'; break;
                                    case 'earning': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'; break;
                                    case 'referral': echo 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'; break;
                                    case 'transfer': echo 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'; break;
                                    default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                }
                            ?>">
                                <span class="w-2 h-2 mr-1 rounded-full <?php
                                    switch ($transaction['type']) {
                                        case 'deposit': echo 'bg-green-400'; break;
                                        case 'withdrawal': echo 'bg-red-400'; break;
                                        case 'investment': echo 'bg-blue-400'; break;
                                        case 'earning': echo 'bg-yellow-400'; break;
                                        case 'referral': echo 'bg-purple-400'; break;
                                        case 'transfer': echo 'bg-orange-400'; break;
                                        default: echo 'bg-gray-400';
                                    }
                                ?>"></span>
                                <?= ucfirst($transaction['type']) ?>
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold <?= (float)$transaction['amount'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>">
                                <?= (float)$transaction['amount'] > 0 ? '+' : '' ?>$<?= number_format(abs((float)$transaction['amount']), 2) ?>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php
                                switch ($transaction['status']) {
                                    case 'completed': echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; break;
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'; break;
                                    case 'active': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'; break;
                                    case 'failed': echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; break;
                                    default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                }
                            ?>">
                                <span class="w-2 h-2 mr-1 rounded-full <?php
                                    switch ($transaction['status']) {
                                        case 'completed': echo 'bg-green-400'; break;
                                        case 'pending': echo 'bg-yellow-400'; break;
                                        case 'active': echo 'bg-blue-400'; break;
                                        case 'failed': echo 'bg-red-400'; break;
                                        default: echo 'bg-gray-400';
                                    }
                                ?>"></span>
                                <?= ucfirst($transaction['status']) ?>
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <div><?= date('M j, Y', strtotime($transaction['date'])) ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?= date('H:i', strtotime($transaction['date'])) ?></div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button onclick="viewTransaction('<?= htmlspecialchars((string)$transaction['id']) ?>')" 
                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- No results message -->
        <div id="noResults" class="hidden text-center py-12">
            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No transactions found</h3>
            <p class="text-gray-500 dark:text-gray-400">Try adjusting your search criteria or filters.</p>
        </div>

        <!-- Pagination -->
        <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-[#f5f3ff] dark:hover:bg-gray-600">
                    Previous
                </button>
                <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-[#f5f3ff] dark:hover:bg-gray-600">
                    Next
                </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Showing <span class="font-medium" id="showingStart">1</span> to <span class="font-medium" id="showingEnd">10</span> of <span class="font-medium" id="totalCount">10</span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-gray-600">
                            Previous
                        </button>
                        <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-indigo-50 dark:bg-indigo-900 border-indigo-500 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                            1
                        </button>
                        <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-[#f5f3ff] dark:hover:bg-gray-600">
                            Next
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filter and search functionality
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
        
        // Search filter
        const matchesSearch = !searchTerm || description.includes(searchTerm) || 
                            row.querySelector('td div div').textContent.toLowerCase().includes(searchTerm);
        
        // Type filter
        const matchesType = !typeFilter || type === typeFilter;
        
        // Status filter
        const matchesStatus = !statusFilter || status === statusFilter;
        
        // Date range filter
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
    
    // Update pagination info
    document.getElementById('showingEnd').textContent = visibleCount;
    document.getElementById('totalCount').textContent = visibleCount;
    
    // Show/hide no results message
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

// Event listeners for filters
document.getElementById('search').addEventListener('input', filterTransactions);
document.getElementById('typeFilter').addEventListener('change', filterTransactions);
document.getElementById('statusFilter').addEventListener('change', filterTransactions);
document.getElementById('startDate').addEventListener('change', filterTransactions);
document.getElementById('endDate').addEventListener('change', filterTransactions);

// View transaction details
function viewTransaction(transactionId) {
    showNotification('Transaction details modal would open here', 'info');
    // In a real app, this would open a modal or navigate to transaction details page
}

// Set default date range to last 30 days
document.addEventListener('DOMContentLoaded', function() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(endDate.getDate() - 30);
    
    document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
    document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
    
    // Initial count
    const totalRows = document.querySelectorAll('.transaction-row').length;
    document.getElementById('totalCount').textContent = totalRows;
    document.getElementById('showingEnd').textContent = totalRows;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>