<?php
declare(strict_types=1);
/**
 * Support Service
 * Handles support ticket management
 */

namespace App\Services;

use Exception;

class SupportService {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * Create support ticket
     */
    public function createTicket($userId, $subject, $message, $category = 'general', $priority = 'normal') {
        try {
            $ticketId = $this->database->insert('support_tickets', [
                'user_id' => $userId,
                'subject' => $subject,
                'message' => $message,
                'category' => $category,
                'priority' => $priority,
                'status' => 'open'
            ]);
            
            return [
                'success' => true,
                'ticket_id' => $ticketId
            ];
        } catch (Exception $e) {
            error_log("Error creating support ticket: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get tickets with filtering
     */
    public function getTickets($filters = []) {
        try {
            $where = "1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $where .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['category'])) {
                $where .= " AND category = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['priority'])) {
                $where .= " AND priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['user_id'])) {
                $where .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            $sql = "SELECT t.*, u.email as user_email, u.first_name, u.last_name 
                    FROM support_tickets t 
                    LEFT JOIN users u ON t.user_id = u.id 
                    WHERE $where 
                    ORDER BY t.created_at DESC";
            
            return $this->database->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error getting tickets: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get ticket by ID
     */
    public function getTicket($ticketId) {
        try {
            $sql = "SELECT t.*, u.email as user_email, u.first_name, u.last_name 
                    FROM support_tickets t 
                    LEFT JOIN users u ON t.user_id = u.id 
                    WHERE t.id = ?";
            
            return $this->database->fetchOne($sql, [$ticketId]);
        } catch (Exception $e) {
            error_log("Error getting ticket: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Add reply to ticket
     */
    public function addReply($ticketId, $userId, $message, $isAdmin = false) {
        try {
            // ticket_replies table uses admin_id/user_id and sender_type columns
            $data = [
                'ticket_id' => $ticketId,
                'message' => $message,
                'sender_type' => $isAdmin ? 'admin' : 'user',
            ];
            if ($isAdmin) {
                $data['admin_id'] = $userId;
            } else {
                $data['user_id'] = $userId;
            }
            $replyId = $this->database->insert('ticket_replies', $data);
            
            // update ticket status - use valid enum values
            $this->database->update('support_tickets', 
                ['status' => $isAdmin ? 'in_progress' : 'pending',
                 'last_reply_by' => $isAdmin ? 'admin' : 'user',
                 'last_reply_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$ticketId]
            );
            
            return [
                'success' => true,
                'reply_id' => $replyId
            ];
        } catch (Exception $e) {
            error_log("Error adding reply: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update ticket status
     */
    public function updateTicketStatus($ticketId, $status, $assignedTo = null) {
        try {
            $data = ['status' => $status];
            if ($assignedTo !== null) {
                $data['assigned_to'] = $assignedTo;
            }
            
            $this->database->update('support_tickets', $data, 'id = ?', [$ticketId]);
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Error updating ticket status: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get ticket statistics
     */
    public function getTicketStats() {
        try {
            $stats = [];
            
            // Total tickets
            $total = $this->database->fetchOne("SELECT COUNT(*) as count FROM support_tickets");
            $stats['total'] = $total['count'] ?? 0;
            
            // Open tickets
            $open = $this->database->fetchOne("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
            $stats['open'] = $open['count'] ?? 0;
            
            // Waiting tickets
            $waiting = $this->database->fetchOne("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'waiting'");
            $stats['waiting'] = $waiting['count'] ?? 0;
            
            // Resolved tickets
            $resolved = $this->database->fetchOne("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'resolved'");
            $stats['resolved'] = $resolved['count'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting ticket stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get ticket categories
     */
    public function getCategories() {
        return [
            'general' => 'General',
            'technical' => 'Technical',
            'billing' => 'Billing',
            'investment' => 'Investment',
            'withdrawal' => 'Withdrawal',
            'deposit' => 'Deposit',
            'account' => 'Account',
            'security' => 'Security',
            'other' => 'Other'
        ];
    }
    
    /**
     * Get priority levels
     */
    public function getPriorities() {
        return [
            'low' => 'Low',
            'normal' => 'Normal',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];
    }
}
