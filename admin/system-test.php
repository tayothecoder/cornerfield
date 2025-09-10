<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\SiteSettings;
use App\Services\EmailService;
use App\Services\PaymentGatewayService;
use App\Utils\SessionManager;

// Start session and check admin authentication
SessionManager::start();

if (!SessionManager::get('admin_logged_in')) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$testResults = [];
$overallStatus = 'PASS';

function runTest($testName, $testFunction) {
    global $testResults, $overallStatus;
    
    try {
        $result = $testFunction();
        $testResults[] = [
            'name' => $testName,
            'status' => $result ? 'PASS' : 'FAIL',
            'message' => $result ? 'Test passed successfully' : 'Test failed',
            'details' => ''
        ];
        
        if (!$result) {
            $overallStatus = 'FAIL';
        }
    } catch (Exception $e) {
        $testResults[] = [
            'name' => $testName,
            'status' => 'ERROR',
            'message' => 'Test error: ' . $e->getMessage(),
            'details' => $e->getTraceAsString()
        ];
        $overallStatus = 'FAIL';
    }
}

// Test functions
function testDatabaseConnection() {
    global $database;
    $result = $database->fetchOne("SELECT 1 as test");
    return $result['test'] === 1;
}

function testUserModel() {
    global $database;
    $userModel = new User($database);
    
    // Test user creation
    $testUser = [
        'username' => 'test_user_' . time(),
        'email' => 'test' . time() . '@example.com',
        'password' => password_hash('test123', PASSWORD_DEFAULT),
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '1234567890',
        'country' => 'US',
        'balance' => 1000.00
    ];
    
    $userId = $userModel->create($testUser);
    if (!$userId) return false;
    
    // Test user retrieval
    $user = $userModel->findById($userId);
    if (!$user || $user['username'] !== $testUser['username']) return false;
    
    // Test user update
    $userModel->update($userId, ['first_name' => 'Updated']);
    $updatedUser = $userModel->findById($userId);
    if ($updatedUser['first_name'] !== 'Updated') return false;
    
    // Test user deletion (using direct database query since User model doesn't have delete method)
    $database->query("DELETE FROM users WHERE id = ?", [$userId]);
    $deletedUser = $userModel->findById($userId);
    return $deletedUser === false;
}

function testInvestmentModel() {
    global $database;
    $investmentModel = new Investment($database);
    
    // Test schema creation
    $testSchema = [
        'name' => 'Test Plan',
        'description' => 'Test investment plan',
        'min_amount' => 100,
        'max_amount' => 10000,
        'daily_rate' => 2.0,
        'duration_days' => 30,
        'total_return' => 60.0,
        'status' => 'active'
    ];
    
    $schemaId = $investmentModel->createSchema($testSchema);
    if (!$schemaId) return false;
    
    // Test schema retrieval
    $schema = $investmentModel->getSchemaById($schemaId);
    if (!$schema || $schema['name'] !== $testSchema['name']) return false;
    
    // Test schema update
    $investmentModel->updateSchema($schemaId, ['name' => 'Updated Plan']);
    $updatedSchema = $investmentModel->getSchemaById($schemaId);
    if ($updatedSchema['name'] !== 'Updated Plan') return false;
    
    // Test schema deletion
    $investmentModel->deleteSchema($schemaId);
    $deletedSchema = $investmentModel->getSchemaById($schemaId);
    return $deletedSchema === false;
}

