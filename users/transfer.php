<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\User;
use App\Services\UserTransferService;
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

    $transferService = new UserTransferService($database);
    
    // Handle transfer form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
        $toUserId = (int)($_POST['to_user_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        $result = $transferService->processTransfer($user_id, $toUserId, $amount, $description);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // Get transfer history
    $transferHistory = $transferService->getTransferHistory($user_id);
    $stats = $userModel->getUserStats($user_id);

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Transfer Funds';
$currentPage = 'transfer';

include __DIR__ . '/includes/header.php';
?>

<style>
    .transfer-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .transfer-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: var(--shadow-lg);
    }

    .transfer-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .transfer-subtitle {
        font-size: 1rem;
        opacity: 0.9;
    }

    .transfer-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .transfer-form-section {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
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

    .form-textarea {
        min-height: 100px;
        resize: vertical;
    }

    .balance-display {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .balance-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--success-color);
        margin-bottom: 0.5rem;
    }

    .balance-label {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }

    .submit-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 1rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
    }

    .submit-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .transfer-history {
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
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1rem;
        color: white;
    }

    .history-icon.sent {
        background: var(--warning-color);
    }

    .history-icon.received {
        background: var(--success-color);
    }

    .history-content {
        flex: 1;
    }

    .history-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }

    .history-details {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .history-amount {
        text-align: right;
    }

    .amount-sent {
        color: var(--warning-color);
        font-weight: 600;
    }

    .amount-received {
        color: var(--success-color);
        font-weight: 600;
    }

    .history-date {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
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

    .user-search {
        position: relative;
    }

    .user-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-top: none;
        border-radius: 0 0 var(--radius) var(--radius);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .suggestion-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
    }

    .suggestion-item:hover {
        background: var(--bg-secondary);
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-email {
        font-weight: 600;
        color: var(--text-primary);
    }

    .suggestion-name {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    @media (max-width: 768px) {
        .transfer-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="transfer-container">
    <!-- Transfer Header -->
    <div class="transfer-header">
        <h1 class="transfer-title">💸 Transfer Funds</h1>
        <p class="transfer-subtitle">Send money to other users instantly and securely</p>
    </div>

    <div class="transfer-grid">
        <!-- Transfer Form -->
        <div class="transfer-form-section">
            <h2 class="section-title">
                <i class="fas fa-paper-plane"></i>
                Send Transfer
            </h2>
            
            <div class="balance-display">
                <div class="balance-amount">$<?= number_format($stats['balance'] ?? 0, 2) ?></div>
                <div class="balance-label">Available Balance</div>
            </div>
            
            <form id="transferForm">
                <div class="form-group">
                    <label class="form-label">Recipient Email</label>
                    <div class="user-search">
                        <input type="email" class="form-input" id="recipientEmail" name="recipient_email" placeholder="Enter recipient's email address" required>
                        <div class="user-suggestions" id="userSuggestions"></div>
                    </div>
                    <input type="hidden" id="toUserId" name="to_user_id">
                </div>

                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <input type="number" class="form-input" name="amount" step="0.01" min="1" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea class="form-input form-textarea" name="description" placeholder="Add a note for this transfer..."></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane me-2"></i>
                    Send Transfer
                </button>
            </form>
        </div>

        <!-- Transfer History -->
        <div class="transfer-history">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Transfer History
            </h2>
            
            <?php if (!empty($transferHistory)): ?>
                <?php foreach ($transferHistory as $transfer): ?>
                    <div class="history-item">
                        <div class="history-icon <?= $transfer['from_user_id'] == $user_id ? 'sent' : 'received' ?>">
                            <i class="fas <?= $transfer['from_user_id'] == $user_id ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                        </div>
                        <div class="history-content">
                            <div class="history-title">
                                <?= $transfer['from_user_id'] == $user_id ? 'Sent to' : 'Received from' ?>
                                <?= htmlspecialchars($transfer['from_user_id'] == $user_id ? $transfer['to_email'] : $transfer['from_email']) ?>
                            </div>
                            <div class="history-details">
                                <?= htmlspecialchars($transfer['description'] ?: 'No description') ?>
                            </div>
                            <div class="history-date">
                                <?= date('M j, Y g:i A', strtotime($transfer['created_at'])) ?>
                            </div>
                        </div>
                        <div class="history-amount">
                            <div class="<?= $transfer['from_user_id'] == $user_id ? 'amount-sent' : 'amount-received' ?>">
                                <?= $transfer['from_user_id'] == $user_id ? '-' : '+' ?>$<?= number_format($transfer['amount'], 2) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>No transfers yet</h3>
                    <p>Your transfer history will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// User search functionality
document.getElementById('recipientEmail').addEventListener('input', function() {
    const email = this.value;
    const suggestions = document.getElementById('userSuggestions');
    
    if (email.length < 3) {
        suggestions.style.display = 'none';
        return;
    }
    
    // Simulate user search (in real implementation, this would be an AJAX call)
    // For now, we'll hide suggestions and let the form handle validation
    suggestions.style.display = 'none';
});

// Transfer form submission
document.getElementById('transferForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'transfer');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitBtn.disabled = true;
    
    fetch('transfer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Transfer completed successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
        console.error('Error:', error);
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-search')) {
        document.getElementById('userSuggestions').style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
