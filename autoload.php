<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: autoload.php
 * Purpose: Proper PSR-4-style autoloader for App namespace mapping to src/
 * Security Level: SYSTEM_ONLY
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

/**
 * PSR-4 Autoloader for Cornerfield Platform
 * 
 * This autoloader maps the App\ namespace to the src/ directory
 * following PSR-4 standards for proper class loading.
 */
spl_autoload_register(function (string $className): void {
    // Only handle App namespace
    if (strpos($className, 'App\\') !== 0) {
        return;
    }
    
    // Remove App\ prefix
    $className = substr($className, 4);
    
    // Convert namespace separators to directory separators
    $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    
    // Build full path
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $fileName;
    
    // Load if exists and is readable
    if (file_exists($filePath) && is_readable($filePath)) {
        require_once $filePath;
        return;
    }
    
    // Log missing class for debugging (only in debug mode)
    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        error_log("Autoloader: Could not load class {$className}. Expected file: {$filePath}");
    }
});

/**
 * Additional autoloader for legacy classes (if needed)
 * This can be removed once all legacy code is refactored
 */
spl_autoload_register(function (string $className): void {
    // Handle any legacy classes not in App namespace
    if (strpos($className, 'App\\') === 0) {
        return; // Already handled by main autoloader
    }
    
    // Legacy class mappings (if any exist)
    $legacyMappings = [
        // Add legacy class mappings here if needed
        // 'OldClassName' => 'path/to/OldClass.php'
    ];
    
    if (isset($legacyMappings[$className])) {
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $legacyMappings[$className];
        if (file_exists($filePath) && is_readable($filePath)) {
            require_once $filePath;
        }
    }
});

/**
 * Namespace to directory mapping examples:
 * 
 * App\Models\UserModel       -> src/Models/UserModel.php
 * App\Controllers\UserController -> src/Controllers/UserController.php  
 * App\Services\EmailService -> src/Services/EmailService.php
 * App\Utils\Security         -> src/Utils/Security.php
 * App\Config\Database        -> src/Config/Database.php
 * App\Middleware\AuthMiddleware -> src/Middleware/AuthMiddleware.php
 */