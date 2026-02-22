<?php
declare(strict_types=1);
namespace App\Config;

use Exception;

// src/Config/EnvLoader.php - Enhanced with getFloat method

class EnvLoader {
    private static $loaded = false;
    private static $data = [];

    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }

        if (!file_exists($path)) {
            throw new Exception('.env file not found');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^".*"$/', $value) || preg_match("/^'.*'$/", $value)) {
                $value = substr($value, 1, -1);
            }

            self::$data[$key] = $value;
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        self::load();
        return self::$data[$key] ?? $_ENV[$key] ?? $default;
    }

    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }

    public static function getBool($key, $default = false) {
        $value = strtolower(self::get($key, $default));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    public static function getArray($key, $default = []) {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        // Handle JSON format
        if (substr($value, 0, 1) === '[' || substr($value, 0, 1) === '{') {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $default;
        }

        // Handle comma-separated values
        return array_map('trim', explode(',', $value));
    }

    // NEW: Add getFloat method
    public static function getFloat($key, $default = 0.0) {
        return (float) self::get($key, $default);
    }

    // NEW: Add getDouble method (alias for getFloat)
    public static function getDouble($key, $default = 0.0) {
        return self::getFloat($key, $default);
    }

    public static function has($key) {
        self::load();
        return isset(self::$data[$key]) || isset($_ENV[$key]);
    }

    public static function all() {
        self::load();
        return self::$data;
    }
}