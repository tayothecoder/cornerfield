<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: register.php
 * Purpose: Modern registration page with comprehensive validation
 * Security Level: PUBLIC
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

// Include autoload and check if already logged in
require_once __DIR__ . '/autoload.php';

// Load .env from project root (path relative to this file so it works from web or CLI)
\App\Config\EnvLoader::load(__DIR__ . DIRECTORY_SEPARATOR . '.env');

use App\Middleware\AuthMiddleware;
use App\Utils\Security;
use App\Controllers\AuthController;

// Set security headers
Security::setSecurityHeaders();

// Start session with cookie path matching app base (so session persists for subdirectory)
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

// Handle AJAX registration requests
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
        // NON-PRODUCTION: only include exception message when APP_DEBUG is on. Set APP_DEBUG=false before production.
        if (\App\Config\EnvLoader::get('APP_DEBUG', 'false') === 'true') {
            $payload['debug'] = $e->getMessage();
        }
        echo json_encode($payload);
    }
    exit;
}

// Get referral code from URL if present
$referralCode = isset($_GET['ref']) ? Security::escape($_GET['ref']) : '';

$base = \App\Config\Config::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Cornerfield Investment Platform</title>
    
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/tailwind-compiled.css">
    
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.svg">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS Variables (keep for theme system) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/cornerfield.css">
    
    <!-- CSRF Token Meta -->
    <meta name="csrf-token" content="<?= Security::generateCsrfToken() ?>">
