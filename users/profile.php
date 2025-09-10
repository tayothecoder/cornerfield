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

$pageTitle = 'Profile';
$currentPage = 'profile';

include __DIR__ . '/includes/header.php';
?>

<style>
    .profile-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: var(--shadow-lg);
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
        font-weight: 800;
        color: white;
        border: 4px solid rgba(255, 255, 255, 0.2);
    }

    .profile-name {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .profile-email {
        opacity: 0.9;
        font-size: 1rem;
    }

    .profile-stats {
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

    .stat-card.balance .stat-icon { background: var(--success-color); }
    .stat-card.earned .stat-icon { background: var(--warning-color); }
    .stat-card.investments .stat-icon { background: var(--info-color); }
    .stat-card.referrals .stat-icon { background: var(--dark-color); }

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

    .profile-sections {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .profile-section {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
    }

    .section-icon {
        width: 30px;
        height: 30px;
        border-radius: var(--radius);
        background: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        color: white;
        font-size: 0.875rem;
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

    .form-input:disabled {
        background: var(--bg-tertiary);
        color: var(--text-secondary);
        cursor: not-allowed;
    }

    .btn {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        color: white;
        text-decoration: none;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }

    .btn-secondary:hover {
        background: var(--border-color);
        color: var(--text-primary);
    }

    .referral-section {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }

    .referral-link {
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        font-family: monospace;
        font-size: 0.875rem;
        word-break: break-all;
        margin-bottom: 1rem;
        color: var(--text-primary);
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
    }

    .copy-btn:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .form-check {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .form-check-input {
        margin-right: 0.75rem;
        margin-top: 0.25rem;
    }

    .form-check-label {
        font-size: 0.9rem;
        color: var(--text-primary);
        line-height: 1.4;
    }

    .form-check-label a {
        color: var(--primary-color);
        text-decoration: underline;
    }

    .form-check-label a:hover {
        opacity: 0.8;
    }

    @media (max-width: 768px) {
        .profile-sections {
            grid-template-columns: 1fr;
        }
        .profile-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            <?= strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="profile-name">
            <?= htmlspecialchars($currentUser['first_name'] ?? '') ?> <?= htmlspecialchars($currentUser['last_name'] ?? '') ?>
        </div>
        <div class="profile-email">
            <?= htmlspecialchars($currentUser['email'] ?? '') ?>
        </div>
    </div>

    <!-- Profile Stats -->
    <div class="profile-stats">
        <div class="stat-card balance">
            <div class="stat-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-value">$<?= number_format($stats['balance'] ?? 0, 2) ?></div>
            <div class="stat-label">Available Balance</div>
        </div>

        <div class="stat-card earned">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">$<?= number_format($stats['total_earned'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Earned</div>
        </div>

        <div class="stat-card investments">
            <div class="stat-icon">
                <i class="fas fa-rocket"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['active_investments'] ?? 0) ?></div>
            <div class="stat-label">Active Investments</div>
        </div>

        <div class="stat-card referrals">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= number_format($stats['referral_count'] ?? 0) ?></div>
            <div class="stat-label">Total Referrals</div>
        </div>
    </div>

    <!-- Profile Sections -->
    <div class="profile-sections">
        <!-- Personal Information -->
        <div class="profile-section">
            <h2 class="section-title">
                <div class="section-icon">
                    <i class="fas fa-user"></i>
                </div>
                Personal Information
            </h2>
            
            <form id="personal-form">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-input" name="first_name" value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-input" name="last_name" value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-input" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save me-2"></i>
                    Update Information
                </button>
            </form>
        </div>

        <!-- Security Settings -->
        <div class="profile-section">
            <h2 class="section-title">
                <div class="section-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                Security Settings
            </h2>
            
            <form id="security-form">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-input" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-input" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-key me-2"></i>
                    Change Password
                </button>
            </form>
        </div>
    </div>

    <!-- Referral Section -->
    <div class="referral-section">
        <h2 class="section-title">
            <div class="section-icon">
                <i class="fas fa-share-alt"></i>
            </div>
            Referral Program
        </h2>
        
        <p class="mb-3" style="color: var(--text-secondary);">Invite friends and earn commissions on their investments!</p>
        
        <div class="form-group">
            <label class="form-label">Your Referral Link</label>
            <div class="referral-link" id="referral-link">
                <?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $currentUser['id'] ?>
            </div>
            <button class="copy-btn" onclick="copyReferralLink()">
                <i class="fas fa-copy me-2"></i>
                Copy Link
            </button>
        </div>
        
        <div class="mt-3">
            <p style="color: var(--text-primary);"><strong>Referral Commission:</strong> 5% of your referrals' investment profits</p>
            <p style="color: var(--text-primary);"><strong>Total Referrals:</strong> <?= number_format($stats['referral_count'] ?? 0) ?></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Personal information form
    document.getElementById('personal-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const button = this.querySelector('button[type="submit"]');
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
        button.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            alert('Personal information updated successfully!');
            button.innerHTML = '<i class="fas fa-save me-2"></i>Update Information';
            button.disabled = false;
        }, 1500);
    });

    // Security form
    document.getElementById('security-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword !== confirmPassword) {
            alert('New passwords do not match!');
            return;
        }
        
        if (newPassword.length < 8) {
            alert('Password must be at least 8 characters long!');
            return;
        }
        
        const button = this.querySelector('button[type="submit"]');
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing...';
        button.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            alert('Password changed successfully!');
            this.reset();
            button.innerHTML = '<i class="fas fa-key me-2"></i>Change Password';
            button.disabled = false;
        }, 1500);
    });
});

function copyReferralLink() {
    const referralLink = document.getElementById('referral-link').textContent;
    
    navigator.clipboard.writeText(referralLink).then(function() {
        const button = document.querySelector('.copy-btn');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check me-2"></i>Copied!';
        button.style.background = '#059669';
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.background = '';
        }, 2000);
    }).catch(function() {
        alert('Failed to copy link. Please copy manually.');
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>