<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\TransferController;

// Auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

try {
    $controller = new TransferController();
    $data = $controller->getTransferData();
} catch (\Throwable $e) {
    // Fallback demo data for preview
    $data = [
        'availableBalance' => 12890.25,
        'transferFee' => 2.50,
        'recentTransfers' => [
            ['id' => 1, 'recipient' => 'trader123', 'amount' => 500.00, 'fee' => 2.50, 'status' => 'completed', 'created_at' => '2024-02-10 14:30:00', 'reference' => 'TRF-2024021001'],
            ['id' => 2, 'recipient' => 'investor456', 'amount' => 250.00, 'fee' => 2.50, 'status' => 'completed', 'created_at' => '2024-02-09 10:15:00', 'reference' => 'TRF-2024020901'],
            ['id' => 3, 'recipient' => 'crypto_lover', 'amount' => 1000.00, 'fee' => 2.50, 'status' => 'pending', 'created_at' => '2024-02-08 16:45:00', 'reference' => 'TRF-2024020801'],
            ['id' => 4, 'recipient' => 'newbie101', 'amount' => 100.00, 'fee' => 2.50, 'status' => 'completed', 'created_at' => '2024-02-07 11:20:00', 'reference' => 'TRF-2024020701'],
            ['id' => 5, 'recipient' => 'pro_investor', 'amount' => 750.00, 'fee' => 2.50, 'status' => 'failed', 'created_at' => '2024-02-06 09:10:00', 'reference' => 'TRF-2024020601', 'failure_reason' => 'Recipient account not found']
        ],
        'transferLimits' => [
            'daily' => 5000.00,
            'monthly' => 50000.00,
            'min' => 10.00,
            'max' => 10000.00
        ],
        'dailyUsed' => 750.00,
        'monthlyUsed' => 2500.00
    ];
}

