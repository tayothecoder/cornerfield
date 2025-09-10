<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Start session
\App\Utils\SessionManager::start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Support Tickets Management';
$currentPage = 'support-tickets';

// Initialize database and services
/** @var \App\Config\Database $database */
$database = new \App\Config\Database();
/** @var \App\Services\SupportService $supportService */
$supportService = new \App\Services\SupportService($database);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            
            $result = $supportService->updateTicketStatus($ticketId, $status, $assignedTo);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'add_reply':
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
                exit;
            }
            
            $result = $supportService->addReply($ticketId, $_SESSION['admin_id'], $message, true);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
    }
}

// Get tickets and stats
$tickets = $supportService->getTickets();
$stats = $supportService->getTicketStats();
$categories = $supportService->getCategories();
$priorities = $supportService->getPriorities();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Support Tickets Management</h2>
                <div class="text-muted mt-1">Manage user support requests and inquiries</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="#" class="btn btn-primary d-none d-sm-inline-block" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Create Ticket
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <!-- Statistics Cards -->
        <div class="row row-deck row-cards mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Total Tickets</div>
                        </div>
                        <div class="h1 mb-3"><?= number_format($stats['total'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Open Tickets</div>
                        </div>
                        <div class="h1 mb-3 text-warning"><?= number_format($stats['open'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Waiting</div>
                        </div>
                        <div class="h1 mb-3 text-info"><?= number_format($stats['waiting'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">Resolved</div>
                        </div>
                        <div class="h1 mb-3 text-success"><?= number_format($stats['resolved'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Support Tickets</h3>
                <div class="card-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            Filter
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" data-filter="all">All Tickets</a>
                            <a class="dropdown-item" href="#" data-filter="open">Open</a>
                            <a class="dropdown-item" href="#" data-filter="waiting">Waiting</a>
                            <a class="dropdown-item" href="#" data-filter="resolved">Resolved</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr data-status="<?= $ticket['status'] ?>">
                            <td>#<?= $ticket['id'] ?></td>
                            <td>
                                <div class="d-flex py-1 align-items-center">
                                    <div class="flex-fill">
                                        <div class="font-weight-medium"><?= htmlspecialchars($ticket['user_email'] ?? 'Unknown') ?></div>
                                        <div class="text-muted"><?= htmlspecialchars(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-medium"><?= htmlspecialchars($ticket['subject']) ?></div>
                                <div class="text-muted"><?= htmlspecialchars(substr($ticket['message'], 0, 100)) ?>...</div>
                            </td>
                            <td>
                                <span class="badge bg-blue"><?= htmlspecialchars($ticket['category']) ?></span>
                            </td>
                            <td>
                                <?php
                                switch($ticket['priority']) {
                                    case 'urgent':
                                        $priorityClass = 'bg-danger';
                                        break;
                                    case 'high':
                                        $priorityClass = 'bg-orange';
                                        break;
                                    case 'medium':
                                        $priorityClass = 'bg-yellow';
                                        break;
                                    case 'normal':
                                        $priorityClass = 'bg-blue';
                                        break;
                                    case 'low':
                                        $priorityClass = 'bg-green';
                                        break;
                                    default:
                                        $priorityClass = 'bg-secondary';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $priorityClass ?>"><?= htmlspecialchars($ticket['priority']) ?></span>
                            </td>
                            <td>
                                <?php
                                switch($ticket['status']) {
                                    case 'open':
                                        $statusClass = 'bg-warning';
                                        break;
                                    case 'waiting':
                                        $statusClass = 'bg-info';
                                        break;
                                    case 'answered':
                                        $statusClass = 'bg-blue';
                                        break;
                                    case 'resolved':
                                        $statusClass = 'bg-success';
                                        break;
                                    case 'closed':
                                        $statusClass = 'bg-secondary';
                                        break;
                                    default:
                                        $statusClass = 'bg-secondary';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($ticket['status']) ?></span>
                            </td>
                            <td>
                                <div class="text-muted"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></div>
                                <div class="text-muted"><?= date('g:i A', strtotime($ticket['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTicket(<?= $ticket['id'] ?>)">
                                        View
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="editTicket(<?= $ticket['id'] ?>)">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Ticket Modal -->
<div class="modal modal-lg" id="viewTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ticket Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ticketDetails">
                <!-- Ticket details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Ticket Modal -->
<div class="modal" id="editTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editTicketForm">
                    <input type="hidden" id="editTicketId" name="ticket_id">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editStatus" name="status">
                            <option value="open">Open</option>
                            <option value="waiting">Waiting</option>
                            <option value="answered">Answered</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" id="editPriority" name="priority">
                            <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Add Reply</label>
                        <textarea class="form-control" id="editReply" name="message" rows="4" placeholder="Add your reply..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTicketChanges()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewTicket(ticketId) {
    // Load ticket details via AJAX
    fetch('support-tickets.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_ticket&ticket_id=' + ticketId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('ticketDetails').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('viewTicketModal')).show();
        }
    });
}

function editTicket(ticketId) {
    document.getElementById('editTicketId').value = ticketId;
    // Load current ticket data
    fetch('support-tickets.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_ticket&ticket_id=' + ticketId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('editStatus').value = data.ticket.status;
            document.getElementById('editPriority').value = data.ticket.priority;
            new bootstrap.Modal(document.getElementById('editTicketModal')).show();
        }
    });
}

function saveTicketChanges() {
    const formData = new FormData(document.getElementById('editTicketForm'));
    formData.append('action', 'update_ticket');
    
    fetch('support-tickets.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Filter functionality
document.querySelectorAll('[data-filter]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const filter = this.dataset.filter;
        
        if (filter === 'all') {
            document.querySelectorAll('tbody tr').forEach(row => row.style.display = '');
        } else {
            document.querySelectorAll('tbody tr').forEach(row => {
                if (row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
