<?php
/**
 * Simple Autoloader for Cornerfield
 * This resolves the "Undefined type" linter warnings
 */

// Include IDE helper for better type hinting
if (file_exists(__DIR__ . '/.ide-helper.php')) {
    require_once __DIR__ . '/.ide-helper.php';
}

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    
    // Check if the class uses our namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Also autoload non-namespaced classes
spl_autoload_register(function ($class) {
    // Handle classes without namespace (like Database, Config, etc.)
    $file = __DIR__ . '/src/Config/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
    
    $file = __DIR__ . '/src/Utils/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
