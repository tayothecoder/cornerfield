<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// set session cookie path before any session starts
if (session_status() === PHP_SESSION_NONE) {
    $basePath = \App\Config\Config::getBasePath() ?: '/';
    session_set_cookie_params([
        'path' => $basePath,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

try {
    // Use the modern factory pattern
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Redirect if already logged in
if ($adminController->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
error_log("Admin login attempt - method: " . $_SERVER['REQUEST_METHOD'] . " email: " . ($_POST['email'] ?? 'empty'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    error_log("Admin login processing - email: $email, pass_len: " . strlen($password));

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $result = $adminController->login($email, $password);
            
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = \App\Config\Config::isDebug() ? 'Login error: ' . $e->getMessage() : 'Login failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Admin Login - <?= \App\Config\Config::getSiteName() ?></title>
    <link href="../assets/tabler/dist/css/tabler.min.css?1692870487" rel="stylesheet"/>
    <style>
        .login-page {
            background: #f5f3ff;
            min-height: 100vh;
        }
        .admin-badge {
            background: #1e0e62;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .bitcoin-icon {
            color: #f7931a;
            font-size: 2rem;
        }
    </style>
</head>
<body class="d-flex flex-column login-page">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <div class="bitcoin-icon mb-3">₿</div>
                <h1 class="text-white mb-2"><?= explode(' ', \App\Config\Config::getSiteName())[0] ?></h1>
                <div class="admin-badge">Admin Panel</div>
            </div>
            
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">Administrator Login</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <div class="d-flex">
                                <div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <circle cx="12" cy="12" r="9"/>
                                        <line x1="12" y1="8" x2="12" y2="12"/>
                                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                                    </svg>
                                </div>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Admin Email</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="<?= \App\Config\Config::getAdminEmail() ?>" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   autocomplete="username" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Your password" autocomplete="current-password" required>
                        </div>
                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100">Sign in to Admin Panel</button>
                        </div>
                    </form>
                    
                    <?php if (\App\Config\Config::isDebug()): ?>
                    <div class="mt-3 p-2" style="background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                        <strong>Debug Mode:</strong><br>
                        Email: <?= \App\Config\Config::getAdminEmail() ?><br>
                        Password: admin123
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="../dashboard.php" class="text-white text-decoration-none opacity-75">
                    ← Back to User Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <script src="../assets/tabler/dist/js/tabler.min.js?1692870487" defer></script>
</body>
</html>