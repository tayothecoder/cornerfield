<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}

require_once dirname(__DIR__) . '/autoload.php';
\App\Config\EnvLoader::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

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

<div class="mb-6">
    <div class="">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Support Tickets Management</h2>
                <div class="text-gray-400 dark:text-gray-500 mt-1">Manage user support requests and inquiries</div>
            </div>
            <div class="col-auto ml-auto d-print-none">
                <div class="flex flex-wrap gap-2">
                    <a href="#" class="btn btn-primary d-none d-sm-inline-block" onclick="showModal(this.getAttribute('data-target'))" data-target="newTicketModal">
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

<div class="space-y-6">
    <div class="">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tickets</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-gray-900 dark:text-white mb-3"><?= number_format($stats['total'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Open Tickets</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-amber-600 dark:text-amber-400 mb-3"><?= number_format($stats['open'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Waiting</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-blue-600 dark:text-blue-400 mb-3"><?= number_format($stats['waiting'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="">
                <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex align-items-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Resolved</div>
                        </div>
                        <div class="text-3xl font-light tracking-tighter text-emerald-600 dark:text-emerald-400 mb-3"><?= number_format($stats['resolved'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Support Tickets</h3>
                <div class="flex flex-wrap gap-2 mt-3">
                    <button type="button" class="ticket-filter px-3 py-1.5 text-sm font-medium rounded-full cursor-pointer bg-[#1e0e62] text-white border border-[#1e0e62]" data-filter="all">All Tickets</button>
                    <button type="button" class="ticket-filter px-3 py-1.5 text-sm font-medium rounded-full cursor-pointer border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-400 hover:border-[#1e0e62]" data-filter="open">Open</button>
                    <button type="button" class="ticket-filter px-4 py-2 text-sm font-medium rounded-full cursor-pointer border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-400 hover:border-[#1e0e62]" data-filter="in_progress">In Progress</button>
                    <button type="button" class="ticket-filter px-3 py-1.5 text-sm font-medium rounded-full cursor-pointer border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-400 hover:border-[#1e0e62]" data-filter="waiting">Waiting</button>
                    <button type="button" class="ticket-filter px-3 py-1.5 text-sm font-medium rounded-full cursor-pointer border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-400 hover:border-[#1e0e62]" data-filter="resolved">Resolved</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr data-status="<?= $ticket['status'] ?>" class="border-b border-gray-50 dark:border-[#2d1b6e]/30 hover:bg-gray-50/50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">#<?= $ticket['id'] ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex py-1 align-items-center">
                                    <div class="flex-fill">
                                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($ticket['user_email'] ?? 'Unknown') ?></div>
                                        <div class="text-gray-400 dark:text-gray-500"><?= htmlspecialchars(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($ticket['subject']) ?></div>
                                <div class="text-gray-400 dark:text-gray-500"><?= htmlspecialchars(substr($ticket['message'], 0, 100)) ?>...</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400"><?= htmlspecialchars($ticket['category']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php
                                switch($ticket['priority']) {
                                    case 'urgent':
                                        $priorityClass = 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400';
                                        break;
                                    case 'high':
                                        $priorityClass = 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400';
                                        break;
                                    case 'medium':
                                        $priorityClass = 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400';
                                        break;
                                    case 'normal':
                                        $priorityClass = 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400';
                                        break;
                                    case 'low':
                                        $priorityClass = 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400';
                                        break;
                                    default:
                                        $priorityClass = 'bg-gray-100 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400';
                                        break;
                                }
                                ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $priorityClass ?>"><?= htmlspecialchars(ucfirst($ticket['priority'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <?php
                                switch($ticket['status']) {
                                    case 'open':
                                        $statusClass = 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400';
                                        break;
                                    case 'in_progress':
                                        $statusClass = 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400';
                                        break;
                                    case 'waiting':
                                        $statusClass = 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400';
                                        break;
                                    case 'answered':
                                        $statusClass = 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400';
                                        break;
                                    case 'resolved':
                                        $statusClass = 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400';
                                        break;
                                    case 'closed':
                                        $statusClass = 'bg-gray-100 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-100 dark:bg-gray-800/40 text-gray-600 dark:text-gray-400';
                                        break;
                                }
                                ?>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="text-gray-400 dark:text-gray-500"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></div>
                                <div class="text-gray-400 dark:text-gray-500"><?= date('g:i A', strtotime($ticket['created_at'])) ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex gap-1">
                                    <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 text-xs font-medium rounded-full" onclick="viewTicket(<?= $ticket['id'] ?>)">
                                        View
                                    </button>
                                    <button class="px-3 py-1 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 text-xs font-medium rounded-full" onclick="editTicket(<?= $ticket['id'] ?>)">
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
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="viewTicketModal" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Ticket Details</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" ></button>
            </div>
            <div class="p-6" id="ticketDetails">
                <!-- Ticket details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Ticket Modal -->
<div class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm p-4" id="editTicketModal" tabindex="-1">
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-sm">
        <div class="">
            <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-[#2d1b6e]">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Ticket</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white" ></button>
            </div>
            <div class="p-6">
                <form id="editTicketForm">
                    <input type="hidden" id="editTicketId" name="ticket_id">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                        <select class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" id="editStatus" name="status">
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="waiting">Waiting</option>
                            <option value="answered">Answered</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Priority</label>
                        <select class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none" id="editPriority" name="priority">
                            <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1.5">Add Reply</label>
                        <textarea class="w-full px-4 py-2.5 bg-white dark:bg-[#1a1145] border border-gray-200 dark:border-[#2d1b6e] rounded-xl text-gray-900 dark:text-white outline-none focus:border-[#1e0e62]" id="editReply" name="message" rows="4" placeholder="Add your reply..."></textarea>
                    </div>
                </form>
            </div>
            <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100 dark:border-[#2d1b6e]">
                <button type="button" class="px-4 py-2 border border-gray-200 dark:border-[#2d1b6e] text-gray-600 dark:text-gray-300 text-sm font-medium rounded-full transition-colors" >Cancel</button>
                <button type="button" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors" onclick="saveTicketChanges()">Save Changes</button>
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
            showModal('viewTicketModal');
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
            showModal('editTicketModal');
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

// filter functionality with pill tab highlighting
document.querySelectorAll('.ticket-filter').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var filter = this.dataset.filter;

        // update active tab style
        document.querySelectorAll('.ticket-filter').forEach(function(b) {
            b.classList.remove('bg-[#1e0e62]', 'text-white', 'border-[#1e0e62]');
            b.classList.add('text-gray-600', 'dark:text-gray-400', 'border-gray-200', 'dark:border-[#2d1b6e]');
        });
        this.classList.remove('text-gray-600', 'dark:text-gray-400', 'border-gray-200', 'dark:border-[#2d1b6e]');
        this.classList.add('bg-[#1e0e62]', 'text-white', 'border-[#1e0e62]');

        // filter rows
        if (filter === 'all') {
            document.querySelectorAll('tbody tr').forEach(function(row) { row.style.display = ''; });
        } else {
            document.querySelectorAll('tbody tr').forEach(function(row) {
                row.style.display = (row.dataset.status === filter) ? '' : 'none';
            });
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
