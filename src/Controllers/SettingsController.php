<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/SettingsController.php
 * Purpose: User settings and security management controller
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\UserWalletModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;
use PDO;

class SettingsController 
{
    private UserModel $userModel;
    private UserWalletModel $walletModel;
    private PDO $db;
    
    public function __construct() 
    {
        $this->userModel = new UserModel();
        $this->walletModel = new UserWalletModel();
        $this->db = $this->userModel->db;
    }
    
    /**
     * Change user password
     * @return void
     */
    public function changePassword(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Rate limiting
        if (!Security::rateLimitCheck('password_change_' . $userId, 'password_change', 3, 900)) {
            JsonResponse::error('Too many password change attempts. Please wait 15 minutes.', 429);
            return;
        }
        
        // Validate inputs
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) {
            JsonResponse::error('Current password is required');
            return;
        }
        
        if (empty($newPassword)) {
            JsonResponse::error('New password is required');
            return;
        }
        
        if ($newPassword !== $confirmPassword) {
            JsonResponse::error('Password confirmation does not match');
            return;
        }
        
        if (!Validator::isValidPassword($newPassword)) {
            JsonResponse::error('Password must be at least 8 characters with mixed case, numbers, and symbols');
            return;
        }
        
        try {
            // Get current user
            $user = $this->userModel->findById($userId);
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }
            
            // Verify current password
            if (!Security::verifyPassword($currentPassword, $user['password_hash'])) {
                // Log failed attempt
                Security::logAudit($userId, 'password_change_failed', 'users', $userId, null, [
                    'reason' => 'incorrect_current_password',
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                JsonResponse::error('Current password is incorrect');
                return;
            }
            
            // Check if new password is the same as current
            if (Security::verifyPassword($newPassword, $user['password_hash'])) {
                JsonResponse::error('New password must be different from current password');
                return;
            }
            
            // Update password
            $result = $this->userModel->updatePassword($userId, $newPassword);
            
            if ($result['success']) {
                // Log successful password change
                Security::logAudit($userId, 'password_changed', 'users', $userId, null, [
                    'changed_at' => date('Y-m-d H:i:s'),
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                JsonResponse::success(['message' => 'Password changed successfully']);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Password change error for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Password change failed. Please try again.');
        }
    }
    
    /**
     * Get active user sessions
     * @return array
     */
    public function getActiveSessions(): array 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, ip_address, user_agent, last_activity, created_at,
                        CASE WHEN id = ? THEN 1 ELSE 0 END as is_current
                 FROM user_sessions 
                 WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
                 ORDER BY last_activity DESC"
            );
            
            $currentSessionId = session_id();
            $stmt->execute([$currentSessionId, $userId]);
            $sessions = $stmt->fetchAll();
            
            // Parse user agents for better display
            foreach ($sessions as &$session) {
                $session['parsed_agent'] = $this->parseUserAgent($session['user_agent']);
                $session['last_activity_formatted'] = $this->formatDateTime($session['last_activity']);
                $session['created_at_formatted'] = $this->formatDateTime($session['created_at']);
            }
            
            return [
                'success' => true,
                'sessions' => $sessions
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to fetch active sessions for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load sessions'
            ];
        }
    }
    
    /**
     * Revoke a specific user session
     * @param int $sessionId
     * @return void
     */
    public function revokeSession(int $sessionId): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Prevent revoking current session
            $currentSessionId = session_id();
            $stmt = $this->db->prepare(
                "SELECT id FROM user_sessions WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$currentSessionId, $userId]);
            
            if ($stmt->fetch() && $currentSessionId == $sessionId) {
                JsonResponse::error('Cannot revoke current session. Use logout instead.');
                return;
            }
            
            // Revoke the session
            $stmt = $this->db->prepare(
                "UPDATE user_sessions 
                 SET is_active = 0, expires_at = NOW() 
                 WHERE id = ? AND user_id = ?"
            );
            
            $success = $stmt->execute([$sessionId, $userId]);
            
            if ($success && $stmt->rowCount() > 0) {
                // Log session revocation
                Security::logAudit($userId, 'session_revoked', 'user_sessions', $sessionId, null, [
                    'revoked_by' => 'user',
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                JsonResponse::success(['message' => 'Session revoked successfully']);
            } else {
                JsonResponse::error('Session not found or already inactive');
            }
            
        } catch (\Exception $e) {
            error_log("Failed to revoke session for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to revoke session');
        }
    }
    
    /**
     * Get login history
     * @return array
     */
    public function getLoginHistory(): array 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $stmt = $this->db->prepare(
                "SELECT event_type, ip_address, user_agent, created_at, data
                 FROM security_logs 
                 WHERE user_id = ? AND event_type IN ('login_success', 'login_failed', 'logout')
                 ORDER BY created_at DESC 
                 LIMIT 50"
            );
            
            $stmt->execute([$userId]);
            $logs = $stmt->fetchAll();
            
            // Format the data for display
            foreach ($logs as &$log) {
                $log['parsed_agent'] = $this->parseUserAgent($log['user_agent']);
                $log['formatted_time'] = $this->formatDateTime($log['created_at']);
                
                // Parse additional data if available
                if (!empty($log['data'])) {
                    $logData = json_decode($log['data'], true);
                    $log['additional_info'] = $logData;
                }
            }
            
            return [
                'success' => true,
                'login_history' => $logs
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to fetch login history for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load login history'
            ];
        }
    }
    
