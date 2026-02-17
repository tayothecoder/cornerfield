<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

$pageTitle = 'Dashboard';

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize session
\App\Utils\SessionManager::start();

try {
    // Use the modern factory pattern
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

// Check if admin is logged in
if (!$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $adminController->logout();
    header('Location: login.php');
    exit;
}

// Get current admin and dashboard data
$currentAdmin = $adminController->getCurrentAdmin();
$dashboardData = $adminController->getDashboardData();

// Get admin settings for display
$siteName = $adminSettingsModel->getSetting('site_name', 'Cornerfield Investment Platform');
$currencySymbol = $adminSettingsModel->getSetting('currency_symbol', '$');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Validate CSRF token
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
            // Return updated dashboard stats
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

// Ensure we have data with enhanced structure for modern dashboard
if (!$dashboardData) {
    $dashboardData = [
        'stats' => [
            'total_users' => 0,
            'total_investments' => 0,
            'total_investment_amount' => 0,
            'active_schemas' => 0,
            'total_deposits' => 0,
            'pending_withdrawals' => 0,
            'total_profits_distributed' => 0,
            'active_users_today' => 0
        ],
        'investment_schemas' => [],
        'recent_investments' => [],
        'recent_users' => [],
        'recent_deposits' => [],
        'recent_withdrawals' => [],
        'recent_transactions' => []
    ];
}

// Get additional stats for enhanced dashboard
try {
    // Get pending counts for badges
    $pendingDeposits = $database->fetchOne("SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'")['count'] ?? 0;
    $pendingWithdrawals = $database->fetchOne("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'pending'")['count'] ?? 0;
    $pendingTransactions = $database->fetchOne("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'")['count'] ?? 0;

    // Get today's activity
    $todayUsers = $database->fetchOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
    $todayDeposits = $database->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'deposit' AND DATE(created_at) = CURDATE()")['total'] ?? 0;
    $todayInvestments = $database->fetchOne("SELECT COALESCE(SUM(invest_amount), 0) as total FROM investments WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;

    // Update dashboard data with additional stats
    $dashboardData['stats']['pending_deposits'] = $pendingDeposits;
    $dashboardData['stats']['pending_withdrawals'] = $pendingWithdrawals;
    $dashboardData['stats']['pending_transactions'] = $pendingTransactions;
    $dashboardData['stats']['today_users'] = $todayUsers;
    $dashboardData['stats']['today_deposits'] = $todayDeposits;
    $dashboardData['stats']['today_investments'] = $todayInvestments;

    // Get recent activity data for dashboard tables
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
    // Fallback for missing data
    error_log("Dashboard data fetch error: " . $e->getMessage());
}

// Set page variables for header
$pageTitle = 'Admin Dashboard - ' . \App\Config\Config::getSiteName();
$currentPage = 'dashboard';

// Custom JavaScript for dashboard
$pageSpecificJS = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Auto-calculate total return
    const dailyRateInput = document.querySelector("input[name=\"daily_rate\"]");
    const durationInput = document.querySelector("input[name=\"duration_days\"]");
    const totalReturnInput = document.querySelector("input[name=\"total_return\"]");

    function calculateTotalReturn() {
        const dailyRate = parseFloat(dailyRateInput.value) || 0;
        const duration = parseInt(durationInput.value) || 0;

        if (dailyRate > 0 && duration > 0) {
            const totalReturn = dailyRate * duration;
            totalReturnInput.value = totalReturn.toFixed(2);
            totalReturnInput.placeholder = totalReturn.toFixed(2) + "%";
        } else {
            totalReturnInput.value = "";
            totalReturnInput.placeholder = "Auto-calculated";
        }
    }

    if (dailyRateInput && durationInput && totalReturnInput) {
        dailyRateInput.addEventListener("input", calculateTotalReturn);
        durationInput.addEventListener("input", calculateTotalReturn);
    }

    // Validate min/max amounts
    const minAmountInput = document.querySelector("input[name=\"min_amount\"]");
    const maxAmountInput = document.querySelector("input[name=\"max_amount\"]");

    function validateAmounts() {
        const minAmount = parseFloat(minAmountInput.value) || 0;
        const maxAmount = parseFloat(maxAmountInput.value) || 0;

        if (minAmount > 0 && maxAmount > 0 && minAmount >= maxAmount) {
            maxAmountInput.setCustomValidity("Maximum amount must be greater than minimum amount");
            maxAmountInput.classList.add("is-invalid");
        } else {
            maxAmountInput.setCustomValidity("");
            maxAmountInput.classList.remove("is-invalid");
        }
    }

    if (minAmountInput && maxAmountInput) {
        minAmountInput.addEventListener("input", validateAmounts);
        maxAmountInput.addEventListener("input", validateAmounts);
    }

    // Handle form submission
    document.getElementById("create-plan-form").addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append("ajax", "1");
        formData.append("action", "create_schema");
        formData.append("csrf_token", document.querySelector(\'input[name="csrf_token"]\').value);

        // Convert checkboxes to 1/0
        formData.set("featured", formData.get("featured") ? "1" : "0");
        formData.set("status", formData.get("status") ? "1" : "0");

        // Show loading state
        const submitBtn = this.querySelector("button[type=\"submit\"]");
        const originalHTML = submitBtn.innerHTML;
        submitBtn.innerHTML = `
           <span class="spinner-border spinner-border-sm me-2" role="status"></span>
           Creating...
       `;
        submitBtn.disabled = true;

        fetch("dashboard.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showNotification("Investment plan created successfully!", "success");

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById("modal-create-plan"));
                modal.hide();

                // Reset form
                this.reset();
                totalReturnInput.value = "";
                totalReturnInput.placeholder = "Auto-calculated";

                // Reload page after short delay
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification("Error: " + (data.message || "Unknown error occurred"), "error");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showNotification("Network error. Please try again.", "error");
        })
        .finally(() => {
            // Reset button state
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled = false;
        });
    });

    // Reset form when modal is hidden
    document.getElementById("modal-create-plan").addEventListener("hidden.bs.modal", function () {
        const form = document.getElementById("create-plan-form");
        form.reset();
        totalReturnInput.value = "";
        totalReturnInput.placeholder = "Auto-calculated";

        // Clear validation states
        form.querySelectorAll(".is-invalid").forEach(input => {
            input.classList.remove("is-invalid");
        });
    });
});
</script>
';

