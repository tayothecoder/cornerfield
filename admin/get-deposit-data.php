<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize session
\App\Utils\SessionManager::start();

// Check if admin is logged in
$database = new \App\Config\Database();
$adminController = new \App\Controllers\AdminController($database);

if (!$adminController->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get deposit ID from request
$depositId = $_GET['id'] ?? null;

if (!$depositId) {
    http_response_code(400);
    echo json_encode(['error' => 'Deposit ID required']);
    exit;
}

try {
    // Get deposit data with transaction info
    $deposit = $database->fetchOne("
        SELECT d.*, t.id as transaction_id, t.amount as transaction_amount
        FROM deposits d
        JOIN transactions t ON d.transaction_id = t.id
        WHERE d.id = ?
    ", [$depositId]);

    if (!$deposit) {
        http_response_code(404);
        echo json_encode(['error' => 'Deposit not found']);
        exit;
    }

    // Return deposit data as JSON
    echo json_encode([
        'deposit_id' => $deposit['id'],
        'transaction_id' => $deposit['transaction_id'],
        'user_id' => $deposit['user_id'],
        'amount' => $deposit['requested_amount'],
        'status' => $deposit['status'],
        'verification_status' => $deposit['verification_status'],
        'payment_method' => $deposit['payment_method'],
        'created_at' => $deposit['created_at']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
