<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\InvestmentController;

// auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle ajax investment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthMiddleware::check()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    // parse json body into $_POST so the controller can read it
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $jsonBody = json_decode(file_get_contents('php://input'), true);
        if (is_array($jsonBody)) {
            $_POST = array_merge($_POST, $jsonBody);
        }
    }
    // inject csrf token from session if not provided (js sends json without it)
    if (empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
    }
    try {
        $controller = new InvestmentController();
        $controller->invest();
    } catch (\Throwable $e) {
        error_log('Investment POST failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error processing investment']);
    }
    exit;
}

if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

// initialize controller and get data
try {
    $controller = new InvestmentController();
    $data = $controller->getInvestmentPlans();
} catch (\Throwable $e) {
    // fallback demo data for preview
    $data = [
        'plans' => [
            [
                'id' => 1,
                'name' => 'Starter Plan',
                'description' => 'Perfect for beginners looking to start their investment journey',
                'min_amount' => 100,
                'max_amount' => 1000,
                'daily_return' => 2.5,
                'duration_days' => 30,
                'total_return' => 75,
                'features' => ['24/7 Support', 'Daily Payouts', 'Mobile App Access'],
                'popular' => false
            ],
            [
                'id' => 2,
                'name' => 'Premium Plan',
                'description' => 'Our most popular plan with excellent returns and features',
                'min_amount' => 1000,
                'max_amount' => 10000,
                'daily_return' => 3.5,
                'duration_days' => 25,
                'total_return' => 87.5,
                'features' => ['Priority Support', 'Daily Payouts', 'VIP Features', 'Dedicated Manager'],
                'popular' => true
            ],
            [
                'id' => 3,
                'name' => 'VIP Plan',
                'description' => 'Exclusive plan for serious investors with maximum returns',
                'min_amount' => 5000,
                'max_amount' => 50000,
                'daily_return' => 4.0,
                'duration_days' => 20,
                'total_return' => 80,
                'features' => ['Personal Assistant', 'Instant Payouts', 'All VIP Features', 'Custom Strategies'],
                'popular' => false
            ]
        ],
        'userBalance' => 12890.25,
        'activeInvestments' => 3
    ];
}