include __DIR__ . '/includes/header.php';
?>

<!-- Page header -->
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    Overview
                </div>
                <h2 class="page-title">
                    Dashboard
                </h2>
            </div>

            <!-- Page title actions -->
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <span class="d-none d-sm-inline">
                        <a href="../users/dashboard.php" class="btn btn-white" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5" />
                                <path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5" />
                            </svg>
                            View User Site
                        </a>
                    </span>

                    <a href="#" class="btn btn-primary d-sm-none btn-icon" data-bs-toggle="modal"
                        data-bs-target="#modal-create-plan">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                    </a>

                    <a href="#" class="btn btn-primary d-none d-sm-inline-block" data-bs-toggle="modal"
                        data-bs-target="#modal-create-plan">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Create Investment Plan
                    </a>

                    <div class="dropdown">
                        <a href="#" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <circle cx="12" cy="12" r="1" />
                                <circle cx="12" cy="5" r="1" />
                                <circle cx="12" cy="19" r="1" />
                            </svg>
                            Actions
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="users.php">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <circle cx="12" cy="7" r="4" />
                                    <path d="m6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                </svg>
                                Manage Users
                            </a>
                            <a class="dropdown-item" href="transactions.php?filter=pending">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <circle cx="12" cy="12" r="9" />
                                    <polyline points="12,7 12,12 15,15" />
                                </svg>
                                Pending Transactions
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                Platform Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-red" href="?action=logout">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon dropdown-item-icon"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                                    <path d="M9 12h12l-3 -3" />
                                    <path d="M18 15l3 -3" />
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Overview Stats Row -->
        <div class="row row-deck row-cards">
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">
                                    Total Users
                                </div>
                                <div class="h1 mb-3" data-stat="total_users">
                                    <?= number_format($dashboardData['stats']['total_users']) ?>
                                </div>
                                <div class="text-secondary">
                                    <span class="text-green d-inline-flex align-items-center lh-1">
                                        +<?= $dashboardData['stats']['today_users'] ?? 0 ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon ms-1" width="24"
                                            height="24" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <polyline points="3,17 9,11 13,15 21,7" />
                                            <polyline points="14,7 21,7 21,14" />
                                        </svg>
                                    </span>
                                    new today
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="bg-primary text-white avatar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="12" cy="7" r="4" />
                                        <path d="m6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">
                                    Total Deposits
                                </div>
                                <div class="h1 mb-3" data-stat="total_deposits">
                                    <?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['total_deposits'] ?? 0, 2) ?>
                                </div>
                                <div class="text-secondary">
                                    <span class="text-green d-inline-flex align-items-center lh-1">
                                        <?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['today_deposits'] ?? 0, 2) ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon ms-1" width="24"
                                            height="24" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <polyline points="3,17 9,11 13,15 21,7" />
                                            <polyline points="14,7 21,7 21,14" />
                                        </svg>
                                    </span>
                                    today
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="bg-success text-white avatar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 3v18" />
                                        <path d="m16 7-4 -4-4 4" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">
                                    Total Investments
                                </div>
                                <div class="h1 mb-3" data-stat="total_investments">
                                    <?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['total_investment_amount'], 2) ?>
                                </div>
                                <div class="text-secondary">
                                    <span class="text-blue d-inline-flex align-items-center lh-1">
                                        <?= htmlspecialchars($currencySymbol) ?><?= number_format($dashboardData['stats']['today_investments'] ?? 0, 2) ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon ms-1" width="24"
                                            height="24" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <polyline points="3,17 9,11 13,15 21,7" />
                                            <polyline points="14,7 21,7 21,14" />
                                        </svg>
                                    </span>
                                    today
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="bg-info text-white avatar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M12 3v18" />
                                        <path d="m8 12 8 0" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">
                                    Pending Withdrawals
                                </div>
                                <div class="h1 mb-3" data-stat="pending_withdrawals">
                                    <?= number_format($dashboardData['stats']['pending_withdrawals']) ?>
                                </div>
                                <div class="text-secondary">
                                    <span class="text-yellow d-inline-flex align-items-center lh-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="24"
                                            height="24" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="12" r="9" />
                                            <polyline points="12,7 12,12 15,15" />
                                        </svg>
                                        Requires attention
                                    </span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="bg-warning text-white avatar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 21v-18" />
                                        <path d="m8 7 4 -4 4 4" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Overview and Charts -->
        <div class="row row-deck row-cards mt-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Platform Activity</h3>
                        <div class="card-actions">
                            <div class="dropdown">
                                <a href="#" class="btn-action dropdown-toggle" data-bs-toggle="dropdown">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="12" cy="12" r="1" />
                                        <circle cx="12" cy="5" r="1" />
                                       <circle cx="12" cy="19" r="1" />
                                   </svg>
                               </a>
                               <div class="dropdown-menu dropdown-menu-end">
                                   <a href="#" class="dropdown-item">Last 7 days</a>
                                   <a href="#" class="dropdown-item">Last 30 days</a>
                                   <a href="#" class="dropdown-item">Last 3 months</a>
                               </div>
                           </div>
                       </div>
                   </div>
                   <div class="card-body">
                       <div class="chart-placeholder">
                           <div class="text-center">
                               <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2" width="24"
                                   height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                   fill="none" stroke-linecap="round" stroke-linejoin="round">
                                   <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                   <polyline points="3,17 9,11 13,15 21,7" />
                                   <polyline points="14,7 21,7 21,14" />
                               </svg>
                               <div class="h3">Activity Chart</div>
                               <div class="text-secondary">Chart library integration ready</div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>

           <div class="col-lg-4">
               <div class="card">
                   <div class="card-header">
                       <h3 class="card-title">Investment Plans Overview</h3>
                   </div>
                   <div class="card-body">
                       <div class="row">
                           <div class="col-6">
                               <div class="h1 m-0"><?= count($dashboardData['investment_schemas']) ?></div>
                               <div class="text-secondary">Active Plans</div>
                           </div>
                           <div class="col-6">
                               <div class="h1 m-0">
                                   <?= number_format($dashboardData['stats']['total_investments']) ?>
                               </div>
                               <div class="text-secondary">Total Investments</div>
                           </div>
                       </div>
                       <div class="mt-3">
                           <?php if (!empty($dashboardData['investment_schemas'])): ?>
                               <?php foreach (array_slice($dashboardData['investment_schemas'], 0, 4) as $schema): ?>
                                   <div class="row align-items-center mb-2">
                                       <div class="col-auto">
                                           <span
                                               class="status-dot bg-<?= $schema['featured'] ? 'warning' : 'success' ?>"></span>
                                       </div>
                                       <div class="col text-truncate">
                                           <a href="#"
                                               class="text-body d-block"><?= htmlspecialchars($schema['name']) ?></a>
                                           <div class="d-block text-secondary text-truncate mt-n1">
                                               <?= $schema['daily_rate'] ?>% daily • <?= $schema['duration_days'] ?>
                                               days
                                           </div>
                                       </div>
                                       <div class="col-auto">
                                           <?php if ($schema['featured']): ?>
                                               <span class="badge bg-yellow">Featured</span>
                                           <?php endif; ?>
                                       </div>
                                   </div>
                               <?php endforeach; ?>
                           <?php else: ?>
                               <div class="text-center text-secondary">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <circle cx="12" cy="12" r="9" />
                                       <path d="M12 3v18" />
                                       <path d="m8 12 8 0" />
                                   </svg>
                                   <div>No investment plans</div>
                               </div>
                           <?php endif; ?>
                       </div>
                       <div class="mt-3">
                           <a href="investment-plans.php" class="btn btn-outline-primary w-100">
                               Manage Investment Plans
                           </a>
                       </div>
                   </div>
               </div>
           </div>
       </div>

       <!-- Recent Activity Tables -->
       <div class="row row-deck row-cards mt-4">
           <!-- Recent Users -->
           <div class="col-lg-6">
               <div class="card">
                   <div class="card-header">
                       <h3 class="card-title">Recent Users</h3>
                       <div class="card-actions">
                           <a href="users.php" class="btn btn-outline-primary btn-sm">
                               View all
                           </a>
                       </div>
                   </div>
                   <div class="card-body p-0">
                       <div class="table-responsive">
                           <table class="table table-vcenter table-hover">
                               <tbody>
                                   <?php if (!empty($dashboardData['recent_users'])): ?>
                                       <?php foreach (array_slice($dashboardData['recent_users'], 0, 5) as $user): ?>
                                           <tr class="activity-item">
                                               <td class="w-1">
                                                   <span class="avatar avatar-sm"
                                                       style="background-image: url(../assets/tabler/static/avatars/user.jpg)"></span>
                                               </td>
                                               <td class="td-truncate">
                                                   <div class="text-truncate">
                                                       <strong><?= htmlspecialchars($user['email']) ?></strong>
                                                   </div>
                                                   <div class="text-secondary text-truncate">
                                                       <?= htmlspecialchars($user['country'] ?? 'Unknown') ?> •
                                                       <?= date('M j', strtotime($user['created_at'])) ?>
                                                   </div>
                                               </td>
                                               <td class="text-end" style="min-width: 80px;">
                                                   <div class="amount-display">
                                                       <span
                                                           class="currency-symbol"><?= htmlspecialchars($currencySymbol) ?></span><?= number_format($user['balance'], 2) ?>
                                                   </div>
                                                   <div class="mt-1">
                                                       <span
                                                           class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?> badge-sm">
                                                           <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                       </span>
                                                   </div>
                                               </td>
                                           </tr>
                                       <?php endforeach; ?>
                                   <?php else: ?>
                                       <tr>
                                           <td colspan="3" class="text-center text-secondary py-5">
                                               <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2"
                                                   width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                                   stroke="currentColor" fill="none" stroke-linecap="round"
                                                   stroke-linejoin="round">
                                                   <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                   <circle cx="12" cy="7" r="4" />
                                                   <path d="m6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                               </svg>
                                               <div>No users yet</div>
                                           </td>
                                       </tr>
                                   <?php endif; ?>
                               </tbody>
                           </table>
                       </div>
                   </div>
               </div>
           </div>
           <!-- Recent Deposits -->
           <div class="col-lg-6">
               <div class="card">
                   <div class="card-header">
                       <h3 class="card-title">Recent Deposits</h3>
                       <div class="card-actions">
                           <a href="deposits.php" class="btn btn-outline-success btn-sm">
                               View all
                           </a>
                       </div>
                   </div>
                   <div class="card-body p-0">
                       <div class="table-responsive">
                           <table class="table table-vcenter table-hover">
                               <tbody>
                                   <?php if (!empty($dashboardData['recent_deposits'])): ?>
                                       <?php foreach (array_slice($dashboardData['recent_deposits'], 0, 5) as $deposit): ?>
                                           <tr class="activity-item">
                                               <td class="w-1">
                                                   <span class="avatar avatar-sm bg-success text-white">
                                                       <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                                                           height="24" viewBox="0 0 24 24" stroke-width="2"
                                                           stroke="currentColor" fill="none" stroke-linecap="round"
                                                           stroke-linejoin="round">
                                                           <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                           <path d="M12 3v18" />
                                                           <path d="m16 7-4 -4-4 4" />
                                                       </svg>
                                                   </span>
                                               </td>
                                               <td class="td-truncate">
                                                   <div class="text-truncate">
                                                       <strong><?= htmlspecialchars($deposit['user_email']) ?></strong>
                                                   </div>
                                                   <div class="text-secondary text-truncate">
                                                       <?= htmlspecialchars($deposit['method_name']) ?> •
                                                       <?= date('M j, g:i A', strtotime($deposit['created_at'])) ?>
                                                   </div>
                                               </td>
                                               <td class="text-end" style="min-width: 100px;">
                                                   <div class="amount-display">
                                                       <span
                                                           class="currency-symbol"><?= htmlspecialchars($currencySymbol) ?></span><?= number_format($deposit['requested_amount'], 2) ?>
                                                   </div>
                                                   <div class="mt-1">
                                                       <span class="badge bg-<?=
                                                           $deposit['status'] === 'completed' ? 'success' :
                                                           ($deposit['status'] === 'pending' ? 'warning' : 'secondary')
                                                           ?> badge-sm">
                                                           <?= ucfirst($deposit['status']) ?>
                                                       </span>
                                                   </div>
                                               </td>
                                           </tr>
                                       <?php endforeach; ?>
                                   <?php else: ?>
                                       <tr>
                                           <td colspan="3" class="text-center text-secondary py-5">
                                               <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2"
                                                   width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                                   stroke="currentColor" fill="none" stroke-linecap="round"
                                                   stroke-linejoin="round">
                                                   <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                   <path d="M12 3v18" />
                                                   <path d="m16 7-4 -4-4 4" />
                                               </svg>
                                               <div>No deposits yet</div>
                                           </td>
                                       </tr>
                                   <?php endif; ?>
                               </tbody>
                           </table>
                       </div>
                   </div>
               </div>
           </div>
       </div>

       <!-- Recent Investments and Withdrawals -->
       <div class="row row-deck row-cards mt-4">
           <!-- Recent Investments -->
           <div class="col-lg-6">
               <div class="card">
                   <div class="card-header">
                       <h3 class="card-title">Recent Investments</h3>
                       <div class="card-actions">
                           <a href="investment-plans.php" class="btn btn-outline-info btn-sm">
                               View all
                           </a>
                       </div>
                   </div>
                   <div class="card-body p-0">
                       <div class="table-responsive">
                           <table class="table table-vcenter table-hover">
                               <tbody>
                                   <?php if (!empty($dashboardData['recent_investments'])): ?>
                                       <?php foreach (array_slice($dashboardData['recent_investments'], 0, 5) as $investment): ?>
                                           <tr class="activity-item">
                                               <td class="w-1">
                                                   <span class="avatar avatar-sm bg-info text-white">
                                                       <span class="crypto-icon">₿</span>
                                                   </span>
                                               </td>
                                               <td class="td-truncate">
                                                   <div class="text-truncate">
                                                       <strong><?= htmlspecialchars($investment['user_email'] ?? 'Unknown User') ?></strong>
                                                   </div>
                                                   <div class="text-secondary text-truncate">
                                                       <?= htmlspecialchars($investment['schema_name'] ?? 'Unknown Plan') ?>
                                                       •
                                                       <?= date('M j, g:i A', strtotime($investment['created_at'])) ?>
                                                   </div>
                                               </td>
                                               <td class="text-end" style="min-width: 100px;">
                                                   <div class="amount-display">
                                                       <span
                                                           class="currency-symbol"><?= htmlspecialchars($currencySymbol) ?></span><?= number_format($investment['invest_amount'], 2) ?>
                                                   </div>
                                                   <div class="mt-1">
                                                       <span
                                                           class="badge bg-<?= $investment['status'] === 'active' ? 'success' : 'secondary' ?> badge-sm">
                                                           <?= ucfirst($investment['status']) ?>
                                                       </span>
                                                   </div>
                                               </td>
                                           </tr>
                                       <?php endforeach; ?>
                                   <?php else: ?>
                                       <tr>
                                           <td colspan="3" class="text-center text-secondary py-5">
                                               <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2"
                                                   width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                                   stroke="currentColor" fill="none" stroke-linecap="round"
                                                   stroke-linejoin="round">
                                                   <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                   <circle cx="12" cy="12" r="9" />
                                                   <path d="M12 3v18" />
                                                   <path d="m8 12 8 0" />
                                               </svg>
                                               <div>No investments yet</div>
                                           </td>
                                       </tr>
                                   <?php endif; ?>
                               </tbody>
                           </table>
                       </div>
                   </div>
               </div>
           </div>

           <!-- Recent Withdrawals -->
           <div class="col-lg-6">
               <div class="card">
                   <div class="card-header">
                       <h3 class="card-title">Recent Withdrawals</h3>
                       <div class="card-actions">
                           <a href="withdrawals.php" class="btn btn-outline-warning btn-sm">
                               View all
                           </a>
                       </div>
                   </div>
                   <div class="card-body p-0">
                       <div class="table-responsive">
                           <table class="table table-vcenter table-hover">
                               <tbody>
                                   <?php if (!empty($dashboardData['recent_withdrawals'])): ?>
                                       <?php foreach (array_slice($dashboardData['recent_withdrawals'], 0, 5) as $withdrawal): ?>
                                           <tr class="activity-item">
                                               <td class="w-1">
                                                   <span class="avatar avatar-sm bg-warning text-white">
                                                       <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                                                           height="24" viewBox="0 0 24 24" stroke-width="2"
                                                           stroke="currentColor" fill="none" stroke-linecap="round"
                                                           stroke-linejoin="round">
                                                           <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                           <path d="M12 21v-18" />
                                                           <path d="m8 7 4 -4 4 4" />
                                                       </svg>
                                                   </span>
                                               </td>
                                               <td class="td-truncate">
                                                   <div class="text-truncate">
                                                       <strong><?= htmlspecialchars($withdrawal['user_email']) ?></strong>
                                                   </div>
                                                   <div class="text-secondary text-truncate">
                                                       <?= htmlspecialchars($withdrawal['currency']) ?> •
                                                       <?= date('M j, g:i A', strtotime($withdrawal['created_at'])) ?>
                                                   </div>
                                               </td>
                                               <td class="text-end" style="min-width: 100px;">
                                                   <div class="amount-display">
                                                       <span
                                                           class="currency-symbol"><?= htmlspecialchars($currencySymbol) ?></span><?= number_format($withdrawal['requested_amount'], 2) ?>
                                                   </div>
                                                   <div class="mt-1">
                                                       <span class="badge bg-<?=
                                                           $withdrawal['status'] === 'completed' ? 'success' :
                                                           ($withdrawal['status'] === 'pending' ? 'warning' :
                                                               ($withdrawal['status'] === 'processing' ? 'info' : 'danger'))
                                                           ?> badge-sm">
                                                           <?= ucfirst($withdrawal['status']) ?>
                                                       </span>
                                                   </div>
                                               </td>
                                           </tr>
                                       <?php endforeach; ?>
                                   <?php else: ?>
                                       <tr>
                                           <td colspan="3" class="text-center text-secondary py-5">
                                               <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2"
                                                   width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                                   stroke="currentColor" fill="none" stroke-linecap="round"
                                                   stroke-linejoin="round">
                                                   <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                   <path d="M12 21v-18" />
                                                   <path d="m8 7 4 -4 4 4" />
                                               </svg>
                                               <div>No withdrawals yet</div>
                                           </td>
                                       </tr>
                                   <?php endif; ?>
                               </tbody>
                           </table>
                       </div>
                   </div>
               </div>
           </div>
       </div>

       <!-- Quick Actions Section -->
       <div class="row row-deck row-cards mt-4">
           <div class="col-12">
               <div class="card">
                   <div class="card-header">
                       <h3 class="card-title">Quick Actions</h3>
                       <div class="card-actions">
                           <div class="text-secondary">
                               Administrative shortcuts
                           </div>
                       </div>
                   </div>
                   <div class="card-body">
                       <div class="row">
                           <div class="col-6 col-sm-4 col-md-2 col-xl-auto py-3">
                               <a href="users.php" class="btn btn-white w-100">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <circle cx="12" cy="7" r="4" />
                                       <path d="m6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                   </svg>
                                   Manage Users
                               </a>
                           </div>

                           <div class="col-6 col-sm-4 col-md-2 col-xl-auto py-3">
                               <a href="deposits.php?filter=pending" class="btn btn-white w-100">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <circle cx="12" cy="12" r="9" />
                                       <polyline points="12,7 12,12 15,15" />
                                   </svg>
                                   Pending Deposits
                                   <?php if ($dashboardData['stats']['pending_deposits'] > 0): ?>
                                       <span
                                           class="badge bg-red ms-1"><?= $dashboardData['stats']['pending_deposits'] ?></span>
                                   <?php endif; ?>
                               </a>
                           </div>

                           <div class="col-6 col-sm-4 col-md-2 col-xl-auto py-3">
                               <a href="withdrawals.php?filter=pending" class="btn btn-white w-100">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <path d="M12 21v-18" />
                                       <path d="m8 7 4 -4 4 4" />
                                   </svg>
                                   Pending Withdrawals
                                   <?php if ($dashboardData['stats']['pending_withdrawals'] > 0): ?>
                                       <span
                                           class="badge bg-orange ms-1"><?= $dashboardData['stats']['pending_withdrawals'] ?></span>
                                   <?php endif; ?>
                               </a>
                           </div>

                           <div class="col-6 col-sm-4 col-md-2 col-xl-auto py-3">
                               <a href="investment-plans.php" class="btn btn-white w-100">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <circle cx="12" cy="12" r="9" />
                                       <path d="M12 3v18" />
                                       <path d="m8 12 8 0" />
                                   </svg>
                                   Investment Plans
                               </a>
                           </div>

                           <div class="col-6 col-sm-4 col-md-2 col-xl-auto py-3">
                               <a href="profits.php" class="btn btn-white w-100">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <polyline points="22,12 18,12 15,21 9,3 6,12 2,12" />
                                   </svg>
                                   Profit Management
                               </a>
                           </div>

                           <div class="col-6 col-sm-4 col-md-2 col-xl-auto py-3">
                               <a href="settings.php" class="btn btn-white w-100">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="24"
                                       height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                       fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                       <path
                                           d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                       <circle cx="12" cy="12" r="3" />
                                   </svg>
                                   Platform Settings
                               </a>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
       </div>

       <!-- System Status Section -->
       <div class="row row-deck row-cards mt-4">
           <div class="col-md-6 col-lg-4">
               <div class="card">
                   <div class="card-body">
                       <div class="d-flex align-items-center">
                           <div class="subheader">Platform Status</div>
                           <div class="ms-auto">
                               <div class="status-dot bg-<?= $healthData['platform']['color'] ?>"></div>
                           </div>
                       </div>
                       <div class="h1 mb-3"><?= htmlspecialchars($healthData['platform']['message']) ?></div>
                       <div class="d-flex mb-2">
                           <div class="d-flex align-items-center flex-fill">
                               <div class="progress progress-sm flex-fill">
                                   <div class="progress-bar bg-<?= $healthData['platform']['color'] ?>"
                                       style="width: <?= $healthData['platform']['color'] === 'success' ? '100' : ($healthData['platform']['color'] === 'warning' ? '75' : '25') ?>%"
                                       role="progressbar"></div>
                               </div>
                               <div class="text-secondary ms-2">
                                   <?= $healthData['platform']['color'] === 'success' ? '100%' : ($healthData['platform']['color'] === 'warning' ? '75%' : '25%') ?>
                               </div>
                           </div>
                       </div>
                       <div class="text-secondary"><?= htmlspecialchars($healthData['platform']['details']) ?>
                       </div>
                   </div>
               </div>
           </div>

           <div class="col-md-6 col-lg-4">
               <div class="card">
                   <div class="card-body">
                       <div class="d-flex align-items-center">
                           <div class="subheader">Daily Profits</div>
                           <div class="ms-auto">
                               <div class="status-dot bg-<?= $healthData['profits']['color'] ?>"></div>
                           </div>
                       </div>
                       <div class="h1 mb-3"><?= htmlspecialchars($healthData['profits']['message']) ?></div>
                       <div class="d-flex mb-2">
                           <div class="d-flex align-items-center flex-fill">
                               <div class="progress progress-sm flex-fill">
                                   <div class="progress-bar bg-<?= $healthData['profits']['color'] ?>"
                                       style="width: <?= $healthData['profits']['color'] === 'success' ? '100' : ($healthData['profits']['color'] === 'warning' ? '60' : '90') ?>%"
                                       role="progressbar"></div>
                               </div>
                               <div class="text-secondary ms-2">
                                   <?= ucfirst($healthData['profits']['status']) ?>
                               </div>
                           </div>
                       </div>
                       <div class="text-secondary"><?= htmlspecialchars($healthData['profits']['details']) ?>
                       </div>
                   </div>
               </div>
           </div>

           <div class="col-md-6 col-lg-4">
               <div class="card">
                   <div class="card-body">
                       <div class="d-flex align-items-center">
                           <div class="subheader">Database</div>
                           <div class="ms-auto">
                               <div class="status-dot bg-<?= $healthData['database']['color'] ?>"></div>
                           </div>
                       </div>
                       <div class="h1 mb-3"><?= htmlspecialchars($healthData['database']['message']) ?></div>
                       <div class="d-flex mb-2">
                           <div class="d-flex align-items-center flex-fill">
                               <div class="progress progress-sm flex-fill">
                                   <div class="progress-bar bg-<?= $healthData['database']['color'] ?>"
                                       style="width: <?= $healthData['database']['color'] === 'success' ? '100' : ($healthData['database']['color'] === 'warning' ? '75' : '0') ?>%"
                                       role="progressbar"></div>
                               </div>
                               <div class="text-secondary ms-2">
                                   <?= $healthData['database']['color'] === 'success' ? 'Online' : ($healthData['database']['color'] === 'warning' ? 'Issues' : 'Offline') ?>
                               </div>
                           </div>
                       </div>
                       <div class="text-secondary"><?= htmlspecialchars($healthData['database']['details']) ?>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>
