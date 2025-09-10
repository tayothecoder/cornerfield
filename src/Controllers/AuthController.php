<?php
// src/Controllers/AuthController.php - Fixed with Modern Database Pattern

namespace App\Controllers;

use App\Models\User;
use App\Utils\Security;
use App\Utils\SessionManager;
use App\Models\AdminSettings;
use App\Config\Database;
use Exception;

class AuthController
{
    private $userModel;
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
        $this->userModel = new User($database);
    }

    /**
     * User login
     */
    public function login($email, $password)
    {
        try {
            $user = $this->userModel->findByEmail($email);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }

            if (!Security::verifyPassword($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }

            if (!$user['is_active']) {
                return [
                    'success' => false,
                    'message' => 'Account is deactivated'
                ];
            }

            // Start user session
            $this->startUserSession($user);

            return [
                'success' => true,
                'user' => $user,
                'message' => 'Login successful'
            ];

        } catch (Exception $e) {
            error_log("User login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }

    /**
     * Start user session
     */
    private function startUserSession($user)
    {
        SessionManager::start();

        // Store user data in session
        SessionManager::set('user_id', $user['id']);
        SessionManager::set('user_email', $user['email']);
        SessionManager::set('user_username', $user['username']);
        SessionManager::set('user_logged_in', true);
        SessionManager::set('user_login_time', time());

        // Regenerate session ID for security
        SessionManager::regenerateId();
    }

    /**
     * User logout
     */
    public function logout()
    {
        SessionManager::destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        SessionManager::start();

        if (!SessionManager::get('user_logged_in', false)) {
            return false;
        }

        // Check session timeout
        $loginTime = SessionManager::get('user_login_time');
        if ($loginTime) {
            $sessionAge = time() - $loginTime;
            if ($sessionAge > 3600) { // 1 hour session lifetime
                $this->logout();
                return false;
            }
        }

        return true;
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $userId = SessionManager::get('user_id');
        return $this->userModel->findById($userId);
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        try {
            // Validate input
            if (empty($data['email']) || empty($data['password'])) {
                return ['success' => false, 'message' => 'Email and password are required'];
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            if (strlen($data['password']) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters'];
            }

            // Check if user already exists
            if ($this->userModel->emailExists($data['email'])) {
                return ['success' => false, 'message' => 'Email already registered'];
            }

            if (!empty($data['username']) && $this->userModel->usernameExists($data['username'])) {
                return ['success' => false, 'message' => 'Username already taken'];
            }

            // Generate referral code
            $referralCode = $this->userModel->generateReferralCode();

            // Check referral code if provided
            $referrerId = null;
            if (!empty($data['referral_code'])) {
                $referrer = $this->userModel->findByReferralCode($data['referral_code']);
                if ($referrer) {
                    $referrerId = $referrer['id'];
                }
            }

            // Hash password
            $data['password_hash'] = Security::hashPassword($data['password']);
            $data['referral_code'] = $referralCode;
            $data['referred_by'] = $referrerId;

            // Set welcome bonus
            // Get dynamic signup bonus from admin settings
            try {
                $adminSettingsModel = new AdminSettings($this->db);
                $signupBonus = $adminSettingsModel->getSetting('signup_bonus', 0);
                $referralRate = $adminSettingsModel->getSetting('referral_bonus_rate', 5);
            } catch (Exception $e) {
                // Fallback to defaults if settings not available
                $signupBonus = 0;
                $referralRate = 5;
            }

            // Set dynamic welcome bonus
            $data['balance'] = 0;
            $data['bonus_balance'] = 0;

            // Create user
            $userId = $this->userModel->create($data);

            if ($userId) {
                // Create referral relationship if referred
                if ($referrerId) {
                    $this->userModel->createReferral($referrerId, $userId, $referralRate); // Dynamic rate
                }

                return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
            } else {
                return ['success' => false, 'message' => 'Registration failed'];
            }

        } catch (Exception $e) {
            error_log("User registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
            $user = $this->userModel->findById($userId);

            if (!$user) {
                error_log("Change password: User not found - ID: $userId");
                return ['success' => false, 'message' => 'User not found'];
            }

            if (!Security::verifyPassword($currentPassword, $user['password_hash'])) {
                error_log("Change password: Invalid current password for user: $userId");
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }

            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'New password must be at least 6 characters'];
            }

            $newHash = Security::hashPassword($newPassword);
            error_log("Change password: Attempting to update password for user: $userId");

            $result = $this->userModel->updatePassword($userId, $newHash);

            error_log("Change password: Update result: " . ($result ? 'true' : 'false'));

            if ($result) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update password in database'];
            }

        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        try {
            $allowedFields = ['first_name', 'last_name', 'phone', 'country'];
            $updateData = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (empty($updateData)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }

            $result = $this->userModel->update($userId, $updateData);

            if ($result) {
                return ['success' => true, 'message' => 'Profile updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update profile'];
            }

        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
    }
}