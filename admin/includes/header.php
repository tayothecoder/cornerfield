<?php
// Prevent direct access
if (!defined('ADMIN_AREA')) {
    die('Direct access not permitted');
}

// Prevent multiple inclusions
if (defined('HEADER_INCLUDED')) {
    return;
}
define('HEADER_INCLUDED', true);

// Include CSRF protection
require_once dirname(__DIR__, 2) . '/src/Utils/CSRFProtection.php';

// Initialize required dependencies if not already loaded
if (!class_exists('\App\Config\Database')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

// Initialize database and required models if not already done
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

// Check admin authentication
if (!isset($adminController) || !$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $adminController->logout();
    header('Location: login.php');
    exit;
}

// Get admin data
if (!isset($currentAdmin) && isset($adminController)) {
    $currentAdmin = $adminController->getCurrentAdmin();
}

// Get admin settings
if (!isset($siteName)) {
    $siteName = $adminSettingsModel->getSetting('site_name', 'Cornerfield Investment Platform');
}
if (!isset($currencySymbol)) {
    $currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');
}

// Set default page variables if not set
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard - ' . \App\Config\Config::getSiteName();
}
if (!isset($currentPage)) {
    $currentPage = 'dashboard';
}

// Get pending counts for navigation badges
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

// Initialize SystemHealth checker if not done
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
?>
<!doctype html>
<html lang="en">
<head>
    <script>if(localStorage.theme==="dark"||(!localStorage.theme&&window.matchMedia("(prefers-color-scheme:dark)").matches)){document.documentElement.classList.add("dark")}else{document.documentElement.classList.remove("dark")}</script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    
    <!-- Tabler CSS -->
    <link href="../assets/tabler/dist/css/tabler.min.css" rel="stylesheet" />
    <link href="../assets/tabler/dist/css/tabler-flags.min.css" rel="stylesheet" />
    <link href="../assets/tabler/dist/css/tabler-payments.min.css" rel="stylesheet" />
    <link href="../assets/tabler/dist/css/tabler-socials.min.css" rel="stylesheet" />
    
    <!-- Custom Clean Admin CSS -->
    <link href="../assets/css/admin-clean.css" rel="stylesheet" />
    
    <style>
        /* Clean, modern admin styles */
        :root {
            --admin-primary: #206bc4;
            --admin-primary-light: #4299e1;
            --admin-primary-dark: #1a4a8c;
            --admin-secondary: #6c757d;
            --admin-success: #28a745;
            --admin-warning: #ffc107;
            --admin-danger: #dc3545;
            --admin-info: #17a2b8;
            --admin-light: #f8f9fa;
            --admin-dark: #343a40;
            --admin-border: #e9ecef;
            --admin-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --admin-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --admin-radius: 0.5rem;
            --admin-transition: all 0.2s ease-in-out;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f7fa;
            color: #495057;
            line-height: 1.6;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--admin-border);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--admin-transition);
            box-shadow: var(--admin-shadow);
        }
        
        .admin-sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--admin-border);
            background: white;
        }
        
        .admin-sidebar-brand {
            color: var(--admin-primary);
            font-size: 1.25rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .admin-sidebar-nav {
            padding: 1rem 0;
        }
        
        .admin-sidebar-nav .nav-item {
            margin: 0.25rem 1rem;
        }
        
        .admin-sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #6c757d;
            text-decoration: none;
            border-radius: var(--admin-radius);
            transition: var(--admin-transition);
            border-left: 3px solid transparent;
        }
        
        .admin-sidebar-nav .nav-link:hover {
            background-color: rgba(32, 107, 196, 0.05);
            color: var(--admin-primary);
            border-left-color: var(--admin-primary);
        }
        
        .admin-sidebar-nav .nav-link.active {
            background-color: rgba(32, 107, 196, 0.1);
            color: var(--admin-primary);
            border-left-color: var(--admin-primary);
            font-weight: 600;
        }
        
        .admin-sidebar-nav .nav-link-icon {
            width: 20px;
            text-align: center;
            color: inherit;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 260px;
            transition: var(--admin-transition);
        }
        
        .admin-header {
            background: white;
            border-bottom: 1px solid var(--admin-border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--admin-shadow);
        }
        
        .admin-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .admin-page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--admin-dark);
            margin: 0;
        }
        
        .admin-user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: var(--admin-transition);
        }
        
        .admin-user-avatar:hover {
            background: var(--admin-primary-dark);
            transform: scale(1.05);
        }
        
        .admin-content {
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: var(--admin-radius);
            border: 1px solid var(--admin-border);
            padding: 1.5rem;
            transition: var(--admin-transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--admin-shadow);
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--admin-shadow-lg);
            border-color: var(--admin-primary-light);
        }
        
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--admin-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stats-icon.users { background: var(--admin-primary); }
        .stats-icon.deposits { background: var(--admin-success); }
        .stats-icon.investments { background: var(--admin-info); }
        .stats-icon.plans { background: var(--admin-warning); }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-dark);
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.75rem;
            font-weight: 500;
        }
        
        .stats-change {
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .admin-card {
            background: white;
            border-radius: var(--admin-radius);
            border: 1px solid var(--admin-border);
            box-shadow: var(--admin-shadow);
            transition: var(--admin-transition);
            overflow: hidden;
        }
        
        .admin-card:hover {
            box-shadow: var(--admin-shadow-lg);
            border-color: var(--admin-primary-light);
        }
        
        .admin-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--admin-border);
            background: #f8f9fa;
        }
        
        .admin-card-title {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--admin-dark);
        }
        
        .admin-card-body {
            padding: 1.5rem;
        }
        
        .admin-table {
            width: 100%;
            margin-bottom: 0;
        }
        
        .admin-table thead th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 0.75rem 1rem;
            text-align: left;
            border: none;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .admin-table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
        }
        
        .admin-table tbody tr:hover {
            background-color: rgba(32, 107, 196, 0.02);
        }
        
        .btn {
            border-radius: var(--admin-radius);
            font-weight: 500;
            transition: var(--admin-transition);
            border: none;
            padding: 0.5rem 1rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--admin-shadow);
        }
        
        .btn-primary {
            background: var(--admin-primary);
            border-color: var(--admin-primary);
        }
        
        .btn-primary:hover {
            background: var(--admin-primary-dark);
            border-color: var(--admin-primary-dark);
        }
        
        .form-control {
            border-radius: var(--admin-radius);
            border: 1px solid var(--admin-border);
            transition: var(--admin-transition);
        }
        
        .form-control:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.2rem rgba(32, 107, 196, 0.25);
        }
        
        .badge {
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.375rem 0.75rem;
        }
        
        .modal-content {
            border-radius: var(--admin-radius);
            border: none;
            box-shadow: var(--admin-shadow-lg);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--admin-border);
            background: #f8f9fa;
        }
        
        .modal-footer {
            border-top: 1px solid var(--admin-border);
            background: #f8f9fa;
        }
        
        /* Responsive design */
        @media (max-width: 1024px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.open {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-sidebar-toggle {
                display: block;
            }
        }
        
        @media (min-width: 1025px) {
            .admin-sidebar-toggle {
                display: none;
            }
        }
        
        .admin-sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--admin-dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--admin-radius);
            transition: var(--admin-transition);
        }
        
        .admin-sidebar-toggle:hover {
            background: var(--admin-light);
            color: var(--admin-primary);
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.open {
            display: block;
        }
        
        /* Notification styles */
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--admin-danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid white;
        }
        
        /* Search styles */
        .admin-search {
            position: relative;
            max-width: 300px;
        }
        
        .admin-search input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            background: white;
            font-size: 0.875rem;
            transition: var(--admin-transition);
        }
        
        .admin-search input:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.2rem rgba(32, 107, 196, 0.25);
        }
        
        .admin-search i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        /* Dropdown styles */
        .dropdown-menu {
            border-radius: var(--admin-radius);
            border: 1px solid var(--admin-border);
            box-shadow: var(--admin-shadow-lg);
            padding: 0.5rem 0;
            min-width: 200px;
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            color: #495057;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--admin-transition);
            font-size: 0.875rem;
        }
        
        .dropdown-item:hover {
            background: var(--admin-light);
            color: var(--admin-primary);
        }
        
        /* Hover effects */
        .hover-lift {
            transition: var(--admin-transition);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
        }
        
        .hover-scale {
            transition: var(--admin-transition);
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Clean Admin Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <a href="dashboard.php" class="admin-sidebar-brand">
                    <i class="fas fa-chart-line"></i>
                    <?= htmlspecialchars($siteName) ?>
                </a>
            </div>
            
            <nav class="admin-sidebar-nav">
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </span>
                        Dashboard
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-users"></i>
                        </span>
                        Users
                        <?php if ($pendingTransactions > 0): ?>
                            <span class="badge bg-warning ms-auto"><?= $pendingTransactions ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'deposits.php' ? 'active' : '' ?>" href="deposits.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-arrow-down"></i>
                        </span>
                        Deposits
                        <?php if ($pendingDeposits > 0): ?>
                            <span class="badge bg-warning ms-auto"><?= $pendingDeposits ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'active' : '' ?>" href="withdrawals.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-arrow-up"></i>
                        </span>
                        Withdrawals
                        <?php if ($pendingWithdrawals > 0): ?>
                            <span class="badge bg-warning ms-auto"><?= $pendingWithdrawals ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'investment-plans.php' ? 'active' : '' ?>" href="investment-plans.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        Investment Plans
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profits.php' ? 'active' : '' ?>" href="profits.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-chart-bar"></i>
                        </span>
                        Profits
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-cog"></i>
                        </span>
                        Settings
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'content-management.php' ? 'active' : '' ?>" href="content-management.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-edit"></i>
                        </span>
                        Content Management
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'email-management.php' ? 'active' : '' ?>" href="email-management.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        Email System
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payment-gateways.php' ? 'active' : '' ?>" href="payment-gateways.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-credit-card"></i>
                        </span>
                        Payment Gateways
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'support-tickets.php' ? 'active' : '' ?>" href="support-tickets.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </span>
                        Support Tickets
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user-transfers.php' ? 'active' : '' ?>" href="user-transfers.php">
                        <span class="nav-link-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </span>
                        User Transfers
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Clean Admin Header -->
            <header class="admin-header">
                <div class="admin-header-content">
                    <div class="d-flex align-items-center gap-3">
                        <button class="admin-sidebar-toggle" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="admin-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                    </div>
                    
                    <div class="admin-user-menu">
                        <!-- Search -->
                        <div class="admin-search me-3">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search..." />
                        </div>
                        
                        <!-- Notifications -->
                        <div class="dropdown me-3">
                            <div class="position-relative" data-bs-toggle="dropdown">
                                <i class="fas fa-bell" style="font-size: 1.125rem; color: #6c757d; cursor: pointer;"></i>
                                <span class="notification-badge">3</span>
                            </div>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-user-plus text-success"></i>
                                    <span>New user registered</span>
                                </a>
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-dollar-sign text-info"></i>
                                    <span>New deposit received</span>
                                </a>
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    <span>System alert</span>
                                </a>
                            </div>
                        </div>
                        
                        <!-- User Avatar -->
                        <div class="dropdown">
                            <div class="admin-user-avatar" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-user-cog"></i>
                                    <span>Profile</span>
                                </a>
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-cog"></i>
                                    <span>Settings</span>
                                </a>
                                <hr class="dropdown-divider">
                                <a href="logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="admin-content">