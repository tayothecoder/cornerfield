<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

$pageTitle = 'Dashboard';

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Initialize session
\App\Utils\SessionManager::start();

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    $adminSettingsModel = new \App\Models\AdminSettings($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Initialize SystemHealth checker
$systemHealth = new \App\Utils\SystemHealth($database);
$healthData = $systemHealth->getSystemHealth();

if (!$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $adminController->logout();
    header('Location: login.php');
    exit;
}

$currentAdmin = $adminController->getCurrentAdmin();
$dashboardData = $adminController->getDashboardData();

$siteName = $adminSettingsModel->getSetting('site_name', 'Cornerfield Investment Platform');
$currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    \App\Utils\CSRFProtection::validateRequest();
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'update_schema':
            $result = $adminController->updateInvestmentSchema($_POST['schema_id'], $_POST);
            echo json_encode($result);
            exit;
        case 'delete_schema':
            $result = $adminController->deleteInvestmentSchema($_POST['schema_id']);
            echo json_encode($result);
            exit;
        case 'create_schema':
            $result = $adminController->createInvestmentSchema($_POST);
            echo json_encode($result);
            exit;
        case 'get_stats':
            $stats = [
                'total_users' => $dashboardData['stats']['total_users'] ?? 0,
                'total_deposits' => $dashboardData['stats']['total_deposits'] ?? 0,
                'total_investments' => $dashboardData['stats']['total_investment_amount'] ?? 0,
                'pending_withdrawals' => $dashboardData['stats']['pending_withdrawals'] ?? 0
            ];
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
    }
}

if (!$dashboardData) {
    $dashboardData = [
        'stats' => [
            'total_users' => 0, 'total_investments' => 0, 'total_investment_amount' => 0,
            'active_schemas' => 0, 'total_deposits' => 0, 'pending_withdrawals' => 0,
            'total_profits_distributed' => 0, 'active_users_today' => 0
        ],
        'investment_schemas' => [], 'recent_investments' => [], 'recent_users' => [],
        'recent_deposits' => [], 'recent_withdrawals' => [], 'recent_transactions' => []
    ];
}

