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

$pageTitle = 'Payment Gateway Management';
$currentPage = 'payment-gateways';

// Initialize database and services
/** @var \App\Config\Database $database */
$database = new \App\Config\Database();
/** @var \App\Services\PaymentGatewayService $paymentGateway */
/** @suppress UndefinedType */
$paymentGateway = new \App\Services\PaymentGatewayService($database);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once __DIR__ . '/includes/csrf.php';
    \App\Utils\CSRFProtection::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_gateway_settings':
            $gateway = $_POST['gateway'] ?? '';
            $settings = $_POST['settings'] ?? '';
            
            // Decode JSON settings if they come as a string
            if (is_string($settings)) {
                $settings = json_decode($settings, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Invalid settings format']);
                    exit;
                }
            }
            
            if (empty($settings)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No settings provided']);
                exit;
            }
            
            $result = $paymentGateway->updateGatewaySettings($settings);
            header('Content-Type: application/json');
            echo json_encode(['success' => $result]);
            exit;
            
        case 'test_gateway':
            $gateway = $_POST['gateway'] ?? '';
            $result = $paymentGateway->testPaymentGateway($gateway);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
    }
}

// Get current gateway settings
$gatewaySettings = $paymentGateway->getGatewayConfig();
$supportedCryptos = $paymentGateway->getSupportedCryptocurrencies();

include __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <div class="">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Payment Gateway Management</h2>
                <div class="text-gray-400 dark:text-gray-500 mt-1">Configure and manage payment gateways for automatic processing</div>
            </div>
        </div>
    </div>
</div>

