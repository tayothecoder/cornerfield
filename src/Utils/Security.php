<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Utils/Security.php
 * Purpose: Core security utilities - CSRF, sanitization, session security
 * Security Level: SYSTEM_ONLY
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Utils;

use App\Config\Database;
use PDO;

class Security 
{
    private static array $rateLimitStore = [];
    
    /**
     * Generate CSRF token for current session
     * IMPORTANT: Session-based, NOT database stored
     */
    public static function generateCsrfToken(): string 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken(string $token): bool 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Check token age (expire after CSRF_TOKEN_LIFETIME)
        $tokenAge = time() - ($_SESSION['csrf_token_time'] ?? 0);
        $maxAge = (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600);
        
        if ($tokenAge > $maxAge) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token for forms
     */
    public static function getCsrfTokenInput(): string 
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString(mixed $input, int $maxLength = 255): string 
    {
        if (!is_string($input)) {
            return '';
        }
        
        // Remove null bytes and normalize line endings
        $sanitized = str_replace(["\0", "\r"], '', $input);
        
        // Trim whitespace
        $sanitized = trim($sanitized);
        
        // Limit length
        $sanitized = substr($sanitized, 0, $maxLength);
        
        return $sanitized;
    }
    
    /**
     * Sanitize integer input
     */
    public static function sanitizeInt(mixed $input): int 
    {
        return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize float input
     */
    public static function sanitizeFloat(mixed $input): float 
    {
        return (float)filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    /**
     * Output escaping helper
     */
    public static function escape(string $value): string 
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Hash password using Argon2ID (strong security)
     */
    public static function hashPassword(string $password): string 
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64 MB
            'time_cost' => 4,        // 4 iterations
            'threads' => 3           // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool 
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString(int $length = 32): string 
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Rate limiting check
     */
    public static function rateLimitCheck(string $identifier, string $action, int $maxAttempts = 5, int $timeWindow = 900): bool 
    {
        $key = "{$action}:{$identifier}";
        $now = time();
        
        // Initialize if not exists
        if (!isset(self::$rateLimitStore[$key])) {
            self::$rateLimitStore[$key] = [];
        }
        
        // Clean old attempts
        self::$rateLimitStore[$key] = array_filter(
            self::$rateLimitStore[$key], 
            fn($timestamp) => $now - $timestamp < $timeWindow
        );
        
        // Check if limit exceeded
        if (count(self::$rateLimitStore[$key]) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        self::$rateLimitStore[$key][] = $now;
        
        return true;
    }
    
    /**
     * Session security - regenerate session ID
     */
    public static function regenerateSessionId(): void 
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Session fingerprinting for security
     */
    public static function getSessionFingerprint(): string 
    {
        return hash('sha256', 
            $_SERVER['HTTP_USER_AGENT'] . 
            $_SERVER['REMOTE_ADDR'] . 
            ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        );
    }
    
    /**
     * Validate session fingerprint
     */
    public static function validateSessionFingerprint(): bool 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $currentFingerprint = self::getSessionFingerprint();
        
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $currentFingerprint;
            return true;
        }
        
        return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders(): void 
    {
        if (!headers_sent()) {
            // Prevent clickjacking
            header('X-Frame-Options: DENY');
            
            // XSS Protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Content Type Options
            header('X-Content-Type-Options: nosniff');
            
            // Referrer Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'");
            
            // Remove server information
            header_remove('X-Powered-By');
            header_remove('Server');
        }
    }
    
    /**
     * Audit logging for security events
     */
    public static function logAudit(int $userId, string $event, string $tableName = null, int $recordId = null, array $oldValues = null, array $newValues = null): void 
    {
        try {
            $db = Database::getInstance();
            
            $logData = [
                'user_id' => $userId,
                'event' => $event,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            $stmt = $db->prepare(
                "INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, data, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([
                $userId,
                $event,
                $logData['ip_address'],
                $logData['user_agent'],
                json_encode($logData)
            ]);
            
        } catch (\Exception $e) {
            // Don't let audit logging break the application
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check if request is over HTTPS
     */
    public static function isHttps(): bool 
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * Force HTTPS redirect
     */
    public static function forceHttps(): void 
    {
        if (!self::isHttps() && !isset($_ENV['APP_DEBUG'])) {
            $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectUrl", true, 301);
            exit();
        }
    }
}