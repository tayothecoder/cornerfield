<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Utils\SessionManager;
use App\Config\Database;
use App\Models\UserManagement;

SessionManager::start();

// Check admin authentication
if (!SessionManager::get('admin_logged_in') || !SessionManager::get('admin_id')) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['user_id'])) {
    header('Location: users.php?error=missing_user_id');
    exit;
}

$userId = (int)$_GET['user_id'];
$adminId = SessionManager::get('admin_id');

// Debug logging
error_log("Impersonation attempt - Admin ID: $adminId, User ID: $userId");

try {
    $database = new Database();
    $userManagement = new UserManagement($database);
    
    // Check if trying to impersonate admin's own user account
    if ($userId == 2 && $adminId == 1) {
        error_log("Preventing admin from impersonating their own user account");
        header('Location: users.php?error=cannot_impersonate_self');
        exit;
    }
    
    // Check if user exists and is not an admin
    $user = $userManagement->getUserById($userId);
    if (!$user) {
        error_log("User not found for impersonation: $userId");
        header('Location: users.php?error=user_not_found');
        exit;
    }
    
    // Check if user is actually an admin (prevent impersonating admins)
    $adminCheck = $database->query("SELECT id FROM admins WHERE email = ?", [$user['email']])->fetch();
    if ($adminCheck) {
        error_log("Cannot impersonate admin user: $userId");
        header('Location: users.php?error=cannot_impersonate_admin');
        exit;
    }

    // Start impersonation using your method
    if ($userManagement->startImpersonation($userId, $adminId)) {
        error_log("Impersonation started successfully for user $userId");
        
        // Don't clear admin session until we're sure the redirect will work
        // Just set the impersonation flag
        SessionManager::set('is_impersonating', true);
        SessionManager::set('impersonating_user_id', $userId);
        
        // Redirect to user dashboard with impersonation notice
        header('Location: ../users/dashboard.php?impersonated=1');
        exit;
    } else {
        error_log("Impersonation failed for user $userId");
        header('Location: users.php?error=impersonation_failed');
        exit;
    }
} catch (Exception $e) {
    error_log("Impersonation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Location: users.php?error=system_error');
    exit;
}