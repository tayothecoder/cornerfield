<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Middleware/AuthMiddleware.php
 * Purpose: User authentication check middleware
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Middleware;

use App\Utils\JsonResponse;
use App\Utils\Security;

class AuthMiddleware 
{
    /**
     * Check if user is authenticated
     * @param bool $requireAdmin Whether admin access is required
     * @return bool True if authenticated, false otherwise
     */
    public static function check(bool $requireAdmin = false): bool 
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is authenticated
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return false;
        }
        
        // Check if user ID exists
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return false;
        }
        
        // Check session timeout
        $sessionTimeout = (int)($_ENV['SESSION_LIFETIME'] ?? 3600);
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $sessionTimeout) {
                self::destroySession();
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Validate session fingerprint
        if (!Security::validateSessionFingerprint()) {
            self::destroySession();
            return false;
        }
        
        // Check admin requirement
        if ($requireAdmin) {
            if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
                return false;
            }
        }
        
        // Regenerate session ID periodically (every 5 minutes)
        if (!isset($_SESSION['last_regeneration']) || 
            time() - $_SESSION['last_regeneration'] > 300) {
            Security::regenerateSessionId();
            $_SESSION['last_regeneration'] = time();
        }
        
        return true;
    }
    
    /**
     * Require authentication (redirect/JSON response if not authenticated)
     * @param bool $requireAdmin Whether admin access is required
     * @param string $redirectUrl Where to redirect if not authenticated
     */
    public static function require(bool $requireAdmin = false, string $redirectUrl = '/login.php'): void 
    {
        if (!self::check($requireAdmin)) {
            if (self::isApiRequest()) {
                if ($requireAdmin) {
                    JsonResponse::forbidden('Admin access required');
                } else {
                    JsonResponse::unauthorized('Authentication required');
                }
            } else {
                // Redirect to login
                header("Location: $redirectUrl");
                exit;
            }
        }
    }
    
    /**
     * Get current authenticated user ID
     * @return int|null User ID or null if not authenticated
     */
    public static function getCurrentUserId(): ?int 
    {
        if (self::check()) {
            return (int)$_SESSION['user_id'];
        }
        
        return null;
    }
    
    /**
     * Get current authenticated user role
     * @return string|null User role or null if not authenticated
     */
    public static function getCurrentUserRole(): ?string 
    {
        if (self::check()) {
            return $_SESSION['user_role'] ?? 'user';
        }
        
        return null;
    }
    
    /**
     * Check if current user is admin
     * @return bool
     */
    public static function isAdmin(): bool 
    {
        return self::check() && 
               (($_SESSION['is_admin'] ?? false) || 
                ($_SESSION['user_role'] ?? '') === 'admin');
    }
    
    /**
     * Authenticate user and create session
     * @param int $userId User ID
     * @param bool $isAdmin Whether user is admin
     * @param string $role User role
     */
    public static function login(int $userId, bool $isAdmin = false, string $role = 'user'): void 
    {
        // Start fresh session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        } else {
            session_start();
        }
        
        // Set session data
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['is_admin'] = $isAdmin;
        $_SESSION['user_role'] = $role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regeneration'] = time();
        $_SESSION['fingerprint'] = Security::getSessionFingerprint();
        
        // Generate new CSRF token
        Security::generateCsrfToken();
        
        // Log successful login
        Security::logAudit($userId, 'user_login', 'users', $userId, null, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    /**
     * Log out user and destroy session
     */
    public static function logout(): void 
    {
        $userId = self::getCurrentUserId();
        
        if ($userId) {
            // Log logout
            Security::logAudit($userId, 'user_logout', 'users', $userId, null, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
        
        self::destroySession();
    }
    
    /**
     * Destroy session data
     */
    private static function destroySession(): void 
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Clear session data
            $_SESSION = [];
            
            // Delete session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            
            // Destroy session
            session_destroy();
        }
    }
    
    /**
     * Check if request is for API
     * @return bool
     */
    private static function isApiRequest(): bool 
    {
        // Check if request is for API endpoint
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            return true;
        }
        
        // Check if client expects JSON
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($acceptHeader, 'application/json') !== false) {
            return true;
        }
        
        // Check for AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Admin impersonation (for support purposes)
     * @param int $targetUserId User ID to impersonate
     * @param int $adminId Admin performing impersonation
     * @return bool Success status
     */
    public static function startImpersonation(int $targetUserId, int $adminId): bool 
    {
        if (!self::isAdmin()) {
            return false;
        }
        
        // Store original admin session
        $_SESSION['original_admin_id'] = self::getCurrentUserId();
        $_SESSION['original_admin_role'] = self::getCurrentUserRole();
        $_SESSION['impersonating'] = true;
        $_SESSION['impersonation_start'] = time();
        
        // Switch to target user
        $_SESSION['user_id'] = $targetUserId;
        $_SESSION['is_admin'] = false;
        $_SESSION['user_role'] = 'user';
        
        // Log impersonation start
        Security::logAudit($adminId, 'admin_impersonation', 'users', $targetUserId, null, [
            'admin_id' => $adminId,
            'target_user_id' => $targetUserId,
            'action' => 'started'
        ]);
        
        return true;
    }
    
    /**
     * Stop admin impersonation
     * @return bool Success status
     */
    public static function stopImpersonation(): bool 
    {
        if (!isset($_SESSION['impersonating']) || !$_SESSION['impersonating']) {
            return false;
        }
        
        $targetUserId = self::getCurrentUserId();
        $adminId = $_SESSION['original_admin_id'] ?? 0;
        
        // Restore original admin session
        $_SESSION['user_id'] = $_SESSION['original_admin_id'];
        $_SESSION['is_admin'] = true;
        $_SESSION['user_role'] = $_SESSION['original_admin_role'];
        
        // Clear impersonation data
        unset($_SESSION['original_admin_id']);
        unset($_SESSION['original_admin_role']);
        unset($_SESSION['impersonating']);
        unset($_SESSION['impersonation_start']);
        
        // Log impersonation end
        Security::logAudit($adminId, 'admin_impersonation', 'users', $targetUserId, null, [
            'admin_id' => $adminId,
            'target_user_id' => $targetUserId,
            'action' => 'stopped'
        ]);
        
        return true;
    }
    
    /**
     * Check if currently impersonating
     * @return bool
     */
    public static function isImpersonating(): bool 
    {
        return isset($_SESSION['impersonating']) && $_SESSION['impersonating'];
    }
    
    /**
     * Get impersonation info
     * @return array|null
     */
    public static function getImpersonationInfo(): ?array 
    {
        if (!self::isImpersonating()) {
            return null;
        }
        
        return [
            'original_admin_id' => $_SESSION['original_admin_id'] ?? null,
            'target_user_id' => self::getCurrentUserId(),
            'start_time' => $_SESSION['impersonation_start'] ?? null,
            'duration' => time() - ($_SESSION['impersonation_start'] ?? 0)
        ];
    }
}