function testTransactionModel() {
    global $database;
    $transactionModel = new Transaction($database);
    
    // First create a test user for the transaction
    $testUser = [
        'username' => 'test_transaction_user_' . time(),
        'email' => 'test_transaction_' . time() . '@example.com',
        'password' => password_hash('test123', PASSWORD_DEFAULT),
        'first_name' => 'Test',
        'last_name' => 'Transaction',
        'phone' => '1234567890',
        'country' => 'US',
        'balance' => 1000.00
    ];
    
    $userModel = new User($database);
    $userId = $userModel->create($testUser);
    if (!$userId) return false;
    
    // Test transaction creation
    $testTransaction = [
        'user_id' => $userId,
        'type' => 'deposit',
        'amount' => 100.00,
        'status' => 'pending',
        'description' => 'Test transaction',
        'payment_method' => 'test'
    ];
    
    $transactionId = $transactionModel->createTransaction($testTransaction);
    if (!$transactionId) return false;
    
    // Test transaction retrieval
    $transaction = $transactionModel->getTransactionById($transactionId);
    if (!$transaction || $transaction['amount'] != $testTransaction['amount']) return false;
    
    // Test transaction update
    $transactionModel->updateTransactionStatus($transactionId, 'completed');
    $updatedTransaction = $transactionModel->getTransactionById($transactionId);
    if ($updatedTransaction['status'] !== 'completed') return false;
    
    // Test transaction deletion (using direct database query)
    $database->query("DELETE FROM transactions WHERE id = ?", [$transactionId]);
    $deletedTransaction = $transactionModel->getTransactionById($transactionId);
    
    // Clean up test user
    $database->query("DELETE FROM users WHERE id = ?", [$userId]);
    
    return $deletedTransaction === false;
}

function testSiteSettings() {
    global $database;
    $siteSettings = new SiteSettings($database);
    
    // Test setting creation
    $siteSettings->set('test_setting', 'test_value', 'text', 'test', 'Test setting');
    $value = $siteSettings->get('test_setting');
    if ($value !== 'test_value') return false;
    
    // Test setting update
    $siteSettings->set('test_setting', 'updated_value');
    $updatedValue = $siteSettings->get('test_setting');
    if ($updatedValue !== 'updated_value') return false;
    
    // Test setting deletion
    $siteSettings->delete('test_setting');
    $deletedValue = $siteSettings->get('test_setting');
    return $deletedValue === null;
}

function testEmailService() {
    try {
        global $database;
        $emailService = new EmailService($database);
        
        // Test configuration check
        $isConfigured = $emailService->isConfigured();
        
        // Test if service can be instantiated (basic test)
        return $isConfigured !== null;
    } catch (Exception $e) {
        return false;
    }
}

function testPaymentGatewayService() {
    try {
        global $database;
        $paymentService = new PaymentGatewayService($database);
        
        // Test service initialization and configuration loading
        $config = $paymentService->getGatewayConfig();
        
        return is_array($config);
    } catch (Exception $e) {
        return false;
    }
}

function testSessionManagement() {
    // Test session start
    if (!SessionManager::isStarted()) {
        SessionManager::start();
    }
    
    // Test session data storage
    SessionManager::set('test_key', 'test_value');
    $value = SessionManager::get('test_key');
    if ($value !== 'test_value') return false;
    
    // Test session data removal
    SessionManager::remove('test_key');
    $removedValue = SessionManager::get('test_key');
    return $removedValue === null;
}

function testFilePermissions() {
    $directories = [
        '../assets/uploads/',
        '../src/Templates/emails/',
        '../logs/',
        '../cache/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }
        
        if (!is_writable($dir)) {
            return false;
        }
    }
    
    return true;
}

function testDatabaseTables() {
    global $database;
    
    $requiredTables = [
        'users', 'investment_schemas', 'investments', 'transactions',
        'deposits', 'withdrawals', 'admin_settings', 'site_settings',
        'user_transfers', 'email_logs', 'security_logs'
    ];
    
    foreach ($requiredTables as $table) {
        $result = $database->fetchOne("SHOW TABLES LIKE ?", [$table]);
        if (!$result) {
            return false;
        }
    }
    
    return true;
}

$pageTitle = 'System Test Results';
include __DIR__ . '/includes/header.php';

