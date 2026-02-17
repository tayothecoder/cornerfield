<?php
declare(strict_types=1);

/**
 * Cornerfield Investment Platform
 * File: src/Models/SupportTicketModel.php
 * Purpose: Support ticket system with replies and status management
 * Security Level: PROTECTED
 * 
 * @author Cornerfield Development Team
 * @version 1.0.0
 * @since 2026-02-10
 */

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use App\Models\BaseModel;
use App\Utils\Security;
use App\Utils\Validator;

class SupportTicketModel extends BaseModel
{
    protected string $table = 'support_tickets';
    
    protected array $fillable = [
        'user_id',
        'subject',
        'message',
        'category',
        'priority',
        'status',
        'assigned_to',
        'resolved_at'
    ];

    /**
     * Find all tickets by user ID
     * @param int $userId
     * @return array
     */
    public function findByUserId(int $userId): array 
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT st.*, 
                        a.full_name as assigned_admin_name,
                        (SELECT COUNT(*) FROM support_ticket_replies str WHERE str.ticket_id = st.id) as reply_count,
                        (SELECT MAX(str.created_at) FROM support_ticket_replies str WHERE str.ticket_id = st.id) as last_reply_at
                 FROM {$this->table} st
                 LEFT JOIN admins a ON st.assigned_to = a.id
                 WHERE st.user_id = ?
                 ORDER BY st.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch tickets for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find ticket by ID with user validation
     * @param int $id
     * @param int|null $userId Optional user ID for ownership validation
     * @return array|null
     */
    public function findById(int $id, ?int $userId = null): ?array 
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Ticket ID must be positive integer');
        }
        
        try {
            $sql = "SELECT st.*, 
                           u.username, u.first_name, u.last_name, u.email,
                           a.full_name as assigned_admin_name
                    FROM {$this->table} st
                    LEFT JOIN users u ON st.user_id = u.id
                    LEFT JOIN admins a ON st.assigned_to = a.id
                    WHERE st.id = ?";
            
            $params = [$id];
            
            if ($userId !== null) {
                $sql .= " AND st.user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Failed to fetch ticket {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new support ticket
     * @param int $userId
     * @param string $subject
     * @param string $message
     * @param string $category
     * @param string $priority
     * @return array
     */
    public function createTicket(int $userId, string $subject, string $message, string $category, string $priority): array 
    {
        // Validate inputs
        if ($userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid user ID'
            ];
        }
        
        $subject = Validator::sanitizeString($subject, 255);
        if (empty($subject)) {
            return [
                'success' => false,
                'error' => 'Subject is required'
            ];
        }
        
        $message = Validator::sanitizeString($message, 5000);
        if (empty($message)) {
            return [
                'success' => false,
                'error' => 'Message is required'
            ];
        }
        
        $validCategories = ['general', 'technical', 'billing', 'investment', 'kyc', 'other'];
        if (!in_array($category, $validCategories)) {
            $category = 'general';
        }
        
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'normal';
        }
        
        try {
            $this->db->beginTransaction();
            
            // Create ticket
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, subject, message, category, priority, status, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, 'open', NOW(), NOW())"
            );
            
            $success = $stmt->execute([$userId, $subject, $message, $category, $priority]);
            
            if (!$success) {
                throw new PDOException('Failed to create ticket');
            }
            
            $ticketId = (int)$this->db->lastInsertId();
            
            // Add initial message as first reply
            $this->addInitialReply($ticketId, $userId, $message);
            
            $this->db->commit();
            
            // Log ticket creation
            Security::logAudit($userId, 'support_ticket_created', 'support_tickets', $ticketId, null, [
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority
            ]);
            
            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'message' => 'Support ticket created successfully'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to create ticket: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to create support ticket. Please try again.'
            ];
        }
    }

    /**
     * Add reply to ticket
     * @param int $ticketId
     * @param int $userId
     * @param string $message
     * @param string $senderType 'user' or 'admin'
     * @return array
     */
    public function addReply(int $ticketId, int $userId, string $message, string $senderType = 'user'): array 
    {
        if ($ticketId <= 0 || $userId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid ticket or user ID'
            ];
        }
        
        $message = Validator::sanitizeString($message, 5000);
        if (empty($message)) {
            return [
                'success' => false,
                'error' => 'Message is required'
            ];
        }
        
        if (!in_array($senderType, ['user', 'admin'])) {
            $senderType = 'user';
        }
        
        try {
            // Verify ticket exists and user has permission
            $ticket = $this->findById($ticketId, $senderType === 'user' ? $userId : null);
            if (!$ticket) {
                return [
                    'success' => false,
                    'error' => 'Ticket not found'
                ];
            }
            
            if ($ticket['status'] === 'closed') {
                return [
                    'success' => false,
                    'error' => 'Cannot reply to closed ticket'
                ];
            }
            
            $this->db->beginTransaction();
            
            // Add reply
            $stmt = $this->db->prepare(
                "INSERT INTO support_ticket_replies (ticket_id, user_id, message, sender_type, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            
            $success = $stmt->execute([$ticketId, $userId, $message, $senderType]);
            
            if (!$success) {
                throw new PDOException('Failed to add reply');
            }
            
            $replyId = (int)$this->db->lastInsertId();
            
            // Update ticket status and timestamp
            $newStatus = $senderType === 'user' ? 'waiting' : 'answered';
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$newStatus, $ticketId]);
            
            $this->db->commit();
            
            // Log reply
            Security::logAudit($userId, 'support_ticket_reply', 'support_ticket_replies', $replyId, null, [
                'ticket_id' => $ticketId,
                'sender_type' => $senderType,
                'message_length' => strlen($message)
            ]);
            
            return [
                'success' => true,
                'reply_id' => $replyId,
                'message' => 'Reply added successfully'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to add reply to ticket {$ticketId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to add reply. Please try again.'
            ];
        }
    }

    /**
     * Update ticket status
     * @param int $id
     * @param string $status
     * @return array
     */
    public function updateStatus(int $id, string $status): array 
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid ticket ID'
            ];
        }
        
        $validStatuses = ['open', 'waiting', 'answered', 'resolved', 'closed'];
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'error' => 'Invalid status'
            ];
        }
        
        try {
            $updateData = ['status' => $status];
            
            // Set resolved timestamp if status is resolved or closed
            if (in_array($status, ['resolved', 'closed'])) {
                $updateData['resolved_at'] = date('Y-m-d H:i:s');
            }
            
            $setClause = [];
            $params = [];
            
            foreach ($updateData as $field => $value) {
                $setClause[] = "{$field} = ?";
                $params[] = $value;
            }
            
            $setClause[] = "updated_at = NOW()";
            $params[] = $id;
            
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE id = ?"
            );
            
            $success = $stmt->execute($params);
            
            if ($success && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Ticket status updated successfully'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Ticket not found'
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to update ticket status {$id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Unable to update ticket status. Please try again.'
            ];
        }
    }

    /**
     * Get all replies for a ticket
     * @param int $ticketId
     * @return array
     */
    public function getReplies(int $ticketId): array 
    {
        if ($ticketId <= 0) {
            throw new InvalidArgumentException('Ticket ID must be positive integer');
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT str.*, 
                        u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
                        a.full_name as admin_name, a.username as admin_username
                 FROM support_ticket_replies str
                 LEFT JOIN users u ON str.user_id = u.id AND str.sender_type = 'user'
                 LEFT JOIN admins a ON str.user_id = a.id AND str.sender_type = 'admin'
                 WHERE str.ticket_id = ?
                 ORDER BY str.created_at ASC"
            );
            
            $stmt->execute([$ticketId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to fetch replies for ticket {$ticketId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get ticket statistics for user
     * @param int $userId
     * @return array
     */
    public function getUserTicketStats(int $userId): array 
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_tickets,
                    COUNT(CASE WHEN status = 'open' THEN 1 END) as open_tickets,
                    COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting_tickets,
                    COUNT(CASE WHEN status = 'answered' THEN 1 END) as answered_tickets,
                    COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as resolved_tickets,
                    AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time_hours
                 FROM {$this->table}
                 WHERE user_id = ?"
            );
            
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
            
            if (!$stats) {
                return [
                    'total_tickets' => 0,
                    'open_tickets' => 0,
                    'waiting_tickets' => 0,
                    'answered_tickets' => 0,
                    'resolved_tickets' => 0,
                    'avg_resolution_time_hours' => 0
                ];
            }
            
            return [
                'total_tickets' => (int)$stats['total_tickets'],
                'open_tickets' => (int)$stats['open_tickets'],
                'waiting_tickets' => (int)$stats['waiting_tickets'],
                'answered_tickets' => (int)$stats['answered_tickets'],
                'resolved_tickets' => (int)$stats['resolved_tickets'],
                'avg_resolution_time_hours' => round((float)$stats['avg_resolution_time_hours'], 1)
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to fetch ticket stats for user {$userId}: " . $e->getMessage());
            return [
                'total_tickets' => 0,
                'open_tickets' => 0,
                'waiting_tickets' => 0,
                'answered_tickets' => 0,
                'resolved_tickets' => 0,
                'avg_resolution_time_hours' => 0
            ];
        }
    }

    /**
     * Search tickets by keyword
     * @param int $userId
     * @param string $keyword
     * @return array
     */
    public function searchUserTickets(int $userId, string $keyword): array 
    {
        try {
            $keyword = '%' . Validator::sanitizeString($keyword, 100) . '%';
            
            $stmt = $this->db->prepare(
                "SELECT st.*, 
                        (SELECT COUNT(*) FROM support_ticket_replies str WHERE str.ticket_id = st.id) as reply_count
                 FROM {$this->table} st
                 WHERE st.user_id = ? AND (st.subject LIKE ? OR st.message LIKE ?)
                 ORDER BY st.updated_at DESC"
            );
            
            $stmt->execute([$userId, $keyword, $keyword]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Failed to search tickets for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add initial reply (private method)
     * @param int $ticketId
     * @param int $userId
     * @param string $message
     * @return bool
     */
    private function addInitialReply(int $ticketId, int $userId, string $message): bool 
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO support_ticket_replies (ticket_id, user_id, message, sender_type, created_at) 
                 VALUES (?, ?, ?, 'user', NOW())"
            );
            
            return $stmt->execute([$ticketId, $userId, $message]);
            
        } catch (PDOException $e) {
            error_log("Failed to add initial reply: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close ticket
     * @param int $ticketId
     * @param int $userId User ID for permission check
     * @return array
     */
    public function closeTicket(int $ticketId, int $userId): array 
    {
        // Verify user owns the ticket
        $ticket = $this->findById($ticketId, $userId);
        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found'
            ];
        }
        
        if ($ticket['status'] === 'closed') {
            return [
                'success' => false,
                'error' => 'Ticket is already closed'
            ];
        }
        
        return $this->updateStatus($ticketId, 'closed');
    }

    /**
     * Reopen ticket
     * @param int $ticketId
     * @param int $userId User ID for permission check
     * @return array
     */
    public function reopenTicket(int $ticketId, int $userId): array 
    {
        // Verify user owns the ticket
        $ticket = $this->findById($ticketId, $userId);
        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found'
            ];
        }
        
        if (!in_array($ticket['status'], ['resolved', 'closed'])) {
            return [
                'success' => false,
                'error' => 'Only resolved or closed tickets can be reopened'
            ];
        }
        
        return $this->updateStatus($ticketId, 'open');
    }
}