<?php
$base = \App\Config\Config::getBasePath();
// Get user info for demo purposes if session doesn't exist
$user = $_SESSION['user'] ?? [
    'id' => 1,
    'email' => 'demo@cornerfield.io',
    'firstname' => 'Demo',
    'lastname' => 'User',
    'balance' => 15420.50,
    'avatar' => $base . '/assets/images/default-avatar.png'
];

// Navigation items
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

// Current page active class
$currentPage = $currentPage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - Cornerfield</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind-compiled.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/cornerfield.css">
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.png">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .cf-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .cf-gradient-card { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); }
        .dark .cf-gradient-card { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="min-h-full">
        <!-- Mobile sidebar backdrop -->
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 opacity-0 invisible transition-all duration-300 lg:hidden"></div>
        
        <!-- Sidebar -->
        <div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-800 transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0">
            <div class="flex flex-col h-full">
                <!-- Sidebar header -->
                <div class="flex items-center justify-between px-6 py-6 cf-gradient">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white">Cornerfield</span>
                    </div>
                    <button id="sidebar-close" class="lg:hidden text-white hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- User info -->
                <div class="px-6 py-4 bg-slate-700 border-b border-slate-600">
                    <div class="flex items-center">
                        <img class="w-10 h-10 rounded-full object-cover" src="<?= htmlspecialchars($user['avatar']) ?>" alt="<?= htmlspecialchars($user['firstname']) ?>" onerror="this.src='<?= htmlspecialchars($base) ?>/assets/images/default-avatar.png'">
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></p>
                            <p class="text-xs text-gray-300"><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Balance card -->
                <div class="px-6 py-4 bg-slate-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Total Balance</div>
                    <div class="text-2xl font-bold text-white">$<?= number_format($user['balance'], 2) ?></div>
                    <div class="flex items-center mt-1 text-green-400">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs">+2.4%</span>
                    </div>
                </div>
                
                <!-- Navigation -->
                <nav class="flex-1 px-4 py-4 space-y-2">
                    <?php foreach ($navItems as $key => $item): ?>
                    <a href="<?= $item['url'] ?>" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 <?= $currentPage === $key ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-slate-700 hover:text-white' ?>">
                        <svg class="w-5 h-5 mr-3 <?= $currentPage === $key ? 'text-white' : 'text-gray-400 group-hover:text-gray-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?php
                            $icons = [
                                'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>',
                                'trending-up' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>',
                                'list-bullet' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>',
                                'arrow-right-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                                'user-group' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>',
                                'user-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                                'cog-6-tooth' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
                                'chat-bubble-left-right' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 2H4a2 2 0 00-2 2v12a2 2 0 002 2h4l4 4 4-4h4a2 2 0 002-2V4a2 2 0 00-2-2z"></path>'
                            ];
                            echo $icons[$item['icon']] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>';
                            ?>
                        </svg>
                        <?= $item['title'] ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
                
                <!-- Sidebar footer -->
                <div class="px-4 py-4 border-t border-slate-700">
                    <a href="<?= htmlspecialchars($base) ?>/logout.php" class="group flex items-center px-3 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-slate-700 rounded-lg transition-all duration-200">
                        <svg class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main content area -->
        <div class="lg:ml-64">
            <!-- Top bar -->
            <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6">
                    <div class="flex items-center">
                        <button id="sidebar-open" class="mr-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 lg:hidden">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Dark mode toggle -->
                        <button id="theme-toggle" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg id="theme-toggle-dark-icon" class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                            </svg>
                            <svg id="theme-toggle-light-icon" class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                        
                        <!-- Notifications -->
                        <button class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5-5V9a6 6 0 10-12 0v3l-5 5h5m7 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></span>
                        </button>
                        
                        <!-- Quick deposit/withdraw -->
                        <div class="hidden md:flex space-x-2">
                            <a href="<?= htmlspecialchars($base) ?>/users/deposit.php" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Deposit
                            </a>
                            <a href="<?= htmlspecialchars($base) ?>/users/withdraw.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Withdraw
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">