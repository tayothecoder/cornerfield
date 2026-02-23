<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Initialize session
\App\Utils\SessionManager::start();

// Page setup
$pageTitle = 'Investment Plans Management - ' . \App\Config\Config::getSiteName();
$currentPage = 'investment-plans';

try {
    $database = new \App\Config\Database();
    $adminController = new \App\Controllers\AdminController($database);
} catch (Exception $e) {
    if (\App\Config\Config::isDebug()) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('System error. Please try again later.');
    }
}

// Check if admin is logged in
if (!$adminController->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentAdmin = $adminController->getCurrentAdmin();
$success = '';
$error = '';

// Handle AJAX and form requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_schema':
            $result = $adminController->createInvestmentSchema($_POST);
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            } else {
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'update_schema':
            $result = $adminController->updateInvestmentSchema($_POST['schema_id'], $_POST);
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            } else {
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'delete_schema':
            $result = $adminController->deleteInvestmentSchema($_POST['schema_id']);
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            } else {
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'get_schema':
            $schemaId = $_POST['schema_id'];
            error_log("Admin get_schema called with ID: " . $schemaId);
            
            $schema = $database->fetchOne("SELECT * FROM investment_schemas WHERE id = ?", [$schemaId]);

            
            if ($schema) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'schema' => $schema]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Schema not found']);
            }
            exit;
    }
}

// Get all investment schemas
$schemas = $database->fetchAll("SELECT * FROM investment_schemas ORDER BY created_at DESC");

// Get schema statistics
$schemaStats = [
    'total_schemas' => count($schemas),
    'active_schemas' => 0,
    'total_investments' => 0,
    'total_invested' => 0
];

foreach ($schemas as $schema) {
    if ($schema['status']) {
        $schemaStats['active_schemas']++;
    }
}

