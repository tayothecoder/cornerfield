<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Utils\SessionManager;

// Start session and check authentication
SessionManager::start();

if (!SessionManager::get('user_logged_in')) {
    header('Location: ../login.php');
    exit;
}

$user_id = SessionManager::get('user_id');

try {
    $database = new Database();
    $userModel = new User($database);
    $currentUser = $userModel->findById($user_id);

    if (!$currentUser) {
        header('Location: ../login.php');
        exit;
    }

    $stats = $userModel->getUserStats($user_id);

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Withdraw Funds';
$currentPage = 'withdraw';

include __DIR__ . '/includes/header.php';
?>

<style>
    .withdraw-container {
        max-width: 600px;
        margin: 0 auto;
    }

    .balance-info {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: var(--shadow-lg);
    }

    .balance-amount {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .balance-label {
        opacity: 0.9;
        font-size: 1.1rem;
    }

    .withdraw-card {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 0.5rem;
        color: var(--primary-color);
    }

    .crypto-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .crypto-option {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .crypto-option:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .crypto-option.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .crypto-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin: 0 auto 0.5rem;
        color: white;
    }

    .crypto-option.bitcoin .crypto-icon { background: linear-gradient(45deg, #f7931a, #ffd700); }
    .crypto-option.ethereum .crypto-icon { background: linear-gradient(45deg, #627eea, #4f46e5); }
    .crypto-option.usdt .crypto-icon { background: linear-gradient(45deg, #26a17b, #00d4aa); }

    .crypto-symbol {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-input {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        font-size: 1rem;
        width: 100%;
        transition: all 0.3s ease;
        color: var(--text-primary);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .amount-suggestions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .amount-btn {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        color: var(--text-primary);
    }

    .amount-btn:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .withdrawal-summary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--radius);
        padding: 1.5rem;
        margin-top: 1.5rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .summary-row:last-child {
        margin-bottom: 0;
        font-weight: 700;
        font-size: 1.1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        padding-top: 0.5rem;
    }

    .withdraw-btn {
        background: var(--warning-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        margin-top: 1.5rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .withdraw-btn:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .withdraw-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .withdrawal-history {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .history-item {
        display: flex;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border-color);
    }

    .history-item:last-child {
        border-bottom: none;
    }

    .history-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--warning-color);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: white;
    }

    .history-content {
        flex: 1;
    }

    .history-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }

    .history-time {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .history-amount {
        font-weight: 700;
        color: var(--danger-color);
    }

    .status {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status.pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    .status.completed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .status.failed {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--text-muted);
    }
</style>

<div class="withdraw-container">
    <!-- Balance Info -->
    <div class="balance-info">
        <div class="balance-amount">$<?= number_format($stats['balance'] ?? 0, 2) ?></div>
        <div class="balance-label">Available for Withdrawal</div>
    </div>

    <!-- Withdrawal Form -->
    <div class="withdraw-card">
        <h2 class="section-title">
            <i class="fas fa-paper-plane"></i>
            Withdraw Funds
        </h2>
        
        <form class="withdraw-form" id="withdraw-form">
            <div class="form-group">
                <label class="form-label">Withdrawal Method</label>
                <div class="crypto-options">
                    <div class="crypto-option bitcoin selected" data-crypto="btc">
                        <div class="crypto-icon">
                            <i class="fab fa-bitcoin"></i>
                        </div>
                        <div class="crypto-symbol">BTC</div>
                    </div>
                    <div class="crypto-option ethereum" data-crypto="eth">
                        <div class="crypto-icon">
                            <i class="fab fa-ethereum"></i>
                        </div>
                        <div class="crypto-symbol">ETH</div>
                    </div>
                    <div class="crypto-option usdt" data-crypto="usdt">
                        <div class="crypto-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="crypto-symbol">USDT</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Wallet Address</label>
                <input type="text" class="form-input" id="wallet-address" placeholder="Enter your wallet address" required>
            </div>

            <div class="form-group">
                <label class="form-label">Amount (USD)</label>
                <input type="number" class="form-input" id="withdraw-amount" placeholder="Enter amount" min="10" step="0.01" required>
                <div class="amount-suggestions">
                    <button type="button" class="amount-btn" data-amount="50">$50</button>
                    <button type="button" class="amount-btn" data-amount="100">$100</button>
                    <button type="button" class="amount-btn" data-amount="250">$250</button>
                    <button type="button" class="amount-btn" data-amount="500">$500</button>
                    <button type="button" class="amount-btn" data-amount="max">Max</button>
                </div>
            </div>

            <div class="withdrawal-summary">
                <div class="summary-row">
                    <span>Withdrawal Amount:</span>
                    <span id="summary-amount">$0.00</span>
                </div>
                <div class="summary-row">
                    <span>Network Fee:</span>
                    <span id="summary-fee">$0.00</span>
                </div>
                <div class="summary-row">
                    <span>You will receive:</span>
                    <span id="summary-total">$0.00</span>
                </div>
            </div>

            <button type="submit" class="withdraw-btn" id="submit-withdrawal" disabled>
                <i class="fas fa-paper-plane me-2"></i>
                Submit Withdrawal Request
            </button>
        </form>
    </div>

    <!-- Withdrawal History -->
    <div class="withdrawal-history">
        <h2 class="section-title">
            <i class="fas fa-history"></i>
            Recent Withdrawals
        </h2>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>No withdrawal history available.</h3>
            <p>Your withdrawal requests will appear here.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('withdraw-form');
    const amountInput = document.getElementById('withdraw-amount');
    const walletInput = document.getElementById('wallet-address');
    const summaryAmount = document.getElementById('summary-amount');
    const summaryFee = document.getElementById('summary-fee');
    const summaryTotal = document.getElementById('summary-total');
    const submitBtn = document.getElementById('submit-withdrawal');
    const cryptoOptions = document.querySelectorAll('.crypto-option');
    const amountBtns = document.querySelectorAll('.amount-btn');

    let selectedCrypto = 'btc';
    const maxBalance = <?= $stats['balance'] ?? 0 ?>;

    // Crypto selection
    cryptoOptions.forEach(option => {
        option.addEventListener('click', function() {
            cryptoOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            selectedCrypto = this.dataset.crypto;
        });
    });

    // Amount buttons
    amountBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const amount = this.dataset.amount;
            if (amount === 'max') {
                amountInput.value = maxBalance.toFixed(2);
            } else {
                amountInput.value = amount;
            }
            updateSummary();
        });
    });

    // Amount input
    amountInput.addEventListener('input', updateSummary);
    walletInput.addEventListener('input', updateSummary);

    function updateSummary() {
        const amount = parseFloat(amountInput.value) || 0;
        const fee = amount * 0.05; // 5% fee
        const total = amount - fee;

        summaryAmount.textContent = `$${amount.toFixed(2)}`;
        summaryFee.textContent = `$${fee.toFixed(2)}`;
        summaryTotal.textContent = `$${total.toFixed(2)}`;

        const isValid = amount >= 10 && amount <= maxBalance && walletInput.value.trim().length > 0;
        submitBtn.disabled = !isValid;
    }

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const amount = parseFloat(amountInput.value);
        const wallet = walletInput.value.trim();

        if (amount < 10) {
            alert('Minimum withdrawal amount is $10');
            return;
        }

        if (amount > maxBalance) {
            alert('Insufficient balance');
            return;
        }

        if (!wallet) {
            alert('Please enter a valid wallet address');
            return;
        }

        // Simulate withdrawal request
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        submitBtn.disabled = true;

        setTimeout(() => {
            alert('Withdrawal request submitted successfully! You will receive your funds within 24 hours.');
            form.reset();
            updateSummary();
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Withdrawal Request';
        }, 2000);
    });

    // Initialize
    updateSummary();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>