<div class="space-y-6">
    <div class="">
        <!-- Cryptomus Configuration -->
        <div class="card mb-4">
            <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon mr-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <rect x="3" y="4" width="18" height="8" rx="1" />
                        <path d="M12 8v4" />
                        <path d="M12 16h.01" />
                    </svg>
                    Cryptomus Gateway
                </h3>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2">
                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" id="cryptomus-enabled" 
                               <?= ($gatewaySettings['payment_cryptomus_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Enable</span>
                    </label>
                </div>
            </div>
            <div class="p-6">
                <form id="cryptomus-form">
                    <?= \App\Utils\CSRFProtection::getTokenField() ?>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Merchant ID</label>
                                <input type="text" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_cryptomus_merchant_id" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_merchant_id'] ?? '') ?>" 
                                       placeholder="Enter your Cryptomus Merchant ID">
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">API Key</label>
                                <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_cryptomus_api_key" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_api_key'] ?? '') ?>" 
                                       placeholder="Enter your Cryptomus API Key">
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Secret Key</label>
                                <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_cryptomus_secret_key" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_secret_key'] ?? '') ?>" 
                                       placeholder="Enter your Cryptomus Secret Key">
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Return URL</label>
                                <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_cryptomus_return_url" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_return_url'] ?? '') ?>" 
                                       placeholder="https://yourdomain.com/payment/return">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Callback URL</label>
                        <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_cryptomus_callback_url" 
                               value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_callback_url'] ?? '') ?>" 
                               placeholder="https://yourdomain.com/payment/callback">
                        <small class="text-xs text-gray-400 mt-1">This URL will receive payment notifications from Cryptomus</small>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveCryptomusSettings()">
                            Save Settings
                        </button>
                        <button type="button" class="px-4 py-2 border border-[#1e0e62] text-[#1e0e62] dark:text-indigo-400 text-sm font-medium rounded-full hover:bg-[#1e0e62] hover:text-white transition-colors" onclick="testCryptomus()">
                            Test Connection
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- NOWPayments Configuration -->
        <div class="card mb-4">
            <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon mr-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                        <path d="M6.835 9h10.33a1 1 0 0 1 .984 .821l1.637 8a1 1 0 0 1 -.984 1.179h-12.04a1 1 0 0 1 -.984 -1.179l1.637 -8a1 1 0 0 1 .984 -.821z" />
                    </svg>
                    NOWPayments Gateway
                </h3>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2">
                        <input class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" type="checkbox" id="nowpayments-enabled" 
                               <?= ($gatewaySettings['payment_nowpayments_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Enable</span>
                    </label>
                </div>
            </div>
            <div class="p-6">
                <form id="nowpayments-form">
                    <?= \App\Utils\CSRFProtection::getTokenField() ?>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">API Key</label>
                                <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_nowpayments_api_key" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_api_key'] ?? '') ?>" 
                                       placeholder="Enter your NOWPayments API Key">
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">IPN Secret</label>
                                <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_nowpayments_ipn_secret" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_ipn_secret'] ?? '') ?>" 
                                       placeholder="Enter your NOWPayments IPN Secret">
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Success URL</label>
                                <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_nowpayments_success_url" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_success_url'] ?? '') ?>" 
                                       placeholder="https://yourdomain.com/payment/success">
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Cancel URL</label>
                                <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_nowpayments_cancel_url" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_cancel_url'] ?? '') ?>" 
                                       placeholder="https://yourdomain.com/payment/cancel">
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Callback URL</label>
                                <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" name="payment_nowpayments_callback_url" 
                                       value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_callback_url'] ?? '') ?>" 
                                       placeholder="https://yourdomain.com/payment/callback">
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveNOWPaymentsSettings()">
                            Save Settings
                        </button>
                        <button type="button" class="px-4 py-2 border border-[#1e0e62] text-[#1e0e62] dark:text-indigo-400 text-sm font-medium rounded-full hover:bg-[#1e0e62] hover:text-white transition-colors" onclick="testNOWPayments()">
                            Test Connection
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Supported Cryptocurrencies -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon mr-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                        <path d="M12 6v2m0 8v2" />
                    </svg>
                    Supported Cryptocurrencies
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($supportedCryptos as $code => $name): ?>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="flex align-items-center p-2 border rounded">
                            <div class="flex-fill">
                                <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($name) ?></div>
                                <div class="text-gray-400 dark:text-gray-500"><?= strtoupper($code) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle gateway enable/disable
document.getElementById('cryptomus-enabled').addEventListener('change', function() {
    updateGatewayStatus('cryptomus', this.checked);
});

document.getElementById('nowpayments-enabled').addEventListener('change', function() {
    updateGatewayStatus('nowpayments', this.checked);
});

function updateGatewayStatus(gateway, enabled) {
    const settingKey = `payment_${gateway}_enabled`;
    const value = enabled ? '1' : '0';
    
    const formData = new FormData();
    formData.append('action', 'update_gateway_settings');
    formData.append(`settings[${settingKey}]`, value);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Gateway status updated successfully', 'success');
        } else {
            showNotification('Failed to update gateway status', 'error');
        }
    });
}

function saveCryptomusSettings() {
    const form = document.getElementById('cryptomus-form');
    const formData = new FormData(form);
    formData.append('action', 'update_gateway_settings');
    
    const settings = {};
    for (let [key, value] of formData.entries()) {
        if (key !== 'action') {
            settings[key] = value;
        }
    }
    
    fetch('payment-gateways.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_gateway_settings&settings=${JSON.stringify(settings)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cryptomus settings saved successfully', 'success');
        } else {
            showNotification('Failed to save settings', 'error');
        }
    });
}

function saveNOWPaymentsSettings() {
    const form = document.getElementById('nowpayments-form');
    const formData = new FormData(form);
    formData.append('action', 'update_gateway_settings');
    
    const settings = {};
    for (let [key, value] of formData.entries()) {
        if (key !== 'action') {
            settings[key] = value;
        }
    }
    
    const formData2 = new FormData();
    formData2.append('action', 'update_gateway_settings');
    formData2.append('settings', JSON.stringify(settings));
    formData2.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', {
        method: 'POST',
        body: formData2
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('NOWPayments settings saved successfully', 'success');
        } else {
            showNotification('Failed to save settings', 'error');
        }
    });
}

function testCryptomus() {
    testGateway('cryptomus');
}

function testNOWPayments() {
    testGateway('nowpayments');
}

function testGateway(gateway) {
    const formData = new FormData();
    formData.append('action', 'test_gateway');
    formData.append('gateway', gateway);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`${gateway} connection test successful`, 'success');
        } else {
            showNotification(`${gateway} connection test failed: ${data.message}`, 'error');
        }
    });
}

function showNotification(message, type) {
    // Simple notification - you can enhance this with a proper toast library
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" ></button>
    `;
    
    document.querySelector('.page-body').insertBefore(alertDiv, document.querySelector('.page-body').firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
