<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// initialize session
\App\Utils\SessionManager::start();

// page setup
$pageTitle = 'Investments Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'investments';

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

if (!$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentAdmin = $adminController->getCurrentAdmin();
$currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');

// get filter parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// build where clause
$whereConditions = [];
$params = [];

switch ($filter) {
    case 'active':
        $whereConditions[] = "i.status = 'active'";
        break;
    case 'completed':
        $whereConditions[] = "i.status = 'completed'";
        break;
    case 'cancelled':
        $whereConditions[] = "i.status = 'cancelled'";
        break;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// get investments with user and plan info
$investments = $database->fetchAll("
    SELECT i.*, u.username, u.email, u.first_name, u.last_name,
           s.name as plan_name, s.daily_rate, s.duration_days
    FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN investment_schemas s ON i.schema_id = s.id
    $whereClause
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
", array_merge($params, [$limit, $offset]));

// get stats
$stats = $database->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        COALESCE(SUM(invest_amount), 0) as total_amount,
        COALESCE(SUM(total_profit_amount), 0) as total_profit
    FROM investments
");

// get total count for pagination
$totalCount = $database->fetchOne("
    SELECT COUNT(*) as count FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN investment_schemas s ON i.schema_id = s.id
    $whereClause
", $params)['count'];
$totalPages = ceil($totalCount / $limit);

include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Investments</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($stats['total']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Active</p>
            <p class="text-3xl font-light tracking-tighter text-emerald-600 dark:text-emerald-400"><?= number_format($stats['active']) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Invested</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($stats['total_amount'], 2) ?></p>
        </div>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Profit Paid</p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($stats['total_profit'], 2) ?></p>
        </div>
    </div>

    <!-- filters -->
    <div class="flex flex-wrap gap-2">
        <?php foreach (['all' => 'All', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $fk => $fl): ?>
        <a href="?filter=<?= $fk ?>" class="px-4 py-2 text-sm font-medium rounded-full transition-colors <?= $filter === $fk ? 'bg-[#1e0e62] text-white' : 'border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 hover:border-[#1e0e62]' ?>">
            <?= $fl ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- table -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-white/5">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">End Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Earned</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                    <?php if (empty($investments)): ?>
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No investments found</td></tr>
                    <?php else: ?>
                        <?php foreach ($investments as $inv): ?>
                        <?php
                            $endDate = date('Y-m-d', strtotime($inv['created_at'] . ' + ' . $inv['duration_days'] . ' days'));
                            $sc = match($inv['status']) {
                                'active' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400',
                                'completed' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400',
                                'cancelled' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
                                default => 'bg-gray-100 dark:bg-gray-800 text-gray-500'
                            };
                        ?>
                        <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars(trim(($inv['first_name'] ?? '') . ' ' . ($inv['last_name'] ?? '')) ?: $inv['username']) ?></p>
                                <p class="text-xs text-gray-400">@<?= htmlspecialchars($inv['username']) ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($inv['plan_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= number_format($inv['daily_rate'], 2) ?>% daily / <?= $inv['duration_days'] ?> days</p>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currencySymbol) ?><?= number_format($inv['invest_amount'], 2) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y', strtotime($inv['created_at'])) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y', strtotime($endDate)) ?></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $sc ?>"><?= ucfirst($inv['status']) ?></span></td>
                            <td class="px-4 py-3 text-sm font-medium text-emerald-600 dark:text-emerald-400"><?= htmlspecialchars($currencySymbol) ?><?= number_format($inv['total_profit_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-6 border-t border-gray-100 dark:border-[#2d1b6e] flex justify-center">
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $i ?>" class="px-3 py-1 text-sm rounded-full <?= $i === $page ? 'bg-[#1e0e62] text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/10' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
