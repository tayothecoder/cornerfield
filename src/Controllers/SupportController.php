<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Controllers/SupportController.php
 * Purpose: Customer support ticket management controller
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Controllers;

use App\Models\SupportTicketModel;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\JsonResponse;

class SupportController 
{
    private SupportTicketModel $ticketModel;
    
    public function __construct() 
    {
        $this->ticketModel = new SupportTicketModel();
    }
    
    /**
     * Get user's support tickets
     * @return array
     */
    public function getTickets(): array 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        try {
            // Get tickets
            $tickets = $this->ticketModel->findByUserId($userId);
            
            // Get ticket statistics
            $stats = $this->ticketModel->getUserTicketStats($userId);
            
            // Format tickets for display
            foreach ($tickets as &$ticket) {
                $ticket['status_badge_class'] = $this->getStatusBadgeClass($ticket['status']);
                $ticket['priority_badge_class'] = $this->getPriorityBadgeClass($ticket['priority']);
                $ticket['formatted_created'] = $this->formatDateTime($ticket['created_at']);
                $ticket['formatted_updated'] = $this->formatDateTime($ticket['updated_at']);
                
                if ($ticket['last_reply_at']) {
                    $ticket['formatted_last_reply'] = $this->formatDateTime($ticket['last_reply_at']);
                } else {
                    $ticket['formatted_last_reply'] = 'No replies yet';
                }
                
                // Truncate subject and message for list display
                $ticket['short_subject'] = strlen($ticket['subject']) > 50 ? 
                    substr($ticket['subject'], 0, 50) . '...' : $ticket['subject'];
                $ticket['short_message'] = strlen($ticket['message']) > 100 ? 
                    substr($ticket['message'], 0, 100) . '...' : $ticket['message'];
            }
            
            return [
                'success' => true,
                'tickets' => $tickets,
                'stats' => $stats
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to fetch tickets for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load support tickets'
            ];
        }
    }
    
    /**
     * View specific ticket with replies
     * @param int $id
     * @return array
     */
    public function viewTicket(int $id): array 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid ticket ID'
            ];
        }
        
        try {
            // Get ticket (with user ownership check)
            $ticket = $this->ticketModel->findById($id, $userId);
            
            if (!$ticket) {
                return [
                    'success' => false,
                    'error' => 'Ticket not found'
                ];
            }
            
            // Get ticket replies
            $replies = $this->ticketModel->getReplies($id);
            
            // Format ticket data
            $ticket['status_badge_class'] = $this->getStatusBadgeClass($ticket['status']);
            $ticket['priority_badge_class'] = $this->getPriorityBadgeClass($ticket['priority']);
            $ticket['formatted_created'] = $this->formatDateTime($ticket['created_at']);
            $ticket['formatted_updated'] = $this->formatDateTime($ticket['updated_at']);
            
            // Format replies
            foreach ($replies as &$reply) {
                $reply['formatted_created'] = $this->formatDateTime($reply['created_at']);
                $reply['is_admin'] = $reply['sender_type'] === 'admin';
                
                if ($reply['sender_type'] === 'admin') {
                    $reply['sender_name'] = $reply['admin_name'] ?? 'Support Team';
                    $reply['sender_avatar'] = $this->getAdminAvatar($reply['admin_name']);
                } else {
                    $reply['sender_name'] = trim(($reply['user_first_name'] ?? '') . ' ' . ($reply['user_last_name'] ?? '')) 
                        ?: $reply['user_username'];
                    $reply['sender_avatar'] = $this->getUserAvatar($reply['sender_name']);
                }
            }
            
            return [
                'success' => true,
                'ticket' => $ticket,
                'replies' => $replies
            ];
            
        } catch (\Exception $e) {
            error_log("Failed to fetch ticket {$id} for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load ticket details'
            ];
        }
    }
    
    /**
     * Create new support ticket
     * @return void
     */
    public function createTicket(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Rate limiting
        if (!Security::rateLimitCheck('ticket_create_' . $userId, 'ticket_create', 3, 3600)) {
            JsonResponse::error('Too many ticket creation attempts. Please wait 1 hour.', 429);
            return;
        }
        
        // Validate inputs
        $subject = Validator::sanitizeString($_POST['subject'] ?? '', 255);
        $message = Validator::sanitizeString($_POST['message'] ?? '', 5000);
        $category = Validator::sanitizeString($_POST['category'] ?? 'general', 50);
        $priority = Validator::sanitizeString($_POST['priority'] ?? 'normal', 20);
        
        $errors = [];
        
        if (empty($subject)) {
            $errors[] = 'Subject is required';
        } elseif (strlen($subject) < 5) {
            $errors[] = 'Subject must be at least 5 characters long';
        }
        
        if (empty($message)) {
            $errors[] = 'Message is required';
        } elseif (strlen($message) < 10) {
            $errors[] = 'Message must be at least 10 characters long';
        }
        
        $validCategories = ['general', 'technical', 'billing', 'investment', 'kyc', 'other'];
        if (!in_array($category, $validCategories)) {
            $category = 'general';
        }
        
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        
        if (!empty($errors)) {
            JsonResponse::error(implode(', ', $errors));
            return;
        }
        
        try {
            $result = $this->ticketModel->createTicket($userId, $subject, $message, $category, $priority);
            
            if ($result['success']) {
                JsonResponse::success([
                    'message' => $result['message'],
                    'ticket_id' => $result['ticket_id']
                ]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to create ticket for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to create support ticket. Please try again.');
        }
    }
    
    /**
     * Reply to support ticket
     * @return void
     */
    public function replyToTicket(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        // Rate limiting
        if (!Security::rateLimitCheck('ticket_reply_' . $userId, 'ticket_reply', 5, 300)) {
            JsonResponse::error('Too many reply attempts. Please wait 5 minutes.', 429);
            return;
        }
        
        // Validate inputs
        $ticketId = Validator::sanitizeInt($_POST['ticket_id'] ?? 0);
        $message = Validator::sanitizeString($_POST['message'] ?? '', 5000);
        
        if ($ticketId <= 0) {
            JsonResponse::error('Invalid ticket ID');
            return;
        }
        
        if (empty($message)) {
            JsonResponse::error('Message is required');
            return;
        }
        
        if (strlen($message) < 5) {
            JsonResponse::error('Message must be at least 5 characters long');
            return;
        }
        
        try {
            $result = $this->ticketModel->addReply($ticketId, $userId, $message, 'user');
            
            if ($result['success']) {
                JsonResponse::success([
                    'message' => $result['message'],
                    'reply_id' => $result['reply_id']
                ]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to add reply for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to add reply. Please try again.');
        }
    }
    
    /**
     * Close support ticket
     * @return void
     */
    public function closeTicket(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $ticketId = Validator::sanitizeInt($_POST['ticket_id'] ?? 0);
        
        if ($ticketId <= 0) {
            JsonResponse::error('Invalid ticket ID');
            return;
        }
        
        try {
            $result = $this->ticketModel->closeTicket($ticketId, $userId);
            
            if ($result['success']) {
                JsonResponse::success(['message' => $result['message']]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to close ticket {$ticketId} for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to close ticket. Please try again.');
        }
    }
    
    /**
     * Reopen support ticket
     * @return void
     */
    public function reopenTicket(): void 
    {
        // CSRF Protection
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            JsonResponse::error('Invalid security token', 403);
            return;
        }
        
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $ticketId = Validator::sanitizeInt($_POST['ticket_id'] ?? 0);
        
        if ($ticketId <= 0) {
            JsonResponse::error('Invalid ticket ID');
            return;
        }
        
        try {
            $result = $this->ticketModel->reopenTicket($ticketId, $userId);
            
            if ($result['success']) {
                JsonResponse::success(['message' => $result['message']]);
            } else {
                JsonResponse::error($result['error']);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to reopen ticket {$ticketId} for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Failed to reopen ticket. Please try again.');
        }
    }
    
    /**
     * Search user tickets
     * @return void
     */
    public function searchTickets(): void 
    {
        // Authentication check
        if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
            JsonResponse::unauthorized();
            return;
        }
        
        $userId = (int)$_SESSION['user_id'];
        $keyword = Validator::sanitizeString($_GET['q'] ?? '', 100);
        
        if (empty($keyword)) {
            JsonResponse::error('Search keyword is required');
            return;
        }
        
        if (strlen($keyword) < 3) {
            JsonResponse::error('Search keyword must be at least 3 characters');
            return;
        }
        
        try {
            $tickets = $this->ticketModel->searchUserTickets($userId, $keyword);
            
            // Format tickets for display
            foreach ($tickets as &$ticket) {
                $ticket['status_badge_class'] = $this->getStatusBadgeClass($ticket['status']);
                $ticket['priority_badge_class'] = $this->getPriorityBadgeClass($ticket['priority']);
                $ticket['formatted_created'] = $this->formatDateTime($ticket['created_at']);
                $ticket['formatted_updated'] = $this->formatDateTime($ticket['updated_at']);
                
                // Highlight search terms (basic implementation)
                $ticket['highlighted_subject'] = $this->highlightText($ticket['subject'], $keyword);
                $ticket['highlighted_message'] = $this->highlightText(
                    strlen($ticket['message']) > 200 ? substr($ticket['message'], 0, 200) . '...' : $ticket['message'], 
                    $keyword
                );
            }
            
            JsonResponse::success([
                'tickets' => $tickets,
                'count' => count($tickets),
                'keyword' => $keyword
            ]);
            
        } catch (\Exception $e) {
            error_log("Failed to search tickets for user {$userId}: " . $e->getMessage());
            JsonResponse::error('Search failed. Please try again.');
        }
    }
    
    /**
     * Get support categories and their descriptions
     * @return void
     */
    public function getCategories(): void 
    {
        $categories = [
            'general' => [
                'name' => 'General Inquiry',
                'description' => 'General questions about the platform',
                'icon' => 'question-circle'
            ],
            'technical' => [
                'name' => 'Technical Issue',
                'description' => 'Website bugs, login issues, technical problems',
                'icon' => 'cog'
            ],
            'billing' => [
                'name' => 'Billing & Payments',
                'description' => 'Deposit, withdrawal, and payment issues',
                'icon' => 'credit-card'
            ],
            'investment' => [
                'name' => 'Investment Support',
                'description' => 'Questions about investment plans and profits',
                'icon' => 'chart-line'
            ],
            'kyc' => [
                'name' => 'KYC Verification',
                'description' => 'Identity verification and document issues',
                'icon' => 'user-check'
            ],
            'other' => [
                'name' => 'Other',
                'description' => 'Issues not covered by other categories',
                'icon' => 'ellipsis'
            ]
        ];
        
        JsonResponse::success(['categories' => $categories]);
    }
    
    /**
     * Get status badge CSS class
     * @param string $status
     * @return string
     */
    private function getStatusBadgeClass(string $status): string 
    {
        switch ($status) {
            case 'open':
                return 'bg-cf-info/10 text-cf-info';
            case 'waiting':
                return 'bg-cf-warning/10 text-cf-warning';
            case 'answered':
                return 'bg-cf-success/10 text-cf-success';
            case 'resolved':
                return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
            case 'closed':
                return 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500';
            default:
                return 'bg-gray-100 text-gray-600';
        }
    }
    
    /**
     * Get priority badge CSS class
     * @param string $priority
     * @return string
     */
    private function getPriorityBadgeClass(string $priority): string 
    {
        switch ($priority) {
            case 'urgent':
                return 'bg-cf-danger/10 text-cf-danger';
            case 'high':
                return 'bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-300';
            case 'normal':
                return 'bg-cf-info/10 text-cf-info';
            case 'low':
                return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
            default:
                return 'bg-gray-100 text-gray-600';
        }
    }
    
    /**
     * Get user avatar initials
     * @param string $name
     * @return string
     */
    private function getUserAvatar(string $name): string 
    {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
    
    /**
     * Get admin avatar
     * @param string|null $name
     * @return string
     */
    private function getAdminAvatar(?string $name): string 
    {
        if (empty($name)) {
            return 'ST'; // Support Team
        }
        
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
    
    /**
     * Highlight search terms in text
     * @param string $text
     * @param string $keyword
     * @return string
     */
    private function highlightText(string $text, string $keyword): string 
    {
        $escapedText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escapedKeyword = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
        
        return preg_replace(
            '/(' . preg_quote($escapedKeyword, '/') . ')/i',
            '<mark class="bg-yellow-200 dark:bg-yellow-800">$1</mark>',
            $escapedText
        );
    }
    
    /**
     * Format datetime for display
     * @param string $datetime
     * @return string
     */
    private function formatDateTime(string $datetime): string 
    {
        $date = new \DateTime($datetime);
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->d == 0 && $diff->h < 24) {
            if ($diff->h == 0 && $diff->i < 60) {
                if ($diff->i < 1) {
                    return 'Just now';
                }
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            }
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d < 7) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } else {
            return $date->format('M j, Y g:i A');
        }
    }
}