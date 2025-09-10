<?php
namespace App\Utils;

class SessionManager {
    private static $started = false;
    
    public static function start(): void {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            // Configure session settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', \App\Config\Config::isProduction() ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', \App\Config\Config::getSessionLifetime());
            
            session_start();
            self::$started = true;
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created_at'])) {
                $_SESSION['created_at'] = time();
            } elseif (time() - $_SESSION['created_at'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created_at'] = time();
            }
        }
    }
    
    public static function isStarted(): bool {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    
    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove(string $key): void {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function destroy(): void {
        if (self::isStarted()) {
            session_unset();
            session_destroy();
            self::$started = false;
        }
    }
    
    public static function regenerateId(): void {
        if (self::isStarted()) {
            session_regenerate_id(true);
        }
    }
    
    public static function flash(string $key, $value = null) {
        if ($value === null) {
            // Get flash message
            $message = self::get("flash_{$key}");
            self::remove("flash_{$key}");
            return $message;
        } else {
            // Set flash message
            self::set("flash_{$key}", $value);
        }
    }
}