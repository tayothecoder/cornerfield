<?php
namespace App\Models;

use App\Config\Database;
use App\Utils\SessionManager;
use Exception;

class UserManagement {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all users with pagination and search
     */
    public function getAllUsers($page = 1, $limit = 20, $search = '', $status = 'all') {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        // Search functionality
        if (!empty($search)) {
            $whereConditions[] = "(email LIKE ? OR username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // Status filter
        if ($status !== 'all') {
            switch ($status) {
                case 'active':
                    $whereConditions[] = "is_active = 1";
                    break;
                case 'inactive':
                    $whereConditions[] = "is_active = 0";
                    break;
                case 'verified':
                    $whereConditions[] = "email_verified = 1";
                    break;
                case 'unverified':
                    $whereConditions[] = "email_verified = 0";
                    break;
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get users with investment statistics
        $sql = "
            SELECT u.*,
                   COUNT(DISTINCT i.id) as total_investments,
                   COALESCE(SUM(i.invest_amount), 0) as total_invested_amount,
                   COUNT(DISTINCT CASE WHEN i.status = 'active' THEN i.id END) as active_investments,
                   COUNT(DISTINCT t.id) as total_transactions,
                   COALESCE(SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount END), 0) as total_withdrawn_amount
            FROM users u
            LEFT JOIN investments i ON u.id = i.user_id
            LEFT JOIN transactions t ON u.id = t.user_id
            {$whereClause}
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get total users count for pagination
     */
    public function getTotalUsersCount($search = '', $status = 'all') {
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(email LIKE ? OR username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($status !== 'all') {
            switch ($status) {
                case 'active':
                    $whereConditions[] = "is_active = 1";
                    break;
                case 'inactive':
                    $whereConditions[] = "is_active = 0";
                    break;
                case 'verified':
                    $whereConditions[] = "email_verified = 1";
                    break;
                case 'unverified':
                    $whereConditions[] = "email_verified = 0";
                    break;
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users {$whereClause}", $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * Get user by ID with detailed information
     */
    public function getUserById($userId) {
        $sql = "
            SELECT u.*,
                   COUNT(DISTINCT i.id) as total_investments,
                   COALESCE(SUM(i.invest_amount), 0) as total_invested_amount,
                   COUNT(DISTINCT CASE WHEN i.status = 'active' THEN i.id END) as active_investments,
                   COUNT(DISTINCT t.id) as total_transactions,
                   COALESCE(SUM(CASE WHEN t.type = 'profit' AND t.status = 'completed' THEN t.amount END), 0) as total_profits_earned,
                   COUNT(DISTINCT r1.id) as total_referrals,
                   COUNT(DISTINCT r2.id) as referred_by_count
            FROM users u
            LEFT JOIN investments i ON u.id = i.user_id
            LEFT JOIN transactions t ON u.id = t.user_id
            LEFT JOIN referrals r1 ON u.id = r1.referrer_id
            LEFT JOIN referrals r2 ON u.id = r2.referred_id
            WHERE u.id = ?
            GROUP BY u.id
        ";
        
        return $this->db->fetchOne($sql, [$userId]);
    }
    
    /**
     * Update user information
     */
    public function updateUser($userId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Remove empty values and prepare update data
            $updateData = array_filter($data, function($value) {
                return $value !== null && $value !== '';
            });
            
            // Handle password update separately if provided
            if (isset($updateData['password']) && !empty($updateData['password'])) {
                $updateData['password_hash'] = password_hash($updateData['password'], PASSWORD_DEFAULT);
                unset($updateData['password']);
            }
            
            // Remove fields that shouldn't be updated this way
            unset($updateData['id'], $updateData['created_at'], $updateData['referral_code']);
            
            if (!empty($updateData)) {
                $this->db->update('users', $updateData, 'id = ?', [$userId]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to update user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user balance (add or subtract)
     */
    public function updateUserBalance($userId, $amount, $type = 'add', $adminId = null, $description = '') {
        try {
            $this->db->beginTransaction();
            
            $user = $this->getUserById($userId);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            $currentBalance = $user['balance'];
            $newBalance = $type === 'add' ? $currentBalance + $amount : $currentBalance - $amount;
            
            if ($newBalance < 0) {
                throw new Exception("Insufficient balance");
            }
            
            // Update user balance
            $this->db->update('users', ['balance' => $newBalance], 'id = ?', [$userId]);
            
            // Create transaction record
            $transactionData = [
                'user_id' => $userId,
                'type' => $type === 'add' ? 'deposit' : 'withdrawal',
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'status' => 'completed',
                'payment_method' => 'manual',
                'description' => $description ?: ($type === 'add' ? 'Manual deposit by admin' : 'Manual deduction by admin'),
                'admin_processed_by' => $adminId,
                'processed_by_type' => 'admin',
                'processed_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('transactions', $transactionData);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to update user balance: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle user status (active/inactive)
     */
    public function toggleUserStatus($userId) {
        try {
            $user = $this->getUserById($userId);
            if (!$user) {
                return false;
            }
            
            $newStatus = $user['is_active'] ? 0 : 1;
            return $this->db->update('users', ['is_active' => $newStatus], 'id = ?', [$userId]);
        } catch (Exception $e) {
            error_log("Failed to toggle user status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete user (soft delete - just deactivate)
     */
    public function deleteUser($userId) {
        try {
            return $this->db->update('users', ['is_active' => 0], 'id = ?', [$userId]);
        } catch (Exception $e) {
            error_log("Failed to delete user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's recent transactions
     */
    public function getUserTransactions($userId, $limit = 10) {
        $sql = "
            SELECT * FROM transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ";
        
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }
    
    /**
     * Get user's investments
     */
    public function getUserInvestments($userId) {
        $sql = "
            SELECT i.*, s.name as plan_name, s.daily_rate, s.duration_days
            FROM investments i
            JOIN investment_schemas s ON i.schema_id = s.id
            WHERE i.user_id = ?
            ORDER BY i.created_at DESC
        ";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * Get user statistics for dashboard
     */
    public function getUserStatistics() {
        $stats = [];
        
        // Total users
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users");
        $stats['total_users'] = $result['count'];
        
        // Active users
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
        $stats['active_users'] = $result['count'];
        
        // New users this month
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
        $stats['new_users_this_month'] = $result['count'];
        
        // Verified users
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE email_verified = 1");
        $stats['verified_users'] = $result['count'];
        
        return $stats;
    }

    /**
     * Start user impersonation session
     */
    public function startImpersonation($userId, $adminId) {
        try {
            $user = $this->getUserById($userId);
            if (!$user) {
                error_log("User not found for impersonation: $userId");
                return false;
            }
            
            // Verify admin exists in admins table
            $adminCheck = $this->db->query("SELECT id, username, email FROM admins WHERE id = ?", [$adminId])->fetch();
            if (!$adminCheck) {
                error_log("Admin not found in admins table: $adminId");
                return false;
            }
            
            // Store original admin session data
            SessionManager::set('original_admin_id', $adminId);
            SessionManager::set('original_admin_logged_in', true);
            SessionManager::set('original_admin_username', $adminCheck['username']);
            SessionManager::set('original_admin_email', $adminCheck['email']);
            SessionManager::set('impersonating_user_id', $userId);
            SessionManager::set('is_impersonating', true);
            
            // Set user session data for impersonation (don't conflict with admin)
            SessionManager::set('impersonated_user_id', $user['id']);
            SessionManager::set('impersonated_username', $user['username']);
            SessionManager::set('impersonated_email', $user['email']);
            
            // Log the impersonation for security audit
            $this->logImpersonation($adminId, $userId, 'started');
            
            error_log("Impersonation started successfully - Admin: $adminId ({$adminCheck['username']}), User: $userId ({$user['username']})");
            return true;
        } catch (Exception $e) {
            error_log("Failed to start impersonation: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Stop user impersonation and return to admin
     */
    public function stopImpersonation() {
        try {
            $adminId = SessionManager::get('original_admin_id');
            $userId = SessionManager::get('impersonating_user_id');
            
            if (!$adminId || !$userId) {
                return false;
            }
            
            // Log the impersonation end
            $this->logImpersonation($adminId, $userId, 'stopped');
            
            // Clear user session data
            SessionManager::remove('user_id');
            SessionManager::remove('user_logged_in');
            SessionManager::remove('username');
            SessionManager::remove('user_email');
            SessionManager::remove('impersonating_user_id');
            SessionManager::remove('is_impersonating');
            
            // Restore admin session
            SessionManager::set('admin_id', $adminId);
            SessionManager::set('admin_logged_in', true);
            SessionManager::remove('original_admin_id');
            SessionManager::remove('original_admin_logged_in');
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to stop impersonation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if currently impersonating a user
     */
    public function isImpersonating() {
        return SessionManager::get('is_impersonating') === true;
    }
    
    /**
     * Get impersonation details
     */
    public function getImpersonationDetails() {
        if (!$this->isImpersonating()) {
            return null;
        }
        
        return [
            'admin_id' => SessionManager::get('original_admin_id'),
            'user_id' => SessionManager::get('impersonating_user_id'),
            'user_data' => $this->getUserById(SessionManager::get('impersonating_user_id'))
        ];
    }
    
    /**
     * Log impersonation events for security audit
     */
    private function logImpersonation($adminId, $userId, $action) {
        try {
            $logData = [
                'event_type' => 'admin_impersonation',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'user_id' => $userId,
                'data' => json_encode([
                    'admin_id' => $adminId,
                    'target_user_id' => $userId,
                    'action' => $action,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ];
            
            $this->db->insert('security_logs', $logData);
        } catch (Exception $e) {
            error_log("Failed to log impersonation: " . $e->getMessage());
        }
    }
}