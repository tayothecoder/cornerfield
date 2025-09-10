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
    echo 'Unauthorized';
    exit;
}

// Get deposit ID from request
$depositId = $_GET['id'] ?? null;

if (!$depositId) {
    http_response_code(400);
    echo 'Deposit ID required';
    exit;
}

try {
    // Get deposit data with user and transaction info
    $deposit = $database->fetchOne("
        SELECT d.*, u.username, u.email, u.first_name, u.last_name, t.amount as transaction_amount, t.payment_method as transaction_payment_method
        FROM deposits d
        JOIN users u ON d.user_id = u.id
        JOIN transactions t ON d.transaction_id = t.id
        WHERE d.id = ?
    ", [$depositId]);

    if (!$deposit) {
        http_response_code(404);
        echo 'Deposit not found';
        exit;
    }

    // Format the HTML response
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Deposit Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Deposit ID:</strong></td><td>#<?= $deposit['id'] ?></td></tr>
                <tr><td><strong>Amount:</strong></td><td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($deposit['requested_amount'], 2) ?></td></tr>
                <tr><td><strong>Payment Method:</strong></td><td><?= htmlspecialchars($deposit['payment_method']) ?></td></tr>
                <tr><td><strong>Status:</strong></td><td>
                    <span class="badge bg-<?= $deposit['status'] === 'completed' ? 'success' : ($deposit['status'] === 'pending' ? 'warning' : 'danger') ?>">
                        <?= ucfirst($deposit['status']) ?>
                    </span>
                </td></tr>
                <tr><td><strong>Verification:</strong></td><td>
                    <span class="badge bg-<?= $deposit['verification_status'] === 'verified' ? 'success' : ($deposit['verification_status'] === 'pending' ? 'warning' : 'danger') ?>">
                        <?= ucfirst($deposit['verification_status']) ?>
                    </span>
                </td></tr>
                <tr><td><strong>Created:</strong></td><td><?= date('M j, Y g:i A', strtotime($deposit['created_at'])) ?></td></tr>
                <?php if ($deposit['processed_at']): ?>
                <tr><td><strong>Processed:</strong></td><td><?= date('M j, Y g:i A', strtotime($deposit['processed_at'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-muted mb-3">User Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Username:</strong></td><td><?= htmlspecialchars($deposit['username']) ?></td></tr>
                <tr><td><strong>Email:</strong></td><td><?= htmlspecialchars($deposit['email']) ?></td></tr>
                <tr><td><strong>Full Name:</strong></td><td><?= htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']) ?></td></tr>
                <tr><td><strong>User ID:</strong></td><td>#<?= $deposit['user_id'] ?></td></tr>
            </table>
        </div>
    </div>
    
    <?php if ($deposit['admin_notes']): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-muted mb-2">Admin Notes</h6>
            <div class="alert alert-info">
                <?= htmlspecialchars($deposit['admin_notes']) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($deposit['status'] === 'pending'): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button class="btn btn-success" onclick="approveDeposit(<?= $deposit['id'] ?>)">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
                <button class="btn btn-danger" onclick="rejectDeposit(<?= $deposit['id'] ?>)">
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
