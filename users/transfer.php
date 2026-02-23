<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\TransferController;

// auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle transfer POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthMiddleware::check()) {
        header('Location: ' . \App\Config\Config::getBasePath() . '/login.php');
        exit;
    }
    try {
        $controller = new TransferController();
        $controller->transfer();
    } catch (\Throwable $e) {
        error_log('Transfer POST failed: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error processing transfer']);
    }
    exit;
}

if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

try {
    $controller = new TransferController();
    $data = $controller->getTransferData();
} catch (\Throwable $e) {
    // fallback demo data for preview
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

<div class="space-y-6">
    <!-- balance overview -->
    <div class="bg-[#1e0e62] rounded-3xl text-white p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-medium tracking-tight">Transfer Funds</h2>
                <p class="text-indigo-200 mt-1">Send money to other Cornerfield users instantly</p>
            </div>
            <div class="text-right">
                <div class="text-sm opacity-75">Available Balance</div>
                <div class="text-3xl font-light tracking-tighter">$<?= number_format($data['availableBalance'], 2) ?></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- transfer form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-6">Send Money</h3>
                
                <form method="POST" id="transferForm" class="space-y-6">
                    <?= \App\Utils\Security::getCsrfTokenInput() ?>
                    
                    <div>
                        <label for="recipient" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Recipient Username or Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <input type="text" name="recipient" id="recipient" required
                                   class="block w-full pl-10 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]"
                                   placeholder="Enter username or email address">
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            send to any registered Cornerfield user
                        </p>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Transfer Amount (USD)
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 text-sm dark:text-gray-400">$</span>
                            </div>
                            <input type="number" name="amount" id="amount" step="0.01" min="<?= $data['transferLimits']['min'] ?>" max="<?= $data['transferLimits']['max'] ?>" required
                                   class="block w-full pl-7 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]"
                                   placeholder="0.00"
                                   onchange="calculateTotal()">
                        </div>
                        <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400 mt-2">
                            <span>Min: $<?= number_format($data['transferLimits']['min'], 2) ?></span>
                            <span>Max: $<?= number_format($data['transferLimits']['max'], 2) ?></span>
                        </div>
                    </div>

                    <!-- quick amount buttons -->
                    <div class="grid grid-cols-4 gap-3">
                        <button type="button" onclick="setAmount(50)" class="py-2 text-sm bg-[#f5f3ff] dark:bg-[#0f0a2e] hover:bg-gray-100 dark:hover:bg-[#1a1145] text-gray-700 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-[#2d1b6e] transition-colors">
                            $50
                        </button>
                        <button type="button" onclick="setAmount(100)" class="py-2 text-sm bg-[#f5f3ff] dark:bg-[#0f0a2e] hover:bg-gray-100 dark:hover:bg-[#1a1145] text-gray-700 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-[#2d1b6e] transition-colors">
                            $100
                        </button>
                        <button type="button" onclick="setAmount(500)" class="py-2 text-sm bg-[#f5f3ff] dark:bg-[#0f0a2e] hover:bg-gray-100 dark:hover:bg-[#1a1145] text-gray-700 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-[#2d1b6e] transition-colors">
                            $500
                        </button>
                        <button type="button" onclick="setAmount(1000)" class="py-2 text-sm bg-[#f5f3ff] dark:bg-[#0f0a2e] hover:bg-gray-100 dark:hover:bg-[#1a1145] text-gray-700 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-[#2d1b6e] transition-colors">
                            $1000
                        </button>
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Transfer Note <span class="text-gray-400">(optional)</span>
                        </label>
                        <textarea name="note" id="note" rows="3" 
                                  class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]"
                                  placeholder="Add a note for the recipient"></textarea>
                    </div>

                    <!-- transaction summary -->
                    <div class="bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Transaction Summary</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Transfer Amount:</span>
                                <span id="transferAmount" class="text-sm font-medium text-gray-900 dark:text-white">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Transfer Fee:</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">$<?= number_format($data['transferFee'], 2) ?></span>
                            </div>
                            <div class="border-t border-gray-200 dark:border-[#2d1b6e] pt-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Total Deducted:</span>
                                    <span id="totalDeducted" class="text-sm font-medium text-red-600 dark:text-red-400">$<?= number_format($data['transferFee'], 2) ?></span>
                                </div>
                            </div>
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
                                <p class="text-sm text-amber-800 dark:text-amber-200">
                                    transfers are instant and cannot be reversed. please verify recipient details carefully.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="confirmTransfer" class="h-4 w-4 text-[#1e0e62] border-gray-300 dark:border-[#2d1b6e] rounded focus:ring-[#1e0e62]">
                        <label for="confirmTransfer" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                            I confirm that the recipient information is correct and understand that transfers cannot be reversed
                        </label>
                    </div>

                    <button type="submit" 
                            class="w-full bg-[#1e0e62] text-white rounded-full px-6 py-2.5 text-sm font-medium hover:bg-[#2d1b8a] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled id="transferButton">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Send Transfer
                    </button>
                </form>
            </div>
        </div>

        <!-- transfer limits & info -->
        <div class="space-y-6">
            <!-- transfer limits -->
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Transfer Limits</h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-500 dark:text-gray-400">Daily Limit</span>
                            <span class="text-gray-900 dark:text-white font-medium">
                                $<?= number_format($data['dailyUsed'], 2) ?> / $<?= number_format($data['transferLimits']['daily'], 2) ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-[#2d1b6e] rounded-full h-2">
                            <div class="bg-[#1e0e62] dark:bg-indigo-400 h-2 rounded-full transition-all duration-500" style="width: <?= ($data['dailyUsed'] / $data['transferLimits']['daily']) * 100 ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-500 dark:text-gray-400">Monthly Limit</span>
                            <span class="text-gray-900 dark:text-white font-medium">
                                $<?= number_format($data['monthlyUsed'], 2) ?> / $<?= number_format($data['transferLimits']['monthly'], 2) ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-[#2d1b6e] rounded-full h-2">
                            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width: <?= ($data['monthlyUsed'] / $data['transferLimits']['monthly']) * 100 ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                    <div class="flex">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                limits reset daily at midnight UTC and monthly on the 1st
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- transfer info -->
            <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
                <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Transfer Information</h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Instant Transfers</p>
                            <p class="text-gray-500 dark:text-gray-400">funds are transferred immediately to the recipient's account</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Secure & Safe</p>
                            <p class="text-gray-500 dark:text-gray-400">All transfers are encrypted and verified before processing</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Low Fees</p>
                            <p class="text-gray-500 dark:text-gray-400">only $<?= number_format($data['transferFee'], 2) ?> flat fee per transfer</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">Non-Reversible</p>
                            <p class="text-gray-500 dark:text-gray-400">transfers cannot be cancelled once confirmed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- recent transfers -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white">Recent Transfers</h3>
            <div class="flex space-x-2">
                <select class="py-1.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                    <option>All Transfers</option>
                    <option>Sent</option>
                    <option>Received</option>
                </select>
            </div>
        </div>

        <?php if (!empty($data['recentTransfers'])): ?>
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50/50 dark:bg-white/5">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fee</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-[#2d1b6e]">
                            <?php foreach ($data['recentTransfers'] as $transfer): ?>
                            <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($transfer['reference'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl flex items-center justify-center">
                                            <span class="text-[#1e0e62] dark:text-indigo-400 font-medium text-sm">
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
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="text-sm font-medium text-red-600 dark:text-red-400">
                                        -$<?= number_format($transfer['amount'], 2) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        $<?= number_format($transfer['fee'], 2) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= date('M j, H:i', strtotime($transfer['created_at'])) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="inline-block px-2 py-1 text-xs font-medium rounded-full <?php
                                        switch ($transfer['status']) {
                                            case 'completed': echo 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'; break;
                                            case 'pending': echo 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'; break;
                                            case 'failed': echo 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'; break;
                                            default: echo 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-400';
                                        }
                                    ?>">
                                        <?= ucfirst($transfer['status']) ?>
                                    </span>
                                    <?php if ($transfer['status'] === 'failed' && !empty($transfer['failure_reason'])): ?>
                                        <div class="text-xs text-red-600 dark:text-red-400 mt-1"><?= htmlspecialchars($transfer['failure_reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-1">No transfers yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Send your first transfer to another Cornerfield user</p>
            </div>
        <?php endif; ?>
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
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transfer Sent Successfully</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="successMessage">Your transfer has been processed successfully</p>
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
    
    // check if amount exceeds available balance
    const submitButton = document.getElementById('transferButton');
    const confirmCheckbox = document.getElementById('confirmTransfer');
    
    if (total > availableBalance) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="text-red-400">Insufficient Balance</span>';
    } else if (confirmCheckbox.checked && amount >= <?= $data['transferLimits']['min'] ?>) {
        submitButton.disabled = false;
        submitButton.innerHTML = '<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>Send Transfer';
    } else {
        submitButton.disabled = true;
        submitButton.innerHTML = '<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>Send Transfer';
    }
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.add('hidden');
}

// enable/disable button based on confirmation checkbox
document.getElementById('confirmTransfer').addEventListener('change', calculateTotal);

// calculate total when amount changes
document.getElementById('amount').addEventListener('input', calculateTotal);

// initial calculation
calculateTotal();

// intercept form submission for ajax
const transferForm = document.getElementById('transferForm');
if (transferForm) {
    transferForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('transferButton');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Processing...';

        try {
            const formData = new FormData(transferForm);
            const response = await fetch('transfer.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('successMessage').textContent = result.data?.message || 'Transfer completed successfully';
                document.getElementById('successModal').classList.remove('hidden');
                // reset form
                transferForm.reset();
                calculateTotal();
            } else {
                alert(result.error || 'Transfer failed');
            }
        } catch (err) {
            console.error('Transfer error:', err);
            alert('Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>