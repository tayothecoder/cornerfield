<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// start session
\App\Utils\SessionManager::start();

// check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Payment Gateway Management';
$currentPage = 'payment-gateways';

// initialize database and services
$database = new \App\Config\Database();
$paymentGateway = new \App\Services\PaymentGatewayService($database);

// handle ajax requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    \App\Utils\CSRFProtection::validateRequest();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_gateway_settings':
            $gateway = $_POST['gateway'] ?? '';
            $settings = $_POST['settings'] ?? '';
            
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

// get current gateway settings
$gatewaySettings = $paymentGateway->getGatewayConfig();
$supportedCryptos = $paymentGateway->getSupportedCryptocurrencies();

include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- page header -->
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Payment Gateways</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure and manage payment gateways for automatic processing</p>
    </div>

    <!-- cryptomus configuration -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Cryptomus Gateway</h3>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="cryptomus-enabled" class="sr-only peer"
                       <?= ($gatewaySettings['payment_cryptomus_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <div class="w-9 h-5 bg-gray-200 dark:bg-[#2d1b6e] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#1e0e62]"></div>
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Enabled</span>
            </label>
        </div>
        <div class="p-6">
            <form id="cryptomus-form">
                <?= \App\Utils\CSRFProtection::getTokenField() ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Merchant ID</label>
                        <input type="text" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_cryptomus_merchant_id"
                               value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_merchant_id'] ?? '') ?>"
                               placeholder="Enter your Cryptomus Merchant ID">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">API Key</label>
                        <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_cryptomus_api_key"
                               value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_api_key'] ?? '') ?>"
                               placeholder="Enter your Cryptomus API Key">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Secret Key</label>
                        <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_cryptomus_secret_key"
                               value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_secret_key'] ?? '') ?>"
                               placeholder="Enter your Cryptomus Secret Key">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Return URL</label>
                        <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_cryptomus_return_url"
                               value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_return_url'] ?? '') ?>"
                               placeholder="https://yourdomain.com/payment/return">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Callback URL</label>
                        <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_cryptomus_callback_url"
                               value="<?= htmlspecialchars($gatewaySettings['payment_cryptomus_callback_url'] ?? '') ?>"
                               placeholder="https://yourdomain.com/payment/callback">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">This URL will receive payment notifications from Cryptomus</p>
                    </div>
                </div>
                <div class="flex gap-2 mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                    <button type="button" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveCryptomusSettings()">Save Settings</button>
                    <button type="button" class="px-6 py-2.5 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] transition-colors" onclick="testCryptomus()">Test Connection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- nowpayments configuration -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">NOWPayments Gateway</h3>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="nowpayments-enabled" class="sr-only peer"
                       <?= ($gatewaySettings['payment_nowpayments_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <div class="w-9 h-5 bg-gray-200 dark:bg-[#2d1b6e] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#1e0e62]"></div>
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Enabled</span>
            </label>
        </div>
        <div class="p-6">
            <form id="nowpayments-form">
                <?= \App\Utils\CSRFProtection::getTokenField() ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">API Key</label>
                        <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_nowpayments_api_key"
                               value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_api_key'] ?? '') ?>"
                               placeholder="Enter your NOWPayments API Key">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">IPN Secret</label>
                        <input type="password" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_nowpayments_ipn_secret"
                               value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_ipn_secret'] ?? '') ?>"
                               placeholder="Enter your NOWPayments IPN Secret">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Success URL</label>
                        <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_nowpayments_success_url"
                               value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_success_url'] ?? '') ?>"
                               placeholder="https://yourdomain.com/payment/success">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Cancel URL</label>
                        <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_nowpayments_cancel_url"
                               value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_cancel_url'] ?? '') ?>"
                               placeholder="https://yourdomain.com/payment/cancel">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Callback URL</label>
                        <input type="url" class="w-full px-4 py-2.5 bg-white dark:bg-[#0f0a2e] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62] text-sm" name="payment_nowpayments_callback_url"
                               value="<?= htmlspecialchars($gatewaySettings['payment_nowpayments_callback_url'] ?? '') ?>"
                               placeholder="https://yourdomain.com/payment/callback">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">This URL will receive IPN notifications from NOWPayments</p>
                    </div>
                </div>
                <div class="flex gap-2 mt-6 pt-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                    <button type="button" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveNOWPaymentsSettings()">Save Settings</button>
                    <button type="button" class="px-6 py-2.5 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full hover:border-[#1e0e62] transition-colors" onclick="testNOWPayments()">Test Connection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- supported cryptocurrencies -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Supported Cryptocurrencies</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Currencies available for payment processing</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <?php foreach ($supportedCryptos as $code => $name): ?>
                    <div class="flex flex-col items-center p-3 bg-gray-50/50 dark:bg-[#0f0a2e] border border-gray-100 dark:border-[#2d1b6e] rounded-xl text-center">
                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?= strtoupper(htmlspecialchars($code)) ?></span>
                        <span class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"><?= htmlspecialchars($name) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// gateway enable/disable toggles
document.getElementById('cryptomus-enabled').addEventListener('change', function() {
    updateGatewayStatus('cryptomus', this.checked);
});

document.getElementById('nowpayments-enabled').addEventListener('change', function() {
    updateGatewayStatus('nowpayments', this.checked);
});

function updateGatewayStatus(gateway, enabled) {
    var settingKey = 'payment_' + gateway + '_enabled';
    var value = enabled ? '1' : '0';
    
    var formData = new FormData();
    formData.append('action', 'update_gateway_settings');
    formData.append('settings[' + settingKey + ']', value);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification('Gateway status updated', 'success');
            } else {
                showNotification('Failed to update gateway status', 'error');
            }
        });
}

function saveCryptomusSettings() {
    var form = document.getElementById('cryptomus-form');
    var formData = new FormData(form);
    
    var settings = {};
    formData.forEach(function(value, key) {
        if (key !== 'csrf_token') {
            settings[key] = value;
        }
    });
    // include enabled state
    settings['payment_cryptomus_enabled'] = document.getElementById('cryptomus-enabled').checked ? '1' : '0';
    
    var postData = new FormData();
    postData.append('action', 'update_gateway_settings');
    postData.append('settings', JSON.stringify(settings));
    postData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', { method: 'POST', body: postData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification('Cryptomus settings saved', 'success');
            } else {
                showNotification('Failed to save settings', 'error');
            }
        });
}

function saveNOWPaymentsSettings() {
    var form = document.getElementById('nowpayments-form');
    var formData = new FormData(form);
    
    var settings = {};
    formData.forEach(function(value, key) {
        if (key !== 'csrf_token') {
            settings[key] = value;
        }
    });
    settings['payment_nowpayments_enabled'] = document.getElementById('nowpayments-enabled').checked ? '1' : '0';
    
    var postData = new FormData();
    postData.append('action', 'update_gateway_settings');
    postData.append('settings', JSON.stringify(settings));
    postData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', { method: 'POST', body: postData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification('NOWPayments settings saved', 'success');
            } else {
                showNotification('Failed to save settings', 'error');
            }
        });
}

function testCryptomus() { testGateway('cryptomus'); }
function testNOWPayments() { testGateway('nowpayments'); }

function testGateway(gateway) {
    var formData = new FormData();
    formData.append('action', 'test_gateway');
    formData.append('gateway', gateway);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('payment-gateways.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(gateway + ' connection test successful', 'success');
            } else {
                showNotification(gateway + ' connection test failed: ' + (data.message || 'Unknown error'), 'error');
            }
        });
}
</script>