$pageTitle = 'Investment Plans';
$currentPage = 'invest';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- header -->
    <div class="text-center">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white mb-2">Investment Plans</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">choose from our carefully crafted investment plans designed to maximize your returns</p>
    </div>

    <!-- stats overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl mb-3">
                <svg class="w-6 h-6 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path>
                </svg>
            </div>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-1">$<?= number_format($data['userBalance'], 2) ?></p>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Available Balance</p>
        </div>
        
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl mb-3">
                <svg class="w-6 h-6 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2"></path>
                </svg>
            </div>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-1"><?= $data['activeInvestments'] ?></p>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Investments</p>
        </div>
        
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-xl mb-3">
                <svg class="w-6 h-6 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <p class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-1">Up to 4.0%</p>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Daily Returns</p>
        </div>
    </div>

    <!-- investment plans -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($data['plans'] as $plan): ?>
        <div class="relative bg-white dark:bg-[#1a1145] rounded-3xl p-6 <?= $plan['popular'] ? 'ring-2 ring-[#1e0e62] dark:ring-indigo-400' : '' ?>">
            <?php if ($plan['popular']): ?>
            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                <span class="inline-block px-3 py-1 bg-[#1e0e62] text-white text-xs font-medium rounded-full">
                    Most Popular
                </span>
            </div>
            <?php endif; ?>
            
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl mb-4">
                    <svg class="w-8 h-8 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-2"><?= htmlspecialchars($plan['name']) ?></h3>
                <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($plan['description']) ?></p>
            </div>

            <div class="text-center mb-6">
                <div class="text-4xl font-light tracking-tighter text-[#1e0e62] dark:text-indigo-400 mb-1">
                    <?= number_format($plan['daily_return'], 1) ?>%
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Daily Return</p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center">
                    <p class="text-lg font-medium text-gray-900 dark:text-white"><?= $plan['duration_days'] ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Days</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-medium text-gray-900 dark:text-white"><?= number_format($plan['total_return'], 1) ?>%</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Return</p>
                </div>
            </div>

            <div class="space-y-2 mb-6">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Investment Range:</p>
                <p class="text-lg font-light text-gray-900 dark:text-white">
                    $<?= number_format($plan['min_amount']) ?> - $<?= number_format($plan['max_amount']) ?>
                </p>
            </div>

            <div class="space-y-3 mb-6">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Features:</p>
                <ul class="space-y-2">
                    <?php foreach ($plan['features'] as $feature): ?>
                    <li class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                        <svg class="w-4 h-4 text-emerald-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <?= htmlspecialchars($feature) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <button onclick="openInvestModal(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name']) ?>', <?= $plan['min_amount'] ?>, <?= $plan['max_amount'] ?>, <?= $plan['daily_return'] ?>)" 
                    class="w-full bg-[#1e0e62] text-white rounded-full px-6 py-2.5 text-sm font-medium hover:bg-[#2d1b8a] transition-colors">
                Start Investment
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- roi calculator -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-6 text-center">ROI Calculator</h3>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label for="calc-plan" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Plan</label>
                    <select id="calc-plan" class="block w-full py-2.5 px-3 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]">
                        <?php foreach ($data['plans'] as $plan): ?>
                        <option value="<?= $plan['id'] ?>" data-return="<?= $plan['daily_return'] ?>" data-duration="<?= $plan['duration_days'] ?>">
                            <?= htmlspecialchars($plan['name']) ?> (<?= $plan['daily_return'] ?>% daily)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="calc-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Investment Amount (USD)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 text-sm dark:text-gray-400">$</span>
                        </div>
                        <input type="number" id="calc-amount" min="100" max="50000" value="1000" 
                               class="block w-full pl-7 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]" 
                               placeholder="0.00">
                    </div>
                </div>

                <button onclick="calculateROI()" class="w-full bg-[#1e0e62] text-white rounded-full px-6 py-2.5 text-sm font-medium hover:bg-[#2d1b8a] transition-colors">
                    Calculate Returns
                </button>
            </div>

            <div class="bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl p-4">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Projected Returns</h4>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Investment Amount:</span>
                        <span id="calc-investment" class="text-sm font-medium text-gray-900 dark:text-white">$1,000.00</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Daily Return:</span>
                        <span id="calc-daily" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">$35.00</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Duration:</span>
                        <span id="calc-duration" class="text-sm font-medium text-gray-900 dark:text-white">25 days</span>
                    </div>
                    
                    <div class="border-t border-gray-200 dark:border-[#2d1b6e] pt-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Total Return:</span>
                            <span id="calc-total" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">$875.00</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Final Amount:</span>
                            <span id="calc-final" class="text-lg font-medium text-emerald-600 dark:text-emerald-400">$1,875.00</span>
                        </div>
                    </div>
                    
                    <div class="bg-blue-100 dark:bg-blue-900/30 rounded-xl p-3">
                        <p class="text-xs text-blue-800 dark:text-blue-200">
                           Your profit of <span id="calc-profit" class="font-medium">$875.00</span> represents a 
                            <span id="calc-percentage" class="font-medium">87.5%</span> return on investment
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- investment modal -->
<div id="investModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-[#1a1145] rounded-3xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-xl transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="investForm" method="POST" action="/users/invest.php" class="space-y-6" data-validate>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-[#f5f3ff] dark:bg-[#0f0a2e]">
                        <svg class="h-6 w-6 text-[#1e0e62] dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mt-3" id="modalPlanName">Premium Plan</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Enter your investment amount to get started</p>
                </div>

                <input type="hidden" id="modalPlanId" name="plan_id" value="">
                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">

                <div>
                    <label for="investment-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Investment Amount (USD)
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 text-sm dark:text-gray-400">$</span>
                        </div>
                        <input type="number" id="investment-amount" name="amount" required 
                               class="block w-full pl-7 pr-3 py-2.5 text-sm border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#1e0e62] focus:border-[#1e0e62]"
                               placeholder="0.00">
                    </div>
                    <div class="mt-2 flex justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>Min: $<span id="modalMinAmount">1000</span></span>
                        <span>Max: $<span id="modalMaxAmount">10000</span></span>
                    </div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Available: $<?= number_format($data['userBalance'], 2) ?>
                    </div>
                </div>

                <div class="bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl p-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Daily Return Rate:</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white" id="modalDailyRate">3.5%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Expected Daily Profit:</p>
                            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400" id="modalDailyProfit">$0.00</p>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button type="button" onclick="closeInvestModal()" 
                            class="flex-1 px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] rounded-full text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#0f0a2e] hover:border-[#1e0e62] hover:text-[#1e0e62] dark:hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-[#1e0e62] text-white rounded-full px-6 py-2 text-sm font-medium hover:bg-[#2d1b8a] transition-colors"
                            data-original-text="Confirm Investment">
                        <span id="submitText">Confirm Investment</span>
                        <svg id="submitSpinner" class="hidden animate-spin -mr-1 ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
            </form>
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
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Investment Created Successfully</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="successMessage">your investment has been created and is now active</p>
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
// investment modal functionality
function openInvestModal(planId, planName, minAmount, maxAmount, dailyRate) {
    document.getElementById('modalPlanId').value = planId;
    document.getElementById('modalPlanName').textContent = planName;
    document.getElementById('modalMinAmount').textContent = minAmount.toLocaleString();
    document.getElementById('modalMaxAmount').textContent = maxAmount.toLocaleString();
    document.getElementById('modalDailyRate').textContent = dailyRate + '%';
    
    const amountInput = document.getElementById('investment-amount');
    amountInput.min = minAmount;
    amountInput.max = maxAmount;
    amountInput.value = minAmount;
    
    updateModalProfit();
    document.getElementById('investModal').classList.remove('hidden');
}