// Get investment statistics
$investmentStats = $database->fetchOne("
    SELECT 
        COUNT(*) as total_investments,
        SUM(invest_amount) as total_invested,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_investments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_investments
    FROM investments
");

$schemaStats['total_investments'] = $investmentStats['total_investments'] ?? 0;
$schemaStats['total_invested'] = $investmentStats['total_invested'] ?? 0;

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Page Content -->
<div class="space-y-6">
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-sm" >
            <div class="flex">
                <div>
                    <i class="fas fa-check-circle mr-2"></i>
                </div>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
            <a class="text-gray-400 hover:text-gray-600 dark:hover:text-white"  aria-label="close"></a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm" >
            <div class="flex">
                <div>
                    <i class="fas fa-exclamation-circle mr-2"></i>
                </div>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <a class="text-gray-400 hover:text-gray-600 dark:hover:text-white"  aria-label="close"></a>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($schemaStats['total_schemas']) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Plans</div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($schemaStats['active_schemas']) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Plans</div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-users"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= number_format($schemaStats['total_investments']) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Investments</div>
        </div>

        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6 shadow-sm">
            <div class="hidden">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schemaStats['total_invested'], 2) ?></div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invested</div>
        </div>
    </div>

    <!-- Investment Plans Table -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Investment Plans</h3>
            <div class="ml-auto">
                <button class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="showModal(this.getAttribute('data-target'))" data-target="modal-create-plan">
                    <i class="fas fa-plus mr-2"></i>
                    Create New Plan
                </button>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Daily Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Min/Max Investment</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schemas)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">
                                    <i class="fas fa-chart-line fa-2x mb-3"></i>
                                    <div>No investment plans found</div>
                                    <div class="mt-2">
                                        <button class="btn btn-primary btn-sm" onclick="showModal(this.getAttribute('data-target'))" data-target="modal-create-plan">
                                            Create First Plan
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schemas as $schema): ?>
                                <tr class="border-b border-gray-100 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($schema['name']) ?></div>
                                        <div class="text-gray-400 dark:text-gray-500 small"><?= htmlspecialchars($schema['description']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="fw-bold text-success"><?= number_format($schema['daily_rate'], 2) ?>%</div>
                                        <div class="text-gray-400 dark:text-gray-500 small">Daily Return</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= $schema['duration_days'] ?> days</div>
                                        <div class="text-gray-400 dark:text-gray-500 small">Investment Period</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['min_amount'], 2) ?></div>
                                        <div class="text-gray-400 dark:text-gray-500 small">Min: <?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['min_amount'], 2) ?> | Max: <?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['max_amount'], 2) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <?php
                                        switch($schema['status']) {
                                            case 1:
                                                $statusClass = 'bg-success';
                                                $statusText = 'Active';
                                                break;
                                            case 0:
                                                $statusClass = 'bg-secondary';
                                                $statusText = 'Inactive';
                                                break;
                                            default:
                                                $statusClass = 'bg-secondary';
                                                $statusText = 'Unknown';
                                                break;
                                        }
                                        ?>
                                        <?php $sc = match($schema['status']) { 1 => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400', default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }; ?>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium inline-block <?= $sc ?>"><?= $statusText ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="text-gray-400 dark:text-gray-500 small">
                                            <?= date('M j, Y', strtotime($schema['created_at'])) ?>
                                        </div>
                                        <div class="text-gray-400 dark:text-gray-500 small">
                                            <?= date('g:i A', strtotime($schema['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="flex flex-wrap gap-2">
                                            <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-xs font-medium rounded-full hover:border-[#1e0e62] transition-colors" onclick="editPlan(<?= $schema['id'] ?>)">
                                                Edit
                                            </button>
                                            <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-xs font-medium rounded-full hover:border-[#1e0e62] transition-colors" onclick="viewPlan(<?= $schema['id'] ?>)">
                                                View
                                            </button>
                                            <button class="px-3 py-1 text-xs font-medium rounded-full text-red-500 hover:text-red-700 transition-colors" onclick="deletePlan(<?= $schema['id'] ?>, '<?= htmlspecialchars($schema['name']) ?>')">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Plan Modal -->
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="modal-create-plan" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Create Investment Plan</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" onclick="closeCreateModal()"></button>
            </div>
            <form method="POST" id="create-plan-form">
                <div class="p-6">
                    <input type="hidden" name="action" value="create_schema">
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Plan Name</label>
                                <input type="text" name="name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Daily Rate (%)</label>
                                <input type="number" name="daily_rate" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" max="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Duration (Days)</label>
                                <input type="number" name="duration_days" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" min="1" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Total Return (%)</label>
                                <input type="number" name="total_return" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" max="1000" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                                <select name="status" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Featured</label>
                                <select name="featured" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Minimum Investment</label>
                                <input type="number" name="min_amount" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Maximum Investment</label>
                                <input type="number" name="max_amount" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label>
                        <textarea name="description" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" rows="3" placeholder="Plan description..."></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                    <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full transition-colors" onclick="closeCreateModal()">Cancel</button>
                    <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="submitCreateForm()">Create Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="modal-edit-plan" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Investment Plan</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" onclick="closeEditModal()"></button>
            </div>
            <form method="POST" id="edit-plan-form">
                <div class="p-6">
                    <input type="hidden" name="action" value="update_schema">
                    <input type="hidden" name="schema_id" id="edit-schema-id">
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Plan Name</label>
                                <input type="text" name="name" id="edit-name" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Daily Rate (%)</label>
                                <input type="number" name="daily_rate" id="edit-daily-rate" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" max="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Duration (Days)</label>
                                <input type="number" name="duration_days" id="edit-duration-days" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" min="1" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Total Return (%)</label>
                                <input type="number" name="total_return" id="edit-total-return" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" max="1000" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                                <select name="status" id="edit-is-active" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Featured</label>
                                <select name="featured" id="edit-featured" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" required>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Minimum Investment</label>
                                <input type="number" name="min_amount" id="edit-min-investment" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="">
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Maximum Investment</label>
                                <input type="number" name="max_amount" id="edit-max-investment" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label>
                        <textarea name="description" id="edit-description" class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" rows="3" placeholder="Plan description..."></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                    <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full transition-colors" onclick="closeEditModal()">Cancel</button>
                    <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="submitEditForm()">Update Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Plan Modal -->
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="modal-view-plan" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Plan Details</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" ></button>
            </div>
            <div class="p-6" id="plan-details-content">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function editPlan(schemaId) {
    console.log('Editing plan with ID:', schemaId);
    
    // Load schema data via AJAX
    fetch('investment-plans.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_schema&schema_id=${schemaId}`
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success && data.schema) {
            const schema = data.schema;
            console.log('Schema data:', schema);
            
            // Populate form fields
            document.getElementById('edit-schema-id').value = schema.id;
            document.getElementById('edit-name').value = schema.name;
            document.getElementById('edit-daily-rate').value = schema.daily_rate;
            document.getElementById('edit-duration-days').value = schema.duration_days;
            document.getElementById('edit-min-investment').value = schema.min_amount;
            document.getElementById('edit-max-investment').value = schema.max_amount;
            document.getElementById('edit-is-active').value = schema.status;
            document.getElementById('edit-description').value = schema.description;
            document.getElementById('edit-total-return').value = schema.total_return;
            document.getElementById('edit-featured').value = schema.featured;
            
            // Show modal
            const modalElement = document.getElementById('modal-edit-plan');
            if (modalElement) {
                // Check if Bootstrap is available (Tabler includes it)
                if (typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } else {
                    // Fallback: show modal manually
                    modalElement.classList.remove('hidden'); modalElement.style.display = 'flex';
                    modalElement.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            } else {
                console.error('Modal element not found');
                alert('Modal not found. Please refresh the page and try again.');
            }
        } else {
            console.error('Failed to load plan data:', data);
            alert('Failed to load plan data. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading plan data. Please try again.');
    });
}

function viewPlan(schemaId) {
    // Load plan details via AJAX from the same page
    fetch('investment-plans.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_schema&schema_id=${schemaId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.schema) {
            const schema = data.schema;
            
            // Create HTML content for the modal
            const html = `
                <div class="grid grid-cols-1 gap-6">
                    <div class="">
                        <h6>Plan Details</h6>
                        <p><strong>Name:</strong> ${schema.name}</p>
                        <p><strong>Daily Rate:</strong> ${schema.daily_rate}%</p>
                        <p><strong>Duration:</strong> ${schema.duration_days} days</p>
                        <p><strong>Total Return:</strong> ${schema.total_return}%</p>
                    </div>
                    <div class="">
                        <h6>Investment Limits</h6>
                        <p><strong>Min Investment:</strong> $${parseFloat(schema.min_amount).toLocaleString()}</p>
                        <p><strong>Max Investment:</strong> $${parseFloat(schema.max_amount).toLocaleString()}</p>
                        <p><strong>Status:</strong> <span class="badge ${schema.status ? 'bg-success' : 'bg-secondary'}">${schema.status ? 'Active' : 'Inactive'}</span></p>
                        <p><strong>Featured:</strong> <span class="badge ${schema.featured ? 'bg-warning' : 'bg-secondary'}">${schema.featured ? 'Yes' : 'No'}</span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="">
                        <h6>Description</h6>
                        <p>${schema.description || 'No description available.'}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('plan-details-content').innerHTML = html;
            
            // Show modal with Bootstrap or fallback
            const modalElement = document.getElementById('modal-view-plan');
            if (modalElement) {
                if (typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } else {
                    // Fallback: show modal manually
                    modalElement.classList.remove('hidden'); modalElement.style.display = 'flex';
                    modalElement.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }
        } else {
            alert('Failed to load plan details. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error loading plan details:', error);
        alert('Error loading plan details. Please try again.');
    });
}

function submitCreateForm() {
    const form = document.getElementById('create-plan-form');
    const formData = new FormData(form);
    
    fetch('investment-plans.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Plan created successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to create plan'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating plan. Please try again.');
    });
}

function submitEditForm() {
    const form = document.getElementById('edit-plan-form');
    const formData = new FormData(form);
    
    fetch('investment-plans.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Plan updated successfully!');
            closeEditModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update plan'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating plan. Please try again.');
    });
}

function closeEditModal() {
    const modalElement = document.getElementById('modal-edit-plan');
    if (modalElement) {
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        } else {
            // Manual close
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Remove backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    }
}

function closeCreateModal() {
    const modalElement = document.getElementById('modal-create-plan');
    if (modalElement) {
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        } else {
            // Manual close
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Remove backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    }
}

function deletePlan(schemaId, planName) {
    if (confirm(`Are you sure you want to delete the plan "${planName}"?\n\nThis action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_schema">
            <input type="hidden" name="schema_id" value="${schemaId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>