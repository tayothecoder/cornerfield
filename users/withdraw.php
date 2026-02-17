<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: users/withdraw.php
 * Purpose: Complete withdrawal interface with Tailwind CSS
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

// Set page metadata
$pageTitle = 'Withdraw';
$pageDescription = 'Withdraw funds from your account securely via cryptocurrency';

// Include header
require_once __DIR__ . '/includes/header.php';

use App\Models\WithdrawalModel;
use App\Utils\Security;

// Get user balance for display
$withdrawalModel = new WithdrawalModel();

?>

<!-- Withdrawal Content -->
<div class="space-y-6">
    <!-- Balance Overview -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-600 rounded-lg shadow-lg text-white">
        <div class="px-6 py-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Available Balance</h2>
                    <p class="text-lg opacity-90" id="userBalance">$<?= number_format((float)$currentUser['balance'], 2) ?></p>
                </div>
                <div class="text-right">
                    <div class="text-sm opacity-75">Total Withdrawn</div>
                    <div class="text-lg font-semibold">$<?= number_format((float)$currentUser['total_withdrawn'], 2) ?></div>
                </div>
            </div>
            
            <!-- Pending Withdrawals Alert -->
            <div id="pendingAlert" class="mt-4 bg-white bg-opacity-10 rounded-lg p-3" style="display: none;">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <span id="pendingText" class="text-sm">You have pending withdrawals totaling $0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Withdrawal Form -->
    <div class="bg-white shadow-sm rounded-lg dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Create Withdrawal</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Withdraw funds to your cryptocurrency wallet</p>
        </div>
        
        <form id="withdrawalForm" class="p-6 space-y-6">
            <?= Security::getCsrfTokenInput() ?>
            
            <!-- Amount Input -->
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Withdrawal Amount (USD)</label>
                <div class="mt-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm dark:text-gray-400">$</span>
                    </div>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           step="0.01" 
                           min="10" 
                           max="50000" 
                           class="block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" 
                           placeholder="0.00"
                           required>
                </div>
                <div class="mt-1 flex justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>Min: $10.00</span>
                    <span>Max: $50,000.00</span>
                </div>
            </div>

            <!-- Currency and Network Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                    <select id="currency" 
                            name="currency" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                            required>
                        <option value="">Select currency</option>
                        <option value="BTC">₿ Bitcoin (BTC)</option>
                        <option value="ETH">Ξ Ethereum (ETH)</option>
                        <option value="USDT" selected>₮ Tether (USDT)</option>
                        <option value="LTC">Ł Litecoin (LTC)</option>
                    </select>
                </div>
                <div>
                    <label for="network" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Network</label>
                    <select id="network" 
                            name="network" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                            required>
                        <option value="">Select network</option>
                        <option value="TRC20">TRC20 (Tron)</option>
                        <option value="ERC20">ERC20 (Ethereum)</option>
                        <option value="BEP20">BEP20 (BSC)</option>
                    </select>
                </div>
            </div>

            <!-- Wallet Address -->
            <div>
                <label for="walletAddress" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Wallet Address</label>
                <input type="text" 
                       id="walletAddress" 
                       name="wallet_address" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" 
                       placeholder="Enter your wallet address"
                       required>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Make sure the wallet address matches the selected currency and network</p>
            </div>

            <!-- Fee Calculation Display -->
            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700" id="feeCalculation" style="display: none;">
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
                    <div class="border-t border-gray-200 pt-2 dark:border-gray-600">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Deducted:</span>
                            <span id="displayTotalDeduction" class="text-sm font-medium text-red-600 dark:text-red-400">$0.00</span>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 pt-2 dark:border-gray-600">
                        <div class="flex justify-between items-center">
                            <span class="text-base font-semibold text-gray-900 dark:text-white">You will receive:</span>
                            <span id="displayNetAmount" class="text-base font-semibold text-green-600 dark:text-green-400">$0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <p>Processing time: 1-24 hours</p>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 dark:bg-yellow-900 dark:border-yellow-600">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Security Notice</h3>
                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
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

            <!-- Account Restrictions (if any) -->
            <div id="restrictionsAlert" class="bg-red-50 border-l-4 border-red-400 p-4 dark:bg-red-900 dark:border-red-600" style="display: none;">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Account Restrictions</h3>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300" id="restrictionsList">
                            <!-- Restrictions will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-3">
                <button type="button" 
                        id="cancelBtn" 
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                    Clear Form
                </button>
                <button type="submit" 
                        id="submitBtn" 
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="submitText">Create Withdrawal</span>
                    <svg id="submitSpinner" class="hidden animate-spin -mr-1 ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <!-- Withdrawal History -->
    <div class="bg-white shadow-sm rounded-lg dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Withdrawal History</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track your withdrawal requests and their status</p>
        </div>
        
        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Currency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Hash</th>
                        </tr>
                    </thead>
                    <tbody id="withdrawalHistory" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        <!-- Withdrawal history will be loaded here -->
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading withdrawals...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="fixed inset-0 z-50 overflow-y-auto hidden" id="confirmationModal">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 dark:bg-gray-800">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-800">
                    <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Confirm Withdrawal</h3>
                    <div class="mt-2">
                        <div class="text-sm text-gray-500 dark:text-gray-400 space-y-2">
                            <p><strong>Amount:</strong> <span id="confirmAmount">$0.00</span></p>
                            <p><strong>Fee:</strong> <span id="confirmFee">$0.00</span></p>
                            <p><strong>Total Deducted:</strong> <span id="confirmTotal" class="text-red-600">$0.00</span></p>
                            <p><strong>Currency:</strong> <span id="confirmCurrency">USDT</span></p>
                            <p><strong>Network:</strong> <span id="confirmNetwork">TRC20</span></p>
                            <p><strong>Address:</strong> <span id="confirmAddress" class="font-mono text-xs">...</span></p>
                        </div>
                        <div class="mt-4 p-3 bg-red-50 rounded-md dark:bg-red-900">
                            <p class="text-sm text-red-800 dark:text-red-200">⚠️ This withdrawal cannot be reversed. Please verify all details are correct.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                <button type="button" id="confirmWithdrawal" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:col-start-2 sm:text-sm">
                    Confirm Withdrawal
                </button>
                <button type="button" onclick="closeConfirmationModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="fixed inset-0 z-50 overflow-y-auto hidden" id="successModal">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeSuccessModal()"></div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 dark:bg-gray-800">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-800">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Withdrawal Submitted!</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="successMessage">Your withdrawal request has been submitted and will be processed within 1-24 hours.</p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6">
                <button type="button" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:text-sm" onclick="closeSuccessModal()">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="<?= htmlspecialchars($base ?? \App\Config\Config::getBasePath()) ?>/assets/js/withdraw.js"></script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>