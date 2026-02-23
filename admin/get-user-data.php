<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

\App\Utils\SessionManager::start();

// Check admin authentication
if (!\App\Utils\SessionManager::get('admin_logged_in') || !\App\Utils\SessionManager::get('admin_id')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

$userId = (int)$_GET['user_id'];
$database = new \App\Config\Database();
$userManagement = new \App\Models\UserManagement($database);

// Get user details
$user = $userManagement->getUserById($userId);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Return user data
echo json_encode([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'country' => $user['country'],
        'balance' => $user['balance'],
        'is_active' => $user['is_active'],
        'email_verified' => $user['email_verified']
    ]
]);