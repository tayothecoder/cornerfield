<?php
require_once __DIR__ . '/../../autoload.php';
\App\Config\Config::init();

use App\Middleware\AuthMiddleware;

$base = \App\Config\Config::getBasePath();

// ensure session is started for auth checks
if (session_status() === PHP_SESSION_NONE) {
    $cookiePath = $base ?: '/';
    session_set_cookie_params([
        'path' => $cookiePath,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// auth check
if (!AuthMiddleware::check()) {
    header('Location: ' . $base . '/login.php');
    exit;
}

// get user info
if (!empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
} else {
    $userId = $_SESSION['user_id'] ?? 0;
    $user = [
        'id' => $userId,
        'email' => '',
        'firstname' => 'User',
        'lastname' => '',
        'balance' => 0,
        'avatar' => $base . '/assets/images/default-avatar.png'
    ];
    if ($userId > 0) {
        try {
            $userModel = new \App\Models\UserModel();
            $dbUser = $userModel->findById($userId);
            if ($dbUser) {
                $user = [
                    'id' => (int)$dbUser['id'],
                    'email' => $dbUser['email'] ?? '',
                    'firstname' => $dbUser['first_name'] ?? 'User',
                    'lastname' => $dbUser['last_name'] ?? '',
                    'username' => $dbUser['username'] ?? '',
                    'balance' => (float)($dbUser['balance'] ?? 0),
                    'avatar' => $base . '/assets/images/default-avatar.png'
                ];
                $_SESSION['user'] = $user;
            }
        } catch (\Throwable $e) {
            // keep fallback data
        }
    }
}

// navigation items
$navItems = [
    'dashboard' => ['title' => 'Dashboard', 'icon' => 'home', 'url' => $base . '/users/dashboard.php'],
    'invest' => ['title' => 'Invest', 'icon' => 'trending-up', 'url' => $base . '/users/invest.php'],
    'transactions' => ['title' => 'Transactions', 'icon' => 'list-bullet', 'url' => $base . '/users/transactions.php'],
    'transfer' => ['title' => 'Transfer', 'icon' => 'arrow-right-circle', 'url' => $base . '/users/transfer.php'],
    'referrals' => ['title' => 'Referrals', 'icon' => 'user-group', 'url' => $base . '/users/referrals.php'],
    'profile' => ['title' => 'Profile', 'icon' => 'user-circle', 'url' => $base . '/users/profile.php'],
    'settings' => ['title' => 'Settings', 'icon' => 'cog-6-tooth', 'url' => $base . '/users/settings.php'],
    'support' => ['title' => 'Support', 'icon' => 'chat-bubble-left-right', 'url' => $base . '/users/support.php']
];

$currentPage = $currentPage ?? 'dashboard';

// user initials for avatar
$initials = strtoupper(substr($user['firstname'] ?? 'U', 0, 1) . substr($user['lastname'] ?? '', 0, 1));
if (strlen($initials) < 2) $initials = strtoupper(substr($user['firstname'] ?? 'U', 0, 2));
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <script>if(localStorage.theme==="dark"||(!localStorage.theme&&window.matchMedia("(prefers-color-scheme:dark)").matches)){document.documentElement.classList.add("dark")}else{document.documentElement.classList.remove("dark")}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - Cornerfield</title>
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
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-[#1e0e62] flex flex-col -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 lg:flex-shrink-0">
            <!-- brand -->
            <div class="flex items-center justify-between px-6 h-16">
                <span class="text-lg font-semibold text-white tracking-tight">Cornerfield</span>
                <button id="sidebar-close" class="lg:hidden text-white/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <!-- nav -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors <?= $currentPage === $key ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?php
                        $icons = [
                            'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
                            'trending-up' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
                            'list-bullet' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
                            'arrow-right-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'user-group' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
                            'user-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'cog-6-tooth' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                            'chat-bubble-left-right' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 2H4a2 2 0 00-2 2v12a2 2 0 002 2h4l4 4 4-4h4a2 2 0 002-2V4a2 2 0 00-2-2z"/>'
                        ];
                        echo $icons[$item['icon']] ?? '';
                        ?>
                    </svg>
                    <?= $item['title'] ?>
                </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- sidebar footer -->
            <div class="px-3 py-4">
                <a href="<?= htmlspecialchars($base) ?>/logout.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
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
                    
                    <!-- user -->
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-[#1e0e62] flex items-center justify-center text-white text-xs font-medium"><?= htmlspecialchars($initials) ?></div>
                        <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300"><?= htmlspecialchars($user['firstname']) ?></span>
                    </div>
                </div>
            </header>
            
            <!-- page content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
