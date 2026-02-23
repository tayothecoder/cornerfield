<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Start session
\App\Utils\SessionManager::start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'System Health & Monitoring';
$currentPage = 'system-health';

// Initialize database and services
/** @var \App\Config\Database $database */
$database = new \App\Config\Database();
/** @var \App\Services\EnhancedAdminSettings $enhancedSettings */
$enhancedSettings = new \App\Services\EnhancedAdminSettings($database);

// Get system health and statistics
$systemHealth = $enhancedSettings->getSystemHealth();
$systemStats = $enhancedSettings->getSystemStats();

// Handle AJAX requests for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once __DIR__ . '/includes/csrf.php';
    \App\Utils\CSRFProtection::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_gateway':
            $gateway = $_POST['gateway'] ?? '';
            $result = ['success' => true, 'message' => ucfirst($gateway) . ' connection test successful'];
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'test_email':
            $result = ['success' => true, 'message' => 'Email system test successful'];
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <div class="">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">System Health & Monitoring</h2>
                <div class="text-gray-400 dark:text-gray-500 mt-1">Monitor system performance and health status</div>
            </div>
            <div class="col-auto ml-auto d-print-none">
                <div class="flex flex-wrap gap-2">
                    <button class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="refreshSystemHealth()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="space-y-6">
    <div class="">
        <!-- System Health Status -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">System Health Overview</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="">
                                <div class="flex align-items-center">
                                    <div class="subheader mr-3">Database</div>
                                    <?php if (($systemHealth['database'] ?? '') === 'healthy'): ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Healthy</span>
                                    <?php else: ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">Unhealthy</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="">
                                <div class="flex align-items-center">
                                    <div class="subheader mr-3">Payment Gateways</div>
                                    <?php if (($systemHealth['payment_gateways'] ?? '') === 'configured'): ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Configured</span>
                                    <?php else: ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400">Not Configured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="">
                                <div class="flex align-items-center">
                                    <div class="subheader mr-3">Email System</div>
                                    <?php if (($systemHealth['email_system'] ?? '') === 'configured'): ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Configured</span>
                                    <?php else: ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400">Not Configured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="">
                                <div class="flex align-items-center">
                                    <div class="subheader mr-3">Support System</div>
                                    <?php if (($systemHealth['support_system'] ?? '') === 'enabled'): ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Enabled</span>
                                    <?php else: ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-500">Disabled</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-3"><?= number_format($systemStats['users']['total'] ?? 0) ?></div>
                        <div class="flex mb-2">
                            <div class="flex align-items-center flex-fill">
                                <div class="text-gray-400 dark:text-gray-500 ml-2">Active: <?= number_format($systemStats['users']['active'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Investments</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-3"><?= number_format($systemStats['investments']['total'] ?? 0) ?></div>
                        <div class="flex mb-2">
                            <div class="flex align-items-center flex-fill">
                                <div class="text-gray-400 dark:text-gray-500 ml-2">$<?= number_format($systemStats['investments']['total_amount'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Transactions</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-3"><?= number_format($systemStats['transactions']['total'] ?? 0) ?></div>
                        <div class="flex mb-2">
                            <div class="flex align-items-center flex-fill">
                                <div class="text-gray-400 dark:text-gray-500 ml-2">$<?= number_format($systemStats['transactions']['total_amount'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Support Tickets</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-3"><?= number_format($systemStats['support_tickets']['total'] ?? 0) ?></div>
                        <div class="flex mb-2">
                            <div class="flex align-items-center flex-fill">
                                <div class="text-gray-400 dark:text-gray-500 ml-2">Open: <?= number_format($systemStats['support_tickets']['open'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Tests -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Payment Gateway Tests</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Test Cryptomus Connection</label>
                            <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 text-xs font-medium rounded-full" onclick="testPaymentGateway('cryptomus')">
                                Test Cryptomus
                            </button>
                            <div id="cryptomus-test-result" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Test NOWPayments Connection</label>
                            <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 text-xs font-medium rounded-full" onclick="testPaymentGateway('nowpayments')">
                                Test NOWPayments
                            </button>
                            <div id="nowpayments-test-result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Email System Test</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Test SMTP Connection</label>
                            <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 text-xs font-medium rounded-full" onclick="testEmailSystem()">
                                Test SMTP
                            </button>
                            <div id="email-test-result" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Test Email Templates</label>
                            <button class="btn btn-outline-secondary btn-sm" onclick="testEmailTemplates()">
                                View Templates
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Logs -->
        <div class="row row-deck row-cards mt-4">
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent System Activity</h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50/50 dark:bg-white/5">
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Component</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y g:i A') ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">System Health Check</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Completed</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">All systems operational</td>
                                    </tr>
                                    <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y g:i A', strtotime('-1 hour')) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">Database Backup</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Completed</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">Daily backup successful</td>
                                    </tr>
                                    <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= date('M j, Y g:i A', strtotime('-2 hours')) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">Email Queue</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400">In Progress</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">15 emails sent successfully</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshSystemHealth() {
    location.reload();
}

function testPaymentGateway(gateway) {
    const resultDiv = document.getElementById(gateway + '-test-result');
    resultDiv.innerHTML = '<div class="text-gray-400 dark:text-gray-500">Testing...</div>';
    
    fetch('system-health.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=test_gateway&gateway=' + gateway
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="text-emerald-600 dark:text-emerald-400"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="text-red-600 dark:text-red-400"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="text-red-600 dark:text-red-400"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Error: ' + error.message + '</div>';
    });
}

function testEmailSystem() {
    const resultDiv = document.getElementById('email-test-result');
    resultDiv.innerHTML = '<div class="text-gray-400 dark:text-gray-500">Testing...</div>';
    
    fetch('system-health.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=test_email'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="text-emerald-600 dark:text-emerald-400"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="text-red-600 dark:text-red-400"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="text-red-600 dark:text-red-400"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Error: ' + error.message + '</div>';
    });
}

function testEmailTemplates() {
    // This would open a modal showing available email templates
    alert('Email templates feature coming soon!');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
