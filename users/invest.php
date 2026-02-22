<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\InvestmentController;

// Auth check (preview-safe)
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
// For demo/preview: wrap in try/catch so pages render even without DB
try {
    $controller = new InvestmentController();
    $data = $controller->getInvestmentPlans();
} catch (\Throwable $e) {
    // Fallback demo data for preview
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
                'popular' => false,
                'color' => 'blue'
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
                'popular' => true,
                'color' => 'indigo'
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
                'popular' => false,
                'color' => 'purple'
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

<!-- Investment Plans Content -->
<div class="space-y-6">
    <!-- Header Section -->
    <div class="text-center">
        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Choose Your Investment Plan</h2>
        <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            Select from our carefully crafted investment plans designed to maximize your returns while minimizing risks.
        </p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="text-center p-6 bg-white dark:bg-[#1a1145] rounded-3xl">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full mb-4">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">$<?= number_format($data['userBalance'], 2) ?></h3>
            <p class="text-gray-600 dark:text-gray-300">Available Balance</p>
        </div>
        
        <div class="text-center p-6 bg-white dark:bg-[#1a1145] rounded-3xl">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-[#f5f3ff] rounded-full mb-4">
                <svg class="w-6 h-6 text-[#1e0e62]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white"><?= $data['activeInvestments'] ?></h3>
            <p class="text-gray-600 dark:text-gray-300">Active Investments</p>
        </div>
        
        <div class="text-center p-6 bg-white dark:bg-[#1a1145] rounded-3xl">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-[#f5f3ff] rounded-full mb-4">
                <svg class="w-6 h-6 text-[#1e0e62]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">Up to 4.0%</h3>
            <p class="text-gray-600 dark:text-gray-300">Daily Returns</p>
        </div>
    </div>

    <!-- Investment Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($data['plans'] as $plan): ?>
        <div class="relative cf-card bg-white dark:bg-[#1a1145] rounded-3xl overflow-hiddentransition-all duration-300" data-hover>
            <?php if ($plan['popular']): ?>
            <div class="absolute top-0 right-0 bg-[#1e0e62] text-white px-3 py-1 text-sm font-medium rounded-bl-xl">
                Most Popular
            </div>
            <?php endif; ?>
            
            <div class="p-6">
                <!-- Plan Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 <?= 
                        $plan['color'] === 'blue' ? 'bg-[#f5f3ff]' : 
                        ($plan['color'] === 'indigo' ? 'bg-[#f5f3ff]' : 
                        'bg-[#f5f3ff]') 
                    ?> rounded-3xl mb-4">
                        <svg class="w-8 h-8 <?= 
                            $plan['color'] === 'blue' ? 'text-[#1e0e62]' : 
                            ($plan['color'] === 'indigo' ? 'text-[#1e0e62]' : 
                            'text-[#1e0e62]') 
                        ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white mb-2"><?= htmlspecialchars($plan['name']) ?></h3>
                    <p class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($plan['description']) ?></p>
                </div>

                <!-- Plan Details -->
                <div class="space-y-4 mb-6">
                    <div class="text-center">
                        <div class="text-4xl font-bold <?= 
                            $plan['color'] === 'blue' ? 'text-[#1e0e62]' : 
                            ($plan['color'] === 'indigo' ? 'text-[#1e0e62]' : 
                            'text-[#1e0e62]') 
                        ?> mb-2">
                            <?= number_format($plan['daily_return'], 1) ?>%
                        </div>
                        <p class="text-gray-600 dark:text-gray-300">Daily Return</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <div class="text-lg font-medium text-gray-900 dark:text-white"><?= $plan['duration_days'] ?> days</div>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Duration</p>
                        </div>
                        <div>
                            <div class="text-lg font-medium text-gray-900 dark:text-white"><?= number_format($plan['total_return'], 1) ?>%</div>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Total Return</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">Investment Range:</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            $<?= number_format($plan['min_amount']) ?> - $<?= number_format($plan['max_amount']) ?>
                        </p>
                    </div>
                </div>

                <!-- Features -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Plan Features:</h4>
                    <ul class="space-y-2">
                        <?php foreach ($plan['features'] as $feature): ?>
                        <li class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <?= htmlspecialchars($feature) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Action Button -->
                <button onclick="openInvestModal(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name']) ?>', <?= $plan['min_amount'] ?>, <?= $plan['max_amount'] ?>, <?= $plan['daily_return'] ?>)" 
                    class="w-full <?= 
                        $plan['color'] === 'blue' ? 'bg-[#1e0e62] hover:bg-[#2d1b8a] rounded-full' : 
                        ($plan['color'] === 'indigo' ? 'bg-[#1e0e62] hover:bg-[#2d1b8a] rounded-full' : 
                        'bg-[#1e0e62] hover:bg-[#2d1b8a] rounded-full') 
                    ?> text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 <?= $plan['popular'] ? '' : '' ?>">
                    Start Investment
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ROI Calculator -->
    <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white mb-6 text-center">ROI Calculator</h3>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Calculator Inputs -->
            <div class="space-y-6">
                <div>
                    <label for="calc-plan" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Plan</label>
                    <select id="calc-plan" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                        <?php foreach ($data['plans'] as $plan): ?>
                        <option value="<?= $plan['id'] ?>" data-return="<?= $plan['daily_return'] ?>" data-duration="<?= $plan['duration_days'] ?>">
                            <?= htmlspecialchars($plan['name']) ?> (<?= $plan['daily_return'] ?>% daily)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="calc-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Investment Amount ($)</label>
                    <input type="number" id="calc-amount" min="100" max="50000" value="1000" 
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white" 
                        placeholder="Enter amount">
                </div>

                <button onclick="calculateROI()" class="w-full bg-[#1e0e62] hover:bg-[#2d1b8a] text-white font-medium py-3 px-4 rounded-full transition-colors">
                    Calculate Returns
                </button>
            </div>

            <!-- Calculator Results -->
            <div class="bg-[#f5f3ff] dark:bg-gray-700 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Projected Returns</h4>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-300">Investment Amount:</span>
                        <span id="calc-investment" class="font-medium text-gray-900 dark:text-white">$1,000.00</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-300">Daily Return:</span>
                        <span id="calc-daily" class="font-medium text-green-600">$35.00</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-300">Duration:</span>
                        <span id="calc-duration" class="font-medium text-gray-900 dark:text-white">25 days</span>
                    </div>
                    
                    <div class="border-t border-gray-200 dark:border-gray-600 pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-300">Total Return:</span>
                            <span id="calc-total" class="font-medium text-green-600">$875.00</span>
                        </div>
                        
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-gray-600 dark:text-gray-300">Final Amount:</span>
                            <span id="calc-final" class="text-xl font-medium tracking-tight text-green-600">$1,875.00</span>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 mt-4">
                        <p class="text-sm text-[#1e0e62]">
                           Your profit of <span id="calc-profit" class="font-medium">$875.00</span> represents a 
                            <span id="calc-percentage" class="font-medium">87.5%</span> return on investment!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Investment Modal -->
<div id="investModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-[#f5f3ff]0 bg-opacity-75 transition-opacity"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-[#1a1145] rounded-3xl text-left overflow-hiddentransition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="investForm" method="POST" action="/users/invest.php" class="p-6" data-validate>
                <div class="mb-6">
                    <h3 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white mb-2" id="modalPlanName">Premium Plan</h3>
                    <p class="text-gray-600 dark:text-gray-300">Enter your investment amount to get started.</p>
                </div>

                <input type="hidden" id="modalPlanId" name="plan_id" value="">
                <input type="hidden" name="csrf_token" value="<?= CsrfMiddleware::getToken() ?>">

                <div class="mb-6">
                    <label for="investment-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Investment Amount ($)
                    </label>
                    <input type="number" id="investment-amount" name="amount" required 
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter amount">
                    <div class="mt-2 flex justify-between text-sm text-gray-500">
                        <span>Min: $<span id="modalMinAmount">1000</span></span>
                        <span>Max: $<span id="modalMaxAmount">10000</span></span>
                        <span>Available: $<?= number_format($data['userBalance'], 2) ?></span>
                    </div>
                </div>

                <div class="mb-6 p-4 bg-[#f5f3ff] dark:bg-gray-700 rounded-lg">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-300">Daily Return Rate:</span>
                            <div class="font-medium text-gray-900 dark:text-white" id="modalDailyRate">3.5%</div>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-300">Expected Daily Profit:</span>
                            <div class="font-medium text-green-600" id="modalDailyProfit">$0.00</div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button type="button" onclick="closeInvestModal()" 
                        class="flex-1 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-[#f5f3ff]0 text-gray-700 dark:text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-[#1e0e62] hover:bg-[#2d1b8a] text-white font-medium py-3 px-4 rounded-full transition-colors"
                        data-original-text="Confirm Investment">
                        Confirm Investment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Investment modal functionality
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

// ROI Calculator
function calculateROI() {
    const planSelect = document.getElementById('calc-plan');
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const dailyReturn = parseFloat(selectedOption.dataset.return);
    const duration = parseInt(selectedOption.dataset.duration);
    const amount = parseFloat(document.getElementById('calc-amount').value) || 0;
    
    if (amount <= 0) {
        showNotification('Please enter a valid investment amount', 'error');
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

// Event listeners
document.getElementById('investment-amount').addEventListener('input', updateModalProfit);
document.getElementById('calc-amount').addEventListener('input', calculateROI);
document.getElementById('calc-plan').addEventListener('change', calculateROI);

// Form submission
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
            showNotification(result.data?.message || 'Investment created successfully', 'success');
            closeInvestModal();
            setTimeout(() => {
                window.location.href = base + '/users/dashboard.php';
            }, 1500);
        } else {
            showNotification(result.error || 'Investment failed', 'error');
        }
    } catch (err) {
        setLoading(submitBtn, false);
        showNotification('Network error. Please try again.', 'error');
    }
});

// Initialize calculator on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateROI();
});

// Close modal on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('investModal');
    if (e.target === modal) {
        closeInvestModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>