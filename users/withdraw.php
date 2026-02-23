<?php
declare(strict_types=1);

use App\Models\WithdrawalModel;
use App\Models\UserModel;
use App\Controllers\WithdrawalController;
use App\Utils\Security;

// handle ajax requests before any html output
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once dirname(__DIR__) . '/autoload.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $controller = new WithdrawalController();
            $controller->create();
        } catch (\Throwable $e) {
            error_log('Withdrawal POST failed: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error processing withdrawal']);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'history') {
        try {
            $controller = new WithdrawalController();
            $controller->getHistory();
        } catch (\Throwable $e) {
            error_log('Withdrawal history failed: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error loading history']);
        }
        exit;
    }
}

$pageTitle = 'Withdraw';
$currentPage = 'withdraw';
require_once __DIR__ . '/includes/header.php';

$userModel = new UserModel();
$currentUser = $userModel->findById((int)($_SESSION['user_id'] ?? 0)) ?? [
    'balance' => 0,
    'total_withdrawn' => 0,
];
$withdrawalModel = new WithdrawalModel();

?>

<div class="space-y-6">
    <!-- balance overview -->
    <div class="bg-[#1e0e62] rounded-3xl text-white p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-medium tracking-tight">Available Balance</h2>
                <p class="text-3xl font-light tracking-tighter mt-1" id="userBalance">$<?= number_format((float)$currentUser['balance'], 2) ?></p>
            </div>
            <div class="text-right">
                <div class="text-sm opacity-75">Total Withdrawn</div>
                <div class="text-lg font-light tracking-tighter">$<?= number_format((float)$currentUser['total_withdrawn'], 2) ?></div>
            </div>
        </div>
        
        <div id="pendingAlert" class="mt-4 bg-white bg-opacity-10 rounded-2xl p-3" style="display: none;">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <span id="pendingText" class="text-sm">You have pending withdrawals totaling $0.00</span>
            </div>
        </div>
    </div>

    <!-- withdrawal form -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Create Withdrawal</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Withdraw funds to your cryptocurrency wallet</p>
        
        <form id="withdrawalForm" class="space-y-6">
            <?= Security::getCsrfTokenInput() ?>
            
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Withdrawal Amount (USD)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 text-sm dark:text-gray-400">$</span>
                    </div>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           step="0.01" 
                           min="10" 
                           max="50000" 
                           class="block w-full pl-7 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]" 
                           placeholder="0.00"
                           required>
                </div>
                <div class="mt-2 flex justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>Min: $10.00</span>
                    <span>Max: $50,000.00</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Currency</label>
                    <select id="currency" 
                            name="currency" 
                            class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]"
                            required>
                        <option value="">Select currency</option>
                        <option value="BTC">₿ Bitcoin (BTC)</option>
                        <option value="ETH">Ξ Ethereum (ETH)</option>
                        <option value="USDT" selected>₮ Tether (USDT)</option>
                        <option value="LTC">Ł Litecoin (LTC)</option>
                    </select>
                </div>
                <div>
                    <label for="network" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Network</label>
                    <select id="network" 
                            name="network" 
                            class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]"
                            required>
                        <option value="">Select network</option>
                        <option value="TRC20">TRC20 (Tron)</option>
                        <option value="ERC20">ERC20 (Ethereum)</option>
                        <option value="BEP20">BEP20 (BSC)</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="walletAddress" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Wallet Address</label>
                <input type="text" 
                       id="walletAddress" 
                       name="wallet_address" 
                       class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]" 
                       placeholder="Enter your wallet address"
                       required>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Make sure the wallet address matches the selected currency and network</p>
            </div>

            <div class="bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl p-4" id="feeCalculation" style="display: none;">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Withdrawal Summary</h4>
                
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Withdrawal Amount:</span>
                        <span id="displayAmount" class="text-sm font-medium text-gray-900 dark:text-white">$0.00</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Processing Fee (<span id="feeRate">5.0</span>%):</span>
                        <span id="displayFee" class="text-sm font-medium text-gray-900 dark:text-white">$0.00</span>
                    </div>
                    <div class="border-t border-gray-200 dark:border-[#2d1b6e] pt-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Deducted:</span>
                            <span id="displayTotalDeduction" class="text-sm font-medium text-red-600 dark:text-red-400">$0.00</span>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 dark:border-[#2d1b6e] pt-2">
                        <div class="flex justify-between items-center">
                            <span class="text-base font-semibold text-gray-900 dark:text-white">You will receive:</span>
                            <span id="displayNetAmount" class="text-base font-semibold text-emerald-600 dark:text-emerald-400">$0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <p>Processing time: 1-24 hours</p>
                </div>
            </div>

            <div class="bg-amber-100 dark:bg-amber-900/30 border-l-4 border-amber-400 p-4 rounded-xl">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Security Notice</h3>
                        <div class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Double-check your wallet address before submitting</li>
                                <li>Withdrawals are processed within 1-24 hours during business hours</li>
                                <li>You will receive an email confirmation once processed</li>
                                <li>All withdrawals are final and cannot be reversed</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div id="restrictionsAlert" class="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-400 p-4 rounded-xl" style="display: none;">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Account Restrictions</h3>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300" id="restrictionsList">
                            <!-- restrictions will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" 
                        id="cancelBtn" 
                        class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] rounded-full text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#0f0a2e] hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
                    Clear Form
                </button>
                <button type="submit" 
                        id="submitBtn" 
                        class="px-6 py-2 bg-[#1e0e62] text-white rounded-full text-sm font-medium hover:bg-[#2d1b8a] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="submitText">Create Withdrawal</span>
                    <svg id="submitSpinner" class="hidden animate-spin -mr-1 ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <!-- withdrawal history -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Withdrawal History</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Track your withdrawal requests and their status</p>
        
        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fee</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Address</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hash</th>
                        </tr>
                    </thead>
                    <tbody id="withdrawalHistory" class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                        <tr>
                            <td colspan="7" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    loading withdrawals...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- confirmation modal -->
