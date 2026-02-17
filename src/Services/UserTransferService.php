<?php
/**
 * User Transfer Service
 * Handles user-to-user balance transfers
 */

namespace App\Services;

use Exception;

class UserTransferService {
    private $database;
    private $config;
    
    public function __construct($database) {
        $this->database = $database;
        $this->config = $this->getTransferConfig();
    }
    
    /**
     * Get transfer configuration
     */
    private function getTransferConfig() {
        try {
            $settings = $this->database->fetchAll("SELECT * FROM admin_settings WHERE setting_key LIKE 'transfer_%'");
            $config = [];
            
            foreach ($settings as $setting) {
                $config[$setting['setting_key']] = $setting['setting_value'];
            }
            
            return $config;
        } catch (Exception $e) {
            error_log("Error getting transfer config: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Process user transfer
     */
    public function processTransfer($fromUserId, $toUserId, $amount, $description = '') {
        try {
            // Validate transfer
            $validation = $this->validateTransfer($fromUserId, $toUserId, $amount);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Start transaction
            $this->database->getConnection()->beginTransaction();
            
            try {
                // Generate transaction ID
                $transactionId = 'TRX' . time() . rand(1000, 9999);
                
                // Create transfer record
                $transferId = $this->database->insert('user_transfers', [
                    'transaction_id' => $transactionId,
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'amount' => $amount,
                    'description' => $description,
                    'status' => 'pending'
                ]);
                
                // Deduct from sender
                $this->database->update('users', 
                    ['balance' => $this->database->raw('balance - ' . $amount)], 
                    'id = ?', 
                    [$fromUserId]
                );
                
                // Add to receiver
                $this->database->update('users', 
                    ['balance' => $this->database->raw('balance + ' . $amount)], 
                    'id = ?', 
                    [$toUserId]
                );
                
                // Update transfer status to completed
                $this->database->update('user_transfers', 
                    ['status' => 'completed'], 
                    'id = ?', 
                    [$transferId]
                );
                
                // Commit transaction
                $this->database->getConnection()->commit();
                
                return [
                    'success' => true,
                    'transfer_id' => $transferId,
                    'transaction_id' => $transactionId,
                    'message' => 'Transfer completed successfully'
                ];
                
            } catch (Exception $e) {
                $this->database->getConnection()->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Error processing transfer: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate transfer
     */
    private function validateTransfer($fromUserId, $toUserId, $amount) {
        try {
            // Check if transfer is enabled
            if (empty($this->config['transfer_enabled']) || $this->config['transfer_enabled'] === '0') {
                return ['success' => false, 'message' => 'Transfers are currently disabled'];
            }
            
            // Check minimum amount
            $minAmount = (float)($this->config['transfer_min_amount'] ?? 1.00);
            if ($amount < $minAmount) {
                return ['success' => false, 'message' => "Minimum transfer amount is $" . number_format($minAmount, 2)];
            }
            
            // Check maximum amount
            $maxAmount = (float)($this->config['transfer_max_amount'] ?? 10000.00);
            if ($amount > $maxAmount) {
                return ['success' => false, 'message' => "Maximum transfer amount is $" . number_format($maxAmount, 2)];
            }
            
            // Check daily limit
            $dailyLimit = (float)($this->config['transfer_daily_limit'] ?? 5000.00);
            $todayTransfers = $this->getUserDailyTransfers($fromUserId);
            if (($todayTransfers + $amount) > $dailyLimit) {
                return ['success' => false, 'message' => "Daily transfer limit exceeded"];
            }
            
            // Check sender balance
            $sender = $this->database->fetchOne("SELECT balance FROM users WHERE id = ?", [$fromUserId]);
            if (!$sender || $sender['balance'] < $amount) {
                return ['success' => false, 'message' => 'Insufficient balance'];
            }
            
            // Check if sender and receiver are different
            if ($fromUserId == $toUserId) {
                return ['success' => false, 'message' => 'Cannot transfer to yourself'];
            }
            
            // Check if receiver exists
            $receiver = $this->database->fetchOne("SELECT id FROM users WHERE id = ?", [$toUserId]);
            if (!$receiver) {
                return ['success' => false, 'message' => 'Receiver not found'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Error validating transfer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Validation error'];
        }
    }
    
    /**
     * Get user daily transfers
     */
    private function getUserDailyTransfers($userId) {
        try {
            $result = $this->database->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) as total FROM user_transfers 
                 WHERE from_user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'",
                [$userId]
            );
            
            return (float)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getting daily transfers: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get transfer history
     */
    public function getTransferHistory($userId = null, $filters = []) {
        try {
            $where = "1=1";
            $params = [];
            
            if ($userId) {
                $where .= " AND (from_user_id = ? OR to_user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
            }
            
            if (!empty($filters['status'])) {
                $where .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            $sql = "SELECT t.*, 
                           u1.email as from_email, u1.first_name as from_first_name, u1.last_name as from_last_name,
                           u2.email as to_email, u2.first_name as to_first_name, u2.last_name as to_last_name
                    FROM user_transfers t 
                    LEFT JOIN users u1 ON t.from_user_id = u1.id 
                    LEFT JOIN users u2 ON t.to_user_id = u2.id 
                    WHERE $where 
                    ORDER BY t.created_at DESC";
            
            return $this->database->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error getting transfer history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get transfer statistics
     */
    public function getTransferStats() {
        try {
            $stats = [];
            
            // Total transfers
            $total = $this->database->fetchOne("SELECT COUNT(*) as count FROM user_transfers");
            $stats['total'] = $total['count'] ?? 0;
            
            // Total amount transferred
            $amount = $this->database->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM user_transfers WHERE status = 'completed'");
            $stats['total_amount'] = $amount['total'] ?? 0;
            
            // Today's transfers
            $today = $this->database->fetchOne("SELECT COUNT(*) as count FROM user_transfers WHERE DATE(created_at) = CURDATE()");
            $stats['today'] = $today['count'] ?? 0;
            
            // Pending transfers
            $pending = $this->database->fetchOne("SELECT COUNT(*) as count FROM user_transfers WHERE status = 'pending'");
            $stats['pending'] = $pending['count'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting transfer stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cancel transfer
     */
    public function cancelTransfer($transferId, $adminId, $reason = '') {
        try {
            $transfer = $this->database->fetchOne("SELECT * FROM user_transfers WHERE id = ? AND status = 'pending'", [$transferId]);
            if (!$transfer) {
                return ['success' => false, 'message' => 'Transfer not found or cannot be cancelled'];
            }
            
            // Start transaction
            $this->database->getConnection()->beginTransaction();
            
            try {
                // Update transfer status
                $this->database->update('user_transfers', 
                    [
                        'status' => 'cancelled',
                        'cancelled_by' => $adminId,
                        'cancellation_reason' => $reason
                    ], 
                    'id = ?', 
                    [$transferId]
                );
                
                // Refund sender
                $this->database->update('users', 
                    ['balance' => $this->database->raw('balance + ' . $transfer['amount'])], 
                    'id = ?', 
                    [$transfer['from_user_id']]
                );
                
                // Commit transaction
                $this->database->getConnection()->commit();
                
                return ['success' => true, 'message' => 'Transfer cancelled successfully'];
                
            } catch (Exception $e) {
                $this->database->getConnection()->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Error cancelling transfer: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