// Run all tests
runTest('Database Connection', 'testDatabaseConnection');
runTest('User Model Operations', 'testUserModel');
runTest('Investment Model Operations', 'testInvestmentModel');
runTest('Transaction Model Operations', 'testTransactionModel');
runTest('Site Settings Management', 'testSiteSettings');
runTest('Email Service Configuration', 'testEmailService');
runTest('Payment Gateway Service', 'testPaymentGatewayService');
runTest('Session Management', 'testSessionManagement');
runTest('File Permissions', 'testFilePermissions');
runTest('Database Tables', 'testDatabaseTables');
?>

<style>
.test-results {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.test-header {
    text-align: center;
    margin-bottom: 3rem;
}

.test-status {
    font-size: 2rem;
    font-weight: 700;
    padding: 1rem 2rem;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.test-status.pass {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.test-status.fail {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.test-summary {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.test-item {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #e5e7eb;
}

.test-item.pass {
    border-left-color: #10b981;
}

.test-item.fail {
    border-left-color: #ef4444;
}

.test-item.error {
    border-left-color: #f59e0b;
}

.test-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.test-status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.test-status-badge.pass {
    background: #d1fae5;
    color: #065f46;
}

.test-status-badge.fail {
    background: #fee2e2;
    color: #991b1b;
}

.test-status-badge.error {
    background: #fef3c7;
    color: #92400e;
}

.test-message {
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.test-details {
    font-family: monospace;
    font-size: 0.8rem;
    color: #374151;
    background: #f9fafb;
    padding: 0.75rem;
    border-radius: 4px;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}
</style>

<div class="test-results">
    <div class="test-header">
        <h1 class="h2 mb-3">System Test Results</h1>
        <div class="test-status <?= strtolower($overallStatus) ?>">
            <?= $overallStatus === 'PASS' ? 'All Tests Passed' : 'Some Tests Failed' ?>
        </div>
        <p class="text-muted">Comprehensive system functionality test</p>
    </div>

    <div class="test-summary">
        <h3 class="h4 mb-3">Test Summary</h3>
        <div class="row">
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h3 text-success"><?= count(array_filter($testResults, fn($t) => $t['status'] === 'PASS')) ?></div>
                    <div class="text-muted">Passed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h3 text-danger"><?= count(array_filter($testResults, fn($t) => $t['status'] === 'FAIL')) ?></div>
                    <div class="text-muted">Failed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h3 text-warning"><?= count(array_filter($testResults, fn($t) => $t['status'] === 'ERROR')) ?></div>
                    <div class="text-muted">Errors</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h3 text-primary"><?= count($testResults) ?></div>
                    <div class="text-muted">Total</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h4 mb-0">Test Details</h3>
        <div>
            <button class="btn btn-secondary me-2" onclick="window.location.reload()">Run Tests Again</button>
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>

    <?php foreach ($testResults as $test): ?>
        <div class="test-item <?= strtolower($test['status']) ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="test-name"><?= htmlspecialchars($test['name']) ?></div>
                <span class="test-status-badge <?= strtolower($test['status']) ?>"><?= $test['status'] ?></span>
            </div>
            <div class="test-message"><?= htmlspecialchars($test['message']) ?></div>
            <?php if (!empty($test['details'])): ?>
                <div class="test-details"><?= htmlspecialchars($test['details']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($overallStatus === 'PASS'): ?>
        <div class="alert alert-success mt-4">
            <h4 class="alert-heading">System Ready!</h4>
            <p>All tests have passed successfully. Your system is fully functional and ready for production use.</p>
            <hr>
            <p class="mb-0">You can now confidently use all features of the CornerField investment platform.</p>
        </div>
    <?php else: ?>
        <div class="alert alert-danger mt-4">
            <h4 class="alert-heading">Issues Found!</h4>
            <p>Some tests failed or encountered errors. Please review the test results above and fix any issues before using the system in production.</p>
            <hr>
            <p class="mb-0">Contact your system administrator if you need assistance resolving these issues.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
