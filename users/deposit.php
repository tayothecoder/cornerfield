<?php
declare(strict_types=1);

use App\Models\DepositModel;
use App\Models\DepositMethodModel;
use App\Controllers\DepositController;
use App\Middleware\AuthMiddleware;
use App\Utils\Security;

// handle ajax requests before including header
require_once __DIR__ . '/../autoload.php';
\App\Config\Config::init();

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

if ($isAjax) {
    $basePath = \App\Config\Config::getBasePath();
    if (session_status() === PHP_SESSION_NONE) {
        $cookiePath = $basePath ?: '/';
        session_set_cookie_params(['path' => $cookiePath, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    if (!AuthMiddleware::check()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $controller = new DepositController();
            $controller->create();
        } catch (\Throwable $e) {
            error_log('Deposit POST failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error processing deposit']);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'history') {
        try {
            $controller = new DepositController();
            $controller->getHistory();
        } catch (\Throwable $e) {
            error_log('Deposit history failed: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error loading history']);
        }
        exit;
    }
}

$pageTitle = 'Deposit';
$currentPage = 'deposit';
require_once __DIR__ . '/includes/header.php';

$depositModel = new DepositModel();
$methodModel = new DepositMethodModel();
$depositMethods = $methodModel->findActive();

?>

<div class="space-y-6">
    <!-- deposit methods -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Choose Deposit Method</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Select your preferred method to add funds to your account</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="depositMethods">
            <?php foreach ($depositMethods as $method): ?>
            <div class="method-card relative border border-gray-200 dark:border-[#2d1b6e] rounded-2xl p-4 cursor-pointer hover:border-[#1e0e62] transition-colors" 
                 data-method-id="<?= $method['id'] ?>" 
                 data-method-type="<?= Security::escape($method['type']) ?>"
                 data-method-name="<?= Security::escape($method['name']) ?>"
                 data-min-amount="<?= $method['minimum_deposit'] ?>"
                 data-max-amount="<?= $method['maximum_deposit'] ?>"
                 data-fee-type="<?= Security::escape($method['charge_type']) ?>"
                 data-fee-rate="<?= $method['charge'] ?>">
                
                <div class="absolute top-3 right-3">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?= $method['type'] === 'auto' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' ?>">
                        <?= $method['type'] === 'auto' ? 'Auto' : 'Manual' ?>
                    </span>
                </div>
                
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white"><?= Security::escape($method['name']) ?></h3>
                    
                    <div class="mt-3 space-y-2">
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"/></svg>
                            Fee: <?= $method['charge_type'] === 'percentage' ? number_format($method['charge'], 2) . '%' : '$' . number_format($method['charge'], 2) ?>
                        </div>
                        
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2"/></svg>
                            Min: $<?= number_format($method['minimum_deposit'], 2) ?> • Max: $<?= number_format($method['maximum_deposit'], 0) ?>
                        </div>
                        
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?= $method['type'] === 'auto' ? 'Instant' : '1-24 hours' ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- deposit form -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6" id="depositFormContainer" style="display: none;">
        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Deposit Details</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6" id="selectedMethodName">Complete your deposit information</p>
        
        <form id="depositForm" class="space-y-6">
            <?= Security::getCsrfTokenInput() ?>
            <input type="hidden" id="selectedMethodId" name="method_id" value="">
            
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Deposit Amount (USD)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 text-sm dark:text-gray-400">$</span>
                    </div>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           step="0.01" 
                           min="0" 
                           class="block w-full pl-7 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]" 
                           placeholder="0.00"
                           required>
                </div>
                <div id="amountLimits" class="mt-2 text-sm text-gray-500 dark:text-gray-400"></div>
            </div>

            <div class="bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl p-4" id="feeCalculation" style="display: none;">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Deposit Amount:</span>
                    <span id="displayAmount" class="text-sm text-gray-900 dark:text-white">$0.00</span>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Processing Fee:</span>
                    <span id="displayFee" class="text-sm text-gray-900 dark:text-white">$0.00</span>
                </div>
                <div class="border-t border-gray-200 dark:border-[#2d1b6e] mt-3 pt-3">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-semibold text-gray-900 dark:text-white">You will receive:</span>
                        <span id="displayNetAmount" class="text-base font-semibold text-emerald-600 dark:text-emerald-400">$0.00</span>
                    </div>
                </div>
            </div>

            <div id="currencySection" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Currency</label>
                        <select id="currency" 
                                name="currency" 
                                class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                            <option value="BTC">Bitcoin (BTC)</option>
                            <option value="ETH">Ethereum (ETH)</option>
                            <option value="USDT" selected>Tether (USDT)</option>
                            <option value="LTC">Litecoin (LTC)</option>
                        </select>
                    </div>
                    <div>
                        <label for="network" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Network</label>
                        <select id="network" 
                                name="network" 
                                class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                            <option value="TRC20">TRC20</option>
                            <option value="ERC20">ERC20</option>
                            <option value="BEP20">BEP20</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="manualFields" style="display: none;">
                <div>
                    <label for="transactionHash" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Transaction Hash</label>
                    <input type="text" 
                           id="transactionHash" 
                           name="transaction_hash" 
                           class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]" 
                           placeholder="Enter blockchain transaction hash">
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Optional: provide the transaction hash after sending payment</p>
                </div>

                <div>
                    <label for="proofOfPayment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Proof of Payment</label>
                    <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-[#2d1b6e] border-dashed rounded-xl">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                <label for="proofOfPayment" class="relative cursor-pointer bg-white dark:bg-[#0f0a2e] rounded-md font-medium text-[#1e0e62] dark:text-indigo-400 hover:text-[#2d1b8a] focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-[#1e0e62]">
                                    <span>Upload a file</span>
                                    <input id="proofOfPayment" name="proof_of_payment" type="file" accept="image/*,.pdf" class="sr-only">
                                </label>
                                <p class="pl-1">Or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, PDF up to 5MB</p>
                        </div>
                    </div>
                    <div id="uploadedFileName" class="mt-2 text-sm text-emerald-600 dark:text-emerald-400" style="display: none;"></div>
                </div>

                <div id="depositAddressSection" class="bg-amber-100 dark:bg-amber-900/30 border-l-4 border-amber-400 p-4 rounded-xl" style="display: none;">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Send payment to this address</h3>
                            <div class="mt-2">
                                <div class="bg-white dark:bg-[#0f0a2e] p-3 rounded-xl border border-gray-200 dark:border-[#2d1b6e] font-mono text-sm break-all" id="displayDepositAddress"></div>
                                <button type="button" id="copyAddressBtn" class="mt-3 inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full bg-[#1e0e62] text-white hover:bg-[#2d1b8a] transition-colors">
                                    Copy Address
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="autoDepositInfo" class="bg-blue-100 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-xl" style="display: none;">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Automatic Processing</h3>
                        <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">You will be redirected to our secure payment processor to complete the transaction. Funds are credited automatically upon successful payment.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" 
                        id="cancelBtn" 
                        class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] rounded-full text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#0f0a2e] hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        id="submitBtn" 
                        class="px-6 py-2 bg-[#1e0e62] text-white rounded-full text-sm font-medium hover:bg-[#2d1b8a] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="submitText">Create Deposit</span>
                    <svg id="submitSpinner" class="hidden animate-spin -mr-1 ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <!-- deposit history -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Recent Deposits</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Track your deposit history and status</p>
        
        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fee</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="depositHistory" class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                        <tr>
                            <td colspan="6" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    loading deposits...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- success modal -->
<div class="fixed inset-0 z-50 overflow-y-auto hidden" id="successModal">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeSuccessModal()"></div>
        <div class="inline-block align-bottom bg-white dark:bg-[#1a1145] rounded-3xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-xl transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Deposit Created Successfully</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="successMessage">Your deposit request has been created and is being processed.</p>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <button type="button" class="w-full inline-flex justify-center rounded-full px-4 py-2 bg-[#1e0e62] text-base font-medium text-white hover:bg-[#2d1b8a] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1e0e62]" onclick="closeSuccessModal()">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($base ?? \App\Config\Config::getBasePath()) ?>/assets/js/deposit.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>