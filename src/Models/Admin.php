<?php
namespace App\Models;

use App\Config\Database;
use Exception;

// src/Models/Admin.php - Fixed for Modern Database Class

class Admin {
    private $db;

    public function __construct(Database $database) {
        $this->db = $database;
    }

    /**
     * Find admin by email
     */
    public function findByEmail($email) {
        try {
            return $this->db->fetchOne("
                SELECT id, username, email, password_hash, full_name, role, status, last_login 
                FROM admins 
                WHERE email = ? AND status = 1
            ", [$email]);
        } catch (Exception $e) {
            error_log("Admin findByEmail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find admin by ID
     */
    public function findById($id) {
        try {
            return $this->db->fetchOne("
                SELECT id, username, email, full_name, role, status, last_login, created_at 
                FROM admins 
                WHERE id = ? AND status = 1
            ", [$id]);
        } catch (Exception $e) {
            error_log("Admin findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login time
     */
    public function updateLastLogin($adminId) {
        try {
            return $this->db->update('admins', [
                'last_login' => date('Y-m-d H:i:s')
            ], 'id = ?', [$adminId]) > 0;
        } catch (Exception $e) {
            error_log("Admin updateLastLogin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new admin
     */
    public function create($data) {
        try {
            return $this->db->insert('admins', [
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password_hash'],
                'full_name' => $data['full_name'] ?? null,
                'role' => $data['role'] ?? 'admin'
            ]);
        } catch (Exception $e) {
            error_log("Admin create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update admin information
     */
    public function update($id, $data) {
        try {
            return $this->db->update('admins', $data, 'id = ?', [$id]) > 0;
        } catch (Exception $e) {
            error_log("Admin update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all admins
     */
    public function getAll() {
        try {
            return $this->db->fetchAll("
                SELECT id, username, email, full_name, role, status, last_login, created_at 
                FROM admins 
                ORDER BY created_at DESC
            ");
        } catch (Exception $e) {
            error_log("Admin getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete admin (soft delete by setting status to 0)
     */
    public function delete($id) {
        try {
            return $this->db->update('admins', [
                'status' => 0
            ], 'id = ? AND role != ?', [$id, 'super_admin']) > 0;
        } catch (Exception $e) {
            error_log("Admin delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total users
            $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $stats['total_users'] = $result['count'];
            
            // Total investments
            $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM investments");
            $stats['total_investments'] = $result['count'];
            
            // Total investment amount
            $result = $this->db->fetchOne("SELECT COALESCE(SUM(invest_amount), 0) as total FROM investments");
            $stats['total_investment_amount'] = $result['total'];
            
            // Active investment schemas
            $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM investment_schemas WHERE status = 1");
            $stats['active_schemas'] = $result['count'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Admin getDashboardStats error: " . $e->getMessage());
            return [
                'total_users' => 0,
                'total_investments' => 0,
                'total_investment_amount' => 0,
                'active_schemas' => 0
            ];
        }
    }

    /**
     * Create admin session
     */
    public function createSession($adminId, $sessionId, $ipAddress, $userAgent) {
        try {
            return $this->db->insert('admin_sessions', [
                'id' => $sessionId,
                'admin_id' => $adminId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);
        } catch (Exception $e) {
            error_log("Admin createSession error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete admin session
     */
    public function deleteSession($sessionId) {
        try {
            return $this->db->delete('admin_sessions', 'id = ?', [$sessionId]) > 0;
        } catch (Exception $e) {
            error_log("Admin deleteSession error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean expired sessions (older than 24 hours)
     */
    public function cleanExpiredSessions() {
        try {
            return $this->db->delete('admin_sessions', 
                'last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            ) >= 0; // >= 0 because it might delete 0 rows and that's still success
        } catch (Exception $e) {
            error_log("Admin cleanExpiredSessions error: " . $e->getMessage());
            return false;
        }
    }
}