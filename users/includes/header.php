<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: users/includes/header.php
 * Purpose: Shared header for all user dashboard pages
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

// Include autoload and authentication check
require_once dirname(__DIR__, 2) . '/autoload.php';

use App\Models\UserModel;
use App\Utils\Security;

// Set security headers
Security::setSecurityHeaders();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check - redirect to login if not authenticated
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated'] || !isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Check session timeout
if (time() - ($_SESSION['last_activity'] ?? 0) > 1800) { // 30 minutes
    session_destroy();
    header('Location: /login.php?timeout=1');
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get current user data
$userModel = new UserModel();
$currentUser = $userModel->findById((int)$_SESSION['user_id']);

if (!$currentUser || !$currentUser['is_active']) {
    session_destroy();
    header('Location: /login.php?account_inactive=1');
    exit;
}

// Get current page for active navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Page title - can be overridden in individual pages
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'Manage your investments and track your portfolio performance';

?>
<!DOCTYPE html>
<html lang="en" class="<?= ($_SESSION['theme'] ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::escape($pageTitle) ?> - Cornerfield Investment Platform</title>
    <meta name="description" content="<?= Security::escape($pageDescription) ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwindcss.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        cf: {
                            primary: '#667eea',
                            'primary-dark': '#5a67d8',
                            secondary: '#764ba2',
                            success: '#10b981',
                            warning: '#f59e0b',
                            danger: '#ef4444',
                            info: '#3b82f6',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS Variables (keep for theme system) -->
    <link rel="stylesheet" href="/assets/css/cornerfield.css">
    
    <!-- CSRF Token Meta -->
    <meta name="csrf-token" content="<?= Security::generateCsrfToken() ?>">
</head>
<body class="bg-gray-50 dark:bg-slate-900 font-inter">
    <!-- Loading Overlay -->
    <div class="fixed inset-0 bg-white dark:bg-slate-900 flex items-center justify-center z-50 transition-opacity duration-300 hidden" id="loadingOverlay">
        <div class="text-center">
            <svg class="animate-spin w-8 h-8 text-cf-primary mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600 dark:text-gray-300">Loading...</p>
        </div>
    </div>

    <!-- Main Layout Container -->
    <div class="flex h-screen bg-gray-50 dark:bg-slate-900">
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-white dark:bg-slate-800 shadow-xl transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0" id="sidebar">
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="flex items-center justify-center h-16 px-6 bg-gradient-to-r from-cf-primary to-cf-secondary">
                    <a href="/users/dashboard.php" class="flex items-center gap-3">
                        <svg class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                        <span class="text-xl font-bold text-white">Cornerfield</span>
                    </a>
                </div>

                <!-- User Info -->
                <div class="flex items-center px-6 py-4 bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-600">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gradient-to-r from-cf-primary to-cf-secondary rounded-full flex items-center justify-center text-white font-semibold">
                            <?= Security::escape(strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1))) ?>
                        </div>
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            <?= Security::escape($currentUser['first_name'] ? $currentUser['first_name'] . ' ' . ($currentUser['last_name'] ?? '') : $currentUser['username']) ?>
                        </p>
                        <p class="text-sm text-cf-primary font-semibold">
                            $<?= number_format((float)$currentUser['balance'], 2) ?>
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $currentUser['kyc_status'] === 'approved' ? 'bg-cf-success/10 text-cf-success' : ($currentUser['kyc_status'] === 'rejected' ? 'bg-cf-danger/10 text-cf-danger' : 'bg-cf-warning/10 text-cf-warning') ?>">
                            <?= Security::escape(ucfirst($currentUser['kyc_status'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
                    <a href="/users/dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'dashboard' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="/users/invest.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'invest' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Investments
                        <?php if (isset($pendingInvestments) && $pendingInvestments > 0): ?>
                        <span class="ml-auto inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-cf-warning rounded-full">
                            <?= $pendingInvestments ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/users/deposit.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'deposit' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Deposit
                    </a>
                    
                    <a href="/users/withdraw.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'withdraw' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Withdraw
                    </a>
                    
                    <a href="/users/transactions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'transactions' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Transactions
                    </a>
                    
                    <a href="/users/profile.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'profile' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Profile
                        <?php if ($currentUser['kyc_status'] === 'pending'): ?>
                        <span class="ml-auto inline-flex items-center justify-center w-2 h-2 bg-cf-warning rounded-full"></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/users/support.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-colors <?= $currentPage === 'support' ? 'bg-cf-primary text-white shadow-lg shadow-cf-primary/25' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Support
                    </a>
                </nav>

                <!-- Quick Actions -->
                <div class="p-4 border-t border-gray-200 dark:border-slate-600">
                    <button type="button" class="w-full bg-gradient-to-r from-cf-primary to-cf-secondary hover:from-cf-primary-dark hover:to-cf-primary text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg shadow-cf-primary/25" onclick="showQuickInvestModal()">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Quick Invest
                    </button>
                </div>

                <!-- Settings -->
                <div class="p-4 border-t border-gray-200 dark:border-slate-600">
                    <div class="flex items-center justify-between">
                        <button type="button" class="p-2 text-gray-500 dark:text-gray-400 hover:text-cf-primary transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" title="Toggle theme" onclick="toggleTheme()">
                            <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </button>
                        
                        <div class="relative" id="settings-dropdown">
                            <button type="button" class="p-2 text-gray-500 dark:text-gray-400 hover:text-cf-primary transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" onclick="toggleDropdown('settings-dropdown')">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </button>
                            <div class="absolute bottom-full left-0 mb-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 hidden" id="settings-menu">
                                <a href="/users/profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-t-xl">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Profile Settings
                                </a>
                                <a href="/users/security.php" class="flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                    Security
                                </a>
                                <div class="border-t border-gray-200 dark:border-slate-600"></div>
                                <button onclick="logout()" class="flex items-center w-full px-4 py-3 text-sm text-cf-danger hover:bg-gray-100 dark:hover:bg-slate-700 rounded-b-xl">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Logout
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col lg:ml-0">
            <!-- Top Bar -->
            <header class="flex items-center justify-between h-16 px-4 lg:px-6 bg-white dark:bg-slate-800 shadow-sm border-b border-gray-200 dark:border-slate-700">
                <!-- Mobile Menu Toggle -->
                <button type="button" class="p-2 text-gray-600 dark:text-gray-300 hover:text-cf-primary transition-colors lg:hidden" id="mobileToggle">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Page Title (mobile) -->
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white lg:hidden"><?= Security::escape($pageTitle) ?></h1>

                <!-- Notifications and User Menu -->
                <div class="flex items-center gap-4 ml-auto">
                    <!-- Quick Stats (desktop only) -->
                    <div class="hidden lg:flex items-center gap-6 mr-4 text-sm">
                        <div class="text-center">
                            <p class="text-gray-500 dark:text-gray-400">Balance</p>
                            <p class="font-semibold text-gray-900 dark:text-white">$<?= number_format((float)$currentUser['balance'], 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 dark:text-gray-400">Invested</p>
                            <p class="font-semibold text-gray-900 dark:text-white">$<?= number_format((float)$currentUser['total_invested'], 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 dark:text-gray-400">Earned</p>
                            <p class="font-semibold text-cf-success">$<?= number_format((float)$currentUser['total_earned'], 2) ?></p>
                        </div>
                    </div>

                    <!-- Notifications Dropdown -->
                    <div class="relative" id="notifications-dropdown">
                        <button type="button" class="p-2 text-gray-600 dark:text-gray-300 hover:text-cf-primary transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 relative" onclick="toggleDropdown('notifications-dropdown')">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5c-.83-.83-1.21-2.02-1.01-3.17L16 4a4.992 4.992 0 00-8 0L8.5 10.33c.2 1.15-.18 2.34-1.01 3.17L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span class="absolute -top-1 -right-1 h-4 w-4 bg-cf-danger rounded-full text-xs text-white flex items-center justify-center hidden" id="notificationCount"></span>
                        </button>
                        <div class="absolute right-0 top-full mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 hidden z-50" id="notifications-menu">
                            <div class="p-4 border-b border-gray-200 dark:border-slate-600">
                                <div class="flex items-center justify-between">
                                    <h6 class="font-semibold text-gray-900 dark:text-white">Notifications</h6>
                                    <button type="button" class="text-sm text-cf-primary hover:text-cf-primary-dark" onclick="markAllNotificationsRead()">
                                        Mark all read
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-80 overflow-y-auto" id="notificationList">
                                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5c-.83-.83-1.21-2.02-1.01-3.17L16 4a4.992 4.992 0 00-8 0L8.5 10.33c.2 1.15-.18 2.34-1.01 3.17L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    <p>No new notifications</p>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-200 dark:border-slate-600">
                                <a href="/users/notifications.php" class="block w-full text-center text-sm text-cf-primary hover:text-cf-primary-dark transition-colors">
                                    View all notifications
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="relative" id="user-dropdown">
                        <button type="button" class="flex items-center gap-3 p-2 text-gray-600 dark:text-gray-300 hover:text-cf-primary transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" onclick="toggleDropdown('user-dropdown')">
                            <div class="w-8 h-8 bg-gradient-to-r from-cf-primary to-cf-secondary rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                <?= Security::escape(strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1))) ?>
                            </div>
                            <div class="hidden lg:block text-left">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?= Security::escape($currentUser['first_name'] ?? $currentUser['username']) ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Investor</p>
                            </div>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 hidden z-50" id="user-menu">
                            <div class="p-4 border-b border-gray-200 dark:border-slate-600">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    <?= Security::escape($currentUser['first_name'] ? $currentUser['first_name'] . ' ' . ($currentUser['last_name'] ?? '') : $currentUser['username']) ?>
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400"><?= Security::escape($currentUser['email']) ?></p>
                            </div>
                            <a href="/users/profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                My Profile
                            </a>
                            <a href="/users/referrals.php" class="flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                                Referrals
                            </a>
                            <a href="/users/security.php" class="flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                Security Settings
                            </a>
                            <div class="border-t border-gray-200 dark:border-slate-600"></div>
                            <button onclick="logout()" class="flex items-center w-full px-4 py-3 text-sm text-cf-danger hover:bg-gray-100 dark:hover:bg-slate-700 rounded-b-xl">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-auto bg-gray-50 dark:bg-slate-900">
                <!-- Page Header (if not dashboard) -->
                <?php if ($currentPage !== 'dashboard'): ?>
                <div class="bg-white dark:bg-slate-800 shadow-sm border-b border-gray-200 dark:border-slate-700">
                    <div class="px-4 lg:px-6 py-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?= Security::escape($pageTitle) ?></h1>
                                <p class="text-gray-600 dark:text-gray-300"><?= Security::escape($pageDescription) ?></p>
                            </div>
                            
                            <!-- Breadcrumbs -->
                            <nav class="hidden lg:flex" aria-label="Breadcrumb">
                                <ol class="flex items-center gap-2 text-sm">
                                    <li>
                                        <a href="/users/dashboard.php" class="text-gray-500 dark:text-gray-400 hover:text-cf-primary transition-colors">Dashboard</a>
                                    </li>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <li class="text-gray-900 dark:text-white font-medium">
                                        <?= Security::escape($pageTitle) ?>
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Flash Messages Container -->
                <div id="flashMessages" class="px-4 lg:px-6 pt-4"></div>

                <!-- Main Content Wrapper -->
                <div class="px-4 lg:px-6 py-6">