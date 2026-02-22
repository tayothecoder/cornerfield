<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/AuthController.php
 * Purpose: Complete authentication controller with security, rate limiting, and session management
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Utils\Security;
use App\Utils\Validator;
use InvalidArgumentException;

class AuthController 
{
    private UserModel $userModel;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
    }

    /**
     * Handle user registration
     * @return void
     */
    public function register(): void 
    {
        // CSRF Protection - accept from POST (form) or X-CSRF-Token header (AJAX)
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
            return;
        }

        // Rate limiting
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!Security::rateLimitCheck($identifier, 'registration', 3, 3600)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Too many registration attempts. Please wait 1 hour.'
            ], 429);
            return;
        }

        // Input validation and sanitization
        $email = Validator::sanitizeString($_POST['email'] ?? '');
        $username = Validator::sanitizeString($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $firstName = Validator::sanitizeString($_POST['first_name'] ?? '');
        $lastName = Validator::sanitizeString($_POST['last_name'] ?? '');
        $referralCode = Validator::sanitizeString($_POST['referral_code'] ?? '');
        $agreeTerms = isset($_POST['agree_terms']) && $_POST['agree_terms'] === '1';

        // Basic validation
        $errors = [];

        if (!Validator::isValidEmail($email)) {
            $errors[] = 'Valid email address is required';
        }

        if (!Validator::isValidUsername($username)) {
            $errors[] = 'Username must be 3-20 characters, alphanumeric and underscores only';
        }

        if (!Validator::isValidPassword($password)) {
            $errors[] = 'Password must be at least 8 characters with mixed case, numbers, and symbols';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }

        if (!empty($firstName) && !Validator::isValidName($firstName)) {
            $errors[] = 'First name contains invalid characters';
        }

        if (!empty($lastName) && !Validator::isValidName($lastName)) {
            $errors[] = 'Last name contains invalid characters';
        }

        if (!$agreeTerms) {
            $errors[] = 'You must agree to the terms and conditions';
        }

        if (!empty($referralCode) && !Validator::isValidReferralCode($referralCode)) {
            $errors[] = 'Invalid referral code format';
        }

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ]);
            return;
        }

        // Create user account
        $userData = [
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'referral_code' => $referralCode
        ];

        $result = $this->userModel->createUser($userData);

        if ($result['success']) {
            // Log successful registration
            Security::logAudit($result['user_id'], 'user_registered', 'users', $result['user_id'], null, [
                'email' => $email,
                'username' => $username,
                'referred_by' => !empty($referralCode),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Account created successfully! You can now log in.',
                'redirect' => \App\Config\Config::getBasePath() . '/login.php?registered=1'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'error' => $result['error'] ?? 'Registration failed'
            ]);
        }
    }

    /**
     * Handle user login
     * @return void
     */
    public function login(): void 
    {
        // CSRF Protection - accept from POST (form) or X-CSRF-Token header (AJAX)
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
            return;
        }

        // Rate limiting by IP address
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!Security::rateLimitCheck($identifier, 'login', 10, 900)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Too many login attempts from this IP. Please wait 15 minutes.'
            ], 429);
            return;
        }

        // Input validation and sanitization
        $emailOrUsername = Validator::sanitizeString($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($emailOrUsername) || empty($password)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Email/username and password are required'
            ]);
            return;
        }

        // Check if account is locked due to too many attempts
        if (Validator::isValidEmail($emailOrUsername)) {
            if ($this->userModel->isLocked($emailOrUsername)) {
                $lockoutMinutes = (int)($_ENV['ACCOUNT_LOCKOUT_DURATION'] ?? 900) / 60;
                $this->jsonResponse([
                    'success' => false,
                    'error' => "Account is temporarily locked due to too many failed login attempts. Please try again in {$lockoutMinutes} minutes."
                ], 423);
                return;
            }
        }

        // Find user by email or username
        $user = null;
        if (Validator::isValidEmail($emailOrUsername)) {
            $user = $this->userModel->findByEmail($emailOrUsername);
        } else {
            $user = $this->userModel->findByUsername($emailOrUsername);
        }

        // If user not found or password incorrect, increment attempts
        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            if ($user) {
                $this->userModel->incrementLoginAttempts($user['email']);
            }

            // Log failed login attempt
            Security::logAudit(0, 'login_failed', 'users', null, null, [
                'email_or_username' => $emailOrUsername,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid email/username or password'
            ]);
            return;
        }

        // Check if account is active
        if (!$user['is_active']) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Account is deactivated. Please contact support.'
            ]);
            return;
        }

        // Reset login attempts on successful login
        $this->userModel->resetLoginAttempts((int)$user['id']);

        // Start authenticated session
        AuthMiddleware::login(
            (int)$user['id'], 
            (bool)$user['is_admin'], 
            $user['is_admin'] ? 'admin' : 'user'
        );

        // Log successful login
        Security::logAudit((int)$user['id'], 'user_login', 'users', (int)$user['id'], null, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Return success response
        $this->jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => \App\Config\Config::getBasePath() . '/users/dashboard.php'
        ]);
    }

    /**
     * Handle user logout
     * @return void
     */
    public function logout(): void 
    {
        $userId = AuthMiddleware::getCurrentUserId();
        
        if ($userId) {
            // Log logout
            Security::logAudit($userId, 'user_logout', 'users', $userId, null, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Destroy session
        AuthMiddleware::logout();

        // Redirect to login page
        header('Location: ' . \App\Config\Config::getBasePath() . '/login.php?logged_out=1');
        exit;
    }

    /**
     * Handle forgot password request
     * @return void
     */
    public function forgotPassword(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
            return;
        }

        // Rate limiting
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!Security::rateLimitCheck($identifier, 'forgot_password', 5, 3600)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Too many password reset requests. Please wait 1 hour.'
            ], 429);
            return;
        }

        $email = Validator::sanitizeString($_POST['email'] ?? '');

        if (!Validator::isValidEmail($email)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Valid email address is required'
            ]);
            return;
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists or not for security
            $this->jsonResponse([
                'success' => true,
                'message' => 'If the email address exists in our system, password reset instructions have been sent.'
            ]);
            return;
        }

        // Generate secure reset token
        $resetToken = Security::generateRandomString(64);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

        try {
            // Update user with reset token
            $this->userModel->update((int)$user['id'], [
                'password_reset_token' => $resetToken,
                'password_reset_expires' => $expiresAt
            ]);

            // Log password reset request
            Security::logAudit((int)$user['id'], 'password_reset_requested', 'users', (int)$user['id'], null, [
                'email' => $email,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // TODO: Send password reset email
            // EmailService::sendPasswordResetEmail($email, $resetToken);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Password reset instructions have been sent to your email address.'
            ]);

        } catch (\Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Unable to process password reset request. Please try again.'
            ]);
        }
    }

    /**
     * Handle password reset with token
     * @return void
     */
    public function resetPassword(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
            return;
        }

        $token = Validator::sanitizeString($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($token) || empty($password) || empty($confirmPassword)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'All fields are required'
            ]);
            return;
        }

        if (!Validator::isValidPassword($password)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Password must be at least 8 characters with mixed case, numbers, and symbols'
            ]);
            return;
        }

        if ($password !== $confirmPassword) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Passwords do not match'
            ]);
            return;
        }

        try {
            // Find user by reset token
            $stmt = $this->userModel->db->prepare(
                "SELECT id, email, password_reset_expires 
                 FROM users 
                 WHERE password_reset_token = ? AND is_active = 1"
            );
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Invalid or expired reset token'
                ]);
                return;
            }

            // Check if token is expired
            if (strtotime($user['password_reset_expires']) < time()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Reset token has expired. Please request a new one.'
                ]);
                return;
            }

            // Update password and clear reset token
            $result = $this->userModel->updatePassword((int)$user['id'], $password);

            if ($result['success']) {
                // Clear reset token
                $this->userModel->update((int)$user['id'], [
                    'password_reset_token' => null,
                    'password_reset_expires' => null
                ]);

                // Log password reset completion
                Security::logAudit((int)$user['id'], 'password_reset_completed', 'users', (int)$user['id'], null, [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Password has been reset successfully. You can now log in.',
                    'redirect' => \App\Config\Config::getBasePath() . '/login.php?reset=1'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Failed to reset password. Please try again.'
                ]);
            }

        } catch (\Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Unable to reset password. Please try again.'
            ]);
        }
    }

    /**
     * Check if user is authenticated (for AJAX requests)
     * @return void
     */
    public function checkAuth(): void 
    {
        $isAuthenticated = AuthMiddleware::check();
        $userId = AuthMiddleware::getCurrentUserId();
        $userRole = AuthMiddleware::getCurrentUserRole();

        $this->jsonResponse([
            'authenticated' => $isAuthenticated,
            'user_id' => $userId,
            'role' => $userRole,
            'is_admin' => AuthMiddleware::isAdmin()
        ]);
    }

    /**
     * Get current user info
     * @return void
     */
    public function getCurrentUser(): void 
    {
        if (!AuthMiddleware::check()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Not authenticated'
            ], 401);
            return;
        }

        $userId = AuthMiddleware::getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'User not found'
            ], 404);
            return;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'User not found'
            ], 404);
            return;
        }

        // Remove sensitive data before sending
        unset($user['password_hash']);
        unset($user['password_reset_token']);
        unset($user['email_verification_token']);
        unset($user['two_factor_secret']);

        $this->jsonResponse([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Update user profile
     * @return void
     */
    public function updateProfile(): void 
    {
        // Check authentication
        if (!AuthMiddleware::check()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
            return;
        }

        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
            return;
        }

        $userId = AuthMiddleware::getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'User not found'
            ], 404);
            return;
        }

        $profileData = [
            'first_name' => Validator::sanitizeString($_POST['first_name'] ?? ''),
            'last_name' => Validator::sanitizeString($_POST['last_name'] ?? ''),
            'phone' => Validator::sanitizeString($_POST['phone'] ?? ''),
            'country' => Validator::sanitizeString($_POST['country'] ?? '')
        ];

        $result = $this->userModel->updateProfile($userId, $profileData);

        $this->jsonResponse($result);
    }

    /**
     * Change user password
     * @return void
     */
    public function changePassword(): void 
    {
        // Check authentication
        if (!AuthMiddleware::check()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
            return;
        }

        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
            return;
        }

        $userId = AuthMiddleware::getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'User not found'
            ], 404);
            return;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'All password fields are required'
            ]);
            return;
        }

        // Get current user to verify password
        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'User not found'
            ], 404);
            return;
        }

        // Verify current password
        if (!Security::verifyPassword($currentPassword, $user['password_hash'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Current password is incorrect'
            ]);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'New passwords do not match'
            ]);
            return;
        }

        $result = $this->userModel->updatePassword($userId, $newPassword);

        if ($result['success']) {
            // Force re-authentication for security
            AuthMiddleware::logout();
            $result['redirect'] = \App\Config\Config::getBasePath() . '/login.php?password_changed=1';
        }

        $this->jsonResponse($result);
    }

    /**
     * Send JSON response with proper headers
     * @param array $data
     * @param int $httpCode
     * @return void
     */
    private function jsonResponse(array $data, int $httpCode = 200): void 
    {
        // Set security headers
        Security::setSecurityHeaders();

        // Set JSON response headers
        header('Content-Type: application/json');
        http_response_code($httpCode);

        echo json_encode($data);
        exit;
    }
}