    /**
     * Update notification preferences
     * @return void
     */
    public function updateNotificationPrefs(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Define available notification preferences
            $validPrefs = [
                'email_deposits',
                'email_withdrawals',
                'email_profits',
                'email_security',
                'email_marketing',
                'sms_security',
                'push_notifications'
            ];
            
            $preferences = [];
            foreach ($validPrefs as $pref) {
                $preferences[$pref] = isset($_POST[$pref]) ? 1 : 0;
            }
            
            // Store preferences in JSON format in user table or separate table
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET notification_preferences = ?, updated_at = NOW() 
                 WHERE id = ?"
            );
            
            $success = $stmt->execute([json_encode($preferences), $userId]);
            
            if ($success) {
                // Log preference update
                Security::logAudit($userId, 'notification_preferences_updated', 'users', $userId, null, $preferences);
                
                JsonResponse::success(['message' => 'Notification preferences updated successfully']);
            } else {
                JsonResponse::error('Failed to update notification preferences');
            }
            
        } catch (\Exception $e) {
            error_log("Failed to update notification preferences for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to update preferences');
        }
    }
    
    /**
     * Get user's notification preferences
     * @return void
     */
    public function getNotificationPrefs(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $stmt = $this->db->prepare(
                "SELECT notification_preferences FROM users WHERE id = ?"
            );
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                JsonResponse::error('User not found');
                return;
            }
            
            $preferences = [];
            if (!empty($user['notification_preferences'])) {
                $preferences = json_decode($user['notification_preferences'], true) ?? [];
            }
            
            // Set defaults if not set
            $defaultPrefs = [
                'email_deposits' => 1,
                'email_withdrawals' => 1,
                'email_profits' => 1,
                'email_security' => 1,
                'email_marketing' => 0,
                'sms_security' => 0,
                'push_notifications' => 1
            ];
            
            $preferences = array_merge($defaultPrefs, $preferences);
            
            JsonResponse::success(['preferences' => $preferences]);
            
        } catch (\Exception $e) {
            error_log("Failed to get notification preferences for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to load preferences');
        }
    }
    
    /**
     * Manage wallets (list)
     * @return array
     */
    public function manageWallets(): array 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            $wallets = $this->walletModel->findByUserId($userId);
            $stats = $this->walletModel->getUserWalletStats($userId);
            $supportedCurrencies = $this->walletModel->getSupportedCurrencies();
            
            return [
                'success' => true,
                'wallets' => $wallets,
                'stats' => $stats,
                'supported_currencies' => $supportedCurrencies
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to load wallets for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load wallets'
            ];
        }
    }
    
    /**
     * Add new wallet
     * @return void
     */
    public function addWallet(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Rate limiting
        if (!Security::rateLimitCheck('wallet_add_' . $userId, 'wallet_add', 5, 3600)) {
            JsonResponse::error('Too many wallet additions. Please wait 1 hour.', 429);
            return;
        }
        
        // Get and validate inputs
        $currency = Validator::sanitizeString($_POST['currency'] ?? '', 20);
        $network = Validator::sanitizeString($_POST['network'] ?? '', 50);
        $address = Validator::sanitizeString($_POST['address'] ?? '', 255);
        
        try {
            $result = $this->walletModel->addWallet($userId, $currency, $network, $address);
            
            if ($result['success']) {
                JsonResponse::success([
                    'message' => $result['message'],
                    'wallet_id' => $result['wallet_id']
                ]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to add wallet for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to add wallet');
        }
    }
    
    /**
     * Remove wallet
     * @return void
     */
    public function removeWallet(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $walletId = Validator::sanitizeInt($_POST['wallet_id'] ?? 0);
        
        if ($walletId <= 0) {
            JsonResponse::error('Invalid wallet ID');
            return;
        }
        
        try {
            $result = $this->walletModel->removeWallet($walletId, $userId);
            
            if ($result['success']) {
                JsonResponse::success(['message' => $result['message']]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to remove wallet for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to remove wallet');
        }
    }
    
    /**
     * Set default wallet
     * @return void
     */
    public function setDefaultWallet(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $walletId = Validator::sanitizeInt($_POST['wallet_id'] ?? 0);
        
        if ($walletId <= 0) {
            JsonResponse::error('Invalid wallet ID');
            return;
        }
        
        try {
            $result = $this->walletModel->setDefault($walletId, $userId);
            
            if ($result['success']) {
                JsonResponse::success(['message' => $result['message']]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to set default wallet for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to set default wallet');
        }
    }
    
    /**
     * Parse user agent string for better display
     * @param string $userAgent
     * @return array
     */
    private function parseUserAgent(string $userAgent): array 
    {
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'Desktop';
        
        // Simple browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        }
        
        // Simple OS detection
        if (strpos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $os = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $os = 'Android';
            $device = 'Mobile';
        } elseif (strpos($userAgent, 'iOS') !== false || strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
            $device = strpos($userAgent, 'iPad') !== false ? 'Tablet' : 'Mobile';
        }
        
        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'full' => $userAgent
        ];
    }
    
    /**
     * Format datetime for display
     * @param string $datetime
     * @return string
     */
    private function formatDateTime(string $datetime): string 
    {
        $date = new \DateTime($datetime);
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->d == 0 && $diff->h < 24) {
            if ($diff->h == 0 && $diff->i < 60) {
                if ($diff->i < 1) {
                    return 'Just now';
                }
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            }
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d < 7) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } else {
            return $date->format('M j, Y g:i A');
        }
    }
}