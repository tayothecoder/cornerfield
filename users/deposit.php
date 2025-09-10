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

$pageTitle = 'Deposit Funds';
$currentPage = 'deposit';

include __DIR__ . '/includes/header.php';
?>

<style>
    .deposit-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .balance-info {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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

    .deposit-card {
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

    .payment-methods {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .payment-method {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .payment-method:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .payment-method.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .payment-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: var(--primary-color);
    }

    .payment-name {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .crypto-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .crypto-option {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--text-primary);
    }

    .crypto-option:hover {
        border-color: var(--primary-color);
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
        color: var(--primary-color);
        text-decoration: none;
    }

    .crypto-option.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .crypto-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin: 0 auto 1rem;
            color: white;
        }

    .crypto-option.bitcoin .crypto-icon { background: linear-gradient(45deg, #f7931a, #ffd700); }
    .crypto-option.ethereum .crypto-icon { background: linear-gradient(45deg, #627eea, #4f46e5); }
    .crypto-option.usdt .crypto-icon { background: linear-gradient(45deg, #26a17b, #00d4aa); }
    .crypto-option.usdc .crypto-icon { background: linear-gradient(45deg, #2775ca, #0052ff); }

    .crypto-name {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .crypto-symbol {
        font-size: 0.875rem;
        color: var(--text-secondary);
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

    .deposit-summary {
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

    .deposit-btn {
        background: var(--success-color);
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

    .deposit-btn:hover {
        background: #059669;
            transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .deposit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .unavailable-message {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-secondary);
    }

    .unavailable-message i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--text-muted);
    }
    </style>

<div class="deposit-container">
    <!-- Balance Info -->
    <div class="balance-info">
        <div class="balance-amount">$<?= number_format($stats['balance'] ?? 0, 2) ?></div>
        <div class="balance-label">Current Balance</div>
    </div>

    <!-- Payment Methods -->
    <div class="deposit-card">
        <h2 class="section-title">
            <i class="fas fa-credit-card"></i>
            Choose Payment Method
                        </h2>
        <div class="payment-methods">
            <div class="payment-method selected" data-method="crypto">
                <div class="payment-icon">
                    <i class="fab fa-bitcoin"></i>
                    </div>
                <div class="payment-name">Cryptocurrency</div>
                    </div>
            <div class="payment-method" data-method="card">
                <div class="payment-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="payment-name">Credit Card</div>
            </div>
            <div class="payment-method" data-method="bank">
                <div class="payment-icon">
                    <i class="fas fa-university"></i>
                                </div>
                <div class="payment-name">Bank Transfer</div>
                        </div>
                    </div>
                </div>

    <!-- Crypto Deposit -->
    <div class="deposit-card" id="crypto-deposit">
        <h2 class="section-title">
            <i class="fas fa-coins"></i>
            Deposit with Cryptocurrency
        </h2>
        
        <div class="crypto-options">
            <div class="crypto-option bitcoin selected" data-crypto="btc">
                <div class="crypto-icon">
                    <i class="fab fa-bitcoin"></i>
                                                        </div>
                <div class="crypto-name">Bitcoin</div>
                <div class="crypto-symbol">BTC</div>
                                                            </div>
            <div class="crypto-option ethereum" data-crypto="eth">
                <div class="crypto-icon">
                    <i class="fab fa-ethereum"></i>
                                                                </div>
                <div class="crypto-name">Ethereum</div>
                <div class="crypto-symbol">ETH</div>
                                                        </div>
            <div class="crypto-option usdt" data-crypto="usdt">
                <div class="crypto-icon">
                    <i class="fas fa-coins"></i>
                                                        </div>
                <div class="crypto-name">Tether</div>
                <div class="crypto-symbol">USDT</div>
                                                    </div>
            <div class="crypto-option usdc" data-crypto="usdc">
                <div class="crypto-icon">
                    <i class="fas fa-dollar-sign"></i>
                                                </div>
                <div class="crypto-name">USD Coin</div>
                <div class="crypto-symbol">USDC</div>
                                    </div>
                                </div>

        <div class="form-group">
            <label class="form-label">Amount (USD)</label>
            <input type="number" class="form-input" id="deposit-amount" placeholder="Enter amount" min="10" step="0.01">
            <div class="amount-suggestions">
                <button class="amount-btn" data-amount="50">$50</button>
                <button class="amount-btn" data-amount="100">$100</button>
                <button class="amount-btn" data-amount="250">$250</button>
                <button class="amount-btn" data-amount="500">$500</button>
                <button class="amount-btn" data-amount="1000">$1,000</button>
                                    </div>
                                </div>

        <div class="deposit-summary">
            <div class="summary-row">
                <span>Amount:</span>
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

        <button class="deposit-btn" id="generate-address" disabled>
            <i class="fas fa-qrcode me-2"></i>
            Generate Deposit Address
                                    </button>
                </div>

    <!-- Card Deposit -->
    <div class="deposit-card" id="card-deposit" style="display: none;">
        <h2 class="section-title">
            <i class="fas fa-credit-card"></i>
            Deposit with Credit Card
        </h2>
        <div class="unavailable-message">
            <i class="fas fa-credit-card"></i>
            <h3>Credit card deposits are temporarily unavailable.</h3>
            <p>Please use cryptocurrency for instant deposits.</p>
                        </div>
                    </div>

    <!-- Bank Transfer -->
    <div class="deposit-card" id="bank-deposit" style="display: none;">
        <h2 class="section-title">
            <i class="fas fa-university"></i>
            Bank Transfer
        </h2>
        <div class="unavailable-message">
            <i class="fas fa-university"></i>
            <h3>Bank transfers are temporarily unavailable.</h3>
            <p>Please use cryptocurrency for instant deposits.</p>
        </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('deposit-amount');
    const summaryAmount = document.getElementById('summary-amount');
    const summaryFee = document.getElementById('summary-fee');
    const summaryTotal = document.getElementById('summary-total');
    const generateBtn = document.getElementById('generate-address');
    const cryptoOptions = document.querySelectorAll('.crypto-option');
    const paymentMethods = document.querySelectorAll('.payment-method');
    const amountBtns = document.querySelectorAll('.amount-btn');

    let selectedCrypto = 'btc';
    let selectedMethod = 'crypto';

    // Payment method selection
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            paymentMethods.forEach(m => m.classList.remove('selected'));
            this.classList.add('selected');
            selectedMethod = this.dataset.method;

            // Show/hide relevant sections
            document.getElementById('crypto-deposit').style.display = selectedMethod === 'crypto' ? 'block' : 'none';
            document.getElementById('card-deposit').style.display = selectedMethod === 'card' ? 'block' : 'none';
            document.getElementById('bank-deposit').style.display = selectedMethod === 'bank' ? 'block' : 'none';
        });
    });

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
            amountInput.value = amount;
            updateSummary();
        });
    });

    // Amount input
    amountInput.addEventListener('input', updateSummary);

    function updateSummary() {
        const amount = parseFloat(amountInput.value) || 0;
        const fee = amount * 0.02; // 2% fee
        const total = amount - fee;

        summaryAmount.textContent = `$${amount.toFixed(2)}`;
        summaryFee.textContent = `$${fee.toFixed(2)}`;
        summaryTotal.textContent = `$${total.toFixed(2)}`;

        generateBtn.disabled = amount < 10;
    }

    // Generate address
    generateBtn.addEventListener('click', function() {
        if (selectedMethod === 'crypto') {
            // Simulate generating address
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
            this.disabled = true;

            setTimeout(() => {
                alert('Deposit address generated! Check your email for the details.');
                this.innerHTML = '<i class="fas fa-qrcode me-2"></i>Generate Deposit Address';
                this.disabled = false;
            }, 2000);
        }
    });

    // Initialize
    updateSummary();
        });
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>