<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Models\Admin;
use App\Models\Investment;
use App\Utils\Security;
use App\Utils\SessionManager;
use Exception;

// src/Controllers/AdminController.php - Modern Version

class AdminController {
    private $adminModel;
    private $investmentModel;
    private $db;

    public function __construct(Database $database) {
        $this->db = $database;
        $this->adminModel = new Admin($database);
        $this->investmentModel = new Investment($database);
    }

    public function login(string $email, string $password): array {
        try {
            $admin = $this->adminModel->findByEmail($email);
            
            if (!$admin) {
                return [
                    'success' => false, 
                    'message' => 'Invalid credentials'
                ];
            }

            if (!\App\Utils\Security::verifyPassword($password, $admin['password_hash'])) {
                return [
                    'success' => false, 
                    'message' => 'Invalid credentials'
                ];
            }

            // Update last login
            $this->adminModel->updateLastLogin($admin['id']);

            // Start admin session
            $this->startAdminSession($admin);

            return [
                'success' => true,
                'admin' => $admin,
                'message' => 'Login successful'
            ];

        } catch (Exception $e) {
            error_log("Admin login error: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Login failed. Please try again.'
            ];
        }
    }

    private function startAdminSession(array $admin): void {
        \App\Utils\SessionManager::start();
        
        // Generate secure session ID
        $sessionId = bin2hex(random_bytes(32));
        
        // Store in session using modern session manager
        \App\Utils\SessionManager::set('admin_id', $admin['id']);
        \App\Utils\SessionManager::set('admin_email', $admin['email']);
        \App\Utils\SessionManager::set('admin_username', $admin['username']);
        \App\Utils\SessionManager::set('admin_role', $admin['role']);
        \App\Utils\SessionManager::set('admin_session_id', $sessionId);
        \App\Utils\SessionManager::set('admin_logged_in', true);
        \App\Utils\SessionManager::set('admin_login_time', time());

        // Regenerate session ID for security
        \App\Utils\SessionManager::regenerate();

        // Store in database
        $this->adminModel->createSession(
            $admin['id'],
            $sessionId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );

        // Clean old sessions
        $this->adminModel->cleanExpiredSessions();
    }

    public function logout(): array {
        \App\Utils\SessionManager::start();
        
        $sessionId = \App\Utils\SessionManager::get('admin_session_id');
        if ($sessionId) {
            $this->adminModel->deleteSession($sessionId);
        }

        // Destroy session using modern session manager
        \App\Utils\SessionManager::destroy();

        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    public function isLoggedIn(): bool {
        \App\Utils\SessionManager::start();
        
        if (!\App\Utils\SessionManager::get('admin_logged_in', false)) {
            return false;
        }

        // Check session timeout
        $loginTime = \App\Utils\SessionManager::get('admin_login_time');
        if ($loginTime) {
            $sessionAge = time() - $loginTime;
            if ($sessionAge > \App\Config\Config::getSessionLifetime()) {
                $this->logout();
                return false;
            }
        }

        return true;
    }

    public function getCurrentAdmin(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $adminId = SessionManager::get('admin_id');
        return $this->adminModel->findById($adminId);
    }

    public function getDashboardData(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            return [
                'stats' => $this->adminModel->getDashboardStats(),
                'recent_investments' => $this->getRecentInvestments(10),
                'investment_schemas' => $this->investmentModel->getAllSchemas(),
                'recent_users' => $this->getRecentUsers(10)
            ];
        } catch (Exception $e) {
            error_log("Admin getDashboardData error: " . $e->getMessage());
            return null;
        }
    }

    private function getRecentInvestments(int $limit = 10): array {
        try {
            return $this->db->fetchAll("
                SELECT i.*, u.email as user_email, s.name as schema_name
                FROM investments i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN investment_schemas s ON i.schema_id = s.id
                ORDER BY i.created_at DESC
                LIMIT ?
            ", [$limit]);
        } catch (Exception $e) {
            error_log("Admin getRecentInvestments error: " . $e->getMessage());
            return [];
        }
    }

    private function getRecentUsers(int $limit = 10): array {
        try {
            return $this->db->fetchAll("
                SELECT id, email, username, balance, referral_code, created_at, is_active
                FROM users
                WHERE is_admin = 0
                ORDER BY created_at DESC
                LIMIT ?
            ", [$limit]);
        } catch (Exception $e) {
            error_log("Admin getRecentUsers error: " . $e->getMessage());
            return [];
        }
    }

    public function updateInvestmentSchema(int $id, array $data): array {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        try {
            $result = $this->investmentModel->updateSchema($id, $data);
            
            return $result 
                ? ['success' => true, 'message' => 'Schema updated successfully']
                : ['success' => false, 'message' => 'Failed to update schema'];
        } catch (Exception $e) {
            error_log("Admin updateInvestmentSchema error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    public function deleteInvestmentSchema(int $id): array {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        // Check if schema has active investments
        if ($this->investmentModel->schemaHasActiveInvestments($id)) {
            return ['success' => false, 'message' => 'Cannot delete schema with active investments'];
        }

        try {
            $result = $this->investmentModel->deleteSchema($id);
            
            return $result 
                ? ['success' => true, 'message' => 'Schema deleted successfully']
                : ['success' => false, 'message' => 'Failed to delete schema'];
        } catch (Exception $e) {
            error_log("Admin deleteInvestmentSchema error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed'];
        }
    }

    public function createInvestmentSchema(array $data): array {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        try {
            $result = $this->investmentModel->createSchema($data);
            
            return $result 
                ? ['success' => true, 'message' => 'Schema created successfully']
                : ['success' => false, 'message' => 'Failed to create schema'];
        } catch (Exception $e) {
            error_log("Admin createInvestmentSchema error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed'];
        }
    }

    public function getAllUsers(): array {
        if (!$this->isLoggedIn()) {
            return [];
        }

        try {
            return $this->db->fetchAll("
                SELECT id, email, username, balance, referral_code, created_at, is_active,
                       (SELECT COUNT(*) FROM investments WHERE user_id = users.id) as total_investments,
                       (SELECT COALESCE(SUM(invest_amount), 0) FROM investments WHERE user_id = users.id) as total_invested
                FROM users
                WHERE is_admin = 0
                ORDER BY created_at DESC
            ");
        } catch (Exception $e) {
            error_log("Admin getAllUsers error: " . $e->getMessage());
            return [];
        }
    }

    public function getUserDetails(int $userId): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            // Get user info
            $user = $this->db->fetchOne("
                SELECT * FROM users WHERE id = ? AND is_admin = 0
            ", [$userId]);

            if (!$user) {
                return null;
            }

            // Get user investments
            $user['investments'] = $this->db->fetchAll("
                SELECT i.*, s.name as schema_name, s.daily_rate
                FROM investments i
                LEFT JOIN investment_schemas s ON i.schema_id = s.id
                WHERE i.user_id = ?
                ORDER BY i.created_at DESC
            ", [$userId]);

            return $user;
        } catch (Exception $e) {
            error_log("Admin getUserDetails error: " . $e->getMessage());
            return null;
        }
    }
}