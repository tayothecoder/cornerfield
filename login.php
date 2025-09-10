<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Controllers\AuthController;
use App\Utils\SessionManager;
use App\Models\AdminSettings;

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

// Start session and check if already logged in
SessionManager::start();

if (SessionManager::get('user_logged_in')) {
    header('Location: users/dashboard.php'); 
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $database = new Database();
            $authController = new AuthController($database);
            
            $result = $authController->login($email, $password);
            
            if ($result['success']) {
                header('Location: users/dashboard.php'); 
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = Config::isDebug() ? 'Login error: ' . $e->getMessage() : 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= Config::getSiteName() ?></title>
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
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }

        .login-header {
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

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .login-subtitle {
            opacity: 0.9;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
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

        .input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: white;
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .form-check-input {
            margin-right: 0.5rem;
        }

        .form-check-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .login-btn {
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

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(79, 172, 254, 0.3);
        }

        .login-btn:disabled {
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

        .register-btn {
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

        .register-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            opacity: 0.8;
        }

        .login-footer a {
            color: white;
            text-decoration: underline;
            margin: 0 0.5rem;
            font-size: 0.875rem;
        }

        .login-footer a:hover {
            opacity: 0.8;
        }

        .debug-info {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.8rem;
            opacity: 0.8;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="crypto-icon">₿</div>
            <h1 class="site-name"><?= explode(' ', Config::getSiteName())[0] ?></h1>
            <p class="site-tagline">Investment Platform</p>
        </div>

        <div class="login-card">
            <h2 class="login-title">Welcome Back</h2>
            <p class="login-subtitle">Sign in to your investment account</p>
            
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" novalidate>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="your@email.com" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-input" placeholder="Your password" 
                               autocomplete="current-password" required id="password-input">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me on this device</label>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Sign In
                </button>
            </form>

            <div class="divider">
                <span>or</span>
            </div>

            <a href="register.php" class="register-btn">
                <i class="fas fa-user-plus me-2"></i>
                Create New Account
            </a>

            <?php if (Config::isDebug()): ?>
                <div class="debug-info">
                    <strong>🔧 Debug Mode:</strong><br>
                    Test with existing user credentials from database
                </div>
            <?php endif; ?>
        </div>

        <div class="login-footer">
            <a href="admin/login.php">Admin Login</a> |
            <a href="index.php">Back to Site</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password-input');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>