</head>
<body class="min-h-screen bg-gradient-to-br from-cf-primary via-cf-secondary to-cf-primary flex items-center justify-center p-4 relative overflow-x-hidden dark:from-slate-900 dark:via-slate-800 dark:to-slate-900" data-base="<?= htmlspecialchars($base) ?>">
    <!-- Animated Background Pattern -->
    <div class="absolute inset-0 bg-gradient-to-br from-cf-primary/10 via-transparent to-cf-secondary/10 animate-pulse"></div>
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-cf-primary/5 rounded-full blur-3xl animate-bounce" style="animation-delay: 1s;"></div>
    <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-cf-secondary/5 rounded-full blur-3xl animate-bounce" style="animation-delay: 2s;"></div>
    
    <div class="w-full max-w-2xl relative z-10">
        <div class="bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-white/20 dark:border-slate-700/50 rounded-3xl shadow-2xl shadow-cf-primary/20 p-8 transform transition-all duration-700">
            <!-- Logo and Branding -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center gap-3 mb-4">
                    <svg class="w-10 h-10 text-cf-primary" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    </svg>
                    <span class="text-2xl font-extrabold bg-gradient-to-r from-cf-primary to-cf-secondary bg-clip-text text-transparent">Cornerfield</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">Start Your Investment Journey Today</p>
            </div>

            <!-- Registration Form -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Create Your Account</h1>
                <p class="text-gray-600 dark:text-gray-300 mb-8">Join thousands of investors building their crypto portfolio</p>

                <form id="register-form" method="POST" novalidate class="space-y-6">
                    <?= Security::getCsrfTokenInput() ?>
                    
                    <!-- Personal Information -->
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-2xl p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Personal Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                    </svg>
                                    First Name
                                </label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="w-full px-4 py-3 bg-white dark:bg-slate-600 border-2 border-gray-200 dark:border-slate-500 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all duration-200 outline-none"
                                    placeholder="Enter your first name"
                                    autocomplete="given-name"
                                >
                                <div class="hidden text-sm text-cf-danger mt-1" id="first_name-error"></div>
                            </div>
                            
                            <div>
                                <label for="last_name" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                    </svg>
                                    Last Name
                                </label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="w-full px-4 py-3 bg-white dark:bg-slate-600 border-2 border-gray-200 dark:border-slate-500 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all duration-200 outline-none"
                                    placeholder="Enter your last name"
                                    autocomplete="family-name"
                                >
                                <div class="hidden text-sm text-cf-danger mt-1" id="last_name-error"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-2xl p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Account Details</h3>
                        
                        <div>
                            <label for="email" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                </svg>
                                Email Address <span class="text-cf-danger">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="w-full px-4 py-3 bg-white dark:bg-slate-600 border-2 border-gray-200 dark:border-slate-500 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all duration-200 outline-none"
                                placeholder="Enter your email address"
                                autocomplete="email"
                                required
                            >
                            <div class="hidden text-sm text-cf-danger mt-1" id="email-error"></div>
                        </div>

                        <div>
                            <label for="username" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <span class="text-cf-primary">@</span>
                                Username <span class="text-cf-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="w-full px-4 py-3 bg-white dark:bg-slate-600 border-2 border-gray-200 dark:border-slate-500 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all duration-200 outline-none"
                                placeholder="Choose a username (3-20 characters)"
                                autocomplete="username"
                                required
                            >
                            <div class="hidden text-sm text-cf-danger mt-1" id="username-error"></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Only letters, numbers, and underscores allowed</div>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-2xl p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-600 pb-2 mb-4">Security</h3>
                        
                        <div>
                            <label for="password" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                </svg>
                                Password <span class="text-cf-danger">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="w-full px-4 py-3 pr-12 bg-white dark:bg-slate-600 border-2 border-gray-200 dark:border-slate-500 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all duration-200 outline-none"
                                    placeholder="Create a strong password"
                                    autocomplete="new-password"
                                    required
                                >
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors cf-password-toggle" data-target="password">
                                    <svg class="w-5 h-5" id="password-icon" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="hidden text-sm text-cf-danger mt-1" id="password-error"></div>
                            <div class="hidden mt-2" id="password-strength">
                                <div class="bg-gray-200 dark:bg-slate-600 rounded-full h-2 overflow-hidden">
                                    <div class="h-full transition-all duration-300 rounded-full" id="strength-fill"></div>
                                </div>
                                <div class="text-xs mt-1 font-medium" id="strength-text"></div>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                </svg>
                                Confirm Password <span class="text-cf-danger">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="w-full px-4 py-3 pr-12 bg-white dark:bg-slate-600 border-2 border-gray-200 dark:border-slate-500 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-primary focus:ring-4 focus:ring-cf-primary/20 transition-all duration-200 outline-none"
                                    placeholder="Confirm your password"
                                    autocomplete="new-password"
                                    required
                                >
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors cf-password-toggle" data-target="confirm_password">
                                    <svg class="w-5 h-5" id="confirm_password-icon" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="hidden text-sm text-cf-danger mt-1" id="confirm_password-error"></div>
                        </div>
                    </div>

                    <!-- Referral -->
                    <div class="bg-gradient-to-br from-cf-info/10 to-cf-secondary/10 border border-cf-info/20 rounded-2xl p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-cf-info" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 5a3 3 0 015-2.236A3 3 0 0114.83 6H16a2 2 0 110 4h-5V9a1 1 0 10-2 0v1H4a2 2 0 110-4h1.17C5.06 5.687 5 5.35 5 5zm4 1V5a1 1 0 10-1 1h1zm3 0a1 1 0 10-1-1v1h1z" clip-rule="evenodd"></path>
                                <path d="M9 11H3v5a2 2 0 002 2h4v-7zM11 18h4a2 2 0 002-2v-5h-6v7z"></path>
                            </svg>
                            Referral (Optional)
                        </h3>
                        
                        <div>
                            <label for="referral_code" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <span class="text-cf-info">🎁</span>
                                Referral Code
                            </label>
                            <input 
                                type="text" 
                                id="referral_code" 
                                name="referral_code" 
                                class="w-full px-4 py-3 bg-white dark:bg-slate-600 border-2 border-cf-info/30 rounded-xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-cf-info focus:ring-4 focus:ring-cf-info/20 transition-all duration-200 outline-none"
                                placeholder="Enter referral code (if any)"
                                value="<?= $referralCode ?>"
                            >
                            <div class="hidden text-sm text-cf-danger mt-1" id="referral_code-error"></div>
                            <div class="text-xs text-cf-info mt-1">Have a referral code? Enter it to get bonus rewards!</div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-2xl p-6">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="agree_terms" name="agree_terms" value="1" class="mt-1 w-4 h-4 text-cf-primary bg-gray-100 dark:bg-slate-600 border-gray-300 dark:border-slate-500 rounded focus:ring-cf-primary focus:ring-2" required>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                I agree to the <a href="<?= htmlspecialchars($base) ?>/terms.php" target="_blank" class="text-cf-primary hover:text-cf-primary-dark transition-colors font-medium">Terms and Conditions</a> 
                                and <a href="<?= htmlspecialchars($base) ?>/privacy.php" target="_blank" class="text-cf-primary hover:text-cf-primary-dark transition-colors font-medium">Privacy Policy</a> 
                                <span class="text-cf-danger">*</span>
                            </span>
                        </label>
                        <div class="hidden text-sm text-cf-danger mt-1" id="agree_terms-error"></div>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-cf-primary to-cf-primary-dark hover:from-cf-primary-dark hover:to-cf-primary text-white font-semibold py-4 px-4 rounded-xl transition-all duration-200 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-cf-primary/25 focus:outline-none focus:ring-4 focus:ring-cf-primary/20" id="register-btn">
                        <span class="flex items-center justify-center gap-2" id="btn-content">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"></path>
                            </svg>
                            Create Account
                        </span>
                        <div class="hidden items-center justify-center gap-2" id="btn-loading">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Creating account...
                        </div>
                    </button>
                </form>

                <!-- Divider -->
                <div class="flex items-center my-6">
                    <div class="flex-1 border-t border-gray-200 dark:border-slate-600"></div>
                    <span class="px-4 text-sm text-gray-500 dark:text-gray-400">or</span>
                    <div class="flex-1 border-t border-gray-200 dark:border-slate-600"></div>
                </div>

                <!-- Login Link -->
                <a href="<?= htmlspecialchars($base) ?>/login.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-slate-700 border-2 border-gray-200 dark:border-slate-600 text-gray-700 dark:text-gray-300 font-semibold py-3 px-4 rounded-xl transition-all duration-200 hover:border-cf-primary hover:text-cf-primary transform hover:scale-[1.02]">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    Already have an account? Sign In
                </a>
            </div>

            <!-- Footer Links -->
            <div class="flex items-center justify-between mt-8 text-xs text-gray-500 dark:text-gray-400">
                <a href="<?= htmlspecialchars($base) ?>/" class="hover:text-cf-primary transition-colors">← Back to Home</a>
                <a href="<?= htmlspecialchars($base) ?>/help.php" class="hover:text-cf-primary transition-colors">Need Help?</a>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?= htmlspecialchars($base) ?>/assets/js/cornerfield.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize registration form handler
            const registerForm = document.getElementById('register-form');
            const registerBtn = document.getElementById('register-btn');
            const btnContent = document.getElementById('btn-content');
            const btnLoading = document.getElementById('btn-loading');
            
            // Form submission
            registerForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearFormErrors();
                
                // Client-side validation
                if (!validateForm()) {
                    return;
                }
                
                // Show loading state
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
                        console.error('Server did not return JSON. URL:', url, 'Status:', response.status, 'Response:', text.slice(0, 500));
                        const snippet = text.slice(0, 200).replace(/\s+/g, ' ');
                        showAlert('Server error (not JSON). Status: ' + response.status + '. Check browser Console (F12) for details. Snippet: ' + snippet, 'error');
                        btnContent.classList.remove('hidden');
                        btnLoading.classList.add('hidden');
                        btnLoading.classList.remove('flex');
                        registerBtn.disabled = false;
                        return;
                    }
                    
                    if (result.success) {
                        // Show success message and redirect
                        showAlert('Account created successfully! Redirecting to login...', 'success');
                        
                        setTimeout(() => {
                            window.location.href = result.redirect || (base + '/login.php?registered=1');
                        }, 2000);
                    } else {
                        // Show errors (include server debug when APP_DEBUG is on)
                        const msg = result.debug ? result.error + ' (' + result.debug + ')' : result.error;
                        if (result.errors && Array.isArray(result.errors)) {
                            result.errors.forEach(error => {
                                showAlert(error, 'error');
                            });
                        } else if (result.error) {
                            showAlert(msg, 'error');
                        } else {
                            showAlert('Registration failed. Please try again.', 'error');
                        }
                        
                        // Reset form state
                        btnContent.classList.remove('hidden');
                        btnLoading.classList.add('hidden');
                        btnLoading.classList.remove('flex');
                        registerBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    showAlert('Network or request failed: ' + (error.message || error), 'error');
                    
                    // Reset form state
                    btnContent.classList.remove('hidden');
                    btnLoading.classList.add('hidden');
                    btnLoading.classList.remove('flex');
                    registerBtn.disabled = false;
                }
            });
            
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const strengthIndicator = document.getElementById('password-strength');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                updatePasswordStrength(password);
            });
            
            // Password toggle buttons
            document.querySelectorAll('.cf-password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    togglePassword(target);
                });
            });
            
            // Real-time validation
            setupValidation();
        });
        
        function validateForm() {
            let isValid = true;
            
            // Email validation
            const email = document.getElementById('email').value.trim();
            if (!email) {
                showFieldError('email', 'Email address is required');
                isValid = false;
            } else if (!isValidEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
                isValid = false;
            }
            
            // Username validation
            const username = document.getElementById('username').value.trim();
            if (!username) {
                showFieldError('username', 'Username is required');
                isValid = false;
            } else if (!isValidUsername(username)) {
                showFieldError('username', 'Username must be 3-20 characters, letters, numbers, and underscores only');
                isValid = false;
            }
            
            // Password validation
            const password = document.getElementById('password').value;
            if (!password) {
                showFieldError('password', 'Password is required');
                isValid = false;
            } else if (!isStrongPassword(password)) {
                showFieldError('password', 'Password must be at least 8 characters with mixed case, numbers, and symbols');
                isValid = false;
            }
            
            // Confirm password validation
            const confirmPassword = document.getElementById('confirm_password').value;
            if (!confirmPassword) {
                showFieldError('confirm_password', 'Please confirm your password');
                isValid = false;
            } else if (password !== confirmPassword) {
                showFieldError('confirm_password', 'Passwords do not match');
                isValid = false;
            }
            
            // Terms agreement validation
            const agreeTerms = document.getElementById('agree_terms').checked;
            if (!agreeTerms) {
                showFieldError('agree_terms', 'You must agree to the terms and conditions');
                isValid = false;
            }
            
            return isValid;
        }
        
        function setupValidation() {
            const fields = [
                { id: 'email', validator: isValidEmail, message: 'Please enter a valid email address' },
                { id: 'username', validator: isValidUsername, message: 'Username must be 3-20 characters, letters, numbers, and underscores only' },
                { id: 'first_name', validator: isValidName, message: 'Please enter a valid first name' },
                { id: 'last_name', validator: isValidName, message: 'Please enter a valid last name' },
                { id: 'referral_code', validator: (val) => !val || isValidReferralCode(val), message: 'Invalid referral code format' }
            ];
            
            fields.forEach(field => {
                const input = document.getElementById(field.id);
                if (input) {
                    input.addEventListener('blur', function() {
                        const value = this.value.trim();
                        if (value && !field.validator(value)) {
                            showFieldError(field.id, field.message);
                        } else {
                            clearFieldError(field.id);
                        }
                    });
                }
            });
            
            // Password confirmation validation
            const confirmPasswordInput = document.getElementById('confirm_password');
            confirmPasswordInput.addEventListener('blur', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    showFieldError('confirm_password', 'Passwords do not match');
                } else if (confirmPassword) {
                    clearFieldError('confirm_password');
                }
            });
        }
        
        function updatePasswordStrength(password) {
            const strengthIndicator = document.getElementById('password-strength');
            const strengthFill = document.getElementById('strength-fill');
            const strengthText = document.getElementById('strength-text');
            
            if (!password) {
                strengthIndicator.classList.add('hidden');
                return;
            }
            
            strengthIndicator.classList.remove('hidden');
            
            let score = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) score += 1;
            else feedback.push('At least 8 characters');
            
            // Uppercase check
            if (/[A-Z]/.test(password)) score += 1;
            else feedback.push('One uppercase letter');
            
            // Lowercase check
            if (/[a-z]/.test(password)) score += 1;
            else feedback.push('One lowercase letter');
            
            // Number check
            if (/[0-9]/.test(password)) score += 1;
            else feedback.push('One number');
            
            // Symbol check
            if (/[^a-zA-Z0-9]/.test(password)) score += 1;
            else feedback.push('One special character');
            
            // Update strength indicator
            const percentage = (score / 5) * 100;
            strengthFill.style.width = percentage + '%';
            
            let strengthClass = 'bg-cf-danger';
            let strengthLabel = 'Weak';
            let textClass = 'text-cf-danger';
            
            if (score >= 5) {
                strengthClass = 'bg-cf-success';
                strengthLabel = 'Strong';
                textClass = 'text-cf-success';
            } else if (score >= 3) {
                strengthClass = 'bg-cf-warning';
                strengthLabel = 'Medium';
                textClass = 'text-cf-warning';
            }
            
            strengthFill.className = 'h-full transition-all duration-300 rounded-full ' + strengthClass;
            
            if (score >= 5) {
                strengthText.textContent = 'Strong password!';
                strengthText.className = 'text-xs mt-1 font-medium ' + textClass;
            } else {
                strengthText.textContent = 'Missing: ' + feedback.join(', ');
                strengthText.className = 'text-xs mt-1 font-medium ' + textClass;
            }
        }
        
        // Helper validation functions
        function isValidEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
        
        function isValidUsername(username) {
            const regex = /^[a-zA-Z0-9_]{3,20}$/;
            return regex.test(username);
        }
        
        function isValidName(name) {
            return !name || /^[a-zA-Z\s\-'\.]{2,50}$/.test(name);
        }
        
        function isValidReferralCode(code) {
            const regex = /^[A-Z0-9]{8}$/;
            return regex.test(code.toUpperCase());
        }
        
        function isStrongPassword(password) {
            return password.length >= 8 &&
                   /[A-Z]/.test(password) &&
                   /[a-z]/.test(password) &&
                   /[0-9]/.test(password) &&
                   /[^a-zA-Z0-9]/.test(password);
        }
        
        function showAlert(message, type) {
            const existingAlert = document.querySelector('.alert-dynamic');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            const alert = document.createElement('div');
            const colorClasses = {
                success: 'bg-cf-success/10 text-cf-success border-cf-success/20',
                error: 'bg-cf-danger/10 text-cf-danger border-cf-danger/20',
                info: 'bg-cf-info/10 text-cf-info border-cf-info/20'
            };
            
            alert.className = `alert-dynamic p-4 rounded-xl border ${colorClasses[type] || colorClasses.info} mb-6`;
            alert.textContent = message;
            
            const form = document.getElementById('register-form');
            form.parentNode.insertBefore(alert, form);
            
            // Auto-hide success alerts
            if (type === 'success') {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        }
        
        function showFieldError(fieldName, message) {
            const errorElement = document.getElementById(fieldName + '-error');
            const inputElement = document.getElementById(fieldName);
            
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.remove('hidden');
            }
            
            if (inputElement) {
                inputElement.classList.add('border-cf-danger');
                inputElement.classList.remove('border-gray-200', 'dark:border-slate-500');
            }
        }
        
        function clearFieldError(fieldName) {
            const errorElement = document.getElementById(fieldName + '-error');
            const inputElement = document.getElementById(fieldName);
            
            if (errorElement) {
                errorElement.classList.add('hidden');
            }
            
            if (inputElement) {
                inputElement.classList.remove('border-cf-danger');
                inputElement.classList.add('border-gray-200', 'dark:border-slate-500');
            }
        }
        
        function clearFormErrors() {
            const errors = document.querySelectorAll('[id$="-error"]');
            errors.forEach(error => error.classList.add('hidden'));
            
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.classList.remove('border-cf-danger');
                input.classList.add('border-gray-200', 'dark:border-slate-500');
            });
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
    </script>
</body>
</html>