</div>

<!-- Create Investment Plan Modal -->
<div class="modal modal-blur fade" id="modal-create-plan" tabindex="-1" role="dialog" aria-hidden="true">
   <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
       <div class="modal-content">
           <div class="modal-header">
               <h5 class="modal-title">Create Investment Plan</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
           </div>
           <form id="create-plan-form">
               <div class="modal-body">
                   <?= \App\Utils\CSRFProtection::getTokenField() ?>
                   <div class="row">
                       <div class="col-lg-6">
                           <div class="mb-3">
                               <label class="form-label required">Plan Name</label>
                               <input type="text" name="name" class="form-control"
                                   placeholder="e.g., Bitcoin Starter" required>
                           </div>
                       </div>
                       <div class="col-lg-6">
                           <div class="mb-3">
                               <label class="form-label required">Daily Rate (%)</label>
                               <input type="number" name="daily_rate" class="form-control" step="0.01" min="0"
                                   max="100" placeholder="2.50" required>
                           </div>
                       </div>
                   </div>
                   <div class="row">
                       <div class="col-lg-6">
                           <div class="mb-3">
                               <label class="form-label required">Minimum Amount</label>
                               <div class="input-group">
                                   <span class="input-group-text"><?= htmlspecialchars($currencySymbol) ?></span>
                                   <input type="number" name="min_amount" class="form-control" step="0.01" min="0"
                                       placeholder="50.00" required>
                               </div>
                           </div>
                       </div>
                       <div class="col-lg-6">
                           <div class="mb-3">
                               <label class="form-label required">Maximum Amount</label>
                               <div class="input-group">
                                   <span class="input-group-text"><?= htmlspecialchars($currencySymbol) ?></span>
                                   <input type="number" name="max_amount" class="form-control" step="0.01" min="0"
                                       placeholder="999.99" required>
                               </div>
                           </div>
                       </div>
                   </div>
                   <div class="row">
                       <div class="col-lg-6">
                           <div class="mb-3">
                               <label class="form-label required">Duration (Days)</label>
                               <input type="number" name="duration_days" class="form-control" min="1"
                                   placeholder="30" required>
                           </div>
                       </div>
                       <div class="col-lg-6">
                           <div class="mb-3">
                               <label class="form-label">Total Return (%)</label>
                               <input type="number" name="total_return" class="form-control" step="0.01" min="0"
                                   placeholder="Auto-calculated" readonly>
                               <div class="form-hint">Automatically calculated from daily rate × duration</div>
                           </div>
                       </div>
                   </div>
                   <div class="mb-3">
                       <label class="form-label">Plan Description</label>
                       <textarea name="description" class="form-control" rows="3"
                           placeholder="Describe this investment plan and its benefits..."></textarea>
                   </div>
                   <div class="row">
                       <div class="col-lg-6">
                           <label class="form-check form-switch">
                               <input class="form-check-input" type="checkbox" name="featured">
                               <span class="form-check-label">Featured Plan</span>
                               <span class="form-check-description">Display prominently to users</span>
                           </label>
                       </div>
                       <div class="col-lg-6">
                           <label class="form-check form-switch">
                               <input class="form-check-input" type="checkbox" name="status" checked>
                               <span class="form-check-label">Active</span>
                               <span class="form-check-description">Available for investments</span>
                           </label>
                       </div>
                   </div>
               </div>
               <div class="modal-footer">
                   <a href="#" class="btn me-auto" data-bs-dismiss="modal">Cancel</a>
                   <button type="submit" class="btn btn-primary">
                       <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="24" height="24"
                           viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                           stroke-linecap="round" stroke-linejoin="round">
                           <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                           <line x1="12" y1="5" x2="12" y2="19" />
                           <line x1="5" y1="12" x2="19" y2="12" />
                       </svg>
                       Create Investment Plan
                   </button>
               </div>
           </form>
       </div>
   </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>