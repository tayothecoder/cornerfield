<?php
require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

use App\Utils\SessionManager;
use App\Config\Database;
use App\Models\UserManagement;

SessionManager::start();

// Check if we're currently impersonating
if (!SessionManager::get('is_impersonating')) {
    header('Location: login.php');
    exit;
}

try {
    $database = new Database();
    $userManagement = new UserManagement($database);

    // Stop impersonation using your method
    if ($userManagement->stopImpersonation()) {
        // Redirect back to admin users page
        header('Location: users.php?message=impersonation_ended');
        exit;
    } else {
        // If stop failed, force clear sessions and redirect to login
        SessionManager::destroy();
        header('Location: login.php?error=session_error');
        exit;
    }
} catch (Exception $e) {
    error_log("Stop impersonation error: " . $e->getMessage());
    // Force clear sessions and redirect to login
    SessionManager::destroy();
    header('Location: login.php?error=system_error');
    exit;
}