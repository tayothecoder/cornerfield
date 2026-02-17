<?php

namespace App\Utils;

use App\Config\Database;
use Exception;

class SecurityManager 
{
    private static $maxLoginAttempts = 5;
    private static $lockoutDuration = 900; // 15 minutes
    private static $sessionTimeout = 3600; // 1 hour
    
    /**
     * Rate limiting for login attempts
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900)
    {
        $cacheKey = "rate_limit_{$identifier}";
        $attempts = self::getCacheValue($cacheKey, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        self::setCacheValue($cacheKey, $attempts + 1, $timeWindow);
        return true;
    }
    
    /**
     * CSRF Token Management
     */
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time() + 3600; // 1 hour expiry
        
        // Clean expired tokens
        foreach ($_SESSION['csrf_tokens'] as $t => $expiry) {
            if ($expiry < time()) {
                unset($_SESSION['csrf_tokens'][$t]);
            }
        }
        
        return $token;
    }
    
    public static function validateCSRFToken($token)
    {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        if ($_SESSION['csrf_tokens'][$token] < time()) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    /**
     * Input Sanitization
     */
    public static function sanitizeInput($input, $type = 'string')
    {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * SQL Injection Prevention
     */
    public static function preventSQLInjection($input)
    {
        $dangerous_patterns = [
            '/(\s|^)(select|insert|update|delete|drop|create|alter|exec|execute|union|script)/i',
            '/[\'";]/',
            '/--/',
            '/\/\*|\*\//',
            '/\bor\b\s+\d+\s*=\s*\d+/i',
            '/\band\b\s+\d+\s*=\s*\d+/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                error_log("SQL Injection attempt detected: " . $input);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * XSS Prevention
     */
    public static function preventXSS($input)
    {
        $dangerous_tags = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>.*?<\/embed>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i'
        ];
        
        foreach ($dangerous_tags as $pattern) {
            if (preg_match($pattern, $input)) {
                error_log("XSS attempt detected: " . substr($input, 0, 200));
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * File Upload Security
     */
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'])
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Invalid file upload'];
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed'];
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return ['success' => false, 'message' => 'File too large (max 5MB)'];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Check file signature
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 
            'png' => 'image/png',
            'pdf' => 'application/pdf'
        ];
        
        if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
            return ['success' => false, 'message' => 'File type mismatch'];
        }
        
        return ['success' => true, 'message' => 'File validated'];
    }
    
    /**
     * IP Address Validation and Blocking
     */
    public static function validateIP($ip = null)
    {
        $ip = $ip ?: self::getRealIP();
        
        // Check against blacklisted IPs
        $blacklistedIPs = self::getBlacklistedIPs();
        if (in_array($ip, $blacklistedIPs)) {
            self::logSecurityEvent('blocked_ip', ['ip' => $ip]);
            return false;
        }
        
        // Check if IP is from suspicious country (optional)
        if (self::isSuspiciousCountry($ip)) {
            self::logSecurityEvent('suspicious_country', ['ip' => $ip]);
            // Don't block, just log for now
        }
        
        return true;
    }
    
    public static function getRealIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Session Security
     */
    public static function secureSession()
    {
        // Regenerate session ID every 30 minutes
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Check session fingerprint
        $fingerprint = self::generateFingerprint();
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $fingerprint;
        } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
            self::logSecurityEvent('session_hijack_attempt', [
                'ip' => self::getRealIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            session_destroy();
            return false;
        }
        
        return true;
    }
    
    public static function generateFingerprint()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEnc = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return hash('sha256', $userAgent . $acceptLang . $acceptEnc);
    }
    
    /**
     * Two-Factor Authentication
     */
    public static function generate2FASecret()
    {
        return self::base32_encode(random_bytes(20));
    }
    
    public static function verify2FACode($secret, $code)
    {
        // Note: You'll need to implement or include a TOTP library
        // For now, return false to indicate 2FA is not fully implemented
        error_log("2FA verification not fully implemented - TOTP library required");
        return false;
    }
    
    /**
     * Encryption/Decryption for sensitive data
     */
    public static function encrypt($data, $key = null)
    {
        // Use a default encryption key if none provided
        $key = $key ?: 'cornerfield_default_key_2024';
        
        // Ensure key is 32 bytes for AES-256
        $key = hash('sha256', $key, true);
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encrypted === false) {
            error_log("Encryption failed: " . openssl_error_string());
            return false;
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    public static function decrypt($data, $key = null)
    {
        // Use a default encryption key if none provided
        $key = $key ?: 'cornerfield_default_key_2024';
        
        // Ensure key is 32 bytes for AES-256
        $key = hash('sha256', $key, true);
        
        try {
            $data = base64_decode($data);
            if ($data === false) {
                return false;
            }
            
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            
            if ($decrypted === false) {
                error_log("Decryption failed: " . openssl_error_string());
                return false;
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Security Logging
     */
    public static function logSecurityEvent($event, $data = [])
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getRealIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];
        
        error_log("SECURITY EVENT: " . json_encode($logData));
        
        // Also save to database security_logs table if it exists
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            // Check if security_logs table exists
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'security_logs'");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO security_logs (event_type, ip_address, user_agent, data, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $event,
                    $logData['ip'],
                    $logData['user_agent'],
                    json_encode($data)
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to log security event to database: " . $e->getMessage());
        }
    }
    
    /**
     * Helper methods
     */
    private static function getCacheValue($key, $default = null)
    {
        // Simple file-based cache for this example
        $cacheFile = sys_get_temp_dir() . '/cornerfield_cache_' . md5($key);
        if (file_exists($cacheFile)) {
            $data = unserialize(file_get_contents($cacheFile));
            if ($data['expires'] > time()) {
                return $data['value'];
            }
            unlink($cacheFile);
        }
        return $default;
    }
    
    private static function setCacheValue($key, $value, $ttl = 3600)
    {
        $cacheFile = sys_get_temp_dir() . '/cornerfield_cache_' . md5($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        file_put_contents($cacheFile, serialize($data));
    }
    
    private static function getBlacklistedIPs()
    {
        return [
            '127.0.0.1', // Example - replace with actual blacklisted IPs
            // Add known malicious IPs here
        ];
    }
    
    private static function isSuspiciousCountry($ip)
    {
        // Implement GeoIP checking if needed
        return false;
    }
    
    /**
     * Base32 encoding for 2FA
     */
    private static function base32_encode($data) 
    {
        $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $v <<= 8;
            $v += ord($data[$i]);
            $vbits += 8;
            
            while ($vbits >= 5) {
                $vbits -= 5;
                $output .= $base32Alphabet[($v >> $vbits) & 31];
            }
        }
        
        if ($vbits > 0) {
            $v <<= (5 - $vbits);
            $output .= $base32Alphabet[$v & 31];
        }
        
        return $output;
    }
}