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

try {
    $controller = new DashboardController();
    $data = $controller->getDashboardData();
} catch (\Throwable $e) {
    // Fallback demo data for preview
    $data = [
        'totalBalance' => 15420.50,
        'availableBalance' => 12890.25,
        'totalEarned' => 2530.25,
        'activeInvestments' => 3,
        'balanceChange' => 2.4,
        'earningsChange' => 15.2,
        'recentTransactions' => [
            ['id' => 1, 'type' => 'deposit', 'amount' => 500.00, 'status' => 'completed', 'date' => '2024-02-10 14:30:00', 'description' => 'Bitcoin Deposit'],
            ['id' => 2, 'type' => 'investment', 'amount' => -1000.00, 'status' => 'active', 'date' => '2024-02-09 10:15:00', 'description' => 'Premium Plan Investment'],
            ['id' => 3, 'type' => 'earning', 'amount' => 125.50, 'status' => 'completed', 'date' => '2024-02-08 16:45:00', 'description' => 'Daily ROI Payout'],
            ['id' => 4, 'type' => 'withdrawal', 'amount' => -250.00, 'status' => 'pending', 'date' => '2024-02-07 11:20:00', 'description' => 'USDT Withdrawal'],
            ['id' => 5, 'type' => 'referral', 'amount' => 50.00, 'status' => 'completed', 'date' => '2024-02-06 09:10:00', 'description' => 'Referral Commission']
        ],
        'activeInvestmentList' => [
            ['id' => 1, 'plan' => 'Starter Plan', 'amount' => 1000.00, 'daily_return' => 25.00, 'total_return' => 375.00, 'progress' => 37.5, 'expires' => '2024-03-10'],
            ['id' => 2, 'plan' => 'Premium Plan', 'amount' => 2500.00, 'daily_return' => 87.50, 'total_return' => 787.50, 'progress' => 31.5, 'expires' => '2024-03-15'],
            ['id' => 3, 'plan' => 'VIP Plan', 'amount' => 5000.00, 'daily_return' => 200.00, 'total_return' => 1200.00, 'progress' => 24.0, 'expires' => '2024-03-20']
        ],
        'chartData' => [150, 280, 220, 340, 290, 410, 380, 450, 420, 510, 480, 520]
    ];
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Dashboard Content -->
<div class="space-y-6">
    <!-- Welcome section -->
    <div class="cf-gradient rounded-2xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-bold mb-2">Welcome back, <?= htmlspecialchars($user['firstname']) ?>! 👋</h2>
                <p class="text-blue-100">Here's your investment overview for today.</p>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <a href="/users/invest.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white px-4 py-2 rounded-lg font-medium transition-all duration-200">
                    Start Investing
                </a>
                <a href="/users/deposit.php" class="bg-white text-indigo-600 hover:bg-gray-50 px-4 py-2 rounded-lg font-medium transition-all duration-200">
                    Deposit Funds
                </a>
            </div>
        </div>
    </div>

    <!-- Balance Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Balance -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900 rounded-lg">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div class="flex items-center text-green-500">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm font-medium">+<?= number_format($data['balanceChange'] ?? 2.4, 1) ?>%</span>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Balance</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['totalBalance'] ?? 15420.50, 2) ?></p>
            <canvas class="mini-chart mt-3 w-full h-12" width="200" height="48" data-chart="[<?= implode(',', $data['chartData'] ?? [150, 280, 220, 340, 290, 410, 380, 450, 420, 510, 480, 520]) ?>]" data-color="#667eea"></canvas>
        </div>

        <!-- Available Balance -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Available Balance</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['availableBalance'] ?? 12890.25, 2) ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ready for investment</p>
        </div>

        <!-- Total Earned -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="flex items-center text-green-500">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm font-medium">+<?= number_format($data['earningsChange'] ?? 15.2, 1) ?>%</span>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Earned</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['totalEarned'] ?? 2530.25, 2) ?></p>
            <p class="text-sm text-green-600 dark:text-green-400 mt-1">Lifetime earnings</p>
        </div>

        <!-- Active Investments -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Investments</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $data['activeInvestments'] ?? 3 ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Running plans</p>
        </div>
    </div>

    <!-- Charts and Stats Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Investment Performance Chart -->
        <div class="lg:col-span-2 cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Investment Performance</h3>
                <div class="flex space-x-2">
                    <button class="px-3 py-1 text-sm bg-indigo-100 text-indigo-600 rounded-lg">7D</button>
                    <button class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 rounded-lg">30D</button>
                    <button class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 rounded-lg">90D</button>
                </div>
            </div>
            <div class="h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-center">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="text-gray-500">Investment chart will be displayed here</p>
                    <p class="text-sm text-gray-400 mt-2">Chart.js integration coming soon</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Quick Actions</h3>
            <div class="space-y-4">
                <a href="/users/deposit.php" class="w-full flex items-center p-4 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-lg transition-colors">
                    <div class="p-2 bg-green-500 rounded-lg mr-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Deposit</p>
                        <p class="text-sm text-gray-500">Add funds to your account</p>
                    </div>
                </a>

                <a href="/users/withdraw.php" class="w-full flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
                    <div class="p-2 bg-blue-500 rounded-lg mr-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Withdraw</p>
                        <p class="text-sm text-gray-500">Withdraw your profits</p>
                    </div>
                </a>

                <a href="/users/invest.php" class="w-full flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30 rounded-lg transition-colors">
                    <div class="p-2 bg-purple-500 rounded-lg mr-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Invest</p>
                        <p class="text-sm text-gray-500">Start new investment</p>
                    </div>
                </a>

                <a href="/users/referrals.php" class="w-full flex items-center p-4 bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 dark:hover:bg-orange-900/30 rounded-lg transition-colors">
                    <div class="p-2 bg-orange-500 rounded-lg mr-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Referrals</p>
                        <p class="text-sm text-gray-500">Invite friends & earn</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Active Investments & Recent Transactions -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <!-- Active Investments -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Investments</h3>
                <a href="/users/invest.php" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">View All</a>
            </div>
            <?php if (!empty($data['activeInvestmentList'])): ?>
                <div class="space-y-4">
                    <?php foreach (array_slice($data['activeInvestmentList'], 0, 3) as $investment): ?>
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($investment['plan']) ?></h4>
                            <span class="text-sm text-gray-500">$<?= number_format($investment['amount'], 2) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300 mb-2">
                            <span>Daily: $<?= number_format($investment['daily_return'], 2) ?></span>
                            <span>Total: $<?= number_format($investment['total_return'], 2) ?></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width: <?= $investment['progress'] ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span><?= number_format($investment['progress'], 1) ?>% complete</span>
                            <span>Expires: <?= date('M j', strtotime($investment['expires'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <p class="text-gray-500">No active investments</p>
                    <a href="/users/invest.php" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium mt-2 inline-block">Start investing now</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions -->
        <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                <a href="/users/transactions.php" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">View All</a>
            </div>
            <?php if (!empty($data['recentTransactions'])): ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($data['recentTransactions'], 0, 5) as $transaction): ?>
                    <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg mr-3 <?php
                                switch ($transaction['type']) {
                                    case 'deposit': echo 'bg-green-100 dark:bg-green-900'; break;
                                    case 'withdrawal': echo 'bg-red-100 dark:bg-red-900'; break;
                                    case 'investment': echo 'bg-blue-100 dark:bg-blue-900'; break;
                                    case 'earning': echo 'bg-yellow-100 dark:bg-yellow-900'; break;
                                    case 'referral': echo 'bg-purple-100 dark:bg-purple-900'; break;
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
                                        default:
                                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>';
                                    }
                                    ?>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($transaction['description']) ?></p>
                                <p class="text-xs text-gray-500"><?= date('M j, Y H:i', strtotime($transaction['date'])) ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-sm <?= $transaction['amount'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $transaction['amount'] > 0 ? '+' : '' ?>$<?= number_format(abs($transaction['amount']), 2) ?>
                            </p>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php
                                switch ($transaction['status']) {
                                    case 'completed': echo 'bg-green-100 text-green-800'; break;
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'active': echo 'bg-blue-100 text-blue-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                            ?>">
                                <?= ucfirst($transaction['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p class="text-gray-500">No transactions yet</p>
                    <p class="text-sm text-gray-400 mt-1">Your transaction history will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>