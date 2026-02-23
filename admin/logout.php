<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Initialize session
\App\Utils\SessionManager::start();

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
    
    // Get current admin for logging
    $currentAdmin = $adminController->getCurrentAdmin();
    
    if ($currentAdmin) {
        // Log the logout action
        try {
            $database->insert('security_logs', [
                'action' => 'admin_logout',
                'user_id' => $currentAdmin['id'],
                'user_type' => 'admin',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'details' => 'Admin logged out successfully',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log error but don't stop logout process
            error_log("Failed to log admin logout: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    // Continue with logout even if database fails
    error_log("Database error during logout: " . $e->getMessage());
}

// Clear all admin session data
unset($_SESSION['admin_id']);
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);

// Clear any impersonation data
unset($_SESSION['original_admin_id']);
unset($_SESSION['impersonating_user_id']);
unset($_SESSION['is_impersonating']);

// Destroy the session completely
session_destroy();

// Redirect to admin login with success message
header('Location: login.php?message=logged_out');
exit;
