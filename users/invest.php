<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Models\Investment;
use App\Utils\SessionManager;

// Start session and check authentication
SessionManager::start();

if (!SessionManager::get('user_logged_in')) {
    header('Location: ../login.php');
    exit;
}

$user_id = SessionManager::get('user_id');

// Handle investment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $userModel = new User($database);
        $investmentModel = new Investment($database);
        
        $schema_id = $_POST['schema_id'] ?? 0;
        $amount = $_POST['amount'] ?? 0;
        
        if (!$schema_id || !$amount) {
            throw new Exception('Missing required parameters');
        }
        
        // Get schema details
        $schema = $investmentModel->getSchemaById($schema_id);
        if (!$schema) {
            throw new Exception('Invalid investment plan');
        }
        
        // Validate amount
        if ($amount < $schema['min_amount'] || $amount > $schema['max_amount']) {
            throw new Exception('Investment amount out of range');
        }
        
        // Check user balance
        $currentUser = $userModel->findById($user_id);
        if ($currentUser['balance'] < $amount) {
            throw new Exception('Insufficient balance');
        }
        
        // Create investment
        $investmentData = [
            'user_id' => $user_id,
            'schema_id' => $schema_id,
            'amount' => $amount
        ];
        
        $investmentId = $investmentModel->createInvestment($investmentData);
        
        if ($investmentId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Investment created successfully']);
            exit;
} else {
            throw new Exception('Failed to create investment');
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

try {
    $database = new Database();
    $userModel = new User($database);
    $currentUser = $userModel->findById($user_id);

    if (!$currentUser) {
        header('Location: ../login.php');
        exit;
    }

    $stats = $userModel->getUserStats($user_id);
    $investmentModel = new Investment($database);
    $investmentSchemas = $investmentModel->getAllSchemas();

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Invest';
$currentPage = 'invest';

include __DIR__ . '/includes/header.php';
?>

<style>
    .invest-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Enhanced Balance Info */
    .balance-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        padding: 3rem 2rem;
        margin-bottom: 3rem;
        text-align: center;
        box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        position: relative;
        overflow: hidden;
    }

    .balance-info::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .balance-amount {
        font-size: 3.5rem;
        font-weight: 900;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        position: relative;
        z-index: 1;
    }

    .balance-label {
        opacity: 0.95;
        font-size: 1.2rem;
        font-weight: 500;
        position: relative;
        z-index: 1;
    }

    /* Enhanced Plans Grid */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 2.5rem;
        margin-bottom: 3rem;
    }

    .plan-card {
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
    }

    .plan-card:hover {
        transform: translateY(-12px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    }

    .plan-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .plan-card.basic::before { 
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%); 
    }
    .plan-card.premium::before { 
        background: linear-gradient(90deg, #fa709a 0%, #fee140 100%); 
    }
    .plan-card.enterprise::before { 
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); 
    }

    .plan-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
        transition: all 0.4s ease;
    }

    .plan-card:hover::after {
        transform: scale(1.2);
    }

    .plan-header {
        text-align: center;
        margin-bottom: 2.5rem;
        position: relative;
        z-index: 1;
    }

    .plan-name {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 0.75rem;
        color: var(--text-primary);
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .plan-description {
        color: var(--text-secondary);
        font-size: 1rem;
        line-height: 1.6;
    }

    /* Enhanced Rate Display */
    .plan-rate {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem 1.5rem;
        border-radius: 20px;
        text-align: center;
        margin-bottom: 2.5rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .plan-rate::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: pulse 4s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 0.8; }
    }

    .rate-value {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        position: relative;
        z-index: 1;
    }

    .rate-label {
        opacity: 0.95;
        font-size: 1.1rem;
        font-weight: 500;
        position: relative;
        z-index: 1;
    }

    /* Enhanced Features */
    .plan-features {
        margin-bottom: 2.5rem;
    }

    .feature-item {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        color: var(--text-primary);
        padding: 0.75rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .feature-item:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: translateX(5px);
    }

    .feature-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: white;
        font-size: 0.8rem;
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
    }

    /* Enhanced Plan Details */
    .plan-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 2.5rem;
        padding: 1.5rem;
        background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .detail-item {
        text-align: center;
        padding: 1rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.7);
        transition: all 0.3s ease;
    }

    .detail-item:hover {
        background: rgba(255, 255, 255, 0.9);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .detail-value {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .detail-label {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    /* Enhanced Invest Button */
    .invest-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 16px;
        padding: 1.25rem 2rem;
        font-size: 1.2rem;
        font-weight: 700;
        width: 100%;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .invest-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .invest-btn:hover::before {
        left: 100%;
    }

    .invest-btn:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
    }

    .invest-btn:active {
        transform: translateY(-1px);
    }

    .invest-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
    }

    /* Enhanced Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        color: var(--text-muted);
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: var(--text-primary);
    }

    .empty-state p {
        font-size: 1.1rem;
        line-height: 1.6;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .plans-grid {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .balance-amount {
            font-size: 2.5rem;
        }
        
        .plan-card {
            padding: 2rem;
        }
        
        .rate-value {
            font-size: 2.5rem;
        }
    }

    /* Loading Animation */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="invest-container">
    <!-- Balance Info -->
    <div class="balance-info">
        <div class="balance-amount">$<?= number_format($stats['balance'] ?? 0, 2) ?></div>
        <div class="balance-label">Available for Investment</div>
    </div>

    <!-- Investment Plans -->
    <div class="plans-grid">
        <?php if (!empty($investmentSchemas)): ?>
            <?php foreach ($investmentSchemas as $index => $schema): ?>
                <div class="plan-card <?= $index === 0 ? 'basic' : ($index === 1 ? 'premium' : 'enterprise') ?>" data-plan-id="<?= $schema['id'] ?>">
                    <div class="plan-header">
                        <div class="plan-name"><?= htmlspecialchars($schema['name']) ?></div>
                        <div class="plan-description"><?= htmlspecialchars($schema['description'] ?? 'High-yield investment plan') ?></div>
                    </div>

                    <div class="plan-rate">
                        <div class="rate-value"><?= number_format($schema['daily_rate'], 2) ?>%</div>
                        <div class="rate-label">Daily Return</div>
                    </div>

                    <div class="plan-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <span>Minimum: $<?= number_format($schema['min_amount'], 2) ?></span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <span>Duration: <?= $schema['duration_days'] ?> days</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <span>Total Return: <?= number_format($schema['total_return'] * 100, 1) ?>%</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <span>Daily Payouts</span>
                        </div>
                    </div>

                    <div class="plan-details">
                        <div class="detail-item">
                            <div class="detail-value">$<?= number_format($schema['min_amount'], 0) ?></div>
                            <div class="detail-label">Min Investment</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-value">$<?= number_format($schema['max_amount'] ?? $schema['min_amount'] * 10, 0) ?></div>
                            <div class="detail-label">Max Investment</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-value"><?= $schema['duration_days'] ?></div>
                            <div class="detail-label">Days</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-value"><?= number_format($schema['total_return'] * 100, 0) ?>%</div>
                            <div class="detail-label">Total Return</div>
                        </div>
                    </div>

                    <button class="invest-btn" onclick="investNow(<?= $schema['id'] ?>, '<?= htmlspecialchars($schema['name']) ?>', <?= $schema['min_amount'] ?>, <?= $schema['max_amount'] ?>, <?= $schema['daily_rate'] ?>, <?= $schema['duration_days'] ?>, <?= $schema['total_return'] ?>)">
                        <i class="fas fa-rocket me-2"></i>
                        Invest Now
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="plan-card">
                <div class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <h3>No investment plans available at the moment.</h3>
                    <p>Please check back later for new investment opportunities.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Enhanced Investment Modal -->
<div class="modal fade" id="investmentModal" tabindex="-1" aria-labelledby="investmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px 20px 0 0; padding: 2rem 2rem 1.5rem;">
                <h5 class="modal-title" id="investmentModalLabel" style="font-size: 1.5rem; font-weight: 700;">
                    <i class="fas fa-rocket me-2"></i>Invest in <span id="planName"></span>
                </h5>
                <button type="button" class="btn-close" onclick="closeInvestmentModal()" aria-label="Close" style="filter: brightness(0) invert(1); font-size: 1.5rem;"></button>
            </div>
            <form id="investmentForm">
                <div class="modal-body" style="padding: 2rem;">
                    <input type="hidden" id="planId" name="schema_id">
                    <input type="hidden" id="planMinAmount" name="min_amount">
                    <input type="hidden" id="planMaxAmount" name="max_amount">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label for="investmentAmount" class="form-label" style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">
                                    <i class="fas fa-dollar-sign me-2"></i>Investment Amount
                                </label>
                                <input type="number" class="form-control" id="investmentAmount" name="amount" 
                                       step="0.01" min="0.01" required 
                                       style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 1rem; font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;"
                                       onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'"
                                       onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'">
                                <div class="form-text" style="margin-top: 0.5rem; font-weight: 500;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Min: $<span id="minAmountDisplay"></span> | 
                                    Max: $<span id="maxAmountDisplay"></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label" style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">
                                    <i class="fas fa-chart-line me-2"></i>Plan Details
                                </label>
                                <div class="card" style="background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                                    <div class="card-body" style="padding: 1.5rem;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600; color: var(--text-secondary);">Daily Rate:</span>
                                            <span style="font-weight: 700; color: #10b981; font-size: 1.1rem;"><span id="dailyRateDisplay"></span>%</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600; color: var(--text-secondary);">Duration:</span>
                                            <span style="font-weight: 700; color: #667eea; font-size: 1.1rem;"><span id="durationDisplay"></span> days</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span style="font-weight: 600; color: var(--text-secondary);">Total Return:</span>
                                            <span style="font-weight: 700; color: #764ba2; font-size: 1.1rem;"><span id="totalReturnDisplay"></span>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">
                            <i class="fas fa-calculator me-2"></i>Investment Summary
                        </label>
                        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px; border: none; box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);">
                            <div class="card-body" style="padding: 2rem;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="h4" id="totalReturnAmount" style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">$0.00</div>
                                            <small style="opacity: 0.9; font-weight: 500;">Total Return</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="h4" id="dailyProfitAmount" style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">$0.00</div>
                                            <small style="opacity: 0.9; font-weight: 500;">Daily Profit</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="h4" id="totalProfitAmount" style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">$0.00</div>
                                            <small style="opacity: 0.9; font-weight: 500;">Total Profit</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 1.5rem 2rem 2rem; border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 20px 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeInvestmentModal()" 
                            style="border-radius: 12px; padding: 0.75rem 2rem; font-weight: 600; border: 2px solid #e2e8f0; background: white; color: var(--text-secondary); transition: all 0.3s ease;"
                            onmouseover="this.style.borderColor='#cbd5e0'; this.style.backgroundColor='#f7fafc'"
                            onmouseout="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='white'">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" 
                            style="border-radius: 12px; padding: 0.75rem 2rem; font-weight: 700; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); transition: all 0.3s ease;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(16, 185, 129, 0.4)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.3)'">
                        <i class="fas fa-check me-2"></i>Confirm Investment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function investNow(planId, planName, minAmount, maxAmount, dailyRate, durationDays, totalReturn) {
    // Populate modal with plan data
    document.getElementById('planName').textContent = planName;
    document.getElementById('planId').value = planId;
    document.getElementById('planMinAmount').value = minAmount;
    document.getElementById('planMaxAmount').value = maxAmount;
    document.getElementById('minAmountDisplay').textContent = parseFloat(minAmount).toLocaleString();
    document.getElementById('maxAmountDisplay').textContent = parseFloat(maxAmount).toLocaleString();
    document.getElementById('dailyRateDisplay').textContent = dailyRate;
    document.getElementById('durationDisplay').textContent = durationDays;
    document.getElementById('totalReturnDisplay').textContent = totalReturn;
    
    // Set default amount to minimum
    document.getElementById('investmentAmount').value = minAmount;
    
    // Show modal
    const modalElement = document.getElementById('investmentModal');
    if (modalElement) {
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'investmentModalBackdrop';
        document.body.appendChild(backdrop);
    }
    
    // Calculate initial values
    calculateInvestmentReturns(minAmount);
}

