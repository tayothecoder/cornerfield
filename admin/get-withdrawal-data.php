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

// Get withdrawal ID from request
$withdrawalId = $_GET['id'] ?? null;

if (!$withdrawalId) {
    http_response_code(400);
    echo json_encode(['error' => 'Withdrawal ID required']);
    exit;
}

try {
    // Get withdrawal data with transaction info
    $withdrawal = $database->fetchOne("
        SELECT w.*, t.id as transaction_id, t.amount as transaction_amount
        FROM withdrawals w
        JOIN transactions t ON w.transaction_id = t.id
        WHERE w.id = ?
    ", [$withdrawalId]);

    if (!$withdrawal) {
        http_response_code(404);
        echo json_encode(['error' => 'Withdrawal not found']);
        exit;
    }

    // Return withdrawal data as JSON
    echo json_encode([
        'withdrawal_id' => $withdrawal['id'],
        'transaction_id' => $withdrawal['transaction_id'],
        'user_id' => $withdrawal['user_id'],
        'amount' => $withdrawal['requested_amount'],
        'status' => $withdrawal['status'],
        'payment_method' => $withdrawal['payment_method'],
        'created_at' => $withdrawal['created_at']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
