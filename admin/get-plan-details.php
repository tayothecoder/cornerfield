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

// Get schema ID from request
$schemaId = $_GET['id'] ?? null;

if (!$schemaId) {
    http_response_code(400);
    echo 'Schema ID required';
    exit;
}

try {
    // Get schema data
    $schema = $database->fetchOne("
        SELECT * FROM investment_schemas WHERE id = ?
    ", [$schemaId]);

    if (!$schema) {
        http_response_code(404);
        echo 'Investment plan not found';
        exit;
    }

    // Get schema statistics
    $stats = $database->fetchOne("
        SELECT 
            COUNT(*) as total_investments,
            SUM(invest_amount) as total_amount,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_investments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_investments
        FROM user_investments 
        WHERE schema_id = ?
    ", [$schemaId]);

    // Format the HTML response
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Plan Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Plan ID:</strong></td><td>#<?= $schema['id'] ?></td></tr>
                <tr><td><strong>Name:</strong></td><td><?= htmlspecialchars($schema['name']) ?></td></tr>
                <tr><td><strong>Description:</strong></td><td><?= htmlspecialchars($schema['description']) ?></td></tr>
                <tr><td><strong>Daily Rate:</strong></td><td><?= $schema['daily_rate'] ?>%</td></tr>
                <tr><td><strong>Duration:</strong></td><td><?= $schema['duration_days'] ?> days</td></tr>
                <tr><td><strong>Min Amount:</strong></td><td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['min_amount'], 2) ?></td></tr>
                <tr><td><strong>Max Amount:</strong></td><td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['max_amount'], 2) ?></td></tr>
                <tr><td><strong>Status:</strong></td><td>
                    <span class="badge bg-<?= $schema['is_active'] ? 'success' : 'danger' ?>">
                        <?= $schema['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td></tr>
                <tr><td><strong>Created:</strong></td><td><?= date('M j, Y g:i A', strtotime($schema['created_at'])) ?></td></tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Investment Statistics</h6>
            <table class="table table-sm">
                <tr><td><strong>Total Investments:</strong></td><td><?= number_format($stats['total_investments']) ?></td></tr>
                <tr><td><strong>Total Amount:</strong></td><td><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($stats['total_amount'], 2) ?></td></tr>
                <tr><td><strong>Active Investments:</strong></td><td><?= number_format($stats['active_investments']) ?></td></tr>
                <tr><td><strong>Completed Investments:</strong></td><td><?= number_format($stats['completed_investments']) ?></td></tr>
            </table>
        </div>
    </div>
    
    <?php if ($schema['is_active']): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button class="btn btn-warning" onclick="editPlan(<?= $schema['id'] ?>)">
                    <i class="fas fa-edit me-2"></i>Edit Plan
                </button>
                <button class="btn btn-danger" onclick="deactivatePlan(<?= $schema['id'] ?>)">
                    <i class="fas fa-times me-2"></i>Deactivate
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button class="btn btn-warning" onclick="editPlan(<?= $schema['id'] ?>)">
                    <i class="fas fa-edit me-2"></i>Edit Plan
                </button>
                <button class="btn btn-success" onclick="activatePlan(<?= $schema['id'] ?>)">
                    <i class="fas fa-check me-2"></i>Activate
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
