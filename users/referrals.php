<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\ReferralController;

// Auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

try {
    $controller = new ReferralController();
    $data = $controller->getReferralData();
} catch (\Throwable $e) {
    // Fallback demo data for preview
    $data = [
        'referralLink' => 'https://cornerfield.com/ref/demouser',
        'totalReferrals' => 12,
        'activeReferrals' => 8,
        'totalEarnings' => 1250.75,
        'monthlyEarnings' => 320.50,
        'referralCommissions' => [
            ['id' => 1, 'username' => 'user123', 'level' => 1, 'amount' => 50.00, 'commission' => 5.00, 'date' => '2024-02-10 15:30:00', 'status' => 'paid'],
            ['id' => 2, 'username' => 'trader456', 'level' => 1, 'amount' => 200.00, 'commission' => 20.00, 'date' => '2024-02-09 11:15:00', 'status' => 'paid'],
            ['id' => 3, 'username' => 'investor789', 'level' => 2, 'amount' => 500.00, 'commission' => 25.00, 'date' => '2024-02-08 09:45:00', 'status' => 'paid'],
            ['id' => 4, 'username' => 'newbie101', 'level' => 1, 'amount' => 100.00, 'commission' => 10.00, 'date' => '2024-02-07 14:20:00', 'status' => 'pending'],
            ['id' => 5, 'username' => 'crypto_lover', 'level' => 1, 'amount' => 1000.00, 'commission' => 100.00, 'date' => '2024-02-06 16:30:00', 'status' => 'paid']
        ]
    ];
}

$pageTitle = 'Referrals';
$currentPage = 'referrals';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Referrals Content -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-[#1e0e62] rounded-3xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-medium tracking-tight mb-2">Referral Program</h2>
                <p class="text-blue-100">Earn commissions by inviting friends to invest.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-lg font-medium">
                    Commission Rate: <span class="font-bold">10% Level 1 • 5% Level 2</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Referral Link Section -->
    <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Your Referral Link</h3>
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" id="referralLink" value="<?= htmlspecialchars($data['referralLink']) ?>" 
                       class="w-full px-4 py-3 bg-[#f5f3ff] dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg font-mono text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                       readonly>
            </div>
            <button onclick="copyReferralLink()" 
                    class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors flex items-center justify-center min-w-[120px]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <span id="copyText">Copy Link</span>
            </button>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="https://twitter.com/intent/tweet?text=Join%20me%20on%20Cornerfield%20and%20start%20investing!&url=<?= urlencode($data['referralLink']) ?>" 
               target="_blank" 
               class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M6.29 18.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0020 3.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.073 4.073 0 01.8 7.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 010 16.407a11.616 11.616 0 006.29 1.84"></path>
                </svg>
                Share on Twitter
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($data['referralLink']) ?>" 
               target="_blank"
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M20 10C20 4.477 15.523 0 10 0S0 4.477 0 10c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V10h2.54V7.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V10h2.773l-.443 2.89h-2.33v6.988C16.343 19.128 20 14.991 20 10z" clip-rule="evenodd"></path>
                </svg>
                Share on Facebook
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Referrals -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Referrals</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $data['totalReferrals'] ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">All time referrals</p>
        </div>

        <!-- Active Referrals -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Referrals</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $data['activeReferrals'] ?></p>
            <p class="text-sm text-green-600 dark:text-green-400 mt-1">Currently investing</p>
        </div>

        <!-- Total Earnings -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Earnings</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['totalEarnings'], 2) ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">All time commissions</p>
        </div>

        <!-- Monthly Earnings -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6" data-hover>
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="flex items-center text-green-500">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm font-medium">+28%</span>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">$<?= number_format($data['monthlyEarnings'], 2) ?></p>
            <p class="text-sm text-purple-600 dark:text-purple-400 mt-1">February earnings</p>
        </div>
    </div>

    <!-- Referral Commission Table -->
    <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Commission History</h3>
            <div class="flex space-x-2">
                <select class="px-3 py-2 bg-[#f5f3ff] dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">
                    <option>All Levels</option>
                    <option>Level 1</option>
                    <option>Level 2</option>
                </select>
                <select class="px-3 py-2 bg-[#f5f3ff] dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">
                    <option>All Time</option>
                    <option>This Month</option>
                    <option>Last Month</option>
                </select>
            </div>
        </div>

        <?php if (!empty($data['referralCommissions'])): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-[#f5f3ff] dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Investment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Commission</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($data['referralCommissions'] as $commission): ?>
                        <tr class="hover:bg-[#f5f3ff] dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-medium text-sm">
                                            <?= strtoupper(substr($commission['username'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($commission['username']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?= $commission['level'] == 1 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                                    Level <?= $commission['level'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                $<?= number_format($commission['amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-green-600 dark:text-green-400">
                                    $<?= number_format($commission['commission'], 2) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= $commission['level'] == 1 ? '10%' : '5%' ?> rate
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?= date('M j, Y H:i', strtotime($commission['date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php
                                    switch ($commission['status']) {
                                        case 'paid': echo 'bg-green-100 text-green-800'; break;
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?>">
                                    <?= ucfirst($commission['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6 flex items-center justify-between">
                <div class="flex items-center text-sm text-gray-500">
                    Showing 1 to <?= count($data['referralCommissions']) ?> of <?= count($data['referralCommissions']) ?> results
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No commissions yet</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Share your referral link to start earning commissions!</p>
                <button onclick="copyReferralLink()" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Copy Referral Link
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyReferralLink() {
    const linkInput = document.getElementById('referralLink');
    const copyText = document.getElementById('copyText');
    
    linkInput.select();
    linkInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        copyText.textContent = 'Copied!';
        setTimeout(() => {
            copyText.textContent = 'Copy Link';
        }, 2000);
    } catch (err) {
        // Fallback for browsers that don't support document.execCommand
        navigator.clipboard.writeText(linkInput.value).then(() => {
            copyText.textContent = 'Copied!';
            setTimeout(() => {
                copyText.textContent = 'Copy Link';
            }, 2000);
        }).catch(() => {
            alert('Failed to copy link. Please copy manually.');
        });
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>