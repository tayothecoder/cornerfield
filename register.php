<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Controllers\AuthController;
use App\Utils\SessionManager;
use App\Models\AdminSettings;
use App\Models\User;
use App\Models\Transaction;

// Check maintenance mode
try {
    $database = new Database();
    $adminSettingsModel = new AdminSettings($database);
    $maintenanceMode = $adminSettingsModel->getSetting('maintenance_mode', 0);
    
    if ($maintenanceMode && !isset($_GET['admin_bypass'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Site Under Maintenance - <?= Config::getSiteName() ?></title>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                }
                .maintenance-container {
                    text-align: center;
                    padding: 2rem;
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
                }
                .crypto-icon {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                    background: linear-gradient(45deg, #f7931a, #ffd700);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                h1 { font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700; }
                .lead { font-size: 1.2rem; margin-bottom: 1.5rem; opacity: 0.9; }
                .completion { opacity: 0.8; font-size: 0.95rem; }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="crypto-icon">₿</div>
                <h1>Site Under Maintenance</h1>
                <p class="lead">We're currently performing scheduled maintenance. Please check back shortly.</p>
                <div class="completion">Expected completion: Within 2 hours</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    // If database fails, allow access
}

SessionManager::start();

if (SessionManager::get('user_logged_in')) {
    header('Location: users/dashboard.php');
    exit;
}

$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authController = new AuthController($database);
        
        $formData = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'country' => trim($_POST['country'] ?? ''),
            'referral_code' => trim($_POST['referral_code'] ?? ''),
            'terms' => isset($_POST['terms'])
        ];
        
        if (empty($formData['first_name'])) {
            $errors[] = 'First name is required';
        }
        
        if (empty($formData['last_name'])) {
            $errors[] = 'Last name is required';
        }
        
        if (empty($formData['username'])) {
            $errors[] = 'Username is required';
        } elseif (strlen($formData['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        
        if (empty($formData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($formData['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($formData['password']) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        if ($formData['password'] !== $formData['password_confirm']) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!$formData['terms']) {
            $errors[] = 'You must accept the terms and conditions';
        }
        
        if (empty($errors)) {
            $result = $authController->register($formData);
            
            if ($result['success']) {
                // Apply signup bonus
                $signupBonus = $adminSettingsModel->getSetting('signup_bonus', 0);
                
                if ($signupBonus > 0) {
                    $userId = $result['user_id'];
                    $userModel = new User($database);
                    $transactionModel = new Transaction($database);
                    
                    try {
                        $database->beginTransaction();
                        
                        // Add bonus to balance
                        $userModel->addToBalance($userId, $signupBonus);
                        
                        // Create transaction record
                        $transactionModel->createTransaction([
                            'user_id' => $userId,
                            'type' => 'bonus',
                            'amount' => $signupBonus,
                            'net_amount' => $signupBonus,
                            'description' => 'Welcome signup bonus',
                            'status' => 'completed',
                            'payment_method' => 'system'
                        ]);
                        
                        $database->commit();
                        
                        $success = 'Registration successful! Welcome bonus of $' . number_format($signupBonus, 2) . ' has been added to your account. You can now login.';
                    } catch (Exception $e) {
                        $database->rollback();
                        $success = 'Registration successful! You can now login with your credentials.';
                    }
                } else {
                    $success = 'Registration successful! You can now login with your credentials.';
                }
                
                $formData = [];
            } else {
                $errors[] = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        if (Config::isDebug()) {
            $errors[] = 'Error: ' . $e->getMessage();
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

// Get signup bonus for display
$signupBonusDisplay = 0;
try {
    $signupBonusDisplay = $adminSettingsModel->getSetting('signup_bonus', 0);
} catch (Exception $e) {
    // Ignore if settings not available
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - <?= Config::getSiteName() ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            --border-radius: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 2rem 0;
        }

        .register-container {
            width: 100%;
            max-width: 500px;
            padding: 0 1rem;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .crypto-icon {
            background: linear-gradient(45deg, #f7931a, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .site-name {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .site-tagline {
            opacity: 0.9;
            font-size: 1rem;
            font-weight: 400;
        }

        .register-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .register-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .register-subtitle {
            opacity: 0.9;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .bonus-highlight {
            background: var(--warning-gradient);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 10px 20px rgba(250, 112, 154, 0.3);
        }

        .bonus-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .bonus-amount {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.95rem;
        }

        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .form-select option {
            background: #2d3748;
            color: white;
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
            opacity: 0.9;
            line-height: 1.4;
        }

        .form-check-label a {
            color: white;
            text-decoration: underline;
        }

        .form-check-label a:hover {
            opacity: 0.8;
        }

        .register-btn {
            background: var(--success-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(79, 172, 254, 0.3);
        }

        .register-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #fca5a5;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        .alert ul {
            margin: 0;
            padding-left: 1rem;
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            opacity: 0.7;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
        }

        .divider span {
            background: var(--primary-gradient);
            padding: 0 1rem;
            font-size: 0.875rem;
        }

        .login-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .login-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .register-footer {
            text-align: center;
            margin-top: 2rem;
            opacity: 0.8;
        }

        .register-footer a {
            color: white;
            text-decoration: underline;
            margin: 0 0.5rem;
            font-size: 0.875rem;
        }

        .register-footer a:hover {
            opacity: 0.8;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .feature-value {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .feature-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .form-hint {
            font-size: 0.8rem;
            opacity: 0.7;
            margin-top: 0.25rem;
            display: block;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 0 0.5rem;
            }
            
            .register-card {
                padding: 1.5rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="crypto-icon">₿</div>
            <h1 class="site-name"><?= explode(' ', Config::getSiteName())[0] ?></h1>
            <p class="site-tagline">Investment Platform</p>
        </div>

        <div class="register-card">
            <h2 class="register-title">Create Your Account</h2>
            <p class="register-subtitle">Join thousands of investors earning daily profits with cryptocurrency</p>
            
            <?php if ($signupBonusDisplay > 0): ?>
                <div class="bonus-highlight">
                    <div class="bonus-title">🎉 Welcome Bonus!</div>
                    <div class="bonus-amount">Get $<?= number_format($signupBonusDisplay, 2) ?> instantly</div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <div style="margin-top: 1rem;">
                        <a href="login.php" class="login-btn">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Login Now
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" autocomplete="off" novalidate>
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-input" placeholder="Enter your first name"
                               value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-input" placeholder="Enter your last name"
                               value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" placeholder="Choose a username"
                               value="<?= htmlspecialchars($formData['username'] ?? '') ?>" required>
                        <span class="form-hint">Minimum 3 characters, letters and numbers only</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="your@email.com"
                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Password" required>
                        <span class="form-hint">Minimum 6 characters</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirm" class="form-input" placeholder="Confirm password" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <select name="country" class="form-select">
                            <option value="">Select your country</option>
                            <option value="NG" <?= ($formData['country'] ?? '') === 'NG' ? 'selected' : '' ?>>Nigeria</option>
                            <option value="US" <?= ($formData['country'] ?? '') === 'US' ? 'selected' : '' ?>>United States</option>
                            <option value="GB" <?= ($formData['country'] ?? '') === 'GB' ? 'selected' : '' ?>>United Kingdom</option>
                            <option value="CA" <?= ($formData['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                            <option value="AU" <?= ($formData['country'] ?? '') === 'AU' ? 'selected' : '' ?>>Australia</option>
                            <option value="DE" <?= ($formData['country'] ?? '') === 'DE' ? 'selected' : '' ?>>Germany</option>
                            <option value="FR" <?= ($formData['country'] ?? '') === 'FR' ? 'selected' : '' ?>>France</option>
                            <option value="IN" <?= ($formData['country'] ?? '') === 'IN' ? 'selected' : '' ?>>India</option>
                            <option value="ZA" <?= ($formData['country'] ?? '') === 'ZA' ? 'selected' : '' ?>>South Africa</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Referral Code <span style="opacity: 0.7;">(Optional)</span></label>
                        <input type="text" name="referral_code" class="form-input" placeholder="Enter referral code if you have one"
                               value="<?= htmlspecialchars($formData['referral_code'] ?? $_GET['ref'] ?? '') ?>">
                        <span class="form-hint">Get bonus rewards if referred by another user</span>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="terms" class="form-check-input" required>
                        <label class="form-check-label">
                            I agree to the <a href="#" target="_blank">Terms of Service</a> and 
                            <a href="#" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="register-btn">
                        <i class="fas fa-user-plus me-2"></i>
                        Create Account
                        <?php if ($signupBonusDisplay > 0): ?>
                            & Get $<?= number_format($signupBonusDisplay, 2) ?> Bonus
                        <?php endif; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="register-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-value">2-3.5%</div>
                <div class="feature-label">Daily Returns</div>
            </div>
            <div class="feature-card">
                <div class="feature-value">24/7</div>
                <div class="feature-label">Support</div>
            </div>
            <div class="feature-card">
                <div class="feature-value">₿</div>
                <div class="feature-label">Crypto Pay</div>
            </div>
        </div>
    </div>

    <script>
        // Auto-fill referral code from URL
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');
        if (refCode && !document.querySelector('input[name="referral_code"]').value) {
            document.querySelector('input[name="referral_code"]').value = refCode;
        }
        
        // Password confirmation validation
        const password = document.querySelector('input[name="password"]');
        const passwordConfirm = document.querySelector('input[name="password_confirm"]');
        
        function validatePasswords() {
            if (password.value && passwordConfirm.value && password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('Passwords do not match');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        }
        
        password.addEventListener('input', validatePasswords);
        passwordConfirm.addEventListener('input', validatePasswords);

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>