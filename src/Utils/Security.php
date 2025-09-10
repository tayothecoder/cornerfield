<?php
namespace App\Utils;

class Security {
    public static function hashPassword($password) {
        // Use PASSWORD_DEFAULT for better compatibility
        // This will use bcrypt by default, which is widely supported
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    public static function generateReferralCode($length = 8) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validatePassword($password) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
    
    public static function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', \App\Config\Config::getEncryptionKey(), 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public static function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', \App\Config\Config::getEncryptionKey(), 0, $iv);
    }
    
    public static function rateLimitCheck($identifier, $action, $maxAttempts = 5, $timeWindow = 900) {
        // For now, return true - we'll implement this later
        return true;
    }
    
    public static function logAudit($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        // For now, just log to PHP error log
        error_log("AUDIT: User $userId performed $action on $tableName:$recordId");
    }
}
?>