<?php
// prevent direct access
if (!defined('ADMIN_AREA')) {
    die('Direct access not permitted');
}

// prevent multiple inclusions
if (defined('HEADER_INCLUDED')) {
    return;
}
define('HEADER_INCLUDED', true);

// include csrf protection
require_once dirname(__DIR__, 2) . '/src/Utils/CSRFProtection.php';

// initialize required dependencies if not already loaded
if (!class_exists('\App\Config\Database')) {
    require_once dirname(__DIR__, 2) . '/autoload.php';
}

// initialize database and required models if not already done
if (!isset($database)) {
    try {
        $database = new \App\Config\Database();
    } catch (Exception $e) {
        if (\App\Config\Config::isDebug()) {
            die('Database connection failed: ' . $e->getMessage());
        } else {
            die('System error. Please try again later.');
        }
    }
}

if (!isset($adminController)) {
    $adminController = new \App\Controllers\AdminController($database);
}

if (!isset($adminSettingsModel)) {
    $adminSettingsModel = new \App\Models\AdminSettings($database);
}

// check admin authentication
if (!isset($adminController) || !$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $adminController->logout();
    header('Location: login.php');
    exit;
}

// get admin data
if (!isset($currentAdmin) && isset($adminController)) {
    $currentAdmin = $adminController->getCurrentAdmin();
}

// get admin settings
if (!isset($siteName)) {
    $siteName = $adminSettingsModel->getSetting('site_name', 'Cornerfield Investment Platform');
}
if (!isset($currencySymbol)) {
    $currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');
}

// set default page variables if not set
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard - ' . \App\Config\Config::getSiteName();
}
if (!isset($currentPage)) {
    $currentPage = 'dashboard';
}

// get pending counts for navigation badges
try {
    $pendingDeposits = $database->fetchOne("SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'")['count'] ?? 0;
    $pendingWithdrawals = $database->fetchOne("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'pending'")['count'] ?? 0;
    $pendingTransactions = $database->fetchOne("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'")['count'] ?? 0;
} catch (Exception $e) {
    $pendingDeposits = 0;
    $pendingWithdrawals = 0;
    $pendingTransactions = 0;
    error_log("Navigation badge counts error: " . $e->getMessage());
}

// initialize SystemHealth checker if not done
if (!isset($systemHealth) && isset($database)) {
    try {
        $systemHealth = new \App\Utils\SystemHealth($database);
        $healthData = $systemHealth->getSystemHealth();
    } catch (Exception $e) {
        error_log("SystemHealth initialization error: " . $e->getMessage());
        $systemHealth = null;
        $healthData = [];
    }
}

$base = \App\Config\Config::getBasePath();

// admin initials for avatar
$adminInitials = 'A';
if (!empty($currentAdmin['username'])) {
    $adminInitials = strtoupper(substr($currentAdmin['username'], 0, 2));
} elseif (!empty($currentAdmin['email'])) {
    $adminInitials = strtoupper(substr($currentAdmin['email'], 0, 2));
}

// nav items for admin sidebar
$adminNavItems = [
    'dashboard' => ['title' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
    'users' => ['title' => 'Users', 'url' => 'users.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>'],
    'deposits' => ['title' => 'Deposits', 'url' => 'deposits.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m0 0l-6-6m6 6l6-6"/>', 'badge' => $pendingDeposits],
    'withdrawals' => ['title' => 'Withdrawals', 'url' => 'withdrawals.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 20V4m0 0l-6 6m6-6l6 6"/>', 'badge' => $pendingWithdrawals],
    'transactions' => ['title' => 'Transactions', 'url' => 'transactions.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>'],
    'user-transfers' => ['title' => 'Transfers', 'url' => 'user-transfers.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>'],
    'investments' => ['title' => 'Investments', 'url' => 'investments.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
    'investment-plans' => ['title' => 'Investment Plans', 'url' => 'investment-plans.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>'],
    'profits' => ['title' => 'Profits', 'url' => 'profits.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
    'support-tickets' => ['title' => 'Support', 'url' => 'support-tickets.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 2H4a2 2 0 00-2 2v12a2 2 0 002 2h4l4 4 4-4h4a2 2 0 002-2V4a2 2 0 00-2-2z"/>'],
    'settings' => ['title' => 'Settings', 'url' => 'settings.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
    'content-management' => ['title' => 'Content', 'url' => 'content-management.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>'],
    'email-management' => ['title' => 'Email', 'url' => 'email-management.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>'],
    'payment-gateways' => ['title' => 'Payments', 'url' => 'payment-gateways.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>'],
    'system-health' => ['title' => 'System Health', 'url' => 'system-health.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'],
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <script>if(localStorage.theme==="dark"||(!localStorage.theme&&window.matchMedia("(prefers-color-scheme:dark)").matches)){document.documentElement.classList.add("dark")}else{document.documentElement.classList.remove("dark")}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind-compiled.css?v=4">
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/cornerfield.css?v=4">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.svg">
</head>
<body class="h-full bg-[#f5f3ff] dark:bg-[#0f0a2e]">
    <div class="min-h-full flex">
        <!-- mobile sidebar backdrop -->
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 bg-black/40 opacity-0 invisible transition-opacity duration-300 lg:hidden"></div>

        <!-- sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-[#1e0e62] flex flex-col -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 lg:flex-shrink-0">
            <!-- brand -->
            <div class="flex items-center justify-between px-6 h-16">
                <a href="dashboard.php" class="text-lg font-semibold text-white tracking-tight">
                    <?= htmlspecialchars(explode(' ', $siteName)[0]) ?>
                    <span class="ml-2 text-[10px] font-medium uppercase tracking-wider bg-white/20 text-white/90 px-2 py-0.5 rounded-full">admin</span>
                </a>
                <button id="sidebar-close" class="lg:hidden text-white/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- nav -->
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                <?php foreach ($adminNavItems as $key => $item): ?>
                <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors <?= $currentPage === $key ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $item['icon'] ?></svg>
                    <?= $item['title'] ?>
                    <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                        <span class="ml-auto bg-amber-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- sidebar footer -->
            <div class="px-3 py-4 space-y-1">
                <a href="<?= htmlspecialchars($base) ?>/users/dashboard.php" target="_blank" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View user site
                </a>
                <a href="?action=logout" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- main content wrapper -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- top bar -->
            <header class="sticky top-0 z-10 bg-white dark:bg-[#1a1145] h-16 flex items-center justify-between px-4 sm:px-6 shadow-sm">
                <button id="sidebar-open" class="text-gray-500 dark:text-gray-400 lg:hidden mr-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="flex-1"></div>

                <div class="flex items-center gap-3">
                    <!-- dark mode toggle -->
                    <button id="theme-toggle" class="p-2 text-gray-500 dark:text-gray-400 rounded-lg transition-colors">
                        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>

                    <!-- admin avatar -->
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-[#1e0e62] flex items-center justify-center text-white text-xs font-medium"><?= htmlspecialchars($adminInitials) ?></div>
                        <?php
                        $adminDisplayName = trim(($currentAdmin['first_name'] ?? '') . ' ' . ($currentAdmin['last_name'] ?? ''));
                        if (empty($adminDisplayName)) {
                            $adminDisplayName = $currentAdmin['username'] ?? 'Admin';
                        }
                        ?>
                        <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300 max-w-[250px] pr-2" title="<?= htmlspecialchars($adminDisplayName) ?>"><?= htmlspecialchars($adminDisplayName) ?></span>
                    </div>
                </div>
            </header>

            <!-- page content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
