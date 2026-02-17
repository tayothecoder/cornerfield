<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

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
            error_log("Schema data: " . print_r($schema, true));
            
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
<div class="admin-content">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    <i class="fas fa-check-circle me-2"></i>
                </div>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    <i class="fas fa-exclamation-circle me-2"></i>
                </div>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-primary);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stats-number"><?= number_format($schemaStats['total_schemas']) ?></div>
            <div class="stats-label">Total Plans</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-number"><?= number_format($schemaStats['active_schemas']) ?></div>
            <div class="stats-label">Active Plans</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-info);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-number"><?= number_format($schemaStats['total_investments']) ?></div>
            <div class="stats-label">Total Investments</div>
        </div>

        <div class="stats-card">
            <div class="stats-icon" style="background: var(--admin-warning);">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stats-number"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schemaStats['total_invested'], 2) ?></div>
            <div class="stats-label">Total Invested</div>
        </div>
    </div>

    <!-- Investment Plans Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Investment Plans</h3>
            <div class="ms-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create-plan">
                    <i class="fas fa-plus me-2"></i>
                    Create New Plan
                </button>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Daily Rate</th>
                            <th>Duration</th>
                            <th>Min/Max Investment</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schemas)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-chart-line fa-2x mb-3"></i>
                                    <div>No investment plans found</div>
                                    <div class="mt-2">
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-create-plan">
                                            Create First Plan
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schemas as $schema): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($schema['name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($schema['description']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success"><?= number_format($schema['daily_rate'], 2) ?>%</div>
                                        <div class="text-muted small">Daily Return</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= $schema['duration_days'] ?> days</div>
                                        <div class="text-muted small">Investment Period</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['min_amount'], 2) ?></div>
                                        <div class="text-muted small">Min: <?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['min_amount'], 2) ?> | Max: <?= \App\Config\Config::getCurrencySymbol() ?><?= number_format($schema['max_amount'], 2) ?></div>
                                    </td>
                                    <td>
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
                                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                    <td>
                                        <div class="text-muted small">
                                            <?= date('M j, Y', strtotime($schema['created_at'])) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= date('g:i A', strtotime($schema['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-list">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPlan(<?= $schema['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewPlan(<?= $schema['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deletePlan(<?= $schema['id'] ?>, '<?= htmlspecialchars($schema['name']) ?>')">
                                                <i class="fas fa-trash"></i>
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
<div class="modal fade" id="modal-create-plan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Investment Plan</h5>
                <button type="button" class="btn-close" onclick="closeCreateModal()"></button>
            </div>
            <form method="POST" id="create-plan-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_schema">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Plan Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Daily Rate (%)</label>
                                <input type="number" name="daily_rate" class="form-control" step="0.01" min="0.01" max="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (Days)</label>
                                <input type="number" name="duration_days" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Total Return (%)</label>
                                <input type="number" name="total_return" class="form-control" step="0.01" min="0.01" max="1000" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Featured</label>
                                <select name="featured" class="form-select" required>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Investment</label>
                                <input type="number" name="min_amount" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maximum Investment</label>
                                <input type="number" name="max_amount" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Plan description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitCreateForm()">Create Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="modal-edit-plan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Investment Plan</h5>
                <button type="button" class="btn-close" onclick="closeEditModal()"></button>
            </div>
            <form method="POST" id="edit-plan-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_schema">
                    <input type="hidden" name="schema_id" id="edit-schema-id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Plan Name</label>
                                <input type="text" name="name" id="edit-name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Daily Rate (%)</label>
                                <input type="number" name="daily_rate" id="edit-daily-rate" class="form-control" step="0.01" min="0.01" max="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (Days)</label>
                                <input type="number" name="duration_days" id="edit-duration-days" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Total Return (%)</label>
                                <input type="number" name="total_return" id="edit-total-return" class="form-control" step="0.01" min="0.01" max="1000" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit-is-active" class="form-select" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Featured</label>
                                <select name="featured" id="edit-featured" class="form-select" required>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Investment</label>
                                <input type="number" name="min_amount" id="edit-min-investment" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maximum Investment</label>
                                <input type="number" name="max_amount" id="edit-max-investment" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="3" placeholder="Plan description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditForm()">Update Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Plan Modal -->
<div class="modal fade" id="modal-view-plan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Plan Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="plan-details-content">
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
                    modalElement.style.display = 'block';
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
                <div class="row">
                    <div class="col-md-6">
                        <h6>Plan Details</h6>
                        <p><strong>Name:</strong> ${schema.name}</p>
                        <p><strong>Daily Rate:</strong> ${schema.daily_rate}%</p>
                        <p><strong>Duration:</strong> ${schema.duration_days} days</p>
                        <p><strong>Total Return:</strong> ${schema.total_return}%</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Investment Limits</h6>
                        <p><strong>Min Investment:</strong> $${parseFloat(schema.min_amount).toLocaleString()}</p>
                        <p><strong>Max Investment:</strong> $${parseFloat(schema.max_amount).toLocaleString()}</p>
                        <p><strong>Status:</strong> <span class="badge ${schema.status ? 'bg-success' : 'bg-secondary'}">${schema.status ? 'Active' : 'Inactive'}</span></p>
                        <p><strong>Featured:</strong> <span class="badge ${schema.featured ? 'bg-warning' : 'bg-secondary'}">${schema.featured ? 'Yes' : 'No'}</span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
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
                    modalElement.style.display = 'block';
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