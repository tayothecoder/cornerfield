<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: register.php
 * Purpose: registration page
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

// handle ajax registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    
    try {
        $authController = new AuthController();
        $authController->register();
    } catch (Throwable $e) {
        error_log('Registration request failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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

$referralCode = isset($_GET['ref']) ? Security::escape($_GET['ref']) : '';
$base = \App\Config\Config::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.theme==="dark"||(!localStorage.theme&&window.matchMedia("(prefers-color-scheme:dark)").matches)){document.documentElement.classList.add("dark")}else{document.documentElement.classList.remove("dark")}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Cornerfield</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind-compiled.css?v=4">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/cornerfield.css?v=4">
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
            <h1 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-6">Create your account</h1>

            <form id="register-form" method="POST" novalidate class="space-y-4">
                <?= Security::getCsrfTokenInput() ?>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">First name</label>
                        <input type="text" id="first_name" name="first_name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="Jane" autocomplete="given-name">
                        <div class="hidden text-sm text-red-500 mt-1" id="first_name-error"></div>
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Last name</label>
                        <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="Doe" autocomplete="family-name">
                        <div class="hidden text-sm text-red-500 mt-1" id="last_name-error"></div>
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="you@example.com" autocomplete="email" required>
                    <div class="hidden text-sm text-red-500 mt-1" id="email-error"></div>
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Username</label>
                    <input type="text" id="username" name="username" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="janedoe" autocomplete="username" required>
                    <div class="hidden text-sm text-red-500 mt-1" id="username-error"></div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="w-full px-4 py-2.5 pr-10 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="Min 8 characters" autocomplete="new-password" required>
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cf-password-toggle" data-target="password">
                            <svg class="w-4 h-4" id="password-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                    <div class="hidden text-sm text-red-500 mt-1" id="password-error"></div>
                    <div class="hidden mt-2" id="password-strength">
                        <div class="bg-gray-200 dark:bg-[#2d1b6e] rounded-full h-1.5 overflow-hidden">
                            <div class="h-full transition-all duration-300 rounded-full" id="strength-fill"></div>
                        </div>
                        <div class="text-xs mt-1" id="strength-text"></div>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Confirm password</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-2.5 pr-10 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="Repeat password" autocomplete="new-password" required>
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cf-password-toggle" data-target="confirm_password">
                            <svg class="w-4 h-4" id="confirm_password-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                    <div class="hidden text-sm text-red-500 mt-1" id="confirm_password-error"></div>
                </div>

                <div>
                    <label for="referral_code" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Referral code <span class="text-gray-400 dark:text-gray-500">(optional)</span></label>
                    <input type="text" id="referral_code" name="referral_code" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:border-[#1e0e62] focus:ring-2 focus:ring-[#1e0e62]/20 outline-none transition-colors" placeholder="Enter code" value="<?= $referralCode ?>">
                    <div class="hidden text-sm text-red-500 mt-1" id="referral_code-error"></div>
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer pt-1">
                    <input type="checkbox" id="agree_terms" name="agree_terms" value="1" class="mt-0.5 w-4 h-4 text-[#1e0e62] border-gray-300 rounded focus:ring-[#1e0e62]" required>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        I agree to the <a href="<?= htmlspecialchars($base) ?>/terms.php" target="_blank" class="text-[#1e0e62] dark:text-indigo-400 font-medium">Terms</a> 
                        and <a href="<?= htmlspecialchars($base) ?>/privacy.php" target="_blank" class="text-[#1e0e62] dark:text-indigo-400 font-medium">Privacy Policy</a>
                    </span>
                </label>
                <div class="hidden text-sm text-red-500 mt-1" id="agree_terms-error"></div>

                <button type="submit" class="w-full bg-[#1e0e62] text-white font-medium py-2.5 px-6 rounded-full hover:bg-[#2d1b8a] transition-colors focus:outline-none" id="register-btn">
                    <span id="btn-content">Create account</span>
                    <span class="hidden items-center justify-center gap-2" id="btn-loading">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating account...
                    </span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6">
                Already have an account? <a href="<?= htmlspecialchars($base) ?>/login.php" class="text-[#1e0e62] dark:text-indigo-400 font-medium">Sign in</a>
            </p>
        </div>
    </div>

    <script src="<?= htmlspecialchars($base) ?>/assets/js/cornerfield.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('register-form');
            const registerBtn = document.getElementById('register-btn');
            const btnContent = document.getElementById('btn-content');
            const btnLoading = document.getElementById('btn-loading');
            
            registerForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                clearFormErrors();
                if (!validateForm()) return;
                
                btnContent.classList.add('hidden');
                btnLoading.classList.remove('hidden');
                btnLoading.classList.add('flex');
                registerBtn.disabled = true;
                
                try {
                    const formData = new FormData(registerForm);
                    const base = document.body.getAttribute('data-base') || '';
                    const url = base + '/register.php';
                    
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
                        console.error('Server did not return JSON:', text.slice(0, 500));
                        showAlert('Server error. Check console for details.', 'error');
                        resetBtn();
                        return;
                    }
                    
                    if (result.success) {
                        showAlert('Account created! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = result.redirect || (base + '/login.php?registered=1');
                        }, 2000);
                    } else {
                        const msg = result.debug ? result.error + ' (' + result.debug + ')' : result.error;
                        if (result.errors && Array.isArray(result.errors)) {
                            result.errors.forEach(error => showAlert(error, 'error'));
                        } else if (result.error) {
                            showAlert(msg, 'error');
                        } else {
                            showAlert('Registration failed. Please try again.', 'error');
                        }
                        resetBtn();
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    showAlert('Network error: ' + (error.message || error), 'error');
                    resetBtn();
                }
            });

            function resetBtn() {
                btnContent.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                btnLoading.classList.remove('flex');
                registerBtn.disabled = false;
            }
            
            // password strength
            document.getElementById('password').addEventListener('input', function() {
                updatePasswordStrength(this.value);
            });
            
            // password toggles
            document.querySelectorAll('.cf-password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    togglePassword(this.getAttribute('data-target'));
                });
            });
            
            setupValidation();
        });
        
        function validateForm() {
            let isValid = true;
            const email = document.getElementById('email').value.trim();
            if (!email) { showFieldError('email', 'Email is required'); isValid = false; }
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showFieldError('email', 'Enter a valid email'); isValid = false; }
            
            const username = document.getElementById('username').value.trim();
            if (!username) { showFieldError('username', 'Username is required'); isValid = false; }
            else if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) { showFieldError('username', 'Username: 3-20 chars, letters/numbers/underscores'); isValid = false; }
            
            const password = document.getElementById('password').value;
            if (!password) { showFieldError('password', 'Password is required'); isValid = false; }
            else if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[^a-zA-Z0-9]/.test(password)) {
                showFieldError('password', 'Min 8 chars with uppercase, lowercase, number, and symbol'); isValid = false;
            }
            
            const confirm = document.getElementById('confirm_password').value;
            if (!confirm) { showFieldError('confirm_password', 'Confirm your password'); isValid = false; }
            else if (password !== confirm) { showFieldError('confirm_password', 'Passwords do not match'); isValid = false; }
            
            if (!document.getElementById('agree_terms').checked) { showFieldError('agree_terms', 'You must agree to the terms'); isValid = false; }
            return isValid;
        }
        
        function setupValidation() {
            const fields = [
                { id: 'email', test: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), msg: 'Enter a valid email' },
                { id: 'username', test: v => /^[a-zA-Z0-9_]{3,20}$/.test(v), msg: '3-20 chars, letters/numbers/underscores' },
                { id: 'first_name', test: v => !v || /^[a-zA-Z\s\-'.]{2,50}$/.test(v), msg: 'Enter a valid name' },
                { id: 'last_name', test: v => !v || /^[a-zA-Z\s\-'.]{2,50}$/.test(v), msg: 'Enter a valid name' }
            ];
            fields.forEach(f => {
                const el = document.getElementById(f.id);
                if (el) el.addEventListener('blur', function() {
                    const v = this.value.trim();
                    if (v && !f.test(v)) showFieldError(f.id, f.msg);
                    else clearFieldError(f.id);
                });
            });
            document.getElementById('confirm_password').addEventListener('blur', function() {
                const pw = document.getElementById('password').value;
                if (this.value && pw !== this.value) showFieldError('confirm_password', 'Passwords do not match');
                else if (this.value) clearFieldError('confirm_password');
            });
        }
        
        function updatePasswordStrength(password) {
            const indicator = document.getElementById('password-strength');
            const fill = document.getElementById('strength-fill');
            const text = document.getElementById('strength-text');
            if (!password) { indicator.classList.add('hidden'); return; }
            indicator.classList.remove('hidden');
            let score = 0;
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;
            fill.style.width = (score / 5 * 100) + '%';
            if (score >= 5) { fill.className = 'h-full transition-all duration-300 rounded-full bg-emerald-500'; text.textContent = 'Strong'; text.className = 'text-xs mt-1 text-emerald-600'; }
            else if (score >= 3) { fill.className = 'h-full transition-all duration-300 rounded-full bg-amber-500'; text.textContent = 'Medium'; text.className = 'text-xs mt-1 text-amber-600'; }
            else { fill.className = 'h-full transition-all duration-300 rounded-full bg-red-500'; text.textContent = 'Weak'; text.className = 'text-xs mt-1 text-red-600'; }
        }
        
        function showAlert(message, type) {
            const existing = document.querySelector('.alert-dynamic');
            if (existing) existing.remove();
            const alert = document.createElement('div');
            const colors = { success: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400', error: 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400', info: 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' };
            alert.className = `alert-dynamic p-3 rounded-xl text-sm ${colors[type] || colors.info} mb-4`;
            alert.textContent = message;
            const form = document.getElementById('register-form');
            form.parentNode.insertBefore(alert, form);
            if (type === 'success') setTimeout(() => { alert.style.opacity = '0'; setTimeout(() => alert.remove(), 300); }, 5000);
        }
        
        function showFieldError(name, msg) {
            const err = document.getElementById(name + '-error');
            const inp = document.getElementById(name);
            if (err) { err.textContent = msg; err.classList.remove('hidden'); }
            if (inp) { inp.classList.add('border-red-400'); inp.classList.remove('border-gray-200'); }
        }
        function clearFieldError(name) {
            const err = document.getElementById(name + '-error');
            const inp = document.getElementById(name);
            if (err) err.classList.add('hidden');
            if (inp) { inp.classList.remove('border-red-400'); inp.classList.add('border-gray-200'); }
        }
        function clearFormErrors() {
            document.querySelectorAll('[id$="-error"]').forEach(e => e.classList.add('hidden'));
            document.querySelectorAll('input').forEach(i => { i.classList.remove('border-red-400'); i.classList.add('border-gray-200'); });
        }
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = document.getElementById(id + '-icon');
            if (input.type === 'password') { input.type = 'text'; icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><path d="M1 1l22 22"></path>'; }
            else { input.type = 'password'; icon.innerHTML = '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>'; }
        }
    </script>
</body>
</html>