<div class="fixed inset-0 z-50 overflow-y-auto hidden" id="confirmationModal">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white dark:bg-[#1a1145] rounded-3xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-xl transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Withdrawal</h3>
                    <div class="mt-3">
                        <div class="text-sm text-gray-500 dark:text-gray-400 space-y-2">
                            <p><strong>Amount:</strong> <span id="confirmAmount">$0.00</span></p>
                            <p><strong>Fee:</strong> <span id="confirmFee">$0.00</span></p>
                            <p><strong>Total Deducted:</strong> <span id="confirmTotal" class="text-red-600 dark:text-red-400">$0.00</span></p>
                            <p><strong>Currency:</strong> <span id="confirmCurrency">USDT</span></p>
                            <p><strong>Network:</strong> <span id="confirmNetwork">TRC20</span></p>
                            <p><strong>Address:</strong> <span id="confirmAddress" class="font-mono text-xs break-all">...</span></p>
                        </div>
                        <div class="mt-4 p-3 bg-red-100 dark:bg-red-900/30 rounded-xl">
                            <p class="text-sm text-red-800 dark:text-red-200">This withdrawal cannot be reversed. please verify all details are correct.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" onclick="closeConfirmationModal()" class="inline-flex justify-center rounded-full border border-gray-200 dark:border-[#2d1b6e] px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#0f0a2e] hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
                    Cancel
                </button>
                <button type="button" id="confirmWithdrawal" class="inline-flex justify-center rounded-full px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 transition-colors">
                    Confirm Withdrawal
                </button>
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
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Withdrawal Submitted</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="successMessage">Your withdrawal request has been submitted and will be processed within 1-24 hours.</p>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <button type="button" class="w-full inline-flex justify-center rounded-full px-4 py-2 bg-[#1e0e62] text-base font-medium text-white hover:bg-[#2d1b8a] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1e0e62] transition-colors" onclick="closeSuccessModal()">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($base ?? \App\Config\Config::getBasePath()) ?>/assets/js/withdraw.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>