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

try {
    $controller = new DashboardController();
    $data = $controller->getDashboardData();
} catch (\Throwable $e) {
    // fallback demo data
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

<div class="space-y-6">
    <!-- welcome -->
    <p class="text-sm text-gray-500 dark:text-gray-400">Welcome back, <?= htmlspecialchars($user['firstname']) ?></p>

    <!-- quick actions -->
    <div class="flex flex-wrap gap-2">
        <a href="<?= $base ?>/users/deposit.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
            Deposit
        </a>
        <a href="<?= $base ?>/users/withdraw.php" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
            Withdraw
        </a>
        <a href="<?= $base ?>/users/invest.php" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            Invest
        </a>
        <a href="<?= $base ?>/users/transfer.php" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Transfer
        </a>
    </div>

    <!-- stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Balance</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['totalBalance'] ?? 15420.50, 2) ?></p>
            <div class="flex items-center gap-1 mt-2 text-emerald-600 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                <span class="text-xs font-medium">+<?= number_format($data['balanceChange'] ?? 2.4, 1) ?>%</span>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Available Balance</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['availableBalance'] ?? 12890.25, 2) ?></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Ready to invest</p>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Earned</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white">$<?= number_format($data['totalEarned'] ?? 2530.25, 2) ?></p>
            <div class="flex items-center gap-1 mt-2 text-emerald-600 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                <span class="text-xs font-medium">+<?= number_format($data['earningsChange'] ?? 15.2, 1) ?>%</span>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Active Investments</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= $data['activeInvestments'] ?? 3 ?></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Running plans</p>
        </div>
    </div>

    <!-- transactions and investments -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <!-- recent transactions -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">Recent Transactions</h3>
                <a href="<?= $base ?>/users/transactions.php" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">View all</a>
            </div>
            <?php if (!empty($data['recentTransactions'])): ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($data['recentTransactions'], 0, 5) as $tx): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php
                                switch ($tx['type']) {
                                    case 'deposit': echo 'bg-emerald-100 dark:bg-emerald-900/30'; break;
                                    case 'withdrawal': echo 'bg-red-100 dark:bg-red-900/30'; break;
                                    case 'investment': echo 'bg-blue-100 dark:bg-blue-900/30'; break;
                                    case 'earning': echo 'bg-amber-100 dark:bg-amber-900/30'; break;
                                    default: echo 'bg-purple-100 dark:bg-purple-900/30';
                                }
                            ?>">
                                <svg class="w-4 h-4 <?php
                                    switch ($tx['type']) {
                                        case 'deposit': echo 'text-emerald-600 dark:text-emerald-400'; break;
                                        case 'withdrawal': echo 'text-red-600 dark:text-red-400'; break;
                                        case 'investment': echo 'text-blue-600 dark:text-blue-400'; break;
                                        case 'earning': echo 'text-amber-600 dark:text-amber-400'; break;
                                        default: echo 'text-purple-600 dark:text-purple-400';
                                    }
                                ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <?php
                                    switch ($tx['type']) {
                                        case 'deposit': echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>'; break;
                                        case 'withdrawal': echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/>'; break;
                                        case 'investment': echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>'; break;
                                        case 'earning': echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1"/>'; break;
                                        default: echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7"/>'; break;
                                    }
                                    ?>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($tx['description']) ?></p>
                                <p class="text-xs text-gray-400"><?= date('M j, H:i', strtotime($tx['date'])) ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium <?= $tx['amount'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>">
                                <?= $tx['amount'] > 0 ? '+' : '' ?>$<?= number_format(abs($tx['amount']), 2) ?>
                            </p>
                            <span class="inline-block px-1.5 py-0.5 text-[10px] font-medium rounded-full <?php
                                switch ($tx['status']) {
                                    case 'completed': echo 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'; break;
                                    case 'pending': echo 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'; break;
                                    case 'active': echo 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'; break;
                                    default: echo 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-400';
                                }
                            ?>">
                                <?= ucfirst($tx['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-sm text-gray-400">No transactions yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- active investments -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">Active Investments</h3>
                <a href="<?= $base ?>/users/invest.php" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">View all</a>
            </div>
            <?php if (!empty($data['activeInvestmentList'])): ?>
                <div class="space-y-4">
                    <?php foreach (array_slice($data['activeInvestmentList'], 0, 3) as $inv): ?>
                    <div class="p-4 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($inv['plan']) ?></h4>
                            <span class="text-sm font-light text-gray-500 dark:text-gray-400">$<?= number_format($inv['amount'], 2) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <span>$<?= number_format($inv['daily_return'], 2) ?>/day</span>
                            <span>Earned: $<?= number_format($inv['total_return'], 2) ?></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-[#2d1b6e] rounded-full h-1.5">
                            <div class="bg-[#1e0e62] dark:bg-indigo-400 h-1.5 rounded-full" style="width: <?= $inv['progress'] ?>%"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                            <span><?= number_format($inv['progress'], 1) ?>%</span>
                            <span>Expires <?= date('M j', strtotime($inv['expires'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <p class="text-sm text-gray-400">No active investments</p>
                    <a href="<?= $base ?>/users/invest.php" class="text-xs text-[#1e0e62] dark:text-indigo-400 font-medium mt-1 inline-block">Start investing</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
