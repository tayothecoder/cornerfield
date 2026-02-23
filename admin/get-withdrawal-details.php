<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Initialize session
\App\Utils\SessionManager::start();

// Check if admin is logged in
$database = new \App\Config\Database();
$adminController = new \App\Controllers\AdminController($database);

if (!$adminController->isLoggedIn()) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Get withdrawal ID from request
$withdrawalId = $_GET['id'] ?? null;

if (!$withdrawalId) {
    http_response_code(400);
    echo 'Withdrawal ID required';
    exit;
}

try {
    // Get withdrawal data with user and transaction info
    $withdrawal = $database->fetchOne("
        SELECT w.*, u.username, u.email, u.first_name, u.last_name, t.amount as transaction_amount, t.payment_method as transaction_payment_method
        FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        JOIN transactions t ON w.transaction_id = t.id
        WHERE w.id = ?
    ", [$withdrawalId]);

    if (!$withdrawal) {
        http_response_code(404);
        echo 'Withdrawal not found';
        exit;
    }

    // Format the HTML response
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Withdrawal Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Withdrawal ID:</strong></td><td>#<?= $withdrawal['id'] ?></td></tr>
                <tr><td><strong>Amount:</strong></td><td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($withdrawal['requested_amount'], 2) ?></td></tr>
                <tr><td><strong>Payment Method:</strong></td><td><?= htmlspecialchars($withdrawal['payment_method']) ?></td></tr>
                <tr><td><strong>Status:</strong></td><td>
                    <span class="badge bg-<?= $withdrawal['status'] === 'completed' ? 'success' : ($withdrawal['status'] === 'pending' ? 'warning' : 'danger') ?>">
                        <?= ucfirst($withdrawal['status']) ?>
                    </span>
                </td></tr>
                <tr><td><strong>Created:</strong></td><td><?= date('M j, Y g:i A', strtotime($withdrawal['created_at'])) ?></td></tr>
                <?php if ($withdrawal['processed_at']): ?>
                <tr><td><strong>Processed:</strong></td><td><?= date('M j, Y g:i A', strtotime($withdrawal['processed_at'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-muted mb-3">User Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Username:</strong></td><td><?= htmlspecialchars($withdrawal['username']) ?></td></tr>
                <tr><td><strong>Email:</strong></td><td><?= htmlspecialchars($withdrawal['email']) ?></td></tr>
                <tr><td><strong>Full Name:</strong></td><td><?= htmlspecialchars($withdrawal['first_name'] . ' ' . $withdrawal['last_name']) ?></td></tr>
                <tr><td><strong>User ID:</strong></td><td>#<?= $withdrawal['user_id'] ?></td></tr>
            </table>
        </div>
    </div>
    
    <?php if ($withdrawal['admin_notes']): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-muted mb-2">Admin Notes</h6>
            <div class="alert alert-info">
                <?= htmlspecialchars($withdrawal['admin_notes']) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($withdrawal['status'] === 'pending'): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button class="btn btn-success" onclick="approveWithdrawal(<?= $withdrawal['id'] ?>)">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
                <button class="btn btn-danger" onclick="rejectWithdrawal(<?= $withdrawal['id'] ?>)">
                    <i class="fas fa-times me-2"></i>Reject
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php

} catch (Exception $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
}
