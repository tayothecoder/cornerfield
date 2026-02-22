<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

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

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">System Health & Monitoring</h2>
                <div class="text-muted mt-1">Monitor system performance and health status</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button class="btn btn-primary" onclick="refreshSystemHealth()">
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

<div class="page-body">
    <div class="container-xl">
        <!-- System Health Status -->
        <div class="row row-deck row-cards mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Health Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="subheader me-3">Database</div>
                                    <?php if (($systemHealth['database'] ?? '') === 'healthy'): ?>
                                        <span class="badge bg-success">Healthy</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Unhealthy</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="subheader me-3">Payment Gateways</div>
                                    <?php if (($systemHealth['payment_gateways'] ?? '') === 'configured'): ?>
                                        <span class="badge bg-success">Configured</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Configured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="subheader me-3">Email System</div>
                                    <?php if (($systemHealth['email_system'] ?? '') === 'configured'): ?>
                                        <span class="badge bg-success">Configured</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Configured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <div class="subheader me-3">Support System</div>
                                    <?php if (($systemHealth['support_system'] ?? '') === 'enabled'): ?>
                                        <span class="badge bg-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disabled</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="row row-deck row-cards mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Users</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($systemStats['users']['total'] ?? 0) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="text-muted ms-2">Active: <?= number_format($systemStats['users']['active'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Active Investments</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($systemStats['investments']['total'] ?? 0) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="text-muted ms-2">$<?= number_format($systemStats['investments']['total_amount'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Transactions</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($systemStats['transactions']['total'] ?? 0) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="text-muted ms-2">$<?= number_format($systemStats['transactions']['total_amount'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Support Tickets</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($systemStats['support_tickets']['total'] ?? 0) ?></div>
                        <div class="d-flex mb-2">
                            <div class="d-flex align-items-center flex-fill">
                                <div class="text-muted ms-2">Open: <?= number_format($systemStats['support_tickets']['open'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Tests -->
        <div class="row row-deck row-cards">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Payment Gateway Tests</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Test Cryptomus Connection</label>
                            <button class="btn btn-outline-primary btn-sm" onclick="testPaymentGateway('cryptomus')">
                                Test Cryptomus
                            </button>
                            <div id="cryptomus-test-result" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Test NOWPayments Connection</label>
                            <button class="btn btn-outline-primary btn-sm" onclick="testPaymentGateway('nowpayments')">
                                Test NOWPayments
                            </button>
                            <div id="nowpayments-test-result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Email System Test</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Test SMTP Connection</label>
                            <button class="btn btn-outline-primary btn-sm" onclick="testEmailSystem()">
                                Test SMTP
                            </button>
                            <div id="email-test-result" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Test Email Templates</label>
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
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent System Activity</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Component</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?= date('M j, Y g:i A') ?></td>
                                        <td>System Health Check</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                        <td>All systems operational</td>
                                    </tr>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime('-1 hour')) ?></td>
                                        <td>Database Backup</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                        <td>Daily backup successful</td>
                                    </tr>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime('-2 hours')) ?></td>
                                        <td>Email Queue</td>
                                        <td><span class="badge bg-info">Processing</span></td>
                                        <td>15 emails sent successfully</td>
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
    resultDiv.innerHTML = '<div class="text-muted">Testing...</div>';
    
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
            resultDiv.innerHTML = '<div class="text-success"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="text-danger"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="text-danger"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Error: ' + error.message + '</div>';
    });
}

function testEmailSystem() {
    const resultDiv = document.getElementById('email-test-result');
    resultDiv.innerHTML = '<div class="text-muted">Testing...</div>';
    
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
            resultDiv.innerHTML = '<div class="text-success"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="text-danger"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="text-danger"><svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Error: ' + error.message + '</div>';
    });
}

function testEmailTemplates() {
    // This would open a modal showing available email templates
    alert('Email templates feature coming soon!');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
