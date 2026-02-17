<?php
// CSRF Protection Utility for Admin Panel

class CSRFProtection {
    
    /**
     * Generate a CSRF token
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify a CSRF token
     */
    public static function verifyToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token for forms
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate POST request with CSRF token
     */
    public static function validateRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!self::verifyToken($token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
    
    /**
     * Refresh CSRF token
     */
    public static function refreshToken() {
        unset($_SESSION['csrf_token']);
        return self::generateToken();
    }
}