function closeInvestModal() {
    document.getElementById('investModal').classList.add('hidden');
}

function updateModalProfit() {
    const amount = parseFloat(document.getElementById('investment-amount').value) || 0;
    const dailyRate = parseFloat(document.getElementById('modalDailyRate').textContent) / 100;
    const dailyProfit = amount * dailyRate;
    
    document.getElementById('modalDailyProfit').textContent = '$' + dailyProfit.toFixed(2);
}

// roi calculator
function calculateROI() {
    const planSelect = document.getElementById('calc-plan');
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const dailyReturn = parseFloat(selectedOption.dataset.return);
    const duration = parseInt(selectedOption.dataset.duration);
    const amount = parseFloat(document.getElementById('calc-amount').value) || 0;
    
    if (amount <= 0) {
        return;
    }
    
    const dailyProfit = amount * (dailyReturn / 100);
    const totalReturn = dailyProfit * duration;
    const finalAmount = amount + totalReturn;
    const profitPercentage = (totalReturn / amount) * 100;
    
    document.getElementById('calc-investment').textContent = '$' + amount.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('calc-daily').textContent = '$' + dailyProfit.toFixed(2);
    document.getElementById('calc-duration').textContent = duration + ' days';
    document.getElementById('calc-total').textContent = '$' + totalReturn.toFixed(2);
    document.getElementById('calc-final').textContent = '$' + finalAmount.toFixed(2);
    document.getElementById('calc-profit').textContent = '$' + totalReturn.toFixed(2);
    document.getElementById('calc-percentage').textContent = profitPercentage.toFixed(1) + '%';
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.add('hidden');
}

function setLoading(btn, isLoading) {
    const text = btn.querySelector('#submitText');
    const spinner = btn.querySelector('#submitSpinner');
    
    if (isLoading) {
        text.textContent = 'Creating...';
        spinner.classList.remove('hidden');
        btn.disabled = true;
    } else {
        text.textContent = 'Confirm Investment';
        spinner.classList.add('hidden');
        btn.disabled = false;
    }
}

function showNotification(message, type) {
    // simple alert for now - could be enhanced with a toast system
    alert(message);
}

// event listeners
document.getElementById('investment-amount').addEventListener('input', updateModalProfit);
document.getElementById('calc-amount').addEventListener('input', calculateROI);
document.getElementById('calc-plan').addEventListener('change', calculateROI);

// form submission
document.getElementById('investForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const amount = parseFloat(document.getElementById('investment-amount').value);
    const planId = document.getElementById('modalPlanId').value;
    const minAmount = parseFloat(document.getElementById('investment-amount').min);
    const maxAmount = parseFloat(document.getElementById('investment-amount').max);
    const userBalance = <?= $data['userBalance'] ?>;
    const csrfToken = this.querySelector('input[name="csrf_token"]').value;
    const base = '<?= $base ?>';
    
    if (amount < minAmount || amount > maxAmount) {
        showNotification('Amount must be between $' + minAmount.toLocaleString() + ' and $' + maxAmount.toLocaleString(), 'error');
        return;
    }
    
    if (amount > userBalance) {
        showNotification('Insufficient balance. Please deposit funds first.', 'error');
        return;
    }
    
    setLoading(submitBtn, true);
    
    try {
        const response = await fetch(base + '/users/invest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                schema_id: parseInt(planId),
                amount: amount
            })
        });
        const result = await response.json();
        setLoading(submitBtn, false);
        
        if (result.success) {
            document.getElementById('successMessage').textContent = result.data?.message || 'Investment created successfully';
            closeInvestModal();
            document.getElementById('successModal').classList.remove('hidden');
        } else {
            showNotification(result.error || 'Investment failed', 'error');
        }
    } catch (err) {
        setLoading(submitBtn, false);
        showNotification('Network error. Please try again.', 'error');
    }
});

// initialize calculator on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateROI();
});

// close modal on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('investModal');
    if (e.target === modal) {
        closeInvestModal();
    }
    
    const successModal = document.getElementById('successModal');
    if (e.target === successModal) {
        closeSuccessModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>