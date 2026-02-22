<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Utils/JsonResponse.php
 * Purpose: JSON response utilities for API endpoints
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Utils;

class JsonResponse
{
    /**
     * Send success JSON response
     * @param mixed $data
     * @param int $httpCode
     * @return void
     */
    public static function success($data = null, int $httpCode = 200): void
    {
        self::send([
            'success' => true,
            'data' => $data
        ], $httpCode);
    }

    /**
     * Send error JSON response
     * @param string $message
     * @param int $httpCode
     * @return void
     */
    public static function error(string $message, int $httpCode = 400): void
    {
        self::send([
            'success' => false,
            'error' => $message
        ], $httpCode);
    }

    /**
     * Send validation error response with field-level errors
     * @param array $errors associative array of field => message
     * @param int $httpCode
     * @return void
     */
    public static function validationError(array $errors, int $httpCode = 422): void
    {
        self::send([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $errors
        ], $httpCode);
    }

    /**
     * Send unauthorized response
     * @param string $message
     * @return void
     */
    public static function unauthorized(string $message = 'Authentication required'): void
    {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     * @param string $message
     * @return void
     */
    public static function forbidden(string $message = 'Access forbidden'): void
    {
        self::error($message, 403);
    }

    /**
     * Send not found response
     * @param string $message
     * @return void
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Send JSON response with proper headers
     * @param array $data
     * @param int $httpCode
     * @return void
     */
    private static function send(array $data, int $httpCode): void
    {
        // Set security headers
        Security::setSecurityHeaders();

        // Set JSON response headers
        header('Content-Type: application/json');
        http_response_code($httpCode);

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}