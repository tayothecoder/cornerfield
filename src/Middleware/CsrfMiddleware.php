<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Middleware/CsrfMiddleware.php
 * Purpose: CSRF protection middleware
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Middleware;

use App\Utils\JsonResponse;
use App\Utils\Security;

class CsrfMiddleware 
{
    /**
     * Methods that require CSRF protection
     */
    private static array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    
    /**
     * Routes that should be exempt from CSRF protection
     * (e.g., webhook endpoints that need to receive external requests)
     */
    private static array $exemptRoutes = [
        '/webhook/',
        '/api/webhook/',
        '/ipn/',
        '/callback/'
    ];
    
    /**
     * Validate CSRF token for state-changing requests
     * @param bool $isApiRequest Whether this is an API request
     * @return bool True if validation passes, false otherwise
     */
    public static function validate(bool $isApiRequest = null): bool 
    {
        // Auto-detect if this is an API request
        if ($isApiRequest === null) {
            $isApiRequest = self::isApiRequest();
        }
        
        // Only protect state-changing methods
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, self::$protectedMethods)) {
            return true;
        }
        
        // Check if route is exempt
        if (self::isExemptRoute()) {
            return true;
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get token from request
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            if ($isApiRequest) {
                JsonResponse::error('CSRF token missing', 403);
            } else {
                self::handleCsrfFailure('CSRF token missing');
            }
            return false;
        }
        
        // Validate token
        if (!Security::validateCsrfToken($token)) {
            if ($isApiRequest) {
                JsonResponse::error('Invalid CSRF token', 403);
            } else {
                self::handleCsrfFailure('Invalid CSRF token');
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Require CSRF validation (will exit on failure)
     * @param bool $isApiRequest Whether this is an API request
     */
    public static function require(bool $isApiRequest = null): void 
    {
        self::validate($isApiRequest);
    }
    
    /**
     * Generate and output CSRF token for forms
     * @return string HTML input field with CSRF token
     */
    public static function getTokenInput(): string 
    {
        return Security::getCsrfTokenInput();
    }
    
    /**
     * Get CSRF token value (for AJAX requests)
     * @return string CSRF token
     */
    public static function getToken(): string 
    {
        return Security::generateCsrfToken();
    }
    
    /**
     * Generate meta tag for CSRF token (for page headers)
     * @return string HTML meta tag
     */
    public static function getTokenMeta(): string 
    {
        $token = Security::generateCsrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Validate CSRF for specific financial operations
     * @param string $operation Operation type (investment, withdrawal, etc.)
     * @return bool
     */
    public static function validateFinancialOperation(string $operation): bool 
    {
        if (!self::validate(true)) {
            return false;
        }
        
        // Additional validation for financial operations
        $userId = $_SESSION['user_id'] ?? 0;
        
        // Log financial operation attempt
        Security::logAudit($userId, 'csrf_financial_operation', null, null, null, [
            'operation' => $operation,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return true;
    }
    
    /**
     * Get CSRF token from request (POST, headers, etc.)
     * @return string|null
     */
    private static function getTokenFromRequest(): ?string 
    {
        // Check POST data
        if (isset($_POST['csrf_token'])) {
            return (string)$_POST['csrf_token'];
        }
        
        // Check JSON body for API requests
        if (self::isApiRequest()) {
            $input = file_get_contents('php://input');
            if ($input) {
                $data = json_decode($input, true);
                if (isset($data['csrf_token'])) {
                    return (string)$data['csrf_token'];
                }
            }
        }
        
        // Check custom headers
        $headers = [
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X_XSRF_TOKEN',
            'HTTP_CSRF_TOKEN'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                return (string)$_SERVER[$header];
            }
        }
        
        return null;
    }
    
    /**
     * Check if current route is exempt from CSRF protection
     * @return bool
     */
    private static function isExemptRoute(): bool 
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach (self::$exemptRoutes as $exemptRoute) {
            if (strpos($requestUri, $exemptRoute) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if this is an API request
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
        
        // Check content type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle CSRF validation failure for non-API requests
     * @param string $reason Failure reason
     */
    private static function handleCsrfFailure(string $reason): void 
    {
        // Log the attempt
        $userId = $_SESSION['user_id'] ?? 0;
        Security::logAudit($userId, 'csrf_validation_failed', null, null, null, [
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
        ]);
        
        // Send error response
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Security Error - Cornerfield</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 50px; text-align: center; }
                .error-container { max-width: 600px; margin: 0 auto; }
                h1 { color: #dc3545; }
                .error-code { color: #6c757d; font-size: 1.2em; }
                .actions { margin-top: 30px; }
                .btn { 
                    padding: 10px 20px; 
                    margin: 5px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    display: inline-block; 
                }
                .btn-primary { background: #007bff; color: white; }
                .btn-secondary { background: #6c757d; color: white; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>Security Verification Failed</h1>
                <div class="error-code">Error 403 - Forbidden</div>
                <p>Your request could not be processed due to a security verification failure. This could be caused by:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Expired session or security token</li>
                    <li>Browser security settings</li>
                    <li>Multiple tabs or windows open</li>
                </ul>
                <p><strong>Please try again or contact support if the problem persists.</strong></p>
                <div class="actions">
                    <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                    <a href="/" class="btn btn-primary">Go Home</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Add CSRF token to form action URLs (for GET forms)
     * @param string $url Base URL
     * @return string URL with CSRF token parameter
     */
    public static function addTokenToUrl(string $url): string 
    {
        $token = Security::generateCsrfToken();
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'csrf_token=' . urlencode($token);
    }
    
    /**
     * Middleware for double-submit cookie pattern (additional security)
     * @return bool
     */
    public static function validateDoubleSubmit(): bool 
    {
        // Get token from session
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        // Get token from cookie
        $cookieToken = $_COOKIE['csrf_token'] ?? '';
        
        // Both must exist and match
        if (empty($sessionToken) || empty($cookieToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $cookieToken);
    }
    
    /**
     * Set CSRF token cookie for double-submit pattern
     */
    public static function setTokenCookie(): void 
    {
        $token = Security::generateCsrfToken();
        
        // Set secure cookie
        setcookie('csrf_token', $token, [
            'expires' => time() + 3600, // 1 hour
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => Security::isHttps(),
            'httponly' => false, // Need to be accessible by JavaScript
            'samesite' => 'Strict'
        ]);
    }
}