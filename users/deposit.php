<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: users/deposit.php
 * Purpose: Complete deposit interface with Tailwind CSS
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

// Set page metadata
$pageTitle = 'Deposit';
$pageDescription = 'Add funds to your account securely via cryptocurrency or bank transfer';

// Include header
require_once __DIR__ . '/includes/header.php';

use App\Models\DepositModel;
use App\Models\DepositMethodModel;
use App\Utils\Security;

// Get deposit methods
$depositModel = new DepositModel();
$methodModel = new DepositMethodModel();
$depositMethods = $methodModel->findActive();

?>

<!-- Deposit Content -->
<div class="space-y-6">
    <!-- Deposit Methods -->
    <div class="bg-white shadow-sm rounded-lg dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Choose Deposit Method</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select your preferred deposit method to add funds to your account</p>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="depositMethods">
                <?php foreach ($depositMethods as $method): ?>
                <div class="method-card relative border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-primary-500 hover:shadow-md transition-all duration-200 dark:border-gray-600 dark:hover:border-primary-400" 
                     data-method-id="<?= $method['id'] ?>" 
                     data-method-type="<?= Security::escape($method['type']) ?>"
                     data-method-name="<?= Security::escape($method['name']) ?>"
                     data-min-amount="<?= $method['minimum_deposit'] ?>"
                     data-max-amount="<?= $method['maximum_deposit'] ?>"
                     data-fee-type="<?= Security::escape($method['charge_type']) ?>"
                     data-fee-rate="<?= $method['charge'] ?>">
                    
                    <!-- Method Type Badge -->
                    <div class="absolute top-2 right-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                            <?= $method['type'] === 'auto' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' ?>">
                            <?= $method['type'] === 'auto' ? '⚡ Auto' : '👤 Manual' ?>
                        </span>
                    </div>
                    
                    <!-- Method Info -->
                    <div class="mt-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white"><?= Security::escape($method['name']) ?></h3>
                        
                        <!-- Fee Info -->
                        <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            Fee: <?= $method['charge_type'] === 'percentage' ? number_format($method['charge'], 2) . '%' : '$' . number_format($method['charge'], 2) ?>
                        </div>
                        
                        <!-- Limits -->
                        <div class="mt-1 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2"></path>
                            </svg>
                            Min: $<?= number_format($method['minimum_deposit'], 2) ?> • Max: $<?= number_format($method['maximum_deposit'], 0) ?>
                        </div>
                        
                        <!-- Processing Time -->
                        <div class="mt-1 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?= $method['type'] === 'auto' ? 'Instant' : '1-24 hours' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Deposit Form -->
    <div class="bg-white shadow-sm rounded-lg dark:bg-gray-800" id="depositFormContainer" style="display: none;">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Deposit Details</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" id="selectedMethodName">Complete your deposit information</p>
        </div>
        
        <form id="depositForm" class="p-6 space-y-6">
            <?= Security::getCsrfTokenInput() ?>
            <input type="hidden" id="selectedMethodId" name="method_id" value="">
            
            <!-- Amount Input -->
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Deposit Amount (USD)</label>
                <div class="mt-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm dark:text-gray-400">$</span>
                    </div>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           step="0.01" 
                           min="0" 
                           class="block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" 
                           placeholder="0.00"
                           required>
                </div>
                <div id="amountLimits" class="mt-1 text-sm text-gray-500 dark:text-gray-400"></div>
            </div>

            <!-- Fee Calculation Display -->
            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700" id="feeCalculation" style="display: none;">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Deposit Amount:</span>
                    <span id="displayAmount" class="text-sm text-gray-900 dark:text-white">$0.00</span>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Processing Fee:</span>
                    <span id="displayFee" class="text-sm text-gray-900 dark:text-white">$0.00</span>
                </div>
                <div class="border-t border-gray-200 mt-2 pt-2 dark:border-gray-600">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-semibold text-gray-900 dark:text-white">You will receive:</span>
                        <span id="displayNetAmount" class="text-base font-semibold text-green-600 dark:text-green-400">$0.00</span>
                    </div>
                </div>
            </div>

            <!-- Currency and Network Selection (for manual deposits) -->
            <div id="currencySection" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                        <select id="currency" 
                                name="currency" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            <option value="BTC">Bitcoin (BTC)</option>
                            <option value="ETH">Ethereum (ETH)</option>
                            <option value="USDT" selected>Tether (USDT)</option>
                            <option value="LTC">Litecoin (LTC)</option>
                        </select>
                    </div>
                    <div>
                        <label for="network" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Network</label>
                        <select id="network" 
                                name="network" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            <option value="TRC20">TRC20</option>
                            <option value="ERC20">ERC20</option>
                            <option value="BEP20">BEP20</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Manual Deposit Fields -->
            <div id="manualFields" style="display: none;">
                <!-- Transaction Hash -->
                <div>
                    <label for="transactionHash" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Transaction Hash</label>
                    <input type="text" 
                           id="transactionHash" 
                           name="transaction_hash" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" 
                           placeholder="Enter blockchain transaction hash">
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Optional: Provide the transaction hash after sending payment</p>
                </div>

                <!-- Proof of Payment Upload -->
                <div>
                    <label for="proofOfPayment" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Proof of Payment</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md dark:border-gray-600">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                <label for="proofOfPayment" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-primary-500 dark:bg-gray-700 dark:text-primary-400">
                                    <span>Upload a file</span>
                                    <input id="proofOfPayment" name="proof_of_payment" type="file" accept="image/*,.pdf" class="sr-only">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, PDF up to 5MB</p>
                        </div>
                    </div>
                    <div id="uploadedFileName" class="mt-2 text-sm text-green-600 dark:text-green-400" style="display: none;"></div>
                </div>

                <!-- Deposit Address Display -->
                <div id="depositAddressSection" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 dark:bg-yellow-900 dark:border-yellow-600" style="display: none;">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Send payment to this address</h3>
                            <div class="mt-2">
                                <div class="bg-white p-3 rounded border font-mono text-sm break-all dark:bg-gray-800 dark:border-gray-600" id="displayDepositAddress"></div>
                                <button type="button" id="copyAddressBtn" class="mt-2 inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-primary-700 bg-primary-100 hover:bg-primary-200 dark:text-primary-200 dark:bg-primary-800 dark:hover:bg-primary-700">
                                    Copy Address
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto Deposit Info -->
            <div id="autoDepositInfo" class="bg-blue-50 border-l-4 border-blue-400 p-4 dark:bg-blue-900 dark:border-blue-600" style="display: none;">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Automatic Processing</h3>
                        <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">You will be redirected to our secure payment processor to complete the transaction. Funds are credited automatically upon successful payment.</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-3">
                <button type="button" 
                        id="cancelBtn" 
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit" 
                        id="submitBtn" 
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="submitText">Create Deposit</span>
                    <svg id="submitSpinner" class="hidden animate-spin -mr-1 ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <!-- Deposit History -->
    <div class="bg-white shadow-sm rounded-lg dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Recent Deposits</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track your deposit history and status</p>
        </div>
        
        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="depositHistory" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        <!-- Deposit history will be loaded here -->
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading deposits...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Deposit Created Successfully!</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="successMessage">Your deposit request has been created and is being processed.</p>
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
<script src="<?= htmlspecialchars($base ?? \App\Config\Config::getBasePath()) ?>/assets/js/deposit.js"></script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>