<?php
declare(strict_types=1);

namespace App\Utils;

use App\Config\Config;
use Exception;

class GlobalSecurity 
{
    /**
     * Initialize all security measures
     */
    public static function init() 
    {
        self::setSecurityHeaders();
        self::startSecureSession();
        self::validateRequest();
    }
    
    /**
     * Set security headers
     */
    private static function setSecurityHeaders()
    {
        // Security Headers
        // header('X-Content-Type-Options: nosniff');
        // header('X-Frame-Options: DENY');
        // header('X-XSS-Protection: 1; mode=block');
        // header('Referrer-Policy: strict-origin-when-cross-origin');
        // header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:;');
        
        // Remove server information
        header_remove('X-Powered-By');
        
        // HTTPS enforcement in production
        if (Config::isProduction()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Start secure session
     */
    private static function startSecureSession()
    {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', Config::isProduction() ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        SessionManager::start();
        
        // Validate session security
        SecurityManager::secureSession();
    }
    
    /**
     * Basic request validation
     */
    private static function validateRequest()
    {
        // Validate IP address
        if (!SecurityManager::validateIP()) {
            self::blockRequest('IP blocked');
        }
        
        // Check for basic attack patterns in URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (!SecurityManager::preventXSS($requestUri) || !SecurityManager::preventSQLInjection($requestUri)) {
            SecurityManager::logSecurityEvent('malicious_url_attempt', [
                'url' => $requestUri
            ]);
            self::blockRequest('Malicious request detected');
        }
    }
    
    /**
     * Block malicious requests
     */
    private static function blockRequest($reason)
    {
        http_response_code(403);
        die('Access Denied: ' . $reason);
    }
    
    /**
     * Validate CSRF for all POST requests
     */
    public static function validatePostRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Rate limiting for POST requests
            $clientIP = SecurityManager::getRealIP();
            if (!SecurityManager::checkRateLimit('post_' . $clientIP, 20, 3600)) {
                http_response_code(429);
                die('Too many requests. Please try again later.');
            }
            
            // CSRF validation (except for AJAX requests with custom header)
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if (!$isAjax) {
                if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    SecurityManager::logSecurityEvent('csrf_token_invalid', [
                        'ip' => $clientIP,
                        'url' => $_SERVER['REQUEST_URI'] ?? ''
                    ]);
                    
                    // Redirect back with error instead of dying
                    $_SESSION['security_error'] = 'Security token invalid. Please try again.';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
        }
    }
    
    /**
     * Generate CSRF token for forms
     */
    public static function getCSRFToken()
    {
        return SecurityManager::generateCSRFToken();
    }
    
    /**
     * Get and clear security error
     */
    public static function getSecurityError()
    {
        $error = $_SESSION['security_error'] ?? null;
        unset($_SESSION['security_error']);
        return $error;
    }
    
    /**
     * Sanitize all form inputs
     */
    public static function sanitizeFormInputs($inputs)
    {
        $sanitized = [];
        foreach ($inputs as $key => $value) {
            if ($key === 'csrf_token') continue;
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeFormInputs($value);
            } else {
                $sanitized[$key] = SecurityManager::sanitizeInput($value);
                
                // Additional validation
                if (!SecurityManager::preventXSS($value) || !SecurityManager::preventSQLInjection($value)) {
                    SecurityManager::logSecurityEvent('malicious_input_detected', [
                        'field' => $key,
                        'value' => substr($value, 0, 100)
                    ]);
                    throw new Exception("Invalid input detected in field: {$key}");
                }
            }
        }
        return $sanitized;
    }
}

// Auto-initialize security when this file is included
GlobalSecurity::init();
?>