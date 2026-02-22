<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\ReferralController;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

try {
    $controller = new ReferralController();
    $data = $controller->getReferralData();
} catch (\Throwable $e) {
    $data = [
        'referralLink' => 'https://cornerfield.com/ref/demouser',
        'totalReferrals' => 12, 'activeReferrals' => 8,
        'totalEarnings' => 1250.75, 'monthlyEarnings' => 320.50,
        'referralCommissions' => [
            ['id' => 1, 'username' => 'user123', 'level' => 1, 'amount' => 50.00, 'commission' => 5.00, 'date' => '2024-02-10 15:30:00', 'status' => 'paid'],
            ['id' => 2, 'username' => 'trader456', 'level' => 1, 'amount' => 200.00, 'commission' => 20.00, 'date' => '2024-02-09 11:15:00', 'status' => 'paid'],
            ['id' => 3, 'username' => 'investor789', 'level' => 2, 'amount' => 500.00, 'commission' => 25.00, 'date' => '2024-02-08 09:45:00', 'status' => 'paid'],
            ['id' => 4, 'username' => 'newbie101', 'level' => 1, 'amount' => 100.00, 'commission' => 10.00, 'date' => '2024-02-07 14:20:00', 'status' => 'pending'],
        ]
    ];
}

$pageTitle = 'Referrals';
$currentPage = 'referrals';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- referral link -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">Your Referral Link</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Share to earn 10% L1, 5% L2 commission on investments.</p>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="text" id="referralLink" value="<?= htmlspecialchars($data['referralLink']) ?>" readonly
                   class="flex-1 px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-[#f5f3ff] dark:bg-[#0f0a2e] text-gray-900 dark:text-white font-mono text-sm">
            <button onclick="copyReferralLink()" class="px-6 py-3 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span id="copyText">Copy Link</span>
            </button>
        </div>
    </div>

    <!-- stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $stats = [
            ['Total Referrals', $data['totalReferrals'], null, null],
            ['Active Referrals', $data['activeReferrals'], null, 'Currently investing'],
            ['Total Earnings', '$' . number_format($data['totalEarnings'], 2), null, 'All time'],
            ['This Month', '$' . number_format($data['monthlyEarnings'], 2), true, null],
        ];
        foreach ($stats as [$label, $value, $showTrend, $sub]): ?>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1"><?= $label ?></p>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= $value ?></p>
            <?php if ($showTrend): ?>
            <div class="flex items-center gap-1 mt-2 text-emerald-600 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                <span class="text-xs font-medium">+28%</span>
            </div>
            <?php elseif ($sub): ?>
            <p class="text-xs text-gray-400 mt-2"><?= $sub ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- commission history -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Commission History</h3>

        <?php if (!empty($data['referralCommissions'])): ?>
        <div class="space-y-3">
            <?php foreach ($data['referralCommissions'] as $c): ?>
            <div class="flex items-center justify-between p-4 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[#1e0e62]/10 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                        <span class="text-xs font-medium text-[#1e0e62] dark:text-indigo-400"><?= strtoupper(substr($c['username'], 0, 1)) ?></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($c['username']) ?></p>
                        <div class="flex items-center gap-2">
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full <?= $c['level'] == 1 ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' ?>">L<?= $c['level'] ?></span>
                            <span class="text-xs text-gray-400"><?= date('M j, H:i', strtotime($c['date'])) ?></span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">+$<?= number_format($c['commission'], 2) ?></p>
                    <p class="text-xs text-gray-400">from $<?= number_format($c['amount'], 2) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-10">
            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="text-sm text-gray-400 mb-3">No commissions yet</p>
            <button onclick="copyReferralLink()" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Share Your Link</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyReferralLink() {
    const input = document.getElementById('referralLink');
    const text = document.getElementById('copyText');
    navigator.clipboard.writeText(input.value).then(() => {
        text.textContent = 'Copied';
        setTimeout(() => { text.textContent = 'Copy Link'; }, 2000);
    }).catch(() => {
        input.select();
        document.execCommand('copy');
        text.textContent = 'Copied';
        setTimeout(() => { text.textContent = 'Copy Link'; }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