$pageTitle = 'Transfer Funds';
$currentPage = 'transfer';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Transfer Content -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="cf-gradient rounded-2xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-bold mb-2">Transfer Funds 💸</h2>
                <p class="text-blue-100">Send money to other Cornerfield users instantly.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-lg font-medium">
                    Available: <span class="font-bold">$<?= number_format($data['availableBalance'], 2) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Transfer Form -->
        <div class="lg:col-span-2">
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Send Money</h3>
                
                <form class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Recipient Username or Email
                        </label>
                        <div class="relative">
                            <input type="text" name="recipient" id="recipient" required
                                   class="w-full px-4 py-3 pl-10 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                   placeholder="Enter username or email address">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            You can send to any registered Cornerfield user
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Transfer Amount
                        </label>
                        <div class="relative">
                            <input type="number" name="amount" id="amount" step="0.01" min="<?= $data['transferLimits']['min'] ?>" max="<?= $data['transferLimits']['max'] ?>" required
                                   class="w-full px-4 py-3 pl-8 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                   placeholder="0.00"
                                   onchange="calculateTotal()">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <span class="text-gray-500 text-sm">$</span>
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <span>Min: $<?= number_format($data['transferLimits']['min'], 2) ?></span>
                            <span>Max: $<?= number_format($data['transferLimits']['max'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="grid grid-cols-4 gap-3">
                        <button type="button" onclick="setAmount(50)" class="py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                            $50
                        </button>
                        <button type="button" onclick="setAmount(100)" class="py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                            $100
                        </button>
                        <button type="button" onclick="setAmount(500)" class="py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                            $500
                        </button>
                        <button type="button" onclick="setAmount(1000)" class="py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                            $1000
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Transfer Note (Optional)
                        </label>
                        <textarea name="note" rows="3" 
                                  class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                  placeholder="Add a note for the recipient (optional)"></textarea>
                    </div>

                    <!-- Transaction Summary -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-2">
                        <h4 class="font-medium text-gray-900 dark:text-white">Transaction Summary</h4>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Transfer Amount:</span>
                            <span id="transferAmount" class="text-gray-900 dark:text-white font-medium">$0.00</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Transfer Fee:</span>
                            <span class="text-gray-900 dark:text-white font-medium">$<?= number_format($data['transferFee'], 2) ?></span>
                        </div>
                        <div class="border-t dark:border-gray-600 pt-2">
                            <div class="flex justify-between font-medium">
                                <span class="text-gray-900 dark:text-white">Total Deducted:</span>
                                <span id="totalDeducted" class="text-gray-900 dark:text-white">$<?= number_format($data['transferFee'], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="confirmTransfer" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="confirmTransfer" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                            I confirm that the recipient information is correct and understand that transfers cannot be reversed.
                        </label>
                    </div>

                    <button type="submit" 
                            class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                            disabled id="transferButton">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Send Transfer
                    </button>
                </form>
            </div>
        </div>

        <!-- Transfer Limits & Info -->
        <div class="space-y-6">
            <!-- Transfer Limits -->
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Transfer Limits</h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600 dark:text-gray-400">Daily Limit</span>
                            <span class="text-gray-900 dark:text-white font-medium">
                                $<?= number_format($data['dailyUsed'], 2) ?> / $<?= number_format($data['transferLimits']['daily'], 2) ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width: <?= ($data['dailyUsed'] / $data['transferLimits']['daily']) * 100 ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600 dark:text-gray-400">Monthly Limit</span>
                            <span class="text-gray-900 dark:text-white font-medium">
                                $<?= number_format($data['monthlyUsed'], 2) ?> / $<?= number_format($data['transferLimits']['monthly'], 2) ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full transition-all duration-500" style="width: <?= ($data['monthlyUsed'] / $data['transferLimits']['monthly']) * 100 ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Limits reset daily at midnight UTC and monthly on the 1st.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Info -->
            <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Transfer Information</h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Instant Transfers</p>
                            <p class="text-gray-600 dark:text-gray-400">Funds are transferred immediately to the recipient's account.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Secure & Safe</p>
                            <p class="text-gray-600 dark:text-gray-400">All transfers are encrypted and verified before processing.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Low Fees</p>
                            <p class="text-gray-600 dark:text-gray-400">Only $<?= number_format($data['transferFee'], 2) ?> flat fee per transfer.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Non-Reversible</p>
                            <p class="text-gray-600 dark:text-gray-400">Transfers cannot be cancelled once confirmed. Please double-check recipient details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transfers -->
    <div class="cf-card bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transfers</h3>
            <div class="flex space-x-2">
                <select class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">
                    <option>All Transfers</option>
                    <option>Sent</option>
                    <option>Received</option>
                </select>
                <select class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">
                    <option>All Time</option>
                    <option>This Week</option>
                    <option>This Month</option>
                </select>
            </div>
        </div>

        <?php if (!empty($data['recentTransfers'])): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Recipient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($data['recentTransfers'] as $transfer): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($transfer['reference']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                                        <span class="text-purple-600 dark:text-purple-400 font-medium text-sm">
                                            <?= strtoupper(substr($transfer['recipient'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($transfer['recipient']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600 dark:text-red-400">
                                -$<?= number_format($transfer['amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                $<?= number_format($transfer['fee'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?= date('M j, Y H:i', strtotime($transfer['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php
                                    switch ($transfer['status']) {
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'failed': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?>">
                                    <?= ucfirst($transfer['status']) ?>
                                </span>
                                <?php if ($transfer['status'] === 'failed' && !empty($transfer['failure_reason'])): ?>
                                    <div class="text-xs text-red-600 mt-1"><?= htmlspecialchars($transfer['failure_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6 flex items-center justify-between">
                <div class="flex items-center text-sm text-gray-500">
                    Showing 1 to <?= count($data['recentTransfers']) ?> of <?= count($data['recentTransfers']) ?> results
                </div>
                <div class="flex space-x-2">
                    <button class="px-3 py-1 text-sm bg-gray-100 text-gray-400 rounded cursor-not-allowed">Previous</button>
                    <button class="px-3 py-1 text-sm bg-indigo-600 text-white rounded">1</button>
                    <button class="px-3 py-1 text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 rounded">2</button>
                    <button class="px-3 py-1 text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 rounded">Next</button>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No transfers yet</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Send your first transfer to another Cornerfield user.</p>
                <button onclick="document.getElementById('recipient').focus()" 
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Send Transfer
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const transferFee = <?= $data['transferFee'] ?>;
const availableBalance = <?= $data['availableBalance'] ?>;

function setAmount(amount) {
    document.getElementById('amount').value = amount.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const total = amount + transferFee;
    
    document.getElementById('transferAmount').textContent = '$' + amount.toFixed(2);
    document.getElementById('totalDeducted').textContent = '$' + total.toFixed(2);
    
    // Check if amount exceeds available balance
    const submitButton = document.getElementById('transferButton');
    const confirmCheckbox = document.getElementById('confirmTransfer');
    
    if (total > availableBalance) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="text-red-400">Insufficient Balance</span>';
    } else if (confirmCheckbox.checked && amount >= <?= $data['transferLimits']['min'] ?>) {
        submitButton.disabled = false;
        submitButton.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>Send Transfer';
    } else {
        submitButton.disabled = true;
        submitButton.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>Send Transfer';
    }
}

// Enable/disable button based on confirmation checkbox
document.getElementById('confirmTransfer').addEventListener('change', calculateTotal);

// Calculate total when amount changes
document.getElementById('amount').addEventListener('input', calculateTotal);

// Initial calculation
calculateTotal();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>