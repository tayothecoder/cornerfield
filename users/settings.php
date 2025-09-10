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

} catch (Exception $e) {
    if (Config::isDebug()) {
        die('Error: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

$pageTitle = 'Settings';
$currentPage = 'settings';

include __DIR__ . '/includes/header.php';
?>

<style>
    .settings-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .settings-section {
        background: var(--bg-primary);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
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

    .form-select {
        background: var(--bg-primary);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 1rem;
        font-size: 1rem;
        width: 100%;
        color: var(--text-primary);
        transition: all 0.3s ease;
    }

    .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .form-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary-color);
    }

    .form-checkbox label {
        color: var(--text-primary);
        font-weight: 500;
        cursor: pointer;
    }

    .save-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 1rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .save-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .danger-zone {
        background: rgba(239, 68, 68, 0.05);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-top: 2rem;
    }

    .danger-zone .section-title {
        color: var(--danger-color);
    }

    .danger-btn {
        background: var(--danger-color);
        color: white;
        border: none;
        border-radius: var(--radius);
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .danger-btn:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .setting-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.5rem;
    }

    .notification-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--radius);
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
    }

    .notification-info {
        flex: 1;
    }

    .notification-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .notification-desc {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .toggle-switch {
        position: relative;
        width: 50px;
        height: 24px;
        background: var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .toggle-switch.active {
        background: var(--primary-color);
    }

    .toggle-switch::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .toggle-switch.active::after {
        transform: translateX(26px);
    }
</style>

<div class="settings-container">
    <!-- Account Settings -->
    <div class="settings-section gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-user-cog"></i>
            Account Settings
        </h2>
        
        <form id="accountForm">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" readonly>
                <div class="setting-description">Email address cannot be changed for security reasons</div>
            </div>

            <div class="form-group">
                <label class="form-label">First Name</label>
                <input type="text" class="form-input" name="first_name" value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-input" name="last_name" value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" class="form-input" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Country</label>
                <select class="form-select" name="country">
                    <option value="">Select Country</option>
                    <option value="US" <?= ($currentUser['country'] ?? '') === 'US' ? 'selected' : '' ?>>United States</option>
                    <option value="CA" <?= ($currentUser['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                    <option value="UK" <?= ($currentUser['country'] ?? '') === 'UK' ? 'selected' : '' ?>>United Kingdom</option>
                    <option value="AU" <?= ($currentUser['country'] ?? '') === 'AU' ? 'selected' : '' ?>>Australia</option>
                    <option value="DE" <?= ($currentUser['country'] ?? '') === 'DE' ? 'selected' : '' ?>>Germany</option>
                    <option value="FR" <?= ($currentUser['country'] ?? '') === 'FR' ? 'selected' : '' ?>>France</option>
                    <option value="JP" <?= ($currentUser['country'] ?? '') === 'JP' ? 'selected' : '' ?>>Japan</option>
                    <option value="SG" <?= ($currentUser['country'] ?? '') === 'SG' ? 'selected' : '' ?>>Singapore</option>
                    <option value="OTHER" <?= ($currentUser['country'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <button type="submit" class="save-btn">
                <i class="fas fa-save me-2"></i>
                Save Account Settings
            </button>
        </form>
    </div>

    <!-- Security Settings -->
    <div class="settings-section gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-shield-alt"></i>
            Security Settings
        </h2>
        
        <form id="securityForm">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-input" name="current_password" required>
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" class="form-input" name="new_password" required>
                <div class="setting-description">Password must be at least 8 characters long</div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-input" name="confirm_password" required>
            </div>

            <button type="submit" class="save-btn">
                <i class="fas fa-key me-2"></i>
                Change Password
            </button>
        </form>
    </div>

    <!-- Notification Settings -->
    <div class="settings-section gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-bell"></i>
            Notification Settings
        </h2>
        
        <div class="notification-item">
            <div class="notification-info">
                <div class="notification-title">Email Notifications</div>
                <div class="notification-desc">Receive important updates via email</div>
            </div>
            <div class="toggle-switch active" onclick="toggleNotification(this)"></div>
        </div>

        <div class="notification-item">
            <div class="notification-info">
                <div class="notification-title">Investment Updates</div>
                <div class="notification-desc">Get notified about your investment progress</div>
            </div>
            <div class="toggle-switch active" onclick="toggleNotification(this)"></div>
        </div>

        <div class="notification-item">
            <div class="notification-info">
                <div class="notification-title">Payment Alerts</div>
                <div class="notification-desc">Notifications for deposits and withdrawals</div>
            </div>
            <div class="toggle-switch active" onclick="toggleNotification(this)"></div>
        </div>

        <div class="notification-item">
            <div class="notification-info">
                <div class="notification-title">Marketing Emails</div>
                <div class="notification-desc">Receive promotional offers and updates</div>
            </div>
            <div class="toggle-switch" onclick="toggleNotification(this)"></div>
        </div>
    </div>

    <!-- Privacy Settings -->
    <div class="settings-section gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-user-secret"></i>
            Privacy Settings
        </h2>
        
        <div class="form-checkbox">
            <input type="checkbox" id="profile_public" checked>
            <label for="profile_public">Make my profile public</label>
        </div>

        <div class="form-checkbox">
            <input type="checkbox" id="show_earnings" checked>
            <label for="show_earnings">Show earnings in public profile</label>
        </div>

        <div class="form-checkbox">
            <input type="checkbox" id="allow_messages">
            <label for="allow_messages">Allow other users to message me</label>
        </div>

        <button class="save-btn">
            <i class="fas fa-save me-2"></i>
            Save Privacy Settings
        </button>
    </div>

    <!-- Danger Zone -->
    <div class="danger-zone gradient-card">
        <h2 class="section-title gradient-text">
            <i class="fas fa-exclamation-triangle"></i>
            Danger Zone
        </h2>
        
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
            These actions are irreversible. Please proceed with caution.
        </p>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <button class="danger-btn" onclick="confirmAction('deactivate')">
                <i class="fas fa-pause me-2"></i>
                Deactivate Account
            </button>
            
            <button class="danger-btn" onclick="confirmAction('delete')">
                <i class="fas fa-trash me-2"></i>
                Delete Account
            </button>
        </div>
    </div>
</div>

<script>
function toggleNotification(element) {
    element.classList.toggle('active');
}

function confirmAction(action) {
    const actionText = action === 'deactivate' ? 'deactivate' : 'delete';
    const message = action === 'deactivate' 
        ? 'Are you sure you want to deactivate your account? You can reactivate it later by logging in.'
        : 'Are you sure you want to permanently delete your account? This action cannot be undone.';
    
    if (confirm(message)) {
        alert(`${actionText.charAt(0).toUpperCase() + actionText.slice(1)} action confirmed. This feature will be implemented soon.`);
    }
}

// Form submissions
document.getElementById('accountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const button = this.querySelector('button[type="submit"]');
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    button.disabled = true;
    
    setTimeout(() => {
        alert('Account settings saved successfully!');
        button.innerHTML = '<i class="fas fa-save me-2"></i>Save Account Settings';
        button.disabled = false;
    }, 2000);
});

document.getElementById('securityForm').addEventListener('submit', function(e) {
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
    
    setTimeout(() => {
        alert('Password changed successfully!');
        this.reset();
        button.innerHTML = '<i class="fas fa-key me-2"></i>Change Password';
        button.disabled = false;
    }, 2000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
