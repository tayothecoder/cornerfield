<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Models\Investment;
use App\Models\Transaction;
use App\Utils\SessionManager;
use App\Models\AdminSettings;

try {
    $database = new Database();
    $adminSettingsModel = new AdminSettings($database);
    $maintenanceMode = $adminSettingsModel->getSetting('maintenance_mode', 0);

    if ($maintenanceMode && !isset($_GET['admin_bypass'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Site Under Maintenance - <?= Config::getSiteName() ?></title>
            <!-- Tailwind CSS CDN -->
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
            </style>
        </head>
        <body class="min-h-screen bg-gradient-to-br from-blue-500 via-purple-500 to-blue-600 flex items-center justify-center text-white">
            <div class="text-center p-8 bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl max-w-md">
                <div class="text-6xl mb-6 bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">₿</div>
                <h1 class="text-3xl font-bold mb-4">Site Under Maintenance</h1>
                <p class="text-lg mb-6 opacity-90">We're currently performing scheduled maintenance. Please check back shortly.</p>
                <div class="text-sm opacity-75">Expected completion: Within 2 hours</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    // If database fails, allow access
}

// Start session and check authentication
SessionManager::start();

// Check if we're impersonating
$isImpersonating = SessionManager::get('is_impersonating');
$impersonatedUserId = SessionManager::get('impersonating_user_id');

if ($isImpersonating && $impersonatedUserId) {
    $user_id = $impersonatedUserId;
    error_log("Impersonation active - Using user ID: $user_id");
} elseif (!SessionManager::get('user_logged_in')) {
    header('Location: ../login.php');
    exit;
} else {
    $user_id = SessionManager::get('user_id');
}

try {
    $database = new Database();
    $userModel = new User($database);
    $currentUser = $userModel->findById($user_id);

    if (!$currentUser) {
        header('Location: ../login.php');
        exit;
    }

    $stats = $userModel->getUserStats($user_id);
    $investmentHistory = $userModel->getInvestmentHistory($user_id, 5);
    $investmentModel = new Investment($database);
    $transactionModel = new Transaction($database);
    $investmentSchemas = $investmentModel->getAllSchemas();
    $recentTransactions = $transactionModel->getUserTransactions($user_id, null, 5);

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Impersonation alert
$impersonationAlert = '';
if (SessionManager::get('is_impersonating')) {
    $impersonationAlert = '<div class="mb-6 bg-gradient-to-r from-yellow-400 to-orange-500 text-white p-4 rounded-xl shadow-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div>
                    <strong class="font-semibold">Admin Impersonation Active</strong>
                    <div class="text-sm opacity-90">You are viewing this account as an administrator. All actions are being logged.</div>
                </div>
            </div>
            <a href="../admin/stop-impersonation.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition-colors text-sm font-medium">
                Return to Admin Panel
            </a>
        </div>
    </div>';
}

include __DIR__ . '/includes/header.php';
?>

<!-- Impersonation Alert -->
<?= $impersonationAlert ?>

<!-- Welcome Section -->
<div class="bg-gradient-to-br from-cf-primary via-cf-secondary to-cf-primary text-white rounded-2xl p-8 mb-8 shadow-2xl shadow-cf-primary/20 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-white/10 via-transparent to-white/5 animate-pulse"></div>
    <div class="relative z-10">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Welcome back, <?= htmlspecialchars($currentUser['first_name'] ?? 'User') ?>! 👋</h1>
        <p class="text-lg opacity-95">Here's what's happening with your investments today</p>
    </div>
    <!-- Floating decorative elements -->
    <div class="absolute top-4 right-4 w-20 h-20 bg-white/10 rounded-full blur-xl animate-bounce"></div>
    <div class="absolute bottom-4 left-4 w-16 h-16 bg-white/5 rounded-full blur-lg animate-pulse"></div>
</div>

<!-- Statistics Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    <!-- Available Balance Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cf-success to-emerald-400"></div>
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-r from-cf-success to-emerald-400 rounded-xl flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            $<?= number_format($stats['balance'] ?? 0, 2) ?>
        </div>
        <div class="text-gray-600 dark:text-gray-300 font-medium">Available Balance</div>
    </div>

    <!-- Total Earned Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cf-warning to-amber-400"></div>
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-r from-cf-warning to-amber-400 rounded-xl flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            $<?= number_format($stats['total_earned'] ?? 0, 2) ?>
        </div>
        <div class="text-gray-600 dark:text-gray-300 font-medium">Total Earned</div>
    </div>

    <!-- Active Investments Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cf-primary to-cf-info"></div>
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-r from-cf-primary to-cf-info rounded-xl flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            <?= number_format($stats['active_investments'] ?? 0) ?>
        </div>
        <div class="text-gray-600 dark:text-gray-300 font-medium">Active Investments</div>
    </div>

    <!-- Total Referrals Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cf-secondary to-purple-400"></div>
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-r from-cf-secondary to-purple-400 rounded-xl flex items-center justify-center shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            <?= number_format($stats['referral_count'] ?? 0) ?>
        </div>
        <div class="text-gray-600 dark:text-gray-300 font-medium">Total Referrals</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700 mb-8">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 flex items-center gap-3">
        <svg class="w-6 h-6 text-cf-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
        </svg>
        Quick Actions
    </h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="deposit.php" class="group bg-gradient-to-br from-cf-success/10 to-emerald-50 dark:from-cf-success/20 dark:to-slate-700 border border-cf-success/20 rounded-xl p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex flex-col items-center text-center space-y-3">
                <div class="w-12 h-12 bg-gradient-to-r from-cf-success to-emerald-400 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white">Deposit Funds</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">Add money via crypto</div>
                </div>
            </div>
        </a>

        <a href="withdraw.php" class="group bg-gradient-to-br from-cf-warning/10 to-amber-50 dark:from-cf-warning/20 dark:to-slate-700 border border-cf-warning/20 rounded-xl p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex flex-col items-center text-center space-y-3">
                <div class="w-12 h-12 bg-gradient-to-r from-cf-warning to-amber-400 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m16 0l-4-4m4 4l-4 4"></path>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white">Withdraw</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">Cash out your profits</div>
                </div>
            </div>
        </a>

        <a href="referrals.php" class="group bg-gradient-to-br from-cf-secondary/10 to-purple-50 dark:from-cf-secondary/20 dark:to-slate-700 border border-cf-secondary/20 rounded-xl p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex flex-col items-center text-center space-y-3">
                <div class="w-12 h-12 bg-gradient-to-r from-cf-secondary to-purple-400 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white">Referrals</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">Earn commissions</div>
                </div>
            </div>
        </a>

        <a href="transactions.php" class="group bg-gradient-to-br from-cf-info/10 to-blue-50 dark:from-cf-info/20 dark:to-slate-700 border border-cf-info/20 rounded-xl p-4 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex flex-col items-center text-center space-y-3">
                <div class="w-12 h-12 bg-gradient-to-r from-cf-info to-blue-400 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white">Transactions</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">View all activity</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Investment Plans -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 flex items-center gap-3">
            <svg class="w-6 h-6 text-cf-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Investment Plans
        </h2>
        
        <?php if (!empty($investmentSchemas)): ?>
            <div class="space-y-4">
                <?php foreach ($investmentSchemas as $schema): ?>
                    <div class="bg-gradient-to-br from-cf-primary via-cf-secondary to-cf-primary text-white rounded-2xl p-6 hover:shadow-2xl transition-all duration-300 transform hover:scale-[1.02] relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-white/10 via-transparent to-white/5"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold"><?= htmlspecialchars($schema['name']) ?></h3>
                                <span class="bg-white/20 backdrop-blur-sm px-3 py-1 rounded-full font-semibold text-sm">
                                    <?= number_format($schema['daily_rate'], 2) ?>% Daily
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold">$<?= number_format($schema['min_amount'], 0) ?></div>
                                    <div class="text-sm opacity-90">Min Investment</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold"><?= $schema['duration_days'] ?> Days</div>
                                    <div class="text-sm opacity-90">Duration</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold"><?= number_format($schema['total_return'] * 100, 1) ?>%</div>
                                    <div class="text-sm opacity-90">Total Return</div>
                                </div>
                            </div>
                            <a href="invest.php?plan=<?= $schema['id'] ?>" class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 px-6 py-3 rounded-xl font-semibold transition-all transform hover:scale-105">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Invest Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <p class="text-gray-500 dark:text-gray-400">No investment plans available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-slate-700">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 flex items-center gap-3">
            <svg class="w-6 h-6 text-cf-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Recent Activity
        </h2>
        
        <?php if (!empty($recentTransactions)): ?>
            <div class="space-y-4">
                <?php foreach ($recentTransactions as $transaction): ?>
                    <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-sm
                            <?php
                            switch (strtolower($transaction['type'])) {
                                case 'deposit':
                                    echo 'bg-gradient-to-r from-cf-success to-emerald-400 text-white';
                                    break;
                                case 'withdrawal':
                                case 'withdraw':
                                    echo 'bg-gradient-to-r from-cf-warning to-amber-400 text-white';
                                    break;
                                case 'profit':
                                    echo 'bg-gradient-to-r from-cf-info to-blue-400 text-white';
                                    break;
                                default:
                                    echo 'bg-gradient-to-r from-gray-400 to-gray-500 text-white';
                            }
                            ?>">
                            <?php
                            switch (strtolower($transaction['type'])) {
                                case 'deposit':
                                    echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>';
                                    break;
                                case 'withdrawal':
                                case 'withdraw':
                                    echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>';
                                    break;
                                case 'profit':
                                    echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>';
                                    break;
                                default:
                                    echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>';
                            }
                            ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 dark:text-white">
                                <?= htmlspecialchars(ucfirst($transaction['type'])) ?>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?>
                            </div>
                        </div>
                        <div class="font-semibold
                            <?= $transaction['amount'] >= 0 ? 'text-cf-success' : 'text-cf-danger' ?>">
                            <?= ($transaction['amount'] >= 0 ? '+' : '') ?>$<?= number_format(abs($transaction['amount']), 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-6">
                <a href="transactions.php" class="w-full flex items-center justify-center gap-2 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-300 font-medium py-3 px-4 rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    View All Transactions
                </a>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-gray-500 dark:text-gray-400">No recent transactions.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>