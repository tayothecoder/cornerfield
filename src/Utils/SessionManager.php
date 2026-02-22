<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * simple session manager wrapper for consistent session handling
 */
class SessionManager
{
    /**
     * start session if not already started
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // consistent cookie path across all pages
            $basePath = '/cornerfield';
            if (class_exists('\App\Config\Config')) {
                try { $basePath = \App\Config\Config::getBasePath() ?: '/cornerfield'; } catch (\Throwable $e) {}
            }
            session_set_cookie_params([
                'path' => $basePath,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    /**
     * get a session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * set a session value
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * check if a session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * remove a session key
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * destroy the session completely
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }

    /**
     * regenerate session id for security
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        self::start();
        session_regenerate_id($deleteOld);
    }
}
