<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\SupportController;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthMiddleware::check()) {
        header('Location: ' . \App\Config\Config::getBasePath() . '/login.php');
        exit;
    }
    try {
        $controller = new SupportController();
        if (!empty($_POST['description']) && empty($_POST['message'])) {
            $_POST['message'] = $_POST['description'];
        }
        $ticketIdPost = $_GET['ticket_id'] ?? $_POST['ticket_id'] ?? null;
        if ($ticketIdPost && !empty($_POST['message']) && empty($_POST['subject'])) {
            $_POST['ticket_id'] = (int)$ticketIdPost;
            $controller->replyToTicket();
        } else {
            $controller->createTicket();
        }
    } catch (\Throwable $e) {
        error_log('Support POST failed: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
    exit;
}

if (!AuthMiddleware::check()) {
    $user = ['id' => 1, 'firstname' => 'Demo', 'lastname' => 'User', 'email' => 'demo@cornerfield.com', 'balance' => 15420.50, 'username' => 'demouser'];
    $isPreview = true;
}

$ticketId = $_GET['ticket_id'] ?? null;
$view = $ticketId ? 'detail' : 'list';

try {
    $controller = new SupportController();
    $data = $ticketId ? $controller->viewTicket((int)$ticketId) : $controller->getTickets();
} catch (\Throwable $e) {
    if ($ticketId) {
        $data = [
            'ticket' => ['id' => $ticketId, 'subject' => 'Unable to withdraw funds', 'status' => 'open', 'priority' => 'high', 'created_at' => '2024-02-10 09:30:00', 'updated_at' => '2024-02-10 15:45:00', 'category' => 'withdrawal'],
            'messages' => [
                ['id' => 1, 'sender' => 'user', 'message' => 'I have been trying to withdraw $500 but the transaction keeps failing.', 'created_at' => '2024-02-10 09:30:00'],
                ['id' => 2, 'sender' => 'support', 'message' => 'Thank you for contacting us. Could you provide the transaction ID?', 'created_at' => '2024-02-10 11:15:00'],
            ]
        ];
    } else {
        $data = [
            'tickets' => [
                ['id' => 1, 'subject' => 'Unable to withdraw funds', 'status' => 'open', 'priority' => 'high', 'created_at' => '2024-02-10 09:30:00', 'last_reply' => '2024-02-10 15:45:00', 'category' => 'withdrawal'],
                ['id' => 2, 'subject' => 'Question about investment plans', 'status' => 'resolved', 'priority' => 'medium', 'created_at' => '2024-02-09 14:20:00', 'last_reply' => '2024-02-09 16:30:00', 'category' => 'investment'],
            ],
            'stats' => ['total' => 5, 'open' => 1, 'pending' => 1, 'resolved' => 2, 'closed' => 1]
        ];
    }
}

$pageTitle = 'Support';
$currentPage = 'support';
require_once __DIR__ . '/includes/header.php';

$base = $base ?? \App\Config\Config::getBasePath();
?>

<div class="space-y-6">
    <?php if ($view === 'list'): ?>

    <!-- header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-medium tracking-tight text-gray-900 dark:text-white">Support</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Get help with your account and investments.</p>
        </div>
        <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
            New Ticket
        </button>
    </div>

    <!-- stats -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <?php
        $statItems = [
            ['Total', $data['stats']['total_tickets'] ?? $data['stats']['total'] ?? 0, 'text-gray-600 dark:text-gray-400', 'bg-gray-100 dark:bg-[#0f0a2e]'],
            ['Open', $data['stats']['open_tickets'] ?? $data['stats']['open'] ?? 0, 'text-red-600 dark:text-red-400', 'bg-red-100 dark:bg-red-900/20'],
            ['Pending', $data['stats']['waiting_tickets'] ?? $data['stats']['pending'] ?? 0, 'text-amber-600 dark:text-amber-400', 'bg-amber-100 dark:bg-amber-900/20'],
            ['Resolved', $data['stats']['resolved_tickets'] ?? $data['stats']['resolved'] ?? 0, 'text-emerald-600 dark:text-emerald-400', 'bg-emerald-100 dark:bg-emerald-900/20'],
            ['Closed', $data['stats']['closed'] ?? 0, 'text-gray-600 dark:text-gray-400', 'bg-gray-100 dark:bg-[#0f0a2e]'],
        ];
        foreach ($statItems as [$label, $count, $textColor, $bgColor]): ?>
        <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1"><?= $label ?></p>
            <p class="text-2xl font-light tracking-tighter text-gray-900 dark:text-white"><?= $count ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ticket list -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white mb-4">Your Tickets</h3>

        <?php if (!empty($data['tickets'])): ?>
        <div class="space-y-3">
            <?php foreach ($data['tickets'] as $ticket): ?>
            <a href="<?= $base ?>/users/support.php?ticket_id=<?= $ticket['id'] ?>" class="block p-4 bg-[#f5f3ff] dark:bg-[#0f0a2e] rounded-2xl hover:ring-1 hover:ring-[#1e0e62]/20 transition-all">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1.5">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">#<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['subject']) ?></h4>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full <?php
                                echo match($ticket['status']) {
                                    'open' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                    'pending', 'waiting' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
                                    'in_progress' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                                    'resolved' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
                                    default => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                                };
                            ?>"><?= ucwords(str_replace('_', ' ', $ticket['status'])) ?></span>
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full <?php
                                echo match($ticket['priority']) {
                                    'high' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                    'medium' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
                                    default => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                                };
                            ?>"><?= ucfirst($ticket['priority']) ?></span>
                            <span class="text-xs text-gray-400"><?= date('M j, H:i', strtotime($ticket['created_at'])) ?></span>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-10">
            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 2H4a2 2 0 00-2 2v12a2 2 0 002 2h4l4 4 4-4h4a2 2 0 002-2V4a2 2 0 00-2-2z"/></svg>
            <p class="text-sm text-gray-400 mb-3">No support tickets yet</p>
            <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" class="px-4 py-2 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Create New Ticket</button>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <!-- ticket detail -->
    <div class="bg-white dark:bg-[#1a1145] rounded-3xl p-6">
        <div class="flex items-center gap-3 mb-4">
            <a href="<?= $base ?>/users/support.php" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-medium tracking-tight text-gray-900 dark:text-white flex-1">#<?= $data['ticket']['id'] ?> - <?= htmlspecialchars($data['ticket']['subject']) ?></h2>
            <span class="px-2.5 py-1 text-xs font-medium rounded-full <?php
                echo match($data['ticket']['status']) {
                    'open' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                    'pending', 'waiting' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
                    'in_progress' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                    'resolved' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
                    default => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                };
            ?>"><?= ucwords(str_replace('_', ' ', $data['ticket']['status'])) ?></span>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-400 mb-6">
            <span><?= ucfirst($data['ticket']['category']) ?></span>
            <span>Created <?= date('M j, Y H:i', strtotime($data['ticket']['created_at'])) ?></span>
        </div>

        <!-- messages -->
        <div class="space-y-4">
            <?php foreach ($data['messages'] as $message): ?>
            <div class="flex <?= $message['sender'] === 'user' ? 'justify-end' : 'justify-start' ?>">
                <div class="max-w-md">
                    <div class="flex items-center gap-2 mb-1 <?= $message['sender'] === 'user' ? 'justify-end' : '' ?>">
                        <span class="text-xs font-medium text-gray-900 dark:text-white"><?= $message['sender'] === 'support' ? 'Support' : 'You' ?></span>
                        <span class="text-xs text-gray-400"><?= date('M j, H:i', strtotime($message['created_at'])) ?></span>
                    </div>
                    <div class="p-4 rounded-2xl text-sm <?= $message['sender'] === 'user' ? 'bg-[#1e0e62] text-white' : 'bg-[#f5f3ff] dark:bg-[#0f0a2e] text-gray-900 dark:text-white' ?>">
                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- reply -->
        <?php if ($data['ticket']['status'] !== 'closed'): ?>
        <div class="mt-6 pt-6">
            <form method="POST" class="space-y-3">
                <?= \App\Utils\Security::getCsrfTokenInput() ?>
                <textarea name="message" rows="3" placeholder="Type your reply..." class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Send Reply</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-400 text-center mt-6 pt-6">This ticket is closed.</p>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<!-- new ticket modal -->
<div id="newTicketModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('newTicketModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-[#1a1145] rounded-3xl max-w-lg w-full p-6">
            <h3 class="text-lg font-medium tracking-tight text-gray-900 dark:text-white mb-5">New Support Ticket</h3>

            <form method="POST" class="space-y-4">
                <?= \App\Utils\Security::getCsrfTokenInput() ?>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Subject</label>
                    <input type="text" name="subject" required placeholder="Brief description of your issue"
                           class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Category</label>
                        <select name="category" required class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                            <option value="">Select</option>
                            <option value="account">Account</option>
                            <option value="investment">Investment</option>
                            <option value="withdrawal">Withdrawal</option>
                            <option value="deposit">Deposit</option>
                            <option value="security">Security</option>
                            <option value="technical">Technical</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Priority</label>
                        <select name="priority" required class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Description</label>
                    <textarea name="description" rows="5" required placeholder="Describe your issue in detail..."
                              class="w-full px-4 py-3 border border-gray-200 dark:border-[#2d1b6e] rounded-xl bg-white dark:bg-[#0f0a2e] text-gray-900 dark:text-white focus:ring-[#1e0e62] focus:border-[#1e0e62] text-sm"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-[#2d1b6e] text-gray-700 dark:text-gray-300 text-sm font-medium rounded-full transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-[#1e0e62] text-white text-sm font-medium rounded-full hover:bg-[#2d1b8a] transition-colors">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
