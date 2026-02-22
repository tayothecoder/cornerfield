<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: login.php
 * Purpose: login page
 * Security Level: PUBLIC
 */

require_once __DIR__ . '/autoload.php';
\App\Config\EnvLoader::load(__DIR__ . DIRECTORY_SEPARATOR . '.env');

use App\Middleware\AuthMiddleware;
use App\Utils\Security;
use App\Controllers\AuthController;

Security::setSecurityHeaders();

if (session_status() === PHP_SESSION_NONE) {
    $basePath = \App\Config\Config::getBasePath();
    $cookiePath = $basePath ?: '/';
    session_set_cookie_params([
        'path' => $cookiePath,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (AuthMiddleware::check()) {
    $base = \App\Config\Config::getBasePath();
    header('Location: ' . $base . '/users/dashboard.php');
    exit;
}

// handle ajax login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    
    try {
        $authController = new AuthController();
        $authController->login();
    } catch (Throwable $e) {
        error_log('Login request failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        header('Content-Type: application/json');
        http_response_code(503);
        $payload = [
            'success' => false,
            'error' => 'Connection error. Please try again.'
        ];
        if (\App\Config\EnvLoader::get('APP_DEBUG', 'false') === 'true') {
            $payload['debug'] = $e->getMessage();
        }
        echo json_encode($payload);
    }
    exit;
}

$message = '';
$messageType = 'info';

if (isset($_GET['registered'])) {
    $message = 'Account created successfully! Please log in with your credentials.';
    $messageType = 'success';
}
if (isset($_GET['logged_out'])) {
    $message = 'You have been logged out successfully.';
    $messageType = 'info';
}
if (isset($_GET['password_changed'])) {
    $message = 'Password changed successfully! Please log in with your new password.';
    $messageType = 'success';
}
if (isset($_GET['reset'])) {
    $message = 'Password reset successfully! You can now log in with your new password.';
    $messageType = 'success';
}

$base = \App\Config\Config::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.theme==="dark"||(!localStorage.theme&&window.matchMedia("(prefers-color-scheme:dark)").matches)){document.documentElement.classList.add("dark")}else{document.documentElement.classList.remove("dark")}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cornerfield</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind-compiled.css?v=4">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/cornerfield.css?v=4">
    <?= Security::getCsrfTokenInput() ?>
    <meta name="csrf-token" content="<?= Security::generateCsrfToken() ?>">
</head>
<body class="min-h-screen bg-[#f5f3ff] dark:bg-[#0f0a2e] flex items-center justify-center p-4" data-base="<?= htmlspecialchars($base) ?>">
    <!-- dark mode toggle -->
    <button onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')" class="fixed top-4 right-4 z-50 p-2 rounded-full bg-white/80 dark:bg-[#1a1145]/80 text-gray-500 dark:text-gray-400">
        <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
    </button>

    <div class="w-full max-w-md">
        <!-- logo -->
        <div class="text-center mb-8">
            <span class="text-2xl font-semibold tracking-tight text-[#1e0e62] dark:text-white">Cornerfield</span>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-8 shadow-sm">
            <!-- messages -->
            <?php if ($message): ?>
            <div class="mb-6 p-3 rounded-xl text-sm <?= $messageType === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' : 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' ?>" id="login-message">
                <?= Security::escape($message) ?>
            </div>
            <?php endif; ?>

            <form id="login-form" method="POST" novalidate class="space-y-4">
                <?= Security::getCsrfTokenInput() ?>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Email or username</label>
                    <input 
                        type="text" 
                        id="email" 
                        name="email" 
                        class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors"
                        placeholder="you@example.com"
                        autocomplete="username"
                        required
                    >
                    <div class="hidden text-sm text-red-500 mt-1" id="email-error"></div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Password</label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="w-full px-4 py-2.5 pr-10 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cf-password-toggle" data-target="password">
                            <svg class="w-4 h-4" id="password-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="hidden text-sm text-red-500 mt-1" id="password-error"></div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="remember_me" name="remember_me" class="w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]">
                        <span class="text-gray-600 dark:text-gray-400">Remember me</span>
                    </label>
                    <a href="<?= htmlspecialchars($base) ?>/forgot-password.php" class="text-[#1e0e62] dark:text-indigo-400 font-medium">Forgot password?</a>
                </div>

                <button type="submit" class="w-full bg-[#1e0e62] text-white font-medium py-2.5 px-6 rounded-full hover:bg-[#2d1b8a] transition-colors focus:outline-none" id="login-btn">
                    <span id="btn-content">Sign in</span>
                    <span class="hidden items-center justify-center gap-2" id="btn-loading">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Signing in...
                    </span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6">
                Don't have an account? <a href="<?= htmlspecialchars($base) ?>/register.php" class="text-[#1e0e62] dark:text-indigo-400 font-medium">Create account</a>
            </p>
        </div>
    </div>

    <script src="<?= htmlspecialchars($base) ?>/assets/js/cornerfield.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('login-form');
            const loginBtn = document.getElementById('login-btn');
            const btnContent = document.getElementById('btn-content');
            const btnLoading = document.getElementById('btn-loading');
            
            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                clearFormErrors();
                
                btnContent.classList.add('hidden');
                btnLoading.classList.remove('hidden');
                btnLoading.classList.add('flex');
                loginBtn.disabled = true;
                
                try {
                    const formData = new FormData(loginForm);
                    const base = document.body.getAttribute('data-base') || '';
                    const url = base + '/login.php';
                    
                    const response = await window.fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData,
                        credentials: 'same-origin'
                    });
                    
                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (parseErr) {
                        console.error('Server did not return JSON. URL:', url, 'Status:', response.status, 'Response:', text.slice(0, 500));
                        showAlert('Server error. Check console for details.', 'error');
                        resetBtn();
                        return;
                    }
                    
                    if (result.success) {
                        showAlert('Login successful! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = result.redirect || (base + '/users/dashboard.php');
                        }, 1000);
                    } else {
                        const msg = result.debug ? result.error + ' (' + result.debug + ')' : result.error;
                        if (result.errors && Array.isArray(result.errors)) {
                            result.errors.forEach(error => showAlert(error, 'error'));
                        } else if (result.error) {
                            showAlert(msg, 'error');
                        } else {
                            showAlert('Login failed. Please try again.', 'error');
                        }
                        resetBtn();
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    showAlert('Network error: ' + (error.message || error), 'error');
                    resetBtn();
                }
            });

            function resetBtn() {
                btnContent.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                btnLoading.classList.remove('flex');
                loginBtn.disabled = false;
            }
            
            document.querySelector('.cf-password-toggle').addEventListener('click', function() {
                togglePassword('password');
            });
            
            const loginMessage = document.getElementById('login-message');
            if (loginMessage) {
                setTimeout(() => {
                    loginMessage.style.opacity = '0';
                    setTimeout(() => loginMessage.remove(), 300);
                }, 5000);
            }
            
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            emailInput.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value && !isValidEmailOrUsername(value)) {
                    showFieldError('email', 'Please enter a valid email address or username');
                } else {
                    clearFieldError('email');
                }
            });
        });
        
        function showAlert(message, type) {
            const existingAlert = document.querySelector('.alert-dynamic');
            if (existingAlert) existingAlert.remove();
            
            const alert = document.createElement('div');
            const colors = {
                success: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400',
                error: 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
                info: 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400'
            };
            
            alert.className = `alert-dynamic p-3 rounded-xl text-sm ${colors[type] || colors.info} mb-4`;
            alert.textContent = message;
            
            const form = document.getElementById('login-form');
            form.parentNode.insertBefore(alert, form);
            
            if (type !== 'error') {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        }
        
        function showFieldError(fieldName, message) {
            const errorEl = document.getElementById(fieldName + '-error');
            const inputEl = document.getElementById(fieldName);
            if (errorEl) { errorEl.textContent = message; errorEl.classList.remove('hidden'); }
            if (inputEl) { inputEl.classList.add('border-red-400'); inputEl.classList.remove('border-gray-200'); }
        }
        
        function clearFieldError(fieldName) {
            const errorEl = document.getElementById(fieldName + '-error');
            const inputEl = document.getElementById(fieldName);
            if (errorEl) errorEl.classList.add('hidden');
            if (inputEl) { inputEl.classList.remove('border-red-400'); inputEl.classList.add('border-gray-200'); }
        }
        
        function clearFormErrors() {
            document.querySelectorAll('[id$="-error"]').forEach(e => e.classList.add('hidden'));
            document.querySelectorAll('input').forEach(i => { i.classList.remove('border-red-400'); i.classList.add('border-gray-200'); });
        }
        
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><path d="M1 1l22 22"></path>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>';
            }
        }
        
        function isValidEmailOrUsername(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || /^[a-zA-Z0-9_]{3,20}$/.test(value);
        }
    </script>
</body>
</html>
