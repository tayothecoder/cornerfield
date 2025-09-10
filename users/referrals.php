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
    
    // Get referral data (fallback to basic data)
    $referralData = [
        'total_referrals' => 0,
        'active_referrals' => 0,
        'total_earnings' => 0,
        'referrals' => []
    ];
    $referralLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $currentUser['id'];

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Referrals';
$currentPage = 'referrals';

include __DIR__ . '/includes/header.php';
?>

<style>
    .referrals-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .referral-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow);
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid var(--border-color);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        margin: 0 auto 1rem;
    }

    .stat-card.total .stat-icon { background: var(--primary-color); }
    .stat-card.active .stat-icon { background: var(--success-color); }
    .stat-card.earnings .stat-icon { background: var(--warning-color); }
    .stat-card.commission .stat-icon { background: var(--info-color); }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .referral-link-section {
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

    .referral-link-container {
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .referral-link {
        flex: 1;
        font-family: monospace;
        font-size: 0.875rem;
        color: var(--text-primary);
        word-break: break-all;
    }

    .copy-btn {
        background: var(--success-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    .copy-btn:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .referral-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--radius);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .info-item {
        text-align: center;
    }

    .info-value {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .info-label {
        font-size: 0.875rem;
        opacity: 0.9;
    }

    .referrals-list {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .referral-item {
        display: flex;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border-color);
    }

    .referral-item:last-child {
        border-bottom: none;
    }

    .referral-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: white;
        font-weight: 600;
    }

    .referral-info {
        flex: 1;
    }

    .referral-name {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }

    .referral-email {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .referral-stats {
        text-align: right;
    }

    .referral-amount {
        font-weight: 700;
        color: var(--success-color);
        margin-bottom: 0.25rem;
    }

    .referral-date {
        font-size: 0.75rem;
        color: var(--text-secondary);
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

    .social-share {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .share-btn {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        color: var(--text-primary);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .share-btn:hover {
        background: var(--primary-color);
        color: white;
        text-decoration: none;
    }

    .share-btn.facebook:hover { background: #1877f2; }
    .share-btn.twitter:hover { background: #1da1f2; }
    .share-btn.whatsapp:hover { background: #25d366; }
    .share-btn.telegram:hover { background: #0088cc; }
</style>

<div class="referrals-container">
    <!-- Referral Stats -->
    <div class="referral-stats">
        <div class="stat-card total gradient-card gradient-shadow">
            <div class="stat-icon gradient-bg">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value gradient-text"><?= number_format($referralData['total_referrals'] ?? 0) ?></div>
            <div class="stat-label">Total Referrals</div>
        </div>

        <div class="stat-card active gradient-card gradient-shadow">
            <div class="stat-icon gradient-bg-success">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-value gradient-text"><?= number_format($referralData['active_referrals'] ?? 0) ?></div>
            <div class="stat-label">Active Referrals</div>
        </div>

        <div class="stat-card earnings gradient-card gradient-shadow">
            <div class="stat-icon gradient-bg-warning">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-value gradient-text">$<?= number_format($referralData['total_earnings'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Earnings</div>
        </div>

        <div class="stat-card commission gradient-card gradient-shadow">
            <div class="stat-icon gradient-bg-alt">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-value gradient-text">5%</div>
            <div class="stat-label">Commission Rate</div>
        </div>
    </div>

    <!-- Referral Link Section -->
    <div class="referral-link-section gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-share-alt"></i>
            Your Referral Link
        </h2>
        
        <div class="referral-link-container">
            <div class="referral-link" id="referralLink"><?= htmlspecialchars($referralLink) ?></div>
            <button class="copy-btn" onclick="copyReferralLink()">
                <i class="fas fa-copy"></i>
                Copy Link
            </button>
        </div>

        <div class="social-share">
            <a href="#" class="share-btn facebook" onclick="shareOnFacebook()">
                <i class="fab fa-facebook-f"></i>
                Facebook
            </a>
            <a href="#" class="share-btn twitter" onclick="shareOnTwitter()">
                <i class="fab fa-twitter"></i>
                Twitter
            </a>
            <a href="#" class="share-btn whatsapp" onclick="shareOnWhatsApp()">
                <i class="fab fa-whatsapp"></i>
                WhatsApp
            </a>
            <a href="#" class="share-btn telegram" onclick="shareOnTelegram()">
                <i class="fab fa-telegram-plane"></i>
                Telegram
            </a>
        </div>
    </div>

    <!-- Referral Program Info -->
    <div class="referral-info">
        <h3 style="margin-bottom: 1rem; font-size: 1.25rem;">Referral Program Benefits</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-value">5%</div>
                <div class="info-label">Commission Rate</div>
            </div>
            <div class="info-item">
                <div class="info-value">Lifetime</div>
                <div class="info-label">Earnings Duration</div>
            </div>
            <div class="info-item">
                <div class="info-value">Instant</div>
                <div class="info-label">Commission Payment</div>
            </div>
            <div class="info-item">
                <div class="info-value">Unlimited</div>
                <div class="info-label">Referral Limit</div>
            </div>
        </div>
    </div>

    <!-- Referrals List -->
    <div class="referrals-list gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-list"></i>
            Your Referrals
        </h2>
        
        <?php if (!empty($referralData['referrals'])): ?>
            <?php foreach ($referralData['referrals'] as $referral): ?>
                <div class="referral-item">
                    <div class="referral-avatar">
                        <?= strtoupper(substr($referral['first_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="referral-info">
                        <div class="referral-name">
                            <?= htmlspecialchars($referral['first_name'] ?? '') ?> <?= htmlspecialchars($referral['last_name'] ?? '') ?>
                        </div>
                        <div class="referral-email"><?= htmlspecialchars($referral['email'] ?? '') ?></div>
                    </div>
                    <div class="referral-stats">
                        <div class="referral-amount">$<?= number_format($referral['commission_earned'] ?? 0, 2) ?></div>
                        <div class="referral-date">Joined <?= date('M j, Y', strtotime($referral['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No referrals yet</h3>
                <p>Start sharing your referral link to earn commissions!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyReferralLink() {
    const referralLink = document.getElementById('referralLink').textContent;
    
    navigator.clipboard.writeText(referralLink).then(function() {
        const button = document.querySelector('.copy-btn');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        button.style.background = '#059669';
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.background = '';
        }, 2000);
    }).catch(function() {
        alert('Failed to copy link. Please copy manually.');
    });
}

function shareOnFacebook() {
    const link = document.getElementById('referralLink').textContent;
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`;
    window.open(url, '_blank', 'width=600,height=400');
}

function shareOnTwitter() {
    const link = document.getElementById('referralLink').textContent;
    const text = 'Join me on this amazing investment platform and start earning daily profits!';
    const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(link)}`;
    window.open(url, '_blank', 'width=600,height=400');
}

function shareOnWhatsApp() {
    const link = document.getElementById('referralLink').textContent;
    const text = 'Join me on this amazing investment platform and start earning daily profits!';
    const url = `https://wa.me/?text=${encodeURIComponent(text + ' ' + link)}`;
    window.open(url, '_blank');
}

function shareOnTelegram() {
    const link = document.getElementById('referralLink').textContent;
    const text = 'Join me on this amazing investment platform and start earning daily profits!';
    const url = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>