function calculateInvestmentReturns(amount) {
    const minAmount = parseFloat(document.getElementById('planMinAmount').value);
    const maxAmount = parseFloat(document.getElementById('planMaxAmount').value);
    const dailyRate = parseFloat(document.getElementById('dailyRateDisplay').textContent);
    const durationDays = parseInt(document.getElementById('durationDisplay').textContent);
    
    // Convert amount to number if it's a string
    amount = parseFloat(amount);
    
    // Check if amount is valid
    if (isNaN(amount) || amount <= 0) {
        document.getElementById('dailyProfitAmount').textContent = '$0.00';
        document.getElementById('totalProfitAmount').textContent = '$0.00';
        document.getElementById('totalReturnAmount').textContent = '$0.00';
        return;
    }
    
    if (amount < minAmount || amount > maxAmount) {
        document.getElementById('dailyProfitAmount').textContent = '$0.00';
        document.getElementById('totalProfitAmount').textContent = '$0.00';
        document.getElementById('totalReturnAmount').textContent = '$0.00';
        return;
    }
    
    // Calculate daily profit (amount * daily_rate%)
    const dailyProfit = (amount * dailyRate) / 100;
    
    // Calculate total profit over the entire duration (daily_profit * duration_days)
    const totalProfit = dailyProfit * durationDays;
    
    // Calculate total return (original amount + total profit)
    const totalReturn = amount + totalProfit;
    
    document.getElementById('dailyProfitAmount').textContent = '$' + dailyProfit.toFixed(2);
    document.getElementById('totalProfitAmount').textContent = '$' + totalProfit.toFixed(2);
    document.getElementById('totalReturnAmount').textContent = '$' + totalReturn.toFixed(2);
}

// Add event listener for amount input
document.getElementById('investmentAmount').addEventListener('input', function() {
    calculateInvestmentReturns(this.value);
});

// Close investment modal
function closeInvestmentModal() {
    const modalElement = document.getElementById('investmentModal');
    if (modalElement) {
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        // Remove backdrop
        const backdrop = document.getElementById('investmentModalBackdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
}

// Handle form submission
document.getElementById('investmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const amount = parseFloat(formData.get('amount'));
    const minAmount = parseFloat(document.getElementById('planMinAmount').value);
    const maxAmount = parseFloat(document.getElementById('planMaxAmount').value);
    
    if (amount < minAmount || amount > maxAmount) {
        alert(`Please enter a valid amount between $${minAmount} and $${maxAmount}`);
        return;
    }
    
    // Submit investment
    fetch('invest.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Investment created successfully! You will start earning daily returns immediately.');
            closeInvestmentModal();
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to create investment'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating investment. Please try again.');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>