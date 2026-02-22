<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\SupportController;

// Auth check (preview-safe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// handle support POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthMiddleware::check()) {
        header('Location: ' . \App\Config\Config::getBasePath() . '/login.php');
        exit;
    }
    try {
        $controller = new SupportController();
        // map description field to message for ticket creation
        if (!empty($_POST['description']) && empty($_POST['message'])) {
            $_POST['message'] = $_POST['description'];
        }
        $ticketIdPost = $_GET['ticket_id'] ?? $_POST['ticket_id'] ?? null;
        if ($ticketIdPost && !empty($_POST['message']) && empty($_POST['subject'])) {
            // this is a reply
            $_POST['ticket_id'] = (int)$ticketIdPost;
            $controller->replyToTicket();
        } else {
            // this is a new ticket
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

// get ticket id from url for detail view
$ticketId = $_GET['ticket_id'] ?? null;
$view = $ticketId ? 'detail' : 'list';

try {
    $controller = new SupportController();
    if ($ticketId) {
        $data = $controller->viewTicket((int)$ticketId);
    } else {
        $data = $controller->getTickets();
    }
} catch (\Throwable $e) {
    // Fallback demo data for preview
    if ($ticketId) {
        $data = [
            'ticket' => [
                'id' => $ticketId,
                'subject' => 'Unable to withdraw funds',
                'status' => 'open',
                'priority' => 'high',
                'created_at' => '2024-02-10 09:30:00',
                'updated_at' => '2024-02-10 15:45:00',
                'category' => 'withdrawal',
                'description' => 'I have been trying to withdraw $500 to my Bitcoin wallet for the past 24 hours but the transaction keeps failing. My wallet address is correct and I have verified it multiple times.'
            ],
            'messages' => [
                ['id' => 1, 'sender' => 'user', 'message' => 'I have been trying to withdraw $500 to my Bitcoin wallet for the past 24 hours but the transaction keeps failing. My wallet address is correct and I have verified it multiple times.', 'created_at' => '2024-02-10 09:30:00'],
                ['id' => 2, 'sender' => 'support', 'message' => 'Thank you for contacting us. We have received your withdrawal request and are currently reviewing it. Could you please provide your transaction ID or screenshot of the error message?', 'created_at' => '2024-02-10 11:15:00'],
                ['id' => 3, 'sender' => 'user', 'message' => 'Here is the transaction ID: TXN-2024021000123. The error message says "Insufficient balance" but I clearly have enough funds in my account.', 'created_at' => '2024-02-10 13:20:00'],
                ['id' => 4, 'sender' => 'support', 'message' => 'Thank you for providing the transaction ID. I can see the issue - there was a temporary system glitch affecting Bitcoin withdrawals. This has now been resolved. Please try your withdrawal again and let us know if you encounter any issues.', 'created_at' => '2024-02-10 15:45:00']
            ]
        ];
    } else {
        $data = [
            'tickets' => [
                ['id' => 1, 'subject' => 'Unable to withdraw funds', 'status' => 'open', 'priority' => 'high', 'created_at' => '2024-02-10 09:30:00', 'last_reply' => '2024-02-10 15:45:00', 'category' => 'withdrawal'],
                ['id' => 2, 'subject' => 'Question about investment plans', 'status' => 'resolved', 'priority' => 'medium', 'created_at' => '2024-02-09 14:20:00', 'last_reply' => '2024-02-09 16:30:00', 'category' => 'investment'],
                ['id' => 3, 'subject' => 'Account verification issues', 'status' => 'pending', 'priority' => 'medium', 'created_at' => '2024-02-08 11:15:00', 'last_reply' => '2024-02-08 12:45:00', 'category' => 'account'],
                ['id' => 4, 'subject' => 'Referral commission not credited', 'status' => 'resolved', 'priority' => 'low', 'created_at' => '2024-02-07 16:40:00', 'last_reply' => '2024-02-07 18:20:00', 'category' => 'referral'],
                ['id' => 5, 'subject' => 'Two-factor authentication setup', 'status' => 'closed', 'priority' => 'low', 'created_at' => '2024-02-06 10:30:00', 'last_reply' => '2024-02-06 11:15:00', 'category' => 'security']
            ],
            'stats' => [
                'total' => 5,
                'open' => 1,
                'pending' => 1,
                'resolved' => 2,
                'closed' => 1
            ]
        ];
    }
}

$pageTitle = 'Support';
$currentPage = 'support';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Support Content -->
<div class="space-y-6">
    <?php if ($view === 'list'): ?>
        <!-- Page Header -->
        <div class="bg-[#1e0e62] rounded-3xl p-6 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-medium tracking-tight mb-2">Support Center</h2>
                    <p class="text-blue-100">Get help with your account and investments.</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" 
                            class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        New Ticket
                    </button>
                </div>
            </div>
        </div>

        <!-- Support Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="cf-card bg-white dark:bg-gray-800 rounded-xl p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                        <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white"><?= ($data['stats']['total'] ?? 0) ?></p>
                    </div>
                </div>
            </div>

            <div class="cf-card bg-white dark:bg-gray-800 rounded-xl p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Open</p>
                        <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white"><?= ($data['stats']['open'] ?? 0) ?></p>
                    </div>
                </div>
            </div>

            <div class="cf-card bg-white dark:bg-gray-800 rounded-xl p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                        <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white"><?= ($data['stats']['pending'] ?? 0) ?></p>
                    </div>
                </div>
            </div>

            <div class="cf-card bg-white dark:bg-gray-800 rounded-xl p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Resolved</p>
                        <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white"><?= ($data['stats']['resolved'] ?? 0) ?></p>
                    </div>
                </div>
            </div>

            <div class="cf-card bg-white dark:bg-gray-800 rounded-xl p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Closed</p>
                        <p class="text-xl font-medium tracking-tight text-gray-900 dark:text-white"><?= ($data['stats']['closed'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets List -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Your Support Tickets</h3>
                <div class="flex space-x-2">
                    <select class="px-3 py-2 bg-[#f5f3ff] dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">
                        <option>All Status</option>
                        <option>Open</option>
                        <option>Pending</option>
                        <option>Resolved</option>
                        <option>Closed</option>
                    </select>
                    <select class="px-3 py-2 bg-[#f5f3ff] dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">
                        <option>All Categories</option>
                        <option>Account</option>
                        <option>Investment</option>
                        <option>Withdrawal</option>
                        <option>Security</option>
                        <option>Referral</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($data['tickets'])): ?>
                <div class="space-y-4">
                    <?php foreach ($data['tickets'] as $ticket): ?>
                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-[#f5f3ff] dark:hover:bg-gray-700 transition-colors cursor-pointer" 
                         onclick="window.location.href='/users/support.php?ticket_id=<?= $ticket['id'] ?>'">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h4 class="font-medium text-gray-900 dark:text-white">
                                        #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['subject']) ?>
                                    </h4>
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php
                                        switch ($ticket['status']) {
                                            case 'open': echo 'bg-red-100 text-red-800'; break;
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                            case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                        <?= ucfirst($ticket['status']) ?>
                                    </span>
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php
                                        switch ($ticket['priority']) {
                                            case 'high': echo 'bg-red-100 text-red-800'; break;
                                            case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                        <?= ucfirst($ticket['priority']) ?> Priority
                                    </span>
                                </div>
                                <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 space-x-4">
                                    <span>Category: <?= ucfirst($ticket['category']) ?></span>
                                    <span>Created: <?= date('M j, Y H:i', strtotime($ticket['created_at'])) ?></span>
                                    <span>Last reply: <?= !empty($ticket['last_reply']) ? date('M j, Y H:i', strtotime($ticket['last_reply'])) : 'N/A' ?></span>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No support tickets yet</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Create your first support ticket to get help with your account.</p>
                    <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" 
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create New Ticket
                    </button>
                </div>
            <?php endif; ?>
        </div>

    <?php else: // Ticket Detail View ?>
        <!-- Ticket Detail Header -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-4">
                    <a href="/users/support.php" class="text-indigo-600 hover:text-indigo-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <h2 class="text-xl font-medium tracking-tight text-gray-900 dark:text-white">
                        #<?= $data['ticket']['id'] ?> - <?= htmlspecialchars($data['ticket']['subject']) ?>
                    </h2>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php
                        switch ($data['ticket']['status']) {
                            case 'open': echo 'bg-red-100 text-red-800'; break;
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'resolved': echo 'bg-green-100 text-green-800'; break;
                            case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                    ?>">
                        <?= ucfirst($data['ticket']['status']) ?>
                    </span>
                    <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full <?php
                        switch ($data['ticket']['priority']) {
                            case 'high': echo 'bg-red-100 text-red-800'; break;
                            case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'low': echo 'bg-gray-100 text-gray-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                    ?>">
                        <?= ucfirst($data['ticket']['priority']) ?> Priority
                    </span>
                </div>
            </div>
            <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 space-x-4">
                <span>Category: <?= ucfirst($data['ticket']['category']) ?></span>
                <span>Created: <?= date('M j, Y H:i', strtotime($data['ticket']['created_at'])) ?></span>
                <span>Last updated: <?= date('M j, Y H:i', strtotime($data['ticket']['updated_at'])) ?></span>
            </div>
        </div>

        <!-- Ticket Messages -->
        <div class="cf-card bg-white dark:bg-[#1a1145] rounded-3xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Conversation</h3>
            <div class="space-y-6">
                <?php foreach ($data['messages'] as $message): ?>
                <div class="flex <?= $message['sender'] === 'user' ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-lg">
                        <div class="flex items-center mb-2 <?= $message['sender'] === 'user' ? 'justify-end' : 'justify-start' ?>">
                            <div class="flex items-center space-x-2">
                                <?php if ($message['sender'] === 'support'): ?>
                                <div class="w-6 h-6 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <?php else: ?>
                                <div class="w-6 h-6 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                    <span class="text-gray-600 dark:text-gray-400 font-medium text-xs">
                                        <?= strtoupper(substr($user['firstname'], 0, 1)) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?= $message['sender'] === 'support' ? 'Support Team' : 'You' ?>
                                </span>
                                <span class="text-xs text-gray-500">
                                    <?= date('M j, Y H:i', strtotime($message['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-4 rounded-lg <?= $message['sender'] === 'user' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' ?>">
                            <p><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply Form -->
            <?php if ($data['ticket']['status'] !== 'closed'): ?>
            <div class="mt-8 border-t dark:border-gray-700 pt-6">
                <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Add Reply</h4>
                <form method="POST" class="space-y-4">
                    <?= \App\Utils\Security::getCsrfTokenInput() ?>
                    <div>
                        <textarea name="message" rows="4" 
                                  class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-[#f5f3ff] dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                                  placeholder="Type your reply here..."></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Email me when support replies</span>
                            </label>
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" class="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                                Send Reply
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="mt-8 border-t dark:border-gray-700 pt-6">
                <div class="text-center py-4">
                    <p class="text-gray-500 dark:text-gray-400">This ticket has been closed and cannot accept new replies.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- New Ticket Modal -->
<div id="newTicketModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 rounded-lg bg-white dark:bg-gray-800">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create New Support Ticket</h3>
            <button onclick="document.getElementById('newTicketModal').classList.add('hidden')" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="POST" class="space-y-4">
                    <?= \App\Utils\Security::getCsrfTokenInput() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject</label>
                <input type="text" name="subject" required
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-[#f5f3ff] dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                       placeholder="Brief description of your issue">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                <select name="category" required
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-[#f5f3ff] dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">Select a category</option>
                    <option value="account">Account Issues</option>
                    <option value="investment">Investment Questions</option>
                    <option value="withdrawal">Withdrawal Problems</option>
                    <option value="deposit">Deposit Issues</option>
                    <option value="security">Security Concerns</option>
                    <option value="referral">Referral Program</option>
                    <option value="technical">Technical Support</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority</label>
                <select name="priority" required
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-[#f5f3ff] dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="low">Low - General question</option>
                    <option value="medium" selected>Medium - Issue affecting account</option>
                    <option value="high">High - Urgent issue</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                <textarea name="description" rows="6" required
                          class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-[#f5f3ff] dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          placeholder="Please describe your issue in detail..."></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4">
                <button type="button" 
                        onclick="document.getElementById('newTicketModal').classList.add('hidden')"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                    Create Ticket
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>