try {
    $pendingDeposits = $database->fetchOne("SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'")['count'] ?? 0;
    $pendingWithdrawals = $database->fetchOne("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'pending'")['count'] ?? 0;
    $pendingTransactions = $database->fetchOne("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'")['count'] ?? 0;
    $todayUsers = $database->fetchOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
    $todayDeposits = $database->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'deposit' AND DATE(created_at) = CURDATE()")['total'] ?? 0;
    $todayInvestments = $database->fetchOne("SELECT COALESCE(SUM(invest_amount), 0) as total FROM investments WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;

    $dashboardData['stats']['pending_deposits'] = $pendingDeposits;
    $dashboardData['stats']['pending_withdrawals'] = $pendingWithdrawals;
    $dashboardData['stats']['pending_transactions'] = $pendingTransactions;
    $dashboardData['stats']['today_users'] = $todayUsers;
    $dashboardData['stats']['today_deposits'] = $todayDeposits;
    $dashboardData['stats']['today_investments'] = $todayInvestments;

    $dashboardData['recent_deposits'] = $database->fetchAll("
        SELECT d.*, u.email as user_email, dm.name as method_name 
        FROM deposits d 
        JOIN users u ON d.user_id = u.id 
        JOIN deposit_methods dm ON d.deposit_method_id = dm.id 
        ORDER BY d.created_at DESC 
        LIMIT 10
    ");
    $dashboardData['recent_withdrawals'] = $database->fetchAll("
        SELECT w.*, u.email as user_email 
        FROM withdrawals w 
        JOIN users u ON w.user_id = u.id 
        ORDER BY w.created_at DESC 
        LIMIT 10
    ");
} catch (Exception $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
}

$pageTitle = 'Admin Dashboard - ' . \App\Config\Config::getSiteName();
$currentPage = 'dashboard';

include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Users</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($dashboardData['stats']['total_users']) ?></p>
            <div class="flex items-center gap-1 mt-2 text-emerald-600 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                <span class="text-xs font-medium">+<?= $dashboardData['stats']['today_users'] ?? 0 ?> today</span>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Deposits</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['total_deposits'] ?? 0, 2) ?></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2"><?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['today_deposits'] ?? 0, 2) ?> today</p>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Investments</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['total_investment_amount'], 2) ?></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2"><?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['today_investments'] ?? 0, 2) ?> today</p>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Pending Withdrawals</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($dashboardData['stats']['pending_withdrawals']) ?></p>
            <?php if ($dashboardData['stats']['pending_withdrawals'] > 0): ?>
            <div class="flex items-center gap-1 mt-2 text-amber-600 dark:text-amber-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs font-medium">requires attention</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- quick actions -->
    <div class="flex flex-wrap gap-2">
        <a href="deposits.php?filter=pending" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
            Pending Deposits
            <?php if (($dashboardData['stats']['pending_deposits'] ?? 0) > 0): ?>
                <span class="bg-white/20 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $dashboardData['stats']['pending_deposits'] ?></span>
            <?php endif; ?>
        </a>
        <a href="withdrawals.php?filter=pending" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
            Pending Withdrawals
            <?php if (($dashboardData['stats']['pending_withdrawals'] ?? 0) > 0): ?>
                <span class="bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $dashboardData['stats']['pending_withdrawals'] ?></span>
            <?php endif; ?>
        </a>
        <a href="users.php" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
            Manage Users
        </a>
        <a href="investment-plans.php" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
            Investment Plans
        </a>
    </div>

    <!-- recent activity grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- recent users -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-6 pb-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent Users</h3>
                <a href="users.php" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">View all</a>
            </div>
            <div class="p-6 pt-4">
                <?php if (!empty($dashboardData['recent_users'])): ?>
                <div class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php foreach (array_slice($dashboardData['recent_users'], 0, 5) as $user): ?>
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-[#1e0e62]/10 dark:bg-white/10 flex items-center justify-center text-[#1e0e62] dark:text-white text-xs font-medium flex-shrink-0">
                                <?= strtoupper(substr($user['email'], 0, 2)) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($user['email']) ?></p>
                                <p class="text-xs text-gray-400"><?= date('M j', strtotime($user['created_at'])) ?></p>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($user['balance'], 2) ?></p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $user['is_active'] ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' ?>">
                                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-8">No users yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- recent deposits -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-6 pb-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent Deposits</h3>
                <a href="deposits.php" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">View all</a>
            </div>
            <div class="p-6 pt-4">
                <?php if (!empty($dashboardData['recent_deposits'])): ?>
                <div class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php foreach (array_slice($dashboardData['recent_deposits'], 0, 5) as $deposit): ?>
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($deposit['user_email']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($deposit['method_name']) ?> - <?= date('M j, g:i A', strtotime($deposit['created_at'])) ?></p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($deposit['requested_amount'], 2) ?></p>
                            <?php
                            $sc = $deposit['status'] === 'completed' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : ($deposit['status'] === 'pending' ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500');
                            ?>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $sc ?>"><?= ucfirst($deposit['status']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-8">No deposits yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- recent investments -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-6 pb-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent Investments</h3>
                <a href="investment-plans.php" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">View all</a>
            </div>
            <div class="p-6 pt-4">
                <?php if (!empty($dashboardData['recent_investments'])): ?>
                <div class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php foreach (array_slice($dashboardData['recent_investments'], 0, 5) as $investment): ?>
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($investment['user_email'] ?? 'Unknown User') ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($investment['schema_name'] ?? 'Unknown Plan') ?> - <?= date('M j', strtotime($investment['created_at'])) ?></p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($investment['invest_amount'], 2) ?></p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $investment['status'] === 'active' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' ?>"><?= ucfirst($investment['status']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-8">No investments yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- recent withdrawals -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-6 pb-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent Withdrawals</h3>
                <a href="withdrawals.php" class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400">View all</a>
            </div>
            <div class="p-6 pt-4">
                <?php if (!empty($dashboardData['recent_withdrawals'])): ?>
                <div class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php foreach (array_slice($dashboardData['recent_withdrawals'], 0, 5) as $withdrawal): ?>
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($withdrawal['user_email']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($withdrawal['currency']) ?> - <?= date('M j, g:i A', strtotime($withdrawal['created_at'])) ?></p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($withdrawal['requested_amount'], 2) ?></p>
                            <?php
                            $wc = match($withdrawal['status']) {
                                'completed' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400',
                                'pending' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400',
                                'processing' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400',
                                default => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400'
                            };
                            ?>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $wc ?>"><?= ucfirst($withdrawal['status']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-8">No withdrawals yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- system status -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php foreach (['platform', 'profits', 'database'] as $key): ?>
        <?php if (isset($healthData[$key])): ?>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= ucfirst($key) ?></p>
                <span class="w-2 h-2 rounded-full <?= $healthData[$key]['color'] === 'success' ? 'bg-emerald-500' : ($healthData[$key]['color'] === 'warning' ? 'bg-amber-500' : 'bg-red-500') ?>"></span>
            </div>
            <p class="text-lg font-medium text-gray-900 dark:text-white mb-1"><?= htmlspecialchars($healthData[$key]['message']) ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($healthData[$key]['details']) ?></p>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- create investment plan modal -->
<div id="modal-create-plan" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create Investment Plan</h3>
            <button onclick="hideModal('modal-create-plan')" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="create-plan-form" class="p-6 space-y-4">
            <?= \App\Utils\CSRFProtection::getTokenField() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Plan name</label>
                    <input type="text" name="name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="e.g. Bitcoin Starter" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Daily rate (%)</label>
                    <input type="number" name="daily_rate" step="0.01" min="0" max="100" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="2.50" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Minimum amount</label>
                    <input type="number" name="min_amount" step="0.01" min="0" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="50.00" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Maximum amount</label>
                    <input type="number" name="max_amount" step="0.01" min="0" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="999.99" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Duration (days)</label>
                    <input type="number" name="duration_days" min="1" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="30" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Total return (%)</label>
                    <input type="number" name="total_return" step="0.01" min="0" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="Auto-calculated" readonly>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" placeholder="Describe this investment plan..."></textarea>
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="featured" class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Featured plan</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" checked class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active</span>
                </label>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="hideModal('modal-create-plan')" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-full hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageSpecificJS = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    var dailyRateInput = document.querySelector("input[name=\"daily_rate\"]");
    var durationInput = document.querySelector("input[name=\"duration_days\"]");
    var totalReturnInput = document.querySelector("input[name=\"total_return\"]");

    function calculateTotalReturn() {
        var dailyRate = parseFloat(dailyRateInput.value) || 0;
        var duration = parseInt(durationInput.value) || 0;
        if (dailyRate > 0 && duration > 0) {
            totalReturnInput.value = (dailyRate * duration).toFixed(2);
        } else {
            totalReturnInput.value = "";
        }
    }

    if (dailyRateInput && durationInput && totalReturnInput) {
        dailyRateInput.addEventListener("input", calculateTotalReturn);
        durationInput.addEventListener("input", calculateTotalReturn);
    }

    var createForm = document.getElementById("create-plan-form");
    if (createForm) {
        createForm.addEventListener("submit", function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append("ajax", "1");
            formData.append("action", "create_schema");
            formData.set("featured", formData.get("featured") ? "1" : "0");
            formData.set("status", formData.get("status") ? "1" : "0");

            fetch("dashboard.php", { method: "POST", body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showNotification("Investment plan created successfully", "success");
                        hideModal("modal-create-plan");
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        showNotification("Error: " + (data.message || "Unknown error"), "error");
                    }
                })
                .catch(function() { showNotification("Network error", "error"); });
        });
    }
});
</script>
';

include __DIR__ . '/includes/footer